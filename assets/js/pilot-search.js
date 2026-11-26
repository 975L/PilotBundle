/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Pause after the last key before the search is read, so a word typed is asked once
const DELAY = 300;

// Draws a search's results under its field while typing, the page around left as it is
export default class extends Controller {
    static targets = ["field", "results"];
    static values = { url: String, failedLabel: String };

    disconnect() {
        clearTimeout(this.timer);
        this.request?.abort();
    }

    // Read after a pause, the results emptied below two letters
    type() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.search(), DELAY);
    }

    // Enter reads at once, without leaving the page
    submit(event) {
        event.preventDefault();
        clearTimeout(this.timer);
        this.search();
    }

    // The results of the words in the field, a former reading cancelled
    search() {
        const words = this.fieldTarget.value.trim();
        if (words.length < 2) {
            this.request?.abort();
            this.resultsTarget.innerHTML = "";
            return;
        }
        this.load(`${this.urlValue}?${new URLSearchParams({ q: words })}`);
    }

    // One reading at a time, its html laid in place of the results, a failure said there
    load(url) {
        this.request?.abort();
        this.request = new AbortController();
        fetch(url, { signal: this.request.signal, headers: { "X-Requested-With": "XMLHttpRequest" } })
            .then((response) => (response.ok ? response.text() : Promise.reject(new Error(response.statusText))))
            .then((html) => { this.resultsTarget.innerHTML = html; })
            .catch((error) => {
                if ("AbortError" !== error.name) this.resultsTarget.textContent = this.failedLabelValue;
            });
    }
}
