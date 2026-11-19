/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Opens the browser's print of the page, the card alone when it shows in the dialog, saved as a PDF from there
export default class extends Controller {
    print(event) {
        event.stopPropagation();
        window.print();
    }
}
