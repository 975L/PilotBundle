/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Reads the card again every few seconds while one of its runs waits or works, its content replaced when their state changed; a field being edited is left alone until it is left
export default class extends Controller {
    static values = { url: String, state: String, every: { type: Number, default: 5000 } };

    connect() {
        this.timer = window.setInterval(() => this.check(), this.everyValue);
    }

    disconnect() {
        window.clearInterval(this.timer);
    }

    // The card's content: the dialog's body, or the card's own page
    container() {
        return this.element.closest("[data-pilot-card-modal-target=body], .pilot-dialog-page");
    }

    // The card fetched again, swapped in only when its runs moved on, the dialog told so that the page behind is reloaded once it closes
    check() {
        const box = this.container();
        if (!box || this.busy || box.contains(document.activeElement) && document.activeElement.matches("input, textarea, select")) return;

        this.busy = true;
        fetch(this.urlValue, { headers: { "X-Requested-With": "XMLHttpRequest" } })
            .then((response) => (response.ok ? response.text() : Promise.reject(new Error(response.statusText))))
            .then((html) => {
                const page = new DOMParser().parseFromString(html, "text/html").querySelector(".pilot-dialog-page");
                const watch = page?.querySelector("[data-controller~='pilot-run-watch']");
                if (!page || watch?.dataset.pilotRunWatchStateValue === this.stateValue) return;
                box.dispatchEvent(new CustomEvent("pilot:changed", { bubbles: true }));
                box.replaceChildren(...page.childNodes);
            })
            .catch(() => {})
            .finally(() => { this.busy = false; });
    }
}
