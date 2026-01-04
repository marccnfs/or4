import { Controller } from '@hotwired/stimulus';

const DEFAULT_POLLING_INTERVAL = 4000;
const DEFAULT_RECONNECT_DELAY = 5000;
const DEFAULT_FALLBACK_TIMEOUT = 5000;

export default class extends Controller {
    static values = {
        url: String,
        redirectUrl: String,
        mercureUrl: String,
        topic: String,
        pollingInterval: Number,
        reconnectDelay: Number,
        fallbackTimeout: Number,
        reloadOnChange: Boolean,
        currentStatus: String,
    };

    connect() {
        this.pollingTimer = null;
        this.reconnectTimer = null;
        this.mercureReady = false;
        this.connectMercure();
    }

    disconnect() {
        this.disconnectMercure();
        this.stopPolling();
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
                this.handleStatus(JSON.parse(event.data));
            } catch (error) {
                console.error('Mercure status payload invalid', error);
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

        this.pollStatus();
        this.pollingTimer = window.setInterval(
            () => this.pollStatus(),
            this.pollingIntervalValue || DEFAULT_POLLING_INTERVAL,
        );
    }

    stopPolling() {
        if (this.pollingTimer) {
            window.clearInterval(this.pollingTimer);
            this.pollingTimer = null;
        }
    }

    async pollStatus() {
        if (!this.urlValue) {
            return;
        }

        try {
            const response = await fetch(this.urlValue, {
                cache: 'no-store',
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
        if (!payload) {
            return;
        }

        if (payload.status === 'active' && this.redirectUrlValue) {
            window.location.href = this.redirectUrlValue;
            return;
        }

        if (this.reloadOnChangeValue && this.hasCurrentStatusValue && payload.status !== this.currentStatusValue) {
            window.location.reload();
        }
    }
}