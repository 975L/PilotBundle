/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Base of the vertical lists ordered by dragging, the gesture being UiBundle's: each child gives its handles and saves the order on the drop
export default class extends Controller {
    static targets = ["list", "item"];
    static values = { url: String, token: String, failedLabel: String };

    // Callbacks of the gesture moving this item
    gesture(item) {
        return {
            item,
            onStart: () => this.pickUp(item),
            onMove: (dragged, x, y) => this.moveTo(dragged, y),
            onDrop: () => this.drop(item),
            onCancel: () => this.revert(item),
        };
    }

    pickUp(item) {
        this.origin = item.nextElementSibling;
        item.classList.add("opacity-50");
    }

    // The item goes before the first one whose middle is below the pointer
    moveTo(dragged, y) {
        const before = this.itemTargets.find((item) => {
            if (item === dragged) return false;
            const box = item.getBoundingClientRect();

            return y < box.top + box.height / 2;
        });
        if (before) this.listTarget.insertBefore(dragged, before);
        else this.listTarget.appendChild(dragged);
    }

    // An item put back where it was picked up saves nothing
    drop(item) {
        item.classList.remove("opacity-50");
        if (item.nextElementSibling !== this.origin) this.save();
    }

    revert(item) {
        item.classList.remove("opacity-50");
        this.listTarget.insertBefore(item, this.origin);
    }

    // The ids in their new order, each child naming its items' (itemId), the token travelling as a header
    save() {
        const body = new URLSearchParams();
        this.itemTargets.forEach((item) => { body.append("ids[]", this.itemId(item)); });
        fetch(this.urlValue, { method: "POST", headers: { "X-CSRF-Token": this.tokenValue }, body })
            .then((response) => { if (!response.ok) this.failed(); })
            .catch(() => this.failed());
    }

    // Reloaded so the list shows what was actually saved
    failed() {
        window.alert(this.failedLabelValue);
        window.location.reload();
    }
}
