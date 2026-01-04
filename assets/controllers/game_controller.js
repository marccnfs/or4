import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'step', 'status', 'form'];
    static values = {
        endpoint: String,
    };

    async validate(event) {
        event.preventDefault();

        const step = this.hasStepTarget ? this.stepTarget.value : event.params.step;
        const letter = this.hasInputTarget ? this.inputTarget.value : event.params.letter;

        if (!step || !letter) {
            this.updateStatus("Merci de saisir l'étape et la lettre.");
            return;
        }

        const response = await this.postJson(this.endpointValue || '/api/step/validate', {
            step,
            letter,
        });

        this.updateStatus(response.message || (response.valid ? 'Lettre validée.' : 'Lettre incorrecte.'));

        if (response.valid) {
            if (this.hasFormTarget && this.hasStepTarget) {
                this.formTarget.submit();
            } else {
                window.location.reload();
            }
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
                message: data.message || 'Une erreur est survenue lors de la validation.',
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