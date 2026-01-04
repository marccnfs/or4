import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
        redirectUrl: String,
        streamUrl: String,
    };

    connect() {
        this.connectSource();
    }

    disconnect() {
        this.disconnectSource();
    }

    connectSource() {
        if (!this.streamUrlValue || typeof EventSource === 'undefined') {
            this.checkStatus();
            return;
        }

        this.eventSource = new EventSource(this.streamUrlValue, { withCredentials: true });
        this.eventSource.addEventListener('escape_status', (event) => {
            if (!event.data) {
                return;
            }
            try {
                const payload = JSON.parse(event.data);
                this.handleStatus(payload);
            } catch (error) {
                console.error('Waiting stream payload invalid', error);
            }
        });
        this.eventSource.addEventListener('error', () => {
            this.disconnectSource();
            this.checkStatus();
        });
    }

    disconnectSource() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
    }

    async checkStatus() {
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
            this.handleStatus(payload);
        } catch (error) {
            // no-op
        }
    }

    handleStatus(payload) {
        if (payload && payload.status === 'active' && this.redirectUrlValue) {
            window.location.href = this.redirectUrlValue;
        }
    }
}