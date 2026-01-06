import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.handleGesture = (event) => event.preventDefault();
        this.handleDoubleClick = (event) => event.preventDefault();

        document.addEventListener('gesturestart', this.handleGesture, { passive: false });
        document.addEventListener('gesturechange', this.handleGesture, { passive: false });
        document.addEventListener('gestureend', this.handleGesture, { passive: false });
        document.addEventListener('dblclick', this.handleDoubleClick, { passive: false });
    }

    disconnect() {
        document.removeEventListener('gesturestart', this.handleGesture);
        document.removeEventListener('gesturechange', this.handleGesture);
        document.removeEventListener('gestureend', this.handleGesture);
        document.removeEventListener('dblclick', this.handleDoubleClick);
    }
}