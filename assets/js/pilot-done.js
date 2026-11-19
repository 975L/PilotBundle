/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Fills a fold of done cards the first time it opens, so a space's page does not read them on every visit
export default class extends Controller {
    static targets = ["list"];
    static values = { url: String, failedLabel: String };

    // Read once, again on the next opening after a failure
    load() {
        if (!this.element.open || this.loading || this.loaded) return;

        this.loading = true;
        fetch(this.urlValue, { headers: { "X-Requested-With": "XMLHttpRequest" } })
            .then((response) => (response.ok ? response.text() : Promise.reject(new Error(response.statusText))))
            .then((html) => {
                this.listTarget.innerHTML = html;
                this.loaded = true;
            })
            .catch(() => { this.listTarget.textContent = this.failedLabelValue; })
            .finally(() => { this.loading = false; });
    }
}
