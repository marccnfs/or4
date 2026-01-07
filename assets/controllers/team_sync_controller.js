import { Controller } from '@hotwired/stimulus';

const DEFAULT_POLLING_INTERVAL = 4000;
const DEFAULT_RECONNECT_DELAY = 5000;
const DEFAULT_FALLBACK_TIMEOUT = 5000;
const DEFAULT_PIN_REFRESH_INTERVAL = 60000;

export default class extends Controller {
    static targets = ['pin'];
    static values = {
        url: String,
        mercureUrl: String,
        topic: String,
        pollingInterval: Number,
        reconnectDelay: Number,
        fallbackTimeout: Number,
        currentUpdated: String,
        pinRefreshInterval: Number,
    };

    connect() {
        this.pollingTimer = null;
        this.reconnectTimer = null;
        this.pinRefreshTimer = null;
        this.mercureReady = false;
        this.connectMercure();
        this.startPinRefresh();
    }

    disconnect() {
        this.disconnectMercure();
        this.stopPolling();
        this.stopPinRefresh();
    }

    connectMercure() {
        if (!this.hasMercureUrlValue || !this.hasTopicValue || typeof EventSource === 'undefined') {
            this.startPolling();
            return;
        }

        this.startMercure();
        this.mercureFallbackTimer = window.setTimeout(() => {
            if (!this.mercureReady) {
                this.startPolling();
            }
        }, this.fallbackTimeoutValue || DEFAULT_FALLBACK_TIMEOUT);
    }

    startMercure() {
        const url = this.buildMercureUrl();
        this.eventSource = new EventSource(url, { withCredentials: true });
        this.eventSource.onmessage = (event) => {
            if (!event.data) {
                return;
            }
            this.mercureReady = true;
            this.stopPolling();
            this.clearFallbackTimer();

            try {
                this.handlePayload(JSON.parse(event.data));
            } catch (error) {
                console.error('Mercure team payload invalid', error);
            }
        };
        this.eventSource.onerror = () => {
            if (!this.pollingTimer) {
                this.startPolling();
            }
            this.scheduleReconnect();
        };
    }

    scheduleReconnect() {
        if (this.reconnectTimer) {
            return;
        }

        this.disconnectMercure();
        this.reconnectTimer = window.setTimeout(() => {
            this.reconnectTimer = null;
            this.startMercure();
        }, this.reconnectDelayValue || DEFAULT_RECONNECT_DELAY);
    }

    buildMercureUrl() {
        const url = new URL(this.mercureUrlValue, window.location.origin);
        url.searchParams.append('topic', this.topicValue);
        return url.toString();
    }

    disconnectMercure() {
        this.clearFallbackTimer();
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
    }

    clearFallbackTimer() {
        if (this.mercureFallbackTimer) {
            window.clearTimeout(this.mercureFallbackTimer);
            this.mercureFallbackTimer = null;
        }
    }

    startPolling() {
        if (this.pollingTimer || !this.urlValue) {
            return;
        }

        this.fetchState();
        this.pollingTimer = window.setInterval(
            () => this.fetchState(),
            this.pollingIntervalValue || DEFAULT_POLLING_INTERVAL,
        );
    }

    stopPolling() {
        if (this.pollingTimer) {
            window.clearInterval(this.pollingTimer);
            this.pollingTimer = null;
        }
    }

    startPinRefresh() {
        if (this.pinRefreshTimer || !this.urlValue) {
            return;
        }

        const interval = this.pinRefreshIntervalValue || DEFAULT_PIN_REFRESH_INTERVAL;
        this.pinRefreshTimer = window.setInterval(() => {
            if (!this.pollingTimer) {
                this.fetchState();
            }
        }, interval);
    }

    stopPinRefresh() {
        if (this.pinRefreshTimer) {
            window.clearInterval(this.pinRefreshTimer);
            this.pinRefreshTimer = null;
        }
    }


    async fetchState() {
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
            this.handlePayload(payload);
        } catch (error) {
            // no-op
        }
    }

    handlePayload(payload) {
        if (!payload) {
            return;
        }

        if (this.hasPinTarget && payload.pin) {
            this.pinTarget.textContent = payload.pin;
        }


        const updatedAt = payload.updated_at || '';
        if (this.hasCurrentUpdatedValue && updatedAt && updatedAt !== this.currentUpdatedValue) {
            window.location.reload();
            return;
        }

        if (!this.hasCurrentUpdatedValue) {
            return;
        }

        this.currentUpdatedValue = updatedAt;
    }
}