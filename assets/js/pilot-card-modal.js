/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Opens a card or a file over the page it was clicked on, its own page read and shown in a dialog; the address keeps it as #card-<id> or #doc-<slug>, so a link or the back button reopen it
export default class extends Controller {
    static targets = ["dialog", "body"];
    static values = { url: String };

    connect() {
        this.onClick = (event) => this.click(event);
        this.onPopState = () => this.follow();
        this.onChange = (event) => { if (event.target.matches("[data-pilot-autosubmit]")) event.target.form.requestSubmit(); };
        this.element.addEventListener("click", this.onClick);
        this.element.addEventListener("change", this.onChange);
        this.dialogTarget.addEventListener("submit", (event) => this.submit(event));
        window.addEventListener("popstate", this.onPopState);
        this.dialogTarget.addEventListener("close", () => this.closed());
        this.dialogTarget.addEventListener("click", (event) => { if (event.target === this.dialogTarget) this.dialogTarget.close(); });
        this.follow();
    }

    disconnect() {
        this.element.removeEventListener("click", this.onClick);
        this.element.removeEventListener("change", this.onChange);
        window.removeEventListener("popstate", this.onPopState);
    }

    // A plain click on a card's title or a file's link (data-pilot-dialog naming it); a click asking for a new tab or window, or made during a guided project, keeps the browser's own behaviour
    click(event) {
        const link = event.target.closest("a[data-pilot-dialog]");
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;

        // A guided project running keeps the card on its own page: a modal dialog would make its panel inert
        if (document.querySelector(".guided-project-panel")) return;

        event.preventDefault();
        window.history.pushState(null, "", "#" + link.dataset.pilotDialog);
        this.open(link.href);
    }

    // A gesture made in the dialog stays in it: posted, a refusal shown, then the card read again; the page behind is reloaded once the dialog closes
    submit(event) {
        const form = event.target;
        if (form.method !== "post") return;

        event.preventDefault();
        this.changed = true;
        fetch(form.action, { method: "POST", headers: { "X-Pilot-Dialog": "1" }, body: new FormData(form) })
            .then((response) => (response.ok ? null : response.text()))
            .then((refusal) => {
                if (refusal) window.alert(refusal);
                this.open(this.current);
            })
            .catch(() => form.submit());
    }

    // What the address names: a link of the page naming it, a card by its id otherwise, or nothing
    follow() {
        const name = window.location.hash.slice(1);
        const link = /^(card-\d+|doc-[a-z0-9-]+)$/.test(name) ? this.element.querySelector(`a[data-pilot-dialog="${name}"]`) : null;
        const card = /^card-(\d+)$/.exec(name);
        if (link) this.open(link.href);
        else if (card) this.open(this.urlValue.replace(/0$/, card[1]));
        else if (this.dialogTarget.open) this.dialogTarget.close();
    }

    // The page fetched, its dialog part alone kept; a failure falls back to the page itself
    open(url) {
        this.current = url;
        fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } })
            .then((response) => (response.ok ? response.text() : Promise.reject(new Error(response.statusText))))
            .then((html) => {
                const content = new DOMParser().parseFromString(html, "text/html").querySelector(".pilot-dialog-page");
                if (!content) throw new Error(url);
                this.bodyTarget.replaceChildren(...content.childNodes);
                if (!this.dialogTarget.open) this.dialogTarget.showModal();
            })
            .catch(() => { window.location.href = url; });
    }

    // Closed by its button, Escape or a click beside it: the address no longer names what it showed, the page shows what changed in it
    closed() {
        this.bodyTarget.replaceChildren();
        if (/^#(card|doc)-/.test(window.location.hash)) window.history.pushState(null, "", window.location.pathname + window.location.search);
        if (this.changed) window.location.reload();
    }
}
