import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['form', 'feedback', 'teamCode'];

    async submit(event) {
        event.preventDefault();

        const form = this.formTarget;
        const formData = new FormData(form);

        try {
            const response = await fetch(form.action, {
                method: form.method.toUpperCase(),
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                this.showError(data.error || data.message || 'Une erreur est survenue lors de la validation.');
                return;
            }

            if (data.ok) {
                this.showSuccess(data.message || 'QR validé.');
            } else {
                this.showError(data.error || 'QR incorrect.');
            }
        } catch (error) {
            console.error('Erreur lors de la validation du QR:', error);
            this.showError('Une erreur est survenue lors de la validation.');
        }
    }

    showSuccess(message) {
        this.updateFeedback(message, false);
    }

    showError(message) {
        this.updateFeedback(message, true);
    }

    updateFeedback(message, isError) {
        if (!this.hasFeedbackTarget) {
            return;
        }

        this.feedbackTarget.textContent = message;
        this.feedbackTarget.classList.toggle('closed', isError);
    }
}