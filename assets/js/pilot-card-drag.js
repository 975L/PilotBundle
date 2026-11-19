/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";
import { addSortGesture } from "@c975l/ui-bundle/pointer-sort.js";

// Moves a card to another day of the Planning or to the backlog of its project, to another place in its own, onto another collection's tab or the + making a new one, or onto another space of the column, by dragging its tile with the mouse; the new date, order or space is saved on the drop
export default class extends Controller {
    static targets = ["card", "zone", "space", "set"];
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
        this.space = null;
        this.set = null;
        this.origin = { parent: card.parentElement, next: card.nextElementSibling };
        card.classList.add("opacity-50");
        this.element.classList.add("pilot-dragging");
    }

    // The tile follows the pointer into the zone it is over, before the first tile the pointer is above and left of the middle of (the grid wraps); outside any zone it stays where it is
    moveTo(dragged, x, y) {
        // A space of the column under the pointer is lit, the tile staying where it is
        this.space = this.spaceTargets.find((item) => this.under(item, x, y) && item.dataset.projectId !== dragged.dataset.projectId) || null;
        this.spaceTargets.forEach((item) => {
            item.classList.toggle("pilot-drop-over", item === this.space);
        });
        if (this.space) return;

        // So is the tab of another collection of the space
        this.set = this.setTargets.find((item) => this.under(item, x, y) && item.dataset.collection !== this.origin.parent.dataset.collection) || null;
        this.setTargets.forEach((item) => {
            item.classList.toggle("pilot-drop-over", item === this.set);
        });
        if (this.set) return;

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

    // A new zone saves the card's date (none for the backlog) or its collection on a space's page, a new place in the same zone saves the order of its tiles
    drop(card) {
        this.settle(card);
        if (this.space) return this.move(card, this.space);
        if (this.set) return this.file(card, this.set);
        if (card.parentElement === this.origin.parent) {
            if (card.nextElementSibling !== this.origin.next) this.saveOrder(card.parentElement);

            return;
        }

        // A space's page sorts its cards by collection, the dashboard by day
        const zone = card.parentElement;
        const collection = undefined !== zone.dataset.collection;
        const body = new URLSearchParams(collection ? { _token: card.dataset.token, cardSet: zone.dataset.collection } : { _token: card.dataset.token, due: zone.dataset.due });
        fetch(collection ? card.dataset.editUrl : card.dataset.scheduleUrl, { method: "POST", headers: { "X-Pilot-Dialog": "1" }, body })
            .then((response) => { if (!response.ok) this.failed(); })
            .catch(() => this.failed());
    }

    // The card sent to the space it was dropped on, the page then read again without it
    move(card, space) {
        space.classList.remove("pilot-drop-over");
        this.space = null;
        this.revert(card);
        const body = new URLSearchParams({ _token: card.dataset.token, project: space.dataset.projectId });
        fetch(card.dataset.moveUrl, { method: "POST", headers: { "X-Pilot-Dialog": "1" }, body })
            .then((response) => (response.ok ? window.location.reload() : response.text().then((refusal) => { window.alert(refusal || this.failedLabelValue); })))
            .catch(() => this.failed());
    }

    // The card put in the collection of the tab it was dropped on, the page then read again with it there; dropped on the +, it waits for the new collection's name
    file(card, tab) {
        this.set = null;
        this.revert(card);
        if ("new" === tab.dataset.collection) return this.create(card, tab.closest("details"));
        const body = new URLSearchParams({ _token: card.dataset.token, cardSet: tab.dataset.collection });
        fetch(card.dataset.editUrl, { method: "POST", headers: { "X-Pilot-Dialog": "1" }, body })
            .then((response) => (response.ok ? window.location.reload() : this.failed()))
            .catch(() => this.failed());
    }

    // The +'s panel opened on its name, the card kept in its form until it is sent or closed
    create(card, panel) {
        const field = panel.querySelector("input[name='card']");
        field.value = card.dataset.cardId;
        const clear = () => {
            if (panel.open) return;
            field.value = "";
            panel.removeEventListener("toggle", clear);
        };
        panel.addEventListener("toggle", clear);
        panel.open = true;
        setTimeout(() => panel.querySelector("input[name='name']").focus(), 50);
    }

    under(item, x, y) {
        const box = item.getBoundingClientRect();

        return x >= box.left && x <= box.right && y >= box.top && y <= box.bottom;
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
        [...this.spaceTargets, ...this.setTargets].forEach((item) => {
            item.classList.remove("pilot-drop-over");
        });
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
