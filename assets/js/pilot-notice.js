/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// A refusal or a failure said in the layout's notice dialog, over the page or the card's dialog, as the back office says it; the browser's alert where the page has no such dialog. Resolved once closed, so a reload waits for the message to be read
export function notice(message) {
    const dialog = document.querySelector("dialog.pilot-notice");
    if (!dialog) {
        window.alert(message);

        return Promise.resolve();
    }
    dialog.querySelector("[data-pilot-notice-message]").textContent = message;
    if (!dialog.open) dialog.showModal();

    return new Promise((resolve) => { dialog.addEventListener("close", resolve, { once: true }); });
}
