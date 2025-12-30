import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'status', 'hint'];
    static values = {
        endpoint: String,
    };

    async scan(event) {
        event.preventDefault();

        const code = this.hasInputTarget ? this.inputTarget.value : event.params.code;

        if (!code) {
            this.updateStatus('Merci de saisir le code QR.');
            return;
        }

        const response = await this.postJson(this.endpointValue || '/api/qr/scan', {
            code,
        });

        this.updateStatus(response.message || (response.valid ? 'QR validé.' : 'QR incorrect.'));
        if (this.hasHintTarget) {
            this.hintTarget.textContent = response.nextHint || '';
        }
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
                message: data.message || 'Une erreur est survenue lors du scan.',
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