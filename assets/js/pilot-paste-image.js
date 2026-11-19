/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Joins a screenshot to a line being added: pasted (Ctrl+V) or dropped on its field, or chosen by its button, shown small beside it until the line is sent
export default class extends Controller {
    static targets = ["file", "preview"];

    // The picture of a paste taken, the paste of a text left to the field
    paste(event) {
        const image = [...(event.clipboardData?.files ?? [])].find((file) => file.type.startsWith("image/"));
        if (!image) return;

        event.preventDefault();
        this.take(image);
    }

    // A picture dropped on the field taken as if pasted
    drop(event) {
        const image = [...(event.dataTransfer?.files ?? [])].find((file) => file.type.startsWith("image/"));
        if (!image) return;

        event.preventDefault();
        this.take(image);
    }

    // Dropping allowed only while something is dragged over the field
    over(event) {
        event.preventDefault();
    }

    // The picture chosen by the button shown as well
    chosen() {
        this.show(this.fileTarget.files[0] ?? null);
    }

    // The picture put in the form's file field, as the browser has no other way to send it
    take(image) {
        const transfer = new DataTransfer();
        transfer.items.add(image);
        this.fileTarget.files = transfer.files;
        this.show(image);
    }

    // Read as a data address, the one kind of local picture the Content-Security-Policy lets in
    show(image) {
        this.previewTarget.hidden = !image;
        if (!image) return;

        const reader = new FileReader();
        reader.onload = () => { this.previewTarget.src = reader.result; };
        reader.readAsDataURL(image);
    }
}
