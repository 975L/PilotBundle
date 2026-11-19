/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Edits a field of a card in place: a click on its text shows the form rendered beside it, leaving the field saves it, Escape gives up
export default class extends Controller {
    static targets = ["view", "form"];

    // The text clicked, a link it holds keeping its own behaviour
    edit(event) {
        if (event.target.closest("a")) return;

        this.viewTarget.hidden = true;
        this.formTarget.hidden = false;
        const field = this.formTarget.querySelector("input:not([type=hidden]), textarea, select");
        field?.focus();
        // The caret at the end of a text, a date or a choice having none
        if (field?.matches("textarea, input:not([type]), input[type=text], input[type=url]")) field.setSelectionRange(field.value.length, field.value.length);
    }

    // Focus leaving the form saves it, unless the form was given up or nothing changed
    leave(event) {
        if (this.formTarget.hidden || this.formTarget.contains(event.relatedTarget)) return;
        if (!this.changed()) return this.close();

        this.formTarget.requestSubmit();
    }

    // Escape restores the values and the text; Enter in a one-line field saves it, the browser's own submit
    key(event) {
        if ("Escape" !== event.key) return;

        event.preventDefault();
        event.stopPropagation();
        this.formTarget.reset();
        this.close();
    }

    close() {
        this.formTarget.hidden = true;
        this.viewTarget.hidden = false;
    }

    // A field typed in or a choice moved since the page was read
    changed() {
        return [...this.formTarget.elements].some((field) => {
            if ("select" === field.localName) return [...field.options].some((option) => option.selected !== option.defaultSelected);

            return field.matches("input:not([type=hidden]), textarea") && field.value !== field.defaultValue;
        });
    }
}
