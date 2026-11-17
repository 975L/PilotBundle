/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";
import { addSortGesture } from "@c975l/ui-bundle/pointer-sort.js";

// Moves a card to another day of the Planning, or to the backlog of its project, by dragging its tile with the mouse; the new date is saved on the drop
export default class extends Controller {
    static targets = ["card", "zone"];
    static values = { failedLabel: String };

    // A click on the tile still opens it, buttons and forms inside it keep theirs
    cardTargetConnected(card) {
        addSortGesture(card, {
            item: card,
            touch: false,
            ignore: "button, input, select, form",
            onStart: () => this.pickUp(card),
            onMove: (dragged, x, y) => this.moveTo(dragged, x, y),
            onDrop: () => this.drop(card),
            onCancel: () => this.revert(card),
        });
    }

    pickUp(card) {
        this.origin = { parent: card.parentElement, next: card.nextElementSibling };
        card.classList.add("opacity-50");
        this.element.classList.add("pilot-dragging");
    }

    // The tile follows the pointer into the zone it is over, at the end of it; outside any zone it stays where it is
    moveTo(dragged, x, y) {
        const zone = this.zoneTargets.find((item) => {
            const box = item.getBoundingClientRect();

            return x >= box.left && x <= box.right && y >= box.top && y <= box.bottom;
        });
        if (zone && dragged.parentElement !== zone) zone.appendChild(dragged);
    }

    // Saved only when the tile changed zone, under the day of that zone (none for the backlog)
    drop(card) {
        this.settle(card);
        if (card.parentElement === this.origin.parent) return;

        const body = new URLSearchParams({ _token: card.dataset.token, due: card.parentElement.dataset.due });
        fetch(card.dataset.scheduleUrl, { method: "POST", headers: { "X-Pilot-Dialog": "1" }, body })
            .then((response) => { if (!response.ok) this.failed(); })
            .catch(() => this.failed());
    }

    revert(card) {
        this.settle(card);
        this.origin.parent.insertBefore(card, this.origin.next);
    }

    settle(card) {
        card.classList.remove("opacity-50");
        this.element.classList.remove("pilot-dragging");
    }

    // Reloaded so the page shows what was actually saved
    failed() {
        window.alert(this.failedLabelValue);
        window.location.reload();
    }
}
