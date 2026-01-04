import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
        streamUrl: String,
    };

    static targets = ['list', 'count', 'updated'];

    connect() {
        this.connectSource();
    }

    disconnect() {
        this.disconnectSource();
    }

    connectSource() {
        if (!this.streamUrlValue || typeof EventSource === 'undefined') {
            this.fetchTeams();
            return;
        }

        this.eventSource = new EventSource(this.streamUrlValue, { withCredentials: true });
        this.eventSource.addEventListener('pilot_teams', (event) => {
            if (!event.data) {
                return;
            }
            try {
                this.updateTeams(JSON.parse(event.data));
            } catch (error) {
                console.error('Pilot stream payload invalid', error);
            }
        });
        this.eventSource.addEventListener('error', () => {
            this.disconnectSource();
            this.fetchTeams();
        });
    }

    disconnectSource() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
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
        if (this.hasCountTarget) {
            this.countTarget.textContent = payload.count ?? '0';
        }
        if (this.hasUpdatedTarget) {
            this.updatedTarget.textContent = payload.updated_at
                ? `Dernière mise à jour : ${payload.updated_at}`
                : '';
        }

        if (!this.hasListTarget) {
            return;
        }

        this.renderTeams(payload.teams || []);
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