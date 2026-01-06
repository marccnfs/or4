// assets/controllers/cryptex_controller.js
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static values = {
        solution: String,
        hash: String,
        alphabet: { type: String, default: "ABCDEFGHIJKLMNOPQRSTUVWXYZ" },
        scramble: { type: Boolean, default: true },
        autocheck: { type: Boolean, default: true },
        successMessage: { type: String, default: "Bravo !" }
    }
    static targets = ["status", "reveal", "secret", "rings"]

    connect(){
        this.alphabet = this.alphabetValue.split("");
        this.state = [];
        this.build();
        if (this.autocheckValue) this.check();
    }

    build(){
        const s = (this.solutionValue || "").toUpperCase();
        const letters = (s || "CODE").split("");
        const box = this.ringsTarget || this.element;
        box.innerHTML = "";

        letters.forEach((ch, i)=>{
            const init = this.scrambleValue
                ? this.alphabet[Math.floor(Math.random()*this.alphabet.length)]
                : ch;
            const r = this.makeRing(init, i);
            box.appendChild(r);
        });
    }

    makeRing(initialChar, index){
        const ring = document.createElement("div");
        ring.className = "ring";
        ring.setAttribute("role","group");

        const up = document.createElement("button");
        up.type = "button"; up.textContent = "▲";
        const val = document.createElement("div");
        val.className = "ring-value";
        const down = document.createElement("button");
        down.type = "button"; down.textContent = "▼";

        const startIdx = this.alphabet.indexOf(initialChar);
        this.state[index] = startIdx >= 0 ? startIdx : 0;

        const render = ()=> val.textContent = this.alphabet[this.state[index]];
        const step = (d)=>{
            const m = this.alphabet.length;
            this.state[index] = (this.state[index] + d + m) % m;
            render();
            if (this.autocheckValue) this.check();
        };

        const onUp = ()=> step(1);
        const onDown = ()=> step(-1);
        const onWheel = (e)=>{ e.preventDefault(); step(e.deltaY>0?-1:1); };
        const wheelOpts = {passive:false};
        const onKeydown = (e)=>{
            if (e.key === "ArrowUp") { e.preventDefault(); step(1); }
            if (e.key === "ArrowDown") { e.preventDefault(); step(-1); }
        };

        up.addEventListener("click", onUp);
        down.addEventListener("click", onDown);
        ring.addEventListener("wheel", onWheel, wheelOpts);
        ring.tabIndex = 0;
        ring.addEventListener("keydown", onKeydown);

        ring._listeners = [
            [up, "click", onUp],
            [down, "click", onDown],
            [ring, "wheel", onWheel, wheelOpts],
            [ring, "keydown", onKeydown]
        ];

        render();
        ring.append(up, val, down);
        return ring;
    }

    disconnect(){
        this.element.querySelectorAll(".ring").forEach(ring=>{
            (ring._listeners || []).forEach(([el, evt, fn, opts])=>{
                el.removeEventListener(evt, fn, opts);
            });
        });
    }

    currentWord(){ return this.state.map(i => this.alphabet[i]).join(""); }

    async sha256Hex(str){
        const enc = new TextEncoder().encode(str);
        const buf = await crypto.subtle.digest("SHA-256", enc);
        return [...new Uint8Array(buf)].map(b=>b.toString(16).padStart(2,"0")).join("");
    }

    async check(){
        const word = this.currentWord();
        let ok = false;
        if (this.hashValue) {
            const hex = await this.sha256Hex(word);
            ok = (hex.toLowerCase() === this.hashValue.toLowerCase());
        } else if (this.solutionValue) {
            ok = (word === this.solutionValue.toUpperCase());
        }
        if (this.hasStatusTarget) this.statusTarget.textContent = ok ? "✅ Code correct" : "…";
        if (this.hasRevealTarget) this.revealTarget.hidden = !ok;
        if (ok) this.element.dispatchEvent(new CustomEvent("cryptex:solved",{bubbles:true, detail:{solution:word}}));
        return ok;
    }

    reveal(){
        this.check().then(ok=>{
            if (!ok) return;
            if (this.hasSecretTarget){
                this.secretTarget.hidden = false;
            }
            if (this.hasRevealTarget){
                this.revealTarget.disabled = true;
                this.revealTarget.textContent = "Déverrouillé";
            }
            document.dispatchEvent(new CustomEvent("puzzle:solved",{bubbles:true}));
        });
    }
}
