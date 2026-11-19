/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { addSortGesture } from "@c975l/ui-bundle/pointer-sort.js";
import PilotListSort from "./pilot-list-sort.js";

// Orders the collection tabs of a space by dragging them along the row with the mouse, saved on the drop; a plain click still opens the tab
export default class extends PilotListSort {
    itemTargetConnected(item) {
        addSortGesture(item, { ...this.gesture(item), touch: false, ignore: "input, form" });
    }

    // The tab goes before the first one whose middle is right of the pointer, else after the last collection, the other tabs staying at the end
    moveTo(dragged, x) {
        const before = this.itemTargets.find((item) => {
            if (item === dragged) return false;
            const box = item.getBoundingClientRect();

            return x < box.left + box.width / 2;
        });
        if (before) this.listTarget.insertBefore(dragged, before);
        else this.itemTargets.filter((item) => item !== dragged).at(-1)?.after(dragged);
    }

    gesture(item) {
        return { ...super.gesture(item), onMove: (dragged, x) => this.moveTo(dragged, x) };
    }

    itemId(item) {
        return item.dataset.setId;
    }
}
