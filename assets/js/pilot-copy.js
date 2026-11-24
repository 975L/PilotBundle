/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Copies a text to the clipboard (a card's number, a step handed to an AI) followed by the context an element holds; its button turning green or red for a moment to tell whether it worked
export default class extends Controller {
    static values = { text: String, context: String };

    // The text handed to the clipboard
    copy(event) {
        event.stopPropagation();
        navigator.clipboard.writeText(this.textValue + this.attached()).then(() => this.flash("pilot-copied"), () => this.flash("pilot-copy-failed"));
    }

    // The text of the element the context names (a card's markdown), after a blank line, none without one
    attached() {
        const source = this.contextValue ? document.getElementById(this.contextValue) : null;

        return source ? "\n\n" + source.value : "";
    }

    // The button marked copied or failed for a moment
    flash(state) {
        this.element.classList.add(state);
        setTimeout(() => { this.element.classList.remove(state); }, 1500);
    }
}
