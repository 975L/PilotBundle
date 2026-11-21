/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Opens or closes the spaces' menu behind its burger on a phone, the column showing it always from a medium screen
export default class extends Controller {
    static targets = ["toggle"];

    toggle() {
        const open = this.element.classList.toggle("pilot-menu-open");
        this.toggleTarget.setAttribute("aria-expanded", open ? "true" : "false");
    }
}
