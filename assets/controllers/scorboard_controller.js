import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
        interval: { type: Number, default: 5000 },
    };

    static targets = ['rows', 'updatedAt'];

    connect() {
        this.fetchScoreboard();
        this.startPolling();
    }

    disconnect() {
        this.stopPolling();
    }

    startPolling() {
        this.stopPolling();
        this.timer = setInterval(() => this.fetchScoreboard(), this.intervalValue);
    }

    stopPolling() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
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
            this.renderRows(payload.teams || []);
            this.updatedAtTarget.textContent = payload.updatedAt
                ? `Dernière mise à jour : ${new Date(payload.updatedAt).toLocaleString()}`
                : 'Dernière mise à jour : —';
        } catch (error) {
            this.rowsTarget.innerHTML = '<tr><td colspan="3">Erreur de chargement.</td></tr>';
        }
    }

    renderRows(teams) {
        if (teams.length === 0) {
            this.rowsTarget.innerHTML = '<tr><td colspan="3">Aucune équipe.</td></tr>';
            return;
        }

        this.rowsTarget.innerHTML = teams
            .map(
                (team) => `
                    <tr>
                        <td>${team.name ?? ''}</td>
                        <td>${team.score ?? '—'}</td>
                        <td>${team.status ?? '—'}</td>
                    </tr>
                `
            )
            .join('');
    }
}