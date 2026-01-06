import { Controller } from '@hotwired/stimulus';

const DEFAULT_POLLING_INTERVAL = 4000;
const DEFAULT_RECONNECT_DELAY = 5000;
const DEFAULT_FALLBACK_TIMEOUT = 5000;

export default class extends Controller {
    static values = {
        url: String,
        redirectUrl: String,
        finishedRedirectUrl: String,
        mercureUrl: String,
        topic: String,
        pollingInterval: Number,
        reconnectDelay: Number,
        fallbackTimeout: Number,
        reloadOnChange: Boolean,
        currentStatus: String,
        countdownSeconds: Number,
    };

    static targets = ['countdown', 'countdownOverlay'];


    connect() {
        this.pollingTimer = null;
        this.reconnectTimer = null;
        this.mercureReady = false;
        this.countdownTimer = null;
        this.countdownStarted = false;
        this.countdownDoneHandler = null;
        this.connectMercure();
    }

    disconnect() {
        this.disconnectMercure();
        this.stopPolling();
        this.stopCountdown();
        this.clearCountdownDoneHandler();
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
            if (this.countdownStarted) {
                return;
            }

            if (this.startOverlayCountdown()) {
                return;
            }

            if (this.hasCountdownSecondsValue && this.countdownSecondsValue > 0 && this.hasCountdownTarget) {
                this.startCountdown();
                return;
            }
            window.location.href = this.redirectUrlValue;
            return;
        }

        if (payload.status === 'finished' && this.hasFinishedRedirectUrlValue) {
            window.location.href = this.finishedRedirectUrlValue;
            return;
        }

        if (this.reloadOnChangeValue && this.hasCurrentStatusValue && payload.status !== this.currentStatusValue) {
            window.location.reload();
        }
    }
    startOverlayCountdown() {
        if (!this.hasCountdownOverlayTarget) {
            return false;
        }

        const controller = this.application.getControllerForElementAndIdentifier(
            this.countdownOverlayTarget,
            'countdown',
        );
        if (!controller) {
            return false;
        }

        this.countdownStarted = true;
        this.clearCountdownDoneHandler();
        this.countdownDoneHandler = () => {
            window.location.href = this.redirectUrlValue;
        };
        this.countdownOverlayTarget.addEventListener('countdown:done', this.countdownDoneHandler, { once: true });

        if (this.hasCountdownSecondsValue && this.countdownSecondsValue > 0) {
            controller.secondsValue = this.countdownSecondsValue;
        }
        controller.start();

        return true;
    }

    startCountdown() {
        if (this.countdownStarted) {
            return;
        }

        this.countdownStarted = true;
        let remaining = this.countdownSecondsValue;
        this.countdownTarget.hidden = false;
        this.countdownTarget.textContent = remaining;

        this.countdownTimer = window.setInterval(() => {
            remaining -= 1;
            if (remaining <= 0) {
                this.stopCountdown();
                window.location.href = this.redirectUrlValue;
                return;
            }
            this.countdownTarget.textContent = remaining;
        }, 1000);
    }

    stopCountdown() {
        if (this.countdownTimer) {
            window.clearInterval(this.countdownTimer);
            this.countdownTimer = null;
        }
    }

    clearCountdownDoneHandler() {
        if (this.countdownDoneHandler && this.hasCountdownOverlayTarget) {
            this.countdownOverlayTarget.removeEventListener('countdown:done', this.countdownDoneHandler);
        }
        this.countdownDoneHandler = null;
    }
}