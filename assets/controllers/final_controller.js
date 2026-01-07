import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'status'];
    static values = {
        endpoint: String,
        step: String,
        successRedirectUrl: String,
        suspenseDuration: Number,
        loseRedirectUrl: String,
    };

    async check(event) {
        if (event) {
            event.preventDefault();
        }

        const combination = this.hasInputTarget
            ? this.inputTarget.value
            : (event?.detail?.solution || event?.params?.combination);

        if (!combination) {
            this.updateStatus('Merci de saisir la combinaison finale.', true);
            return;
        }

        const payload = { combination };
        if (this.hasStepValue) {
            payload.step = this.stepValue;
        }

        const response = await this.postJson(this.endpointValue || '/api/final/check', payload);

        this.updateStatus(
            response.message || (response.valid ? 'Combinaison validée.' : 'Combinaison incorrecte.'),
            !response.valid,
        );
        if (response.finished && !response.valid && this.hasLoseRedirectUrlValue) {
            window.location.href = this.loseRedirectUrlValue;
            return;
        }

        if (response.valid && this.hasSuccessRedirectUrlValue) {
            this.startSuccessSequence();
        }
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

    startSuccessSequence() {
        const duration = this.suspenseDurationValue || 2000;
        document.body.classList.add('final-transition');
        this.playSuccessSound();
        window.setTimeout(() => {
            window.location.href = this.successRedirectUrlValue;
        }, duration);
    }

    playSuccessSound() {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) {
            return;
        }

        const context = new AudioContext();
        const oscillator = context.createOscillator();
        const gain = context.createGain();
        oscillator.type = 'sine';
        oscillator.frequency.value = 620;
        gain.gain.setValueAtTime(0.001, context.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.25, context.currentTime + 0.05);
        gain.gain.exponentialRampToValueAtTime(0.001, context.currentTime + 0.7);
        oscillator.connect(gain);
        gain.connect(context.destination);
        oscillator.start();
        oscillator.stop(context.currentTime + 0.75);
        oscillator.onended = () => context.close();
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

    updateStatus(message, isError = false) {
        if (!this.hasStatusTarget) {
            return;
        }
        this.statusTarget.textContent = message;
        this.statusTarget.classList.toggle('closed', isError);
    }
}