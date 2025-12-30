import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'status'];
    static values = {
        endpoint: String,
    };

    async check(event) {
        event.preventDefault();

        const combination = this.hasInputTarget ? this.inputTarget.value : event.params.combination;

        if (!combination) {
            this.updateStatus('Merci de saisir la combinaison finale.');
            return;
        }

        const response = await this.postJson(this.endpointValue || '/api/final/check', {
            combination,
        });

        this.updateStatus(response.message || (response.valid ? 'Combinaison validée.' : 'Combinaison incorrecte.'));
    }

    async postJson(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            return {
                valid: false,
                message: data.message || 'Une erreur est survenue lors de la vérification.',
            };
        }

        return data;
    }

    updateStatus(message) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = message;
        }
    }
}