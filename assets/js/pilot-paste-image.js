/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Joins screenshots to a line being added, up to the most allowed: pasted (Ctrl+V) or dropped on its field one after the other, or chosen together by its button, shown small beside it until the line is sent
export default class extends Controller {
    static targets = ["file", "preview"];
    static values = { max: { type: Number, default: 3 } };

    // The pictures of a paste taken, the paste of a text left to the field
    paste(event) {
        const images = this.pictures(event.clipboardData?.files);
        if (0 === images.length) return;

        event.preventDefault();
        this.take(images);
    }

    // The pictures dropped on the field taken as if pasted
    drop(event) {
        const images = this.pictures(event.dataTransfer?.files);
        if (0 === images.length) return;

        event.preventDefault();
        this.take(images);
    }

    // Dropping allowed only while something is dragged over the field
    over(event) {
        event.preventDefault();
    }

    // The pictures chosen by the button replacing those joined so far, kept to the most allowed
    chosen() {
        this.put(this.pictures(this.fileTarget.files));
    }

    // Only the pictures among the files given
    pictures(files) {
        return [...(files ?? [])].filter((file) => file.type.startsWith("image/"));
    }

    // The pictures added to those already joined, the oldest kept once the most allowed is reached
    take(images) {
        this.put([...this.fileTarget.files, ...images]);
    }

    // The pictures put in the form's file field, as the browser has no other way to send them, and shown
    put(images) {
        const transfer = new DataTransfer();
        for (const image of images.slice(0, this.maxValue)) transfer.items.add(image);
        this.fileTarget.files = transfer.files;
        this.show([...transfer.files]);
    }

    // Each read as a data address, the one kind of local picture the Content-Security-Policy lets in
    show(images) {
        this.previewTarget.replaceChildren();
        this.previewTarget.hidden = 0 === images.length;
        images.forEach((image) => {
            const preview = document.createElement("img");
            preview.className = "pilot-feedback-shot-preview";
            preview.alt = this.previewTarget.dataset.alt ?? "";
            this.previewTarget.append(preview);
            const reader = new FileReader();
            reader.onload = () => { preview.src = reader.result; };
            reader.readAsDataURL(image);
        });
    }
}
