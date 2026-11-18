/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { startStimulusApp } from "@symfony/stimulus-bundle";
import PilotCardDragController from "./js/pilot-card-drag.js";
import PilotCardModalController from "./js/pilot-card-modal.js";
import PilotCopyController from "./js/pilot-copy.js";
import PilotFoldController from "./js/pilot-fold.js";
import PilotInlineEditController from "./js/pilot-inline-edit.js";
import PilotProjectSortController from "./js/pilot-project-sort.js";
import PilotRunWatchController from "./js/pilot-run-watch.js";
import PilotStepSortController from "./js/pilot-step-sort.js";
import PilotTasksController from "./js/pilot-tasks.js";

// Back-office controllers of the piloting pages, joining the one Stimulus application every c975L barrel shares
globalThis.c975lStimulusApp ??= startStimulusApp();
const app = globalThis.c975lStimulusApp;

app.register("pilot-card-drag", PilotCardDragController);
app.register("pilot-card-modal", PilotCardModalController);
app.register("pilot-copy", PilotCopyController);
app.register("pilot-fold", PilotFoldController);
app.register("pilot-inline-edit", PilotInlineEditController);
app.register("pilot-project-sort", PilotProjectSortController);
app.register("pilot-run-watch", PilotRunWatchController);
app.register("pilot-step-sort", PilotStepSortController);
app.register("pilot-tasks", PilotTasksController);
