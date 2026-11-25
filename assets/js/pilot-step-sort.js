/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { addSortGesture } from "@c975l/ui-bundle/pointer-sort.js";
import PilotListSort from "./pilot-list-sort.js";

// Orders a card's steps by dragging them by their handle within their group, saved on the drop then read again, the indices the other forms carry having moved
export default class extends PilotListSort {
    // A step moves among the steps of its own list, its group's
    moveTo(dragged, y) {
        const list = dragged.parentNode;
        const before = [...list.children].find((item) => {
            if (item === dragged) return false;
            const box = item.getBoundingClientRect();

            return y < box.top + box.height / 2;
        });
        if (before) list.insertBefore(dragged, before);
        else list.appendChild(dragged);
    }

    revert(item) {
        item.classList.remove("pilot-faded");
        if (this.origin) item.parentNode.insertBefore(item, this.origin);
        else item.parentNode.appendChild(item);
    }

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
            .then((response) => (response.ok ? window.location.reload() : this.failed()))
            .catch(() => this.failed());
    }
}
