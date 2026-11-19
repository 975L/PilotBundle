/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Shows one pane of a piloting page's tabs: the one the address names (#collection-12), else the last one picked on this page in this browser, else the first
export default class extends Controller {
    static targets = ["tab", "pane"];

    connect() {
        const keys = this.tabTargets.map((tab) => tab.dataset.tab);
        const wanted = [window.location.hash.slice(1), this.read()].find((key) => keys.includes(key));
        this.select(wanted || keys[0]);
    }

    // A tab picked, the address losing the one it named so a reload keeps the pick
    show(event) {
        if (window.location.hash) window.history.replaceState(null, "", window.location.pathname + window.location.search);
        this.select(event.currentTarget.dataset.tab);
        this.write(event.currentTarget.dataset.tab);
    }

    select(key) {
        this.tabTargets.forEach((tab) => {
            const active = tab.dataset.tab === key;
            tab.classList.toggle("active", active);
            tab.setAttribute("aria-selected", active ? "true" : "false");
        });
        this.paneTargets.forEach((pane) => { pane.hidden = pane.dataset.tab !== key; });
    }

    // Browser storage may be refused (private window, blocked site data): the first tab then simply opens
    read() {
        try {
            return window.localStorage.getItem("pilot-tab:" + window.location.pathname);
        } catch {
            return null;
        }
    }

    write(key) {
        try {
            window.localStorage.setItem("pilot-tab:" + window.location.pathname, key);
        } catch {
            // Nothing kept, nothing lost
        }
    }
}
