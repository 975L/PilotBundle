/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Copies a card's link to the clipboard (the one it has, or the one a gesture makes the first time, sent back in X-Pilot-Share), or a text followed by the context an element holds; its button turning green or red for a moment to tell whether it worked
export default class extends Controller {
    static values = { text: String, url: String, token: String, context: String };

    // The text handed to the clipboard as a promise, so that the browser still counts the click while the link is being made
    copy(event) {
        event.stopPropagation();
        const text = this.textValue ? Promise.resolve(this.textValue + this.attached()) : this.make();
        const item = new ClipboardItem({ "text/plain": text.then((value) => new Blob([value], { type: "text/plain" })) });
        navigator.clipboard.write([item]).then(() => this.flash("pilot-copied"), () => this.flash("pilot-copy-failed"));
    }

    // The text of the element the context names (a card's markdown), after a blank line, none without one
    attached() {
        const source = this.contextValue ? document.getElementById(this.contextValue) : null;

        return source ? "\n\n" + source.value : "";
    }

    // The gesture making the link, the same link kept for the next clicks
    make() {
        const body = new URLSearchParams({ _token: this.tokenValue });

        return fetch(this.urlValue, { method: "POST", headers: { "X-Pilot-Dialog": "1" }, body }).then((response) => {
            const link = response.headers.get("X-Pilot-Share");
            if (!response.ok || !link) throw new Error(response.statusText);
            this.textValue = link;

            return link;
        });
    }

    // The button marked copied or failed for a moment
    flash(state) {
        this.element.classList.add(state);
        setTimeout(() => { this.element.classList.remove(state); }, 1500);
    }
}
