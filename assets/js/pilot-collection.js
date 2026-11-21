/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// The entries of a form collection (a template's steps) added from their prototype and removed in place, the form saving them as they stand
export default class extends Controller {
    static targets = ["list", "entry"];
    static values = { prototype: String, index: Number, removeLabel: String };

    // A new entry at the end, numbered after the last one ever added
    add() {
        const item = document.createElement("li");
        item.className = "pilot-framed";
        item.dataset.pilotCollectionTarget = "entry";
        item.innerHTML = this.prototypeValue.replace(/__name__/g, String(this.indexValue));
        const remove = document.createElement("button");
        remove.type = "button";
        remove.className = "pilot-btn pilot-btn-ghost pilot-btn-delete";
        remove.dataset.action = "pilot-collection#remove";
        remove.textContent = this.removeLabelValue;
        item.append(remove);
        this.listTarget.append(item);
        this.indexValue += 1;
    }

    // The entry holding the clicked button taken out
    remove(event) {
        event.target.closest("[data-pilot-collection-target='entry']")?.remove();
    }
}
