/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Folds at once the groups of a card's steps that are entirely ticked, leaving open those still to do, and unfolds every group again, the button saying what its next click does as the groups are opened or closed one by one
export default class extends Controller {
    static targets = ["fold", "unfold", "bar"];

    connect() {
        this.onToggle = () => this.show();
        this.element.addEventListener("toggle", this.onToggle, true);
        this.show();
    }

    disconnect() {
        this.element.removeEventListener("toggle", this.onToggle, true);
    }

    // The finished groups closed when one of them is open, every group opened otherwise
    toggle() {
        const finished = this.finished();
        if (finished.some((group) => group.open)) {
            finished.forEach((group) => { group.open = false; });
        } else {
            this.groups().forEach((group) => { group.open = true; });
        }
        this.show();
    }

    // The fold label while a finished group is open, the unfold one while a group is closed, the button hidden when there is nothing to do
    show() {
        const fold = this.finished().some((group) => group.open);
        const unfold = !fold && this.groups().some((group) => !group.open);
        this.foldTargets.forEach((element) => { element.hidden = !fold; });
        this.unfoldTargets.forEach((element) => { element.hidden = !unfold; });
        this.barTargets.forEach((element) => { element.hidden = !fold && !unfold; });
    }

    // Every fold of the steps: the groups and the ticked steps a list starts with
    groups() {
        return [...this.element.querySelectorAll("details.pilot-step-group, .pilot-steps-done > details")];
    }

    // The folds whose steps are all ticked
    finished() {
        return [...this.element.querySelectorAll("details.pilot-step-group[data-pilot-fold-all-finished], .pilot-steps-done > details")];
    }
}
