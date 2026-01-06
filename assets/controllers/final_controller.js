import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'status'];
    static values = {
        endpoint: String,
        step: String,
    };

    async check(event) {
        if (event) {
            event.preventDefault();
        }

        const combination = this.hasInputTarget
            ? this.inputTarget.value
            : (event?.detail?.solution || event?.params?.combination);

        if (!combination) {
            this.updateStatus('Merci de saisir la combinaison finale.');
            return;
        }

        const payload = { combination };
        if (this.hasStepValue) {
            payload.step = this.stepValue;
        }

        const response = await this.postJson(this.endpointValue || '/api/final/check', payload);

        this.updateStatus(response.message || (response.valid ? 'Combinaison validée.' : 'Combinaison incorrecte.'));
    }

    handleSolved(event) {
        this.check(event);
        this.revealSecret(event?.target);
    }

    revealSecret(root) {
        if (!root) {
            return;
        }

        const secret = root.querySelector('[data-cryptex-target="secret"]');
        if (secret) {
            secret.hidden = false;
        }

        const reveal = root.querySelector('[data-cryptex-target="reveal"]');
        if (reveal) {
            reveal.hidden = true;
            reveal.disabled = true;
        }
    }
    handleReveal(event) {
        this.revealSecret(event.target.closest('[data-cryptex-target="solution"]'));
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