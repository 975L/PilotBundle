/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { addSortGesture } from "@c975l/ui-bundle/pointer-sort.js";
import PilotListSort from "./pilot-list-sort.js";

// Orders the projects of the left column by dragging them, saved on the drop, mouse and finger alike
export default class extends PilotListSort {
    // Each project grabbed by its handle at the finger, anywhere with a mouse: a plain click still opens it
    itemTargetConnected(item) {
        const gesture = this.gesture(item);
        const handle = item.querySelector("[data-pilot-project-sort-handle]");
        if (handle) addSortGesture(handle, gesture);
        addSortGesture(item, { ...gesture, touch: false, ignore: "[data-pilot-project-sort-handle]" });
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
