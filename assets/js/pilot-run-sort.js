/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { addSortGesture } from "@c975l/ui-bundle/pointer-sort.js";
import PilotListSort from "./pilot-list-sort.js";

// Orders the queued runs by dragging them by their handle, saved on the drop, the machines taking the first one first
export default class extends PilotListSort {
    // Each queued run grabbed by its handle only, its link and button keeping their own click
    itemTargetConnected(item) {
        const handle = item.querySelector("[data-pilot-run-sort-handle]");
        if (handle) addSortGesture(handle, this.gesture(item));
    }

    itemId(item) {
        return item.dataset.runId;
    }
}
