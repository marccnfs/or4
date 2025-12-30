import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = [
        "placeholder",
        "details",
        "qrImage",
        "directWrapper",
        "directLink",
        "generateButton",
        "printButton",
        "toolbarPrintButton",
        "expires"
    ];

    static values = {
        fetchUrl: String,
        printUrl: String,
        fallbackMessage: String,
        readyMessage: String,
        generatedMessage: String,
        generatingMessage: String,
        errorMessage: String,
        printWarningMessage: String,
        expiresPattern: String,
        noExpiryMessage: String
    };

    connect() {
        this.token = null;
        this.resetState();
    }

    resetState() {
        this.token = null;
        if (!this.hasFetchUrlValue || !this.fetchUrlValue) {
            this.showMessage(this.fallbackMessageValue || "");
            this.disableButton(this.generateButtonTarget);
            this.disableButton(this.printButtonTarget);
            if (this.hasToolbarPrintButtonTarget) {
                this.disableButton(this.toolbarPrintButtonTarget);
            }
        } else {
            this.showMessage(this.readyMessageValue || "");
            this.enableButton(this.generateButtonTarget);
            this.disableButton(this.printButtonTarget);
            if (this.hasToolbarPrintButtonTarget) {
                this.disableButton(this.toolbarPrintButtonTarget);
            }
        }

        if (this.hasDetailsTarget) {
            this.detailsTarget.hidden = !this.fetchUrlValue;
        }

        if (this.hasDirectWrapperTarget) {
            this.directWrapperTarget.hidden = true;
        }

        if (this.hasQrImageTarget) {
            this.qrImageTarget.src = "";
        }

        this.updateExpires(null, false);
    }

    showMessage(message) {
        if (!this.hasPlaceholderTarget) {
            return;
        }

        this.placeholderTarget.textContent = message;
        this.placeholderTarget.hidden = message === "";
    }

    enableButton(target) {
        if (target) {
            target.disabled = false;
        }
    }

    disableButton(target) {
        if (target) {
            target.disabled = true;
        }
    }

    async generate(event) {
        event.preventDefault();

        if (!this.hasFetchUrlValue || !this.fetchUrlValue) {
            this.showMessage(this.fallbackMessageValue || "");
            return;
        }

        const button = this.hasGenerateButtonTarget ? this.generateButtonTarget : event.currentTarget;
        this.disableButton(button);
        this.showMessage(this.generatingMessageValue || "");

        try {
            const response = await fetch(this.fetchUrlValue, {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    Accept: "application/json"
                }
            });

            if (!response.ok) {
                throw new Error("Request failed");
            }

            const data = await response.json();
            this.applyData(data);
            this.showMessage(this.generatedMessageValue || "");
            this.enableButton(this.printButtonTarget);
            if (this.hasToolbarPrintButtonTarget) {
                this.enableButton(this.toolbarPrintButtonTarget);
            }
            this.element.dispatchEvent(new CustomEvent("qr-print:generated", {
                bubbles: true,
                detail: { token: this.token, data }
            }));
        } catch (error) {
            console.error(error);
            this.showMessage(this.errorMessageValue || "");
        } finally {
            this.enableButton(this.generateButtonTarget);
        }
    }

    applyData(data) {
        if (!data || !data.qr || !data.token) {
            this.showMessage(this.errorMessageValue || "");
            return;
        }

        this.token = data.token;

        if (this.hasQrImageTarget) {
            this.qrImageTarget.src = data.qr;
        }

        if (this.hasDetailsTarget) {
            this.detailsTarget.hidden = false;
        }

        if (this.hasDirectWrapperTarget) {
            if (data.directUrl) {
                this.directWrapperTarget.hidden = false;
                if (this.hasDirectLinkTarget) {
                    this.directLinkTarget.href = data.directUrl;
                    this.directLinkTarget.textContent = data.directUrl;
                }
            } else {
                this.directWrapperTarget.hidden = true;
            }
        }

        this.updateExpires(data.expiresAt ?? null, data.noExpiry === true);
    }

    formatExpires(value) {
        if (!value) {
            return "";
        }

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return "";
        }

        const formattedTime = new Intl.DateTimeFormat(undefined, {
            hour: "2-digit",
            minute: "2-digit"
        }).format(date);

        if (this.hasExpiresPatternValue) {
            return this.expiresPatternValue.replace("__TIME__", formattedTime);
        }

        return formattedTime;
    }

    updateExpires(expiresAt, noExpiry) {
        if (!this.hasExpiresTarget) {
            return;
        }

        if (noExpiry && this.hasNoExpiryMessageValue && this.noExpiryMessageValue) {
            this.expiresTarget.textContent = this.noExpiryMessageValue;
            this.expiresTarget.hidden = false;
            return;
        }

        const formatted = this.formatExpires(expiresAt);
        if (formatted !== "") {
            this.expiresTarget.textContent = formatted;
            this.expiresTarget.hidden = false;
        } else {
            this.expiresTarget.textContent = "";
            this.expiresTarget.hidden = true;
        }
    }

    print(event) {
        event.preventDefault();

        if (!this.token) {
            this.showMessage(this.printWarningMessageValue || "");
            return;
        }

        if (!this.hasPrintUrlValue || !this.printUrlValue) {
            this.showMessage(this.fallbackMessageValue || "");
            return;
        }

        const url = this.printUrlValue.replace("__TOKEN__", encodeURIComponent(this.token));
        const win = window.open(url, "_blank", "noopener");
        if (!win) {
            console.warn("Unable to open print window");
        }
    }
}
