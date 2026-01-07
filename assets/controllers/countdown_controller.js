import { Controller } from "@hotwired/stimulus";

/**
 * Countdown overlay (3,2,1,GO!) + event final
 *
 * Dispatch:
 *  - countdown:done  (detail: { startedAt, endedAt })
 */
export default class extends Controller {
    static targets = ["overlay", "number"];
    static values = {
        seconds: { type: Number, default: 3 },
        autoStart: { type: Boolean, default: true },
        goText: { type: String, default: "GO!" }
    };

    connect() {
        this._running = false;
        this._timer = null;

        if (this.autoStartValue) {
            this.start();
            return;
        }

        if (this.hasOverlayTarget) {
            this.overlayTarget.hidden = true;
            this.overlayTarget.classList.remove("is-out");
        }
    }

    disconnect() {
        this._clearTimer();
    }

    start() {
        if (this._running) return;
        this._running = true;

        this.startedAt = Date.now();

        this.overlayTarget.hidden = false;
        this.overlayTarget.classList.remove("is-out");

        this._current = this.secondsValue;
        this._render(this._current);

        // tick toutes les 1s
        this._timer = window.setInterval(() => {
            this._current -= 1;

            if (this._current > 0) {
                this._render(this._current);
                return;
            }

            if (this._current === 0) {
                this._render(this.goTextValue, true); // GO!
                // on laisse GO! visible un court instant
                window.setTimeout(() => this._finish(), 650);
                return;
            }

            // sécurité (ne devrait pas arriver)
            this._finish();
        }, 1000);
    }

    stop() {
        if (!this._running) {
            this.overlayTarget.hidden = true;
            this.overlayTarget.classList.remove("is-out");
            return;
        }

        this._clearTimer();
        this._running = false;
        this.overlayTarget.hidden = true;
        this.overlayTarget.classList.remove("is-out");
    }


    _finish() {
        this._clearTimer();

        // petite sortie propre
        this.overlayTarget.classList.add("is-out");

        window.setTimeout(() => {
            this.overlayTarget.hidden = true;
            this._running = false;

            const endedAt = Date.now();
            this.dispatch("done", {
                detail: { startedAt: this.startedAt, endedAt }
            });
        }, 240);
    }

    _render(value, isGo = false) {
        // relancer l’anim “pop” en forçant reflow
        this.numberTarget.style.animation = "none";
        // eslint-disable-next-line no-unused-expressions
        this.numberTarget.offsetHeight;
        this.numberTarget.style.animation = "";

        this.numberTarget.textContent = String(value);

        // Couleurs “gai”
        if (isGo) {
            this.numberTarget.style.color = "#22c55e"; // vert GO
        } else {
            // couleurs par étape (3,2,1)
            const map = {
                3: "#ff2d55", // rose
                2: "#f59e0b", // orange
                1: "#3b82f6"  // bleu
            };
            this.numberTarget.style.color = map[value] || "#ff2d55";
        }
    }

    _clearTimer() {
        if (this._timer) {
            window.clearInterval(this._timer);
            this._timer = null;
        }
    }
}
