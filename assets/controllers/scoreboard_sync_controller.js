import { Controller } from '@hotwired/stimulus';

const DEFAULT_POLLING_INTERVAL = 4000;
const DEFAULT_RECONNECT_DELAY = 5000;
const DEFAULT_FALLBACK_TIMEOUT = 5000;

export default class extends Controller {
    static values = {
        url: String,
        mercureUrl: String,
        topic: String,
        pollingInterval: Number,
        reconnectDelay: Number,
        fallbackTimeout: Number,
        winnerUrl: String,
        homeUrl: String,
    };

    static targets = ['rows', 'total', 'status', 'updated'];

    connect() {
        this.pollingTimer = null;
        this.reconnectTimer = null;
        this.mercureReady = false;
        this.connectMercure();
    }

    disconnect() {
        this.disconnectMercure();
        this.stopPolling();
    }

    connectMercure() {
        if (!this.hasMercureUrlValue || !this.hasTopicValue || typeof EventSource === 'undefined') {
            this.startPolling();
            return;
        }

        this.startMercure();
        this.mercureFallbackTimer = window.setTimeout(() => {
            if (!this.mercureReady) {
                this.startPolling();
            }
        }, this.fallbackTimeoutValue || DEFAULT_FALLBACK_TIMEOUT);
    }

    startMercure() {
        const url = this.buildMercureUrl();
        this.eventSource = new EventSource(url, { withCredentials: true });
        this.eventSource.onmessage = (event) => {
            if (!event.data) {
                return;
            }
            this.mercureReady = true;
            this.stopPolling();
            this.clearFallbackTimer();

            try {
                this.updateScoreboard(JSON.parse(event.data));
            } catch (error) {
                console.error('Mercure scoreboard payload invalid', error);
            }
        };
        this.eventSource.onerror = () => {
            if (!this.pollingTimer) {
                this.startPolling();
            }
            this.scheduleReconnect();
        };
    }

    scheduleReconnect() {
        if (this.reconnectTimer) {
            return;
        }

        this.disconnectMercure();
        this.reconnectTimer = window.setTimeout(() => {
            this.reconnectTimer = null;
            this.startMercure();
        }, this.reconnectDelayValue || DEFAULT_RECONNECT_DELAY);
    }

    buildMercureUrl() {
        const url = new URL(this.mercureUrlValue, window.location.origin);
        url.searchParams.append('topic', this.topicValue);
        return url.toString();
    }

    disconnectMercure() {
        this.clearFallbackTimer();
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
    }

    clearFallbackTimer() {
        if (this.mercureFallbackTimer) {
            window.clearTimeout(this.mercureFallbackTimer);
            this.mercureFallbackTimer = null;
        }
    }

    startPolling() {
        if (this.pollingTimer || !this.urlValue) {
            return;
        }

        this.fetchScoreboard();
        this.pollingTimer = window.setInterval(
            () => this.fetchScoreboard(),
            this.pollingIntervalValue || DEFAULT_POLLING_INTERVAL,
        );
    }

    stopPolling() {
        if (this.pollingTimer) {
            window.clearInterval(this.pollingTimer);
            this.pollingTimer = null;
        }
    }

    async fetchScoreboard() {
        if (!this.urlValue) {
            return;
        }

        try {
            const response = await fetch(this.urlValue, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error(`Unexpected response: ${response.status}`);
            }

            const payload = await response.json();
            this.updateScoreboard(payload);
        } catch (error) {
            if (this.hasRowsTarget) {
                this.rowsTarget.innerHTML = '<tr><td colspan="4">Erreur de chargement.</td></tr>';
            }
        }
    }

    updateScoreboard(payload) {
        if (payload?.winner && payload?.status === 'finished' && this.hasWinnerUrlValue) {
            window.location.href = this.winnerUrlValue;
            return;
        }
        if (this.hasHomeUrlValue && ['waiting', 'offline'].includes(payload?.status)) {
            window.location.href = this.homeUrlValue;
            return;
        }
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = payload.status ?? '';
        }
        if (this.hasTotalTarget) {
            this.totalTarget.textContent = payload.total_steps ?? '0';
        }
        if (this.hasUpdatedTarget) {
            this.updatedTarget.textContent = payload.updated_at
                ? `Mis à jour à ${payload.updated_at}`
                : '';
        }

        if (!this.hasRowsTarget) {
            return;
        }

        this.renderRows(payload.teams || [], payload.total_steps ?? 0);
    }

    renderRows(teams, totalSteps) {
        if (teams.length === 0) {
            this.rowsTarget.innerHTML = '<tr><td colspan="4" class="scoreboard-empty">Aucune équipe inscrite pour le moment.</td></tr>';
            return;
        }

        this.rowsTarget.innerHTML = '';

        teams.forEach((team) => {
            const row = document.createElement('tr');

            const isWinner = totalSteps > 0 && team.validated_steps >= totalSteps;
            if (isWinner) {
                row.classList.add('scoreboard-winner');
            }

            const name = document.createElement('td');
            name.textContent = team.name;

            const code = document.createElement('td');
            code.textContent = team.code;

            const progressCell = document.createElement('td');
            const bar = document.createElement('div');
            bar.classList.add('scoreboard-progress');
            const barFill = document.createElement('span');
            const percent = totalSteps > 0 ? Math.min((team.validated_steps / totalSteps) * 100, 100) : 0;
            barFill.style.width = `${percent}%`;
            bar.appendChild(barFill);

            const meta = document.createElement('div');
            meta.classList.add('scoreboard-progress-meta');
            meta.textContent = `${team.validated_steps}/${totalSteps}`;

            progressCell.appendChild(bar);
            progressCell.appendChild(meta);

            const lastUpdate = document.createElement('td');
            lastUpdate.textContent = team.last_update || '—';

            row.appendChild(name);
            row.appendChild(code);
            row.appendChild(progressCell);
            row.appendChild(lastUpdate);
            this.rowsTarget.appendChild(row);
        });
    }
}