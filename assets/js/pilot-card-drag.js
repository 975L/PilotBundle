/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";
import { addSortGesture } from "@c975l/ui-bundle/pointer-sort.js";

// Moves a card to another day of the Planning or to the backlog of its project, or to another place in its own, by dragging its tile with the mouse; the new date or order is saved on the drop
export default class extends Controller {
    static targets = ["card", "zone"];
    static values = { failedLabel: String, orderUrl: String, orderToken: String };

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

    // The tile follows the pointer into the zone it is over, before the first tile the pointer is above and left of the middle of (the grid wraps); outside any zone it stays where it is
    moveTo(dragged, x, y) {
        const zone = this.zoneTargets.find((item) => {
            const box = item.getBoundingClientRect();

            return x >= box.left && x <= box.right && y >= box.top && y <= box.bottom;
        });
        if (!zone) return;

        const before = [...zone.children].find((tile) => {
            if (tile === dragged || !tile.matches("[data-pilot-card-drag-target='card']")) return false;
            const box = tile.getBoundingClientRect();

            return y < box.bottom && x < box.left + box.width / 2;
        });
        if (before) zone.insertBefore(dragged, before);
        else zone.appendChild(dragged);
    }

    // A new zone saves the card's date (none for the backlog), a new place in the same zone saves the order of its tiles
    drop(card) {
        this.settle(card);
        if (card.parentElement === this.origin.parent) {
            if (card.nextElementSibling !== this.origin.next) this.saveOrder(card.parentElement);

            return;
        }

        const body = new URLSearchParams({ _token: card.dataset.token, due: card.parentElement.dataset.due });
        fetch(card.dataset.scheduleUrl, { method: "POST", headers: { "X-Pilot-Dialog": "1" }, body })
            .then((response) => { if (!response.ok) this.failed(); })
            .catch(() => this.failed());
    }

    saveOrder(zone) {
        const body = new URLSearchParams();
        zone.querySelectorAll("[data-pilot-card-drag-target='card']").forEach((tile) => { body.append("ids[]", tile.dataset.cardId); });
        fetch(this.orderUrlValue, { method: "POST", headers: { "X-CSRF-Token": this.orderTokenValue }, body })
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
