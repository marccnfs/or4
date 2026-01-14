import { Controller } from '@hotwired/stimulus';

const FULLSCREEN_STORAGE_KEY = 'or4FullscreenRequested';

export default class extends Controller {
    static targets = ['button'];

    connect() {
        this.handleFullscreenChange = this.handleFullscreenChange.bind(this);
        document.addEventListener('fullscreenchange', this.handleFullscreenChange);

        if (sessionStorage.getItem(FULLSCREEN_STORAGE_KEY) === 'true') {
            this.requestFullscreen();
        }

        this.updateButtonLabel();
    }

    disconnect() {
        document.removeEventListener('fullscreenchange', this.handleFullscreenChange);
    }

    toggle() {
        if (document.fullscreenElement) {
            document.exitFullscreen();
            return;
        }

        this.requestFullscreen();
    }

    requestFullscreen() {
        if (document.fullscreenElement || !document.documentElement.requestFullscreen) {
            return;
        }

        document.documentElement.requestFullscreen().catch(() => {});
    }

    handleFullscreenChange() {
        sessionStorage.setItem(FULLSCREEN_STORAGE_KEY, document.fullscreenElement ? 'true' : 'false');
        this.updateButtonLabel();
    }

    updateButtonLabel() {
        if (!this.hasButtonTarget) {
            return;
        }

        this.buttonTarget.textContent = document.fullscreenElement ? 'Quitter le plein écran' : 'Plein écran';
    }
}