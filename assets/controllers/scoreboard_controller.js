import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
        streamUrl: String,
    };

    static targets = ['rows', 'total', 'status', 'updated'];

    connect() {
        this.connectSource();
    }

    disconnect() {
        this.disconnectSource();
    }

    connectSource() {
        if (!this.streamUrlValue || typeof EventSource === 'undefined') {
            this.fetchScoreboard();
            return;
        }

        this.eventSource = new EventSource(this.streamUrlValue, { withCredentials: true });
        this.eventSource.addEventListener('scoreboard', (event) => {
            if (!event.data) {
                return;
            }
            try {
                this.updateScoreboard(JSON.parse(event.data));
            } catch (error) {
                console.error('Scoreboard stream payload invalid', error);
            }
        });
        this.eventSource.addEventListener('error', () => {
            this.disconnectSource();
            this.fetchScoreboard();
        });
    }

    disconnectSource() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
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