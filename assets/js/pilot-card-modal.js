/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";
import { notice } from "./pilot-notice.js";

// Opens a card or a file over the page it was clicked on, its own page read and shown in a dialog; the address keeps it as #card-<id> or #doc-<slug>, so a link or the back button reopen it
export default class extends Controller {
    static targets = ["dialog", "body", "confirm", "ask", "zoom", "zoomImage", "previous", "next"];
    static values = { url: String, failedLabel: String };

    connect() {
        this.onClick = (event) => this.click(event);
        this.onPopState = () => this.follow();
        this.onChange = (event) => { if (event.target.matches("[data-pilot-autosubmit]")) event.target.form.requestSubmit(); };
        this.element.addEventListener("click", this.onClick);
        this.element.addEventListener("change", this.onChange);
        // A form asking first (data-pilot-confirm) is stopped before anything sends it, in the dialog or on the page, then sent once confirmed
        this.onConfirm = (event) => this.ask(event);
        this.confirmTarget.addEventListener("close", () => this.answered());
        this.zoomTarget.addEventListener("click", () => this.zoomTarget.close());
        this.element.addEventListener("submit", this.onConfirm, true);
        // An open + or gear panel closes on a click beside it or on Escape, as a dialog would
        this.onOutside = (event) => this.fold(event.target);
        this.onEscape = (event) => {
            if ("Escape" !== event.key) return;
            if (this.fold(null)) event.preventDefault();
            // A dialog shown without its backdrop (a guided project running) is not closed by Escape on its own
            else if (this.dialogTarget.open && !this.dialogTarget.matches(":modal")) this.dialogTarget.close();
        };
        document.addEventListener("click", this.onOutside);
        document.addEventListener("keydown", this.onEscape);
        this.onArrow = (event) => this.arrowed(event);
        document.addEventListener("keydown", this.onArrow);
        this.dialogTarget.addEventListener("submit", (event) => this.submit(event));
        // A line being added (data-pilot-refocus) is sent when focus leaves it, as a field edited in place is saved; a press inside its own form (its picture button) keeps it
        this.dialogTarget.addEventListener("pointerdown", (event) => { this.pressed = event.target; }, true);
        this.dialogTarget.addEventListener("focusout", (event) => this.leave(event));
        window.addEventListener("popstate", this.onPopState);
        this.dialogTarget.addEventListener("close", () => this.closed());
        this.dialogTarget.addEventListener("click", (event) => { if (event.target === this.dialogTarget) this.dialogTarget.close(); });
        // A horizontal swipe on the dialog goes to the card beside, as its arrows do
        this.dialogTarget.addEventListener("touchstart", (event) => this.touched(event), { passive: true });
        this.dialogTarget.addEventListener("touchend", (event) => this.swiped(event));
        // The card read again by pilot-run-watch changed: the page behind shows it once the dialog closes
        this.dialogTarget.addEventListener("pilot:changed", () => { this.changed = true; });
        this.follow();
    }

    disconnect() {
        this.element.removeEventListener("click", this.onClick);
        this.element.removeEventListener("change", this.onChange);
        this.element.removeEventListener("submit", this.onConfirm, true);
        window.removeEventListener("popstate", this.onPopState);
        document.removeEventListener("click", this.onOutside);
        document.removeEventListener("keydown", this.onEscape);
        document.removeEventListener("keydown", this.onArrow);
    }

    // The asking shown with the form's question, the form held until it is answered
    ask(event) {
        const form = event.target;
        if (!form.dataset.pilotConfirm || form === this.confirmed) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        this.asking = form;
        this.askTarget.textContent = form.dataset.pilotConfirm;
        this.confirmTarget.returnValue = "";
        this.confirmTarget.showModal();
    }

    // Sent again on a yes, this time let through; left as it was otherwise
    answered() {
        const form = this.asking;
        this.asking = null;
        if (!form || "confirm" !== this.confirmTarget.returnValue) return;

        this.confirmed = form;
        form.requestSubmit();
        this.confirmed = null;
    }

    // The open panels not holding the given element closed, focus back on the button of one that held it; true when one was closed. A click on a guided project's panel leaves them open, its next step pointing into the one it just had opened
    fold(inside) {
        if (inside?.closest?.(".guided-project-panel")) return false;

        const open = [...document.querySelectorAll("details.pilot-card-new[open]")].filter((panel) => !panel.contains(inside));
        open.forEach((panel) => {
            if (panel.contains(document.activeElement)) panel.querySelector("summary")?.focus();
            panel.open = false;
        });

        return 0 < open.length;
    }

    // A plain click on a card's title or a file's link (data-pilot-dialog naming it); a click asking for a new tab or window, or made during a guided project, keeps the browser's own behaviour
    click(event) {
        if (this.zoomed(event)) return;

        const link = event.target.closest("a[data-pilot-dialog]");
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;

        event.preventDefault();
        window.history.pushState(null, "", "#" + link.dataset.pilotDialog);
        this.open(link.href);
    }

    // A screenshot (data-pilot-zoom) shown full size in its own dialog rather than in a new tab, a click anywhere on it closing it
    zoomed(event) {
        const link = event.target.closest("a[data-pilot-zoom]");
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return false;

        event.preventDefault();
        this.zoomImageTarget.src = link.href;
        this.zoomTarget.showModal();

        return true;
    }

    // A gesture made in the dialog stays in it: posted, a refusal shown, then the card read again while the dialog is still open (a field edited in place is saved by the very click that closes it); the page behind is reloaded once the dialog closes
    submit(event) {
        const form = event.target;
        if (form.method !== "post") return;

        event.preventDefault();
        this.changed = true;
        // The form on its way, focus leaving it meanwhile sending it no second time
        this.sending = form;
        // A line added by Enter (data-pilot-refocus) keeps its field focused once the card is read again, for the next one
        this.refocus = form.querySelector("[data-pilot-refocus]")?.contains(document.activeElement) ? form.getAttribute("action") : null;
        let next = null;
        let gone = false;
        // The attribute rather than form.action, which a field named "action" (a step's machine action) shadows
        this.pending = fetch(form.getAttribute("action"), { method: "POST", headers: { "X-Pilot-Dialog": "1" }, body: new FormData(form) })
            .then((response) => {
                // A gesture that made another card (a copy) names it, the dialog then showing it; a card deleted closes the dialog
                next = response.headers.get("X-Pilot-Location");
                gone = response.ok && null !== response.headers.get("X-Pilot-Gone");

                return response.ok ? null : response.text();
            })
            // A refusal read before the card is, or before the page behind reloads once the dialog closed meanwhile
            .then((refusal) => (refusal ? notice(refusal) : null))
            .then(() => {
                if (gone) return this.dialogTarget.close();
                if (next) window.history.replaceState(null, "", "#card-" + next.split("/").pop());
                // The form counted as sending until the card is read again, its filled field removed then sending it no second time
                if (this.dialogTarget.open) return this.open(next || this.current);
            })
            .catch(() => form.submit())
            .finally(() => { if (this.sending === form) this.sending = null; });
    }

    // The line typed sent once focus goes outside its form, an empty one left as it is
    leave(event) {
        const field = event.target;
        const form = field.form;
        const pressed = this.pressed;
        this.pressed = null;
        if (!field.matches?.("[data-pilot-refocus]") || !form.isConnected || form === this.sending || "" === field.value.trim()) return;
        if (form.contains(event.relatedTarget) || form.contains(pressed)) return;

        form.requestSubmit();
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

    // The cards of the page the dialog steps through, in their order, once each: those shown, never one inside the dialog itself
    neighbours() {
        const names = [...this.element.querySelectorAll('a[data-pilot-dialog^="card-"]')]
            .filter((link) => !this.dialogTarget.contains(link) && 0 < link.getClientRects().length)
            .map((link) => link.dataset.pilotDialog);

        return [...new Set(names)];
    }

    // The card before (-1) or after (1) the one shown, or null at either end, for a file, or for a card the page does not list
    beside(step) {
        const names = this.neighbours();
        const index = names.indexOf(window.location.hash.slice(1));

        return -1 === index ? null : (names[index + step] ?? null);
    }

    // The arrows shown only where a card is beside
    arrows() {
        this.previousTarget.hidden = null === this.beside(-1);
        this.nextTarget.hidden = null === this.beside(1);
    }

    // The arrows' actions
    previous() {
        this.go(-1);
    }

    next() {
        this.go(1);
    }

    // The card beside shown in the dialog, the address naming it in place of the one it replaces, so the back button still closes the dialog; a field saved by the click that leaves it is waited for
    go(step) {
        const name = this.beside(step);
        if (!name) return;

        const link = this.element.querySelector(`a[data-pilot-dialog="${name}"]`);
        window.history.replaceState(null, "", "#" + name);
        Promise.resolve(this.pending).finally(() => this.open(link.href));
    }

    // Left and right arrow keys step through the cards while the dialog is the one on top, never while a field is typed in nor with a modifier
    arrowed(event) {
        const step = { ArrowLeft: -1, ArrowRight: 1 }[event.key];
        if (!step || !this.dialogTarget.open || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        if (event.target.closest?.("input, textarea, select, [contenteditable]") || document.querySelector("dialog[open]:not(.pilot-dialog-pinned)")) return;

        event.preventDefault();
        this.go(step);
    }

    // Where a swipe starts, left aside when it starts on a field, a handle dragging a step or a block scrolling sideways
    touched(event) {
        const start = event.touches[0];
        this.swipe = 1 === event.touches.length && !event.target.closest("input, textarea, select, [contenteditable], .pilot-sort-handle, pre, table") ? { x: start.clientX, y: start.clientY } : null;
    }

    // A swipe clearly sideways, long enough and with no text selected, goes to the card after (to the left) or before (to the right)
    swiped(event) {
        const start = this.swipe;
        this.swipe = null;
        if (!start || "" !== String(window.getSelection())) return;

        const dx = event.changedTouches[0].clientX - start.x;
        const dy = event.changedTouches[0].clientY - start.y;
        if (60 > Math.abs(dx) || Math.abs(dx) < 2 * Math.abs(dy)) return;

        this.go(0 > dx ? 1 : -1);
    }

    // The page fetched, its dialog part alone kept, a reply overtaken by a later opening (arrows pressed in a row) dropped; a failure said, the card's own address leading back here
    open(url) {
        this.current = url;

        return fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } })
            .then((response) => (response.ok ? response.text() : Promise.reject(new Error(response.statusText))))
            .then((html) => {
                if (url !== this.current) return;
                const content = new DOMParser().parseFromString(html, "text/html").querySelector(".pilot-dialog-page");
                if (!content) throw new Error(url);
                this.bodyTarget.replaceChildren(...content.childNodes);
                if (!this.dialogTarget.open) this.show();
                this.arrows();
                if (this.refocus) this.bodyTarget.querySelector(`form[action="${CSS.escape(this.refocus)}"] [data-pilot-refocus]`)?.focus();
                this.refocus = null;
            })
            .catch(() => notice(this.failedLabelValue));
    }

    // The dialog opened centred, then held by its top so a group folded or a card read again moves its bottom only; left without the modal's backdrop while a guided project runs, which would make its panel inert, the card shown all the same as it is
    show() {
        const dialog = this.dialogTarget;
        if (document.querySelector(".guided-project-panel")) dialog.show();
        else dialog.showModal();
        dialog.style.setProperty("--pilot-dialog-top", Math.max(8, dialog.getBoundingClientRect().top) + "px");
        dialog.classList.add("pilot-dialog-pinned");
    }

    // Closed by its button, Escape or a click beside it: the address no longer names what it showed, the page shows what changed in it
    closed() {
        this.bodyTarget.replaceChildren();
        this.dialogTarget.classList.remove("pilot-dialog-pinned");
        if (/^#(card|doc)-/.test(window.location.hash)) window.history.pushState(null, "", window.location.pathname + window.location.search);
        // A gesture still on its way is waited for, the reload would cut it
        if (this.changed) Promise.resolve(this.pending).finally(() => window.location.reload());
    }
}
