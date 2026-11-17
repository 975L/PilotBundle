/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";
import { addSortGesture } from "@c975l/ui-bundle/pointer-sort.js";

// Orders the projects of the left column by dragging them, saved on the drop; the gesture is UiBundle's, mouse and finger alike
export default class extends Controller {
    static targets = ["list", "item"];
    static values = { url: String, token: String, failedLabel: String };

    // Each project grabbed by its handle at the finger, anywhere with a mouse: a plain click still opens it
    itemTargetConnected(item) {
        const gesture = {
            item,
            onStart: () => this.pickUp(item),
            onMove: (dragged, x, y) => this.moveTo(dragged, y),
            onDrop: () => this.drop(item),
            onCancel: () => this.revert(item),
        };
        const handle = item.querySelector("[data-pilot-project-sort-handle]");
        if (handle) addSortGesture(handle, gesture);
        addSortGesture(item, { ...gesture, touch: false, ignore: "[data-pilot-project-sort-handle]" });
    }

    pickUp(item) {
        this.origin = item.nextElementSibling;
        item.classList.add("opacity-50");
    }

    // A vertical list: the project goes before the first one whose middle is below the pointer
    moveTo(dragged, y) {
        const before = this.itemTargets.find((item) => {
            if (item === dragged) return false;
            const box = item.getBoundingClientRect();

            return y < box.top + box.height / 2;
        });
        if (before) this.listTarget.insertBefore(dragged, before);
        else this.listTarget.appendChild(dragged);
    }

    // A project put back where it was picked up saves nothing
    drop(item) {
        item.classList.remove("opacity-50");
        if (item.nextElementSibling !== this.origin) this.save();
    }

    revert(item) {
        item.classList.remove("opacity-50");
        this.listTarget.insertBefore(item, this.origin);
    }

    save() {
        const body = new URLSearchParams();
        this.itemTargets.forEach((item) => { body.append("ids[]", item.dataset.projectId); });
        fetch(this.urlValue, { method: "POST", headers: { "X-CSRF-Token": this.tokenValue }, body })
            .then((response) => { if (!response.ok) this.failed(); })
            .catch(() => this.failed());
    }

    // Reloaded so the column shows what was actually saved
    failed() {
        window.alert(this.failedLabelValue);
        window.location.reload();
    }
}
