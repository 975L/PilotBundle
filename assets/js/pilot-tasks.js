/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Turns the boxes of a rendered task list (a project's objectives) into the tick of the cards: a click ticks or unticks the box at its place, then the page is read again
export default class extends Controller {
    static values = { url: String, token: String, label: String, failedLabel: String };

    // The boxes in the order of the text, summary then detail, as the server counts them
    connect() {
        this.element.querySelectorAll("input[type=checkbox]").forEach((box, index) => {
            const tick = document.createElement("button");
            tick.type = "button";
            tick.className = "btn btn-sm py-0 px-1 me-1 " + (box.checked ? "btn-success" : "btn-outline-success");
            tick.title = this.labelValue;
            tick.setAttribute("aria-label", this.labelValue);
            tick.setAttribute("aria-pressed", box.checked ? "true" : "false");
            tick.innerHTML = '<i class="fas fa-check"></i>';
            tick.addEventListener("click", (event) => { event.stopPropagation(); this.toggle(index, tick); });
            box.replaceWith(tick);
        });
    }

    toggle(index, tick) {
        tick.disabled = true;
        const body = new URLSearchParams({ _token: this.tokenValue, index: String(index) });
        fetch(this.urlValue, { method: "POST", headers: { "X-Pilot-Dialog": "1" }, body })
            .then((response) => {
                if (!response.ok) throw new Error(response.statusText);
                window.location.reload();
            })
            .catch(() => {
                tick.disabled = false;
                window.alert(this.failedLabelValue);
            });
    }
}
