/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Reads the card again every few seconds while one of its runs waits or works, its content replaced when their state changed; a field being edited is left alone until it is left. On a page listing the runs under way (runs value set), the list is replaced as they move on and the page reloaded once one ends or starts, never under an open card or a field being edited
export default class extends Controller {
    static values = { url: String, state: String, runs: String, every: { type: Number, default: 5000 } };

    connect() {
        this.timer = window.setInterval(() => this.check(), this.everyValue);
    }

    disconnect() {
        window.clearInterval(this.timer);
    }

    // The card's content: the dialog's body or the card's own page, else the list of runs of a page of cards
    container() {
        return this.hasRunsValue ? this.element.closest(".pilot-runs") : this.element.closest("[data-pilot-card-modal-target=body], .pilot-dialog-page");
    }

    // Whether the content must stay as it is: a field being edited in it, or a card open over the list
    held(box) {
        const scope = this.hasRunsValue ? document : box;
        if (this.hasRunsValue && document.querySelector("dialog[open]")) return true;

        return scope.contains(document.activeElement) && document.activeElement.matches("input, textarea, select");
    }

    // The page fetched again, its content swapped in or the page reloaded only when the runs moved on
    check() {
        const box = this.container();
        if (!box || this.busy || this.held(box)) return;

        this.busy = true;
        fetch(this.urlValue, { headers: { "X-Requested-With": "XMLHttpRequest" } })
            .then((response) => (response.ok ? response.text() : Promise.reject(new Error(response.statusText))))
            .then((html) => {
                const fetched = new DOMParser().parseFromString(html, "text/html");
                if (this.hasRunsValue) this.list(fetched, box);
                else this.card(fetched, box);
            })
            .catch(() => {})
            .finally(() => { this.busy = false; });
    }

    // The card swapped in when its runs changed, the dialog told so that the page behind is reloaded once it closes
    card(fetched, box) {
        const page = fetched.querySelector(".pilot-dialog-page");
        const watch = page?.querySelector("[data-controller~='pilot-run-watch']");
        if (!page || watch?.dataset.pilotRunWatchStateValue === this.stateValue) return;
        box.dispatchEvent(new CustomEvent("pilot:changed", { bubbles: true }));
        box.replaceChildren(...page.childNodes);
    }

    // The list swapped in while the same runs move on, the whole page reloaded once one ended or started, its cards showing it
    list(fetched, box) {
        const section = fetched.querySelector(".pilot-runs");
        const watch = section?.querySelector("[data-controller~='pilot-run-watch']");
        if (watch?.dataset.pilotRunWatchRunsValue !== this.runsValue) {
            window.location.reload();
            return;
        }
        if (watch.dataset.pilotRunWatchStateValue !== this.stateValue) box.replaceChildren(...section.childNodes);
    }
}
