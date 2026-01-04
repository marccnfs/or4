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
    };

    static targets = ['list', 'count', 'updated'];

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
                this.updateTeams(JSON.parse(event.data));
            } catch (error) {
                console.error('Mercure pilot payload invalid', error);
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

        this.fetchTeams();
        this.pollingTimer = window.setInterval(
            () => this.fetchTeams(),
            this.pollingIntervalValue || DEFAULT_POLLING_INTERVAL,
        );
    }

    stopPolling() {
        if (this.pollingTimer) {
            window.clearInterval(this.pollingTimer);
            this.pollingTimer = null;
        }
    }

    async fetchTeams() {
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
            this.updateTeams(payload);
        } catch (error) {
            if (this.hasListTarget) {
                this.listTarget.innerHTML = '<li class="pilot-teams__empty">Erreur de chargement.</li>';
            }
        }
    }

    updateTeams(payload) {
        const teams = payload.teams || [];

        if (this.hasCountTarget) {
            this.countTarget.textContent = payload.count ?? String(teams.length);
        }
        if (this.hasUpdatedTarget) {
            this.updatedTarget.textContent = payload.updated_at
                ? `Dernière mise à jour : ${payload.updated_at}`
                : '';
        }

        if (!this.hasListTarget) {
            return;
        }

        this.renderTeams(teams);
    }

    renderTeams(teams) {
        this.listTarget.innerHTML = '';

        if (teams.length === 0) {
            const item = document.createElement('li');
            item.textContent = 'Aucune équipe inscrite pour le moment.';
            item.classList.add('pilot-teams__empty');
            this.listTarget.appendChild(item);
            return;
        }

        teams.forEach((team) => {
            const item = document.createElement('li');
            const name = document.createElement('span');
            name.textContent = team.name;

            const code = document.createElement('span');
            code.classList.add('team-list__code');
            code.textContent = team.code;

            const state = document.createElement('span');
            state.classList.add('team-list__state');
            state.textContent = team.state;

            const qr = document.createElement('span');
            qr.classList.add('team-list__qr');
            if (team.qr_total && team.qr_total > 0) {
                qr.textContent = `QR ${team.qr_scanned ?? 0}/${team.qr_total}`;
            } else {
                qr.textContent = 'QR —';
            }

            item.appendChild(name);
            item.appendChild(code);
            item.appendChild(state);
            item.appendChild(qr);
            this.listTarget.appendChild(item);
        });
    }
}