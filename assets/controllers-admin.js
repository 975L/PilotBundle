/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { startStimulusApp } from "@symfony/stimulus-bundle";
import AiAssistantController from "@c975l/ui-bundle/ai-assistant.js";
import PilotCardDragController from "./js/pilot-card-drag.js";
import PilotCardModalController from "./js/pilot-card-modal.js";
import PilotCollectionController from "./js/pilot-collection.js";
import PilotCopyController from "./js/pilot-copy.js";
import PilotDoneController from "./js/pilot-done.js";
import PilotFoldController from "./js/pilot-fold.js";
import PilotFoldAllController from "./js/pilot-fold-all.js";
import PilotInlineEditController from "./js/pilot-inline-edit.js";
import PilotMenuController from "./js/pilot-menu.js";
import PilotPasteImageController from "./js/pilot-paste-image.js";
import PilotPrintController from "./js/pilot-print.js";
import PilotProjectSortController from "./js/pilot-project-sort.js";
import PilotRunSortController from "./js/pilot-run-sort.js";
import PilotRunWatchController from "./js/pilot-run-watch.js";
import PilotSearchController from "./js/pilot-search.js";
import PilotStepSortController from "./js/pilot-step-sort.js";
import PilotTabSortController from "./js/pilot-tab-sort.js";
import PilotTabsController from "./js/pilot-tabs.js";

// Controllers of the piloting pages, joining the one Stimulus application every c975L barrel shares
globalThis.c975lStimulusApp ??= startStimulusApp();
const app = globalThis.c975lStimulusApp;

// Donovan's panel, the back office's own script on this shell's markup
app.register("aiAssistant", AiAssistantController);
app.register("pilot-card-drag", PilotCardDragController);
app.register("pilot-card-modal", PilotCardModalController);
app.register("pilot-collection", PilotCollectionController);
app.register("pilot-copy", PilotCopyController);
app.register("pilot-done", PilotDoneController);
app.register("pilot-fold", PilotFoldController);
app.register("pilot-fold-all", PilotFoldAllController);
app.register("pilot-inline-edit", PilotInlineEditController);
app.register("pilot-menu", PilotMenuController);
app.register("pilot-paste-image", PilotPasteImageController);
app.register("pilot-print", PilotPrintController);
app.register("pilot-project-sort", PilotProjectSortController);
app.register("pilot-run-sort", PilotRunSortController);
app.register("pilot-run-watch", PilotRunWatchController);
app.register("pilot-search", PilotSearchController);
app.register("pilot-step-sort", PilotStepSortController);
app.register("pilot-tab-sort", PilotTabSortController);
app.register("pilot-tabs", PilotTabsController);
