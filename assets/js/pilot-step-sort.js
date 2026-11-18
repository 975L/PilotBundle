/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { addSortGesture } from "@c975l/ui-bundle/pointer-sort.js";
import PilotListSort from "./pilot-list-sort.js";

// Orders a card's steps by dragging them by their handle, saved on the drop then read again, the indices the other forms carry having moved
export default class extends PilotListSort {
    // Each step grabbed by its handle only, its text and buttons keeping their own click
    itemTargetConnected(item) {
        const handle = item.querySelector("[data-pilot-step-sort-handle]");
        if (handle) addSortGesture(handle, this.gesture(item));
    }

    save() {
        const body = new FormData();
        body.append("_token", this.tokenValue);
        this.itemTargets.forEach((item) => { body.append("order[]", item.dataset.index); });
        fetch(this.urlValue, { method: "POST", headers: { "X-Pilot-Dialog": "1" }, body })
            .then((response) => {
                if (!response.ok) window.alert(this.failedLabelValue);
                window.location.reload();
            })
            .catch(() => { window.alert(this.failedLabelValue); window.location.reload(); });
    }
}
