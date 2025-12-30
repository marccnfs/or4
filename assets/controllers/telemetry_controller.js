// assets/controllers/telemetry_controller.js
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static values = { slug: String, csrf: String, step: Number, restart: Boolean }

    connect(){
        const storageBase = `eg:${this.slugValue}`;
        const startedKey = `${storageBase}:startedAt`;
        const totalKey = `${storageBase}:totalMs`;
        const finaleKey = `${storageBase}:finalOrder`;
        try {
            if (this.hasRestartValue && this.restartValue) {
                localStorage.setItem(startedKey, String(Date.now()));
                localStorage.removeItem(totalKey);
                localStorage.removeItem(finaleKey);
            } else if (!localStorage.getItem(startedKey)) {
                localStorage.setItem(startedKey, String(Date.now()));
            }
        } catch (err) {
            console.warn('telemetry_controller: unable to access localStorage', err);
        }

        // Start dès l’entrée de jeu
        const startBody = new FormData();
        if (this.hasStepValue) {
            startBody.append('step', String(this.stepValue));
        }
        if (this.hasRestartValue && this.restartValue) {
            startBody.append('restart', '1');
        }
        fetch(`/play/${this.slugValue}/start`, {
            method: 'POST',
            headers: {'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': this.csrfValue},
            body: startBody
        });
        // Écoute des hints
        this._onHint = () => {
            fetch(`/play/${this.slugValue}/hint`, {
                method: 'POST',
                headers: {'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': this.csrfValue}
            });
        };
        document.addEventListener('puzzle:hint', this._onHint);

        this._onSolved = (e) => {
            const step = e?.detail?.step ?? (this.hasStepValue ? this.stepValue : null);
            if (!step) return;
            const body = new FormData();
            body.append('step', String(step));
            fetch(`/play/${this.slugValue}/progress`, {
                method: 'POST',
                headers: {'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': this.csrfValue},
                body
            });
        };

        document.addEventListener('puzzle:solved', this._onSolved, { once: true });
        // Fin (on attend d’avoir une durée côté front)
        this._onGameover = (e) => {
            const ms = e.detail?.durationMs ?? 0;
            const body = new FormData();
            body.append('durationMs', String(ms));
            fetch(`/play/${this.slugValue}/finish`, {
                method: 'POST',
                headers: {'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': this.csrfValue},
                body
            }).then(r=>r.json()).then(({score})=>{
                // tu peux afficher un toast score ici si tu veux
            });
        };
        document.addEventListener('puzzle:gameover', this._onGameover);
    }
    disconnect(){
        document.removeEventListener('puzzle:hint', this._onHint);
        document.removeEventListener('puzzle:gameover', this._onGameover);
        document.removeEventListener('puzzle:solved', this._onSolved);
    }
}
