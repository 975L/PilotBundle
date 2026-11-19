/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Folds a section of a piloting page by a click on its heading, remembered for this page in this browser only
export default class extends Controller {
    connect() {
        this.element.querySelectorAll("[data-pilot-fold]").forEach((section) => {
            if ("1" === this.read(section)) section.classList.add("pilot-folded");
        });
        this.onClick = (event) => this.toggle(event);
        this.element.addEventListener("click", this.onClick);
    }

    disconnect() {
        this.element.removeEventListener("click", this.onClick);
    }

    // A click on a heading, not on a button, link, field or folding box it holds
    toggle(event) {
        const head = event.target.closest(".pilot-fold-head");
        if (!head || event.target.closest("a, button, input, select, textarea, form, details")) return;

        const section = head.closest("[data-pilot-fold]");
        if (!section) return;
        this.write(section, section.classList.toggle("pilot-folded") ? "1" : null);
    }

    key(section) {
        return "pilot-fold:" + window.location.pathname + ":" + section.dataset.pilotFold;
    }

    // Browser storage may be refused (private window, blocked site data): the sections then simply start unfolded
    read(section) {
        try {
            return window.localStorage.getItem(this.key(section));
        } catch {
            return null;
        }
    }

    write(section, value) {
        try {
            if (value) window.localStorage.setItem(this.key(section), value);
            else window.localStorage.removeItem(this.key(section));
        } catch {
            // Nothing kept, nothing lost
        }
    }
}
