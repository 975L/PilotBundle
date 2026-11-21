/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Folds or unfolds every group of a card's steps at once, the button saying what its next click does as the groups are opened or closed one by one
export default class extends Controller {
    static targets = ["fold", "unfold"];

    connect() {
        this.onToggle = () => this.show();
        this.element.addEventListener("toggle", this.onToggle, true);
        this.show();
    }

    disconnect() {
        this.element.removeEventListener("toggle", this.onToggle, true);
    }

    // Every group closed when one is open, all opened otherwise
    toggle() {
        const open = this.groups().some((group) => group.open);
        this.groups().forEach((group) => { group.open = !open; });
        this.show();
    }

    // The fold label while a group is open, the unfold one once all are closed
    show() {
        const open = this.groups().some((group) => group.open);
        this.foldTargets.forEach((element) => { element.hidden = !open; });
        this.unfoldTargets.forEach((element) => { element.hidden = open; });
    }

    groups() {
        return [...this.element.querySelectorAll("details.pilot-step-group, .pilot-steps-done > details")];
    }
}
