import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dialog', 'select', 'winnerInput', 'form'];

    open(event) {
        event.preventDefault();
        if (this.dialogTarget?.showModal) {
            this.dialogTarget.showModal();
            return;
        }

        this.dialogTarget.hidden = false;
    }

    close(event) {
        event?.preventDefault();
        if (this.dialogTarget?.close) {
            this.dialogTarget.close();
            return;
        }

        this.dialogTarget.hidden = true;
    }

    confirm(event) {
        event.preventDefault();
        if (!this.selectTarget?.value) {
            this.selectTarget?.focus();
            return;
        }

        this.winnerInputTarget.value = this.selectTarget.value;
        this.close();
        this.formTarget.requestSubmit();
    }
}