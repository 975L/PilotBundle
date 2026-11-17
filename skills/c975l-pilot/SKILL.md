---
name: c975l-pilot
description: "Use this skill when working with project piloting in a Symfony application built on the c975L ecosystem with c975l/pilot-bundle. Covers projects, cards (backlog without a date, planning with one, steps of a plan), files written on two floors (a short summary for a human, the detail for Claude), plans built from templates, runs executed by a runner machine under a lease, and the import of the Pilotage artifact. Triggers on: pilot, objectives, objectivesOf, this week, horizon, pilot-horizon-days, PilotController, Planning, MenuProvider, sidebar, PilotGuidedProjectProvider, piloting, backlog, planning, card, Card, Project, Doc, summary, detail, Plan, PlanTemplate, Run, Machine, runner, lease, CardStatus, RunStatus, Executor, DocKind, ArtifactReader, ArtifactImporter, c975l:pilot:import, import the artifact, Pilotage artifact, RunnerController, RunQueue, RunOutputs, Report, ReportIngester, report.json, waitingOn, character, portrait, pilot_markdown, cost, fold, DocIngester, doc.md, under way, cancel, CardFlow, PlanFactory, Action, Credit, credits, c975l:pilot:machine-add, machine token, /api/pilot/runner."
---

# c975L PilotBundle

> Projects as cards on a weekly planning, each card able to run an action on a runner machine and come back for validation.

**Package:** `c975l/pilot-bundle` (private) · **Namespace:** `c975L\PilotBundle\` · **Tables:** `pilot_*`

**Key source paths** (relative to the package root):
`src/Entity/`, `src/Enum/`, `src/Repository/`, `src/Service/`, `src/Command/`, `src/Controller/Management/`, `src/Management/`, `src/Controller/RunnerController.php`, `templates/management/`, `assets/`, `config/services.yaml`, `config/configs.json`

**Related skills:** `c975l-config` (the `pilot-horizon-days` key), `c975l-management` (sidebar, onboarding tour, guided projects)

**Related documentation:** this package's `README.md` is the reference. The ecosystem's rules live in `c975l/core-bundle`.

## The model

- A `Project` holds cards and files. Its `slug` is the key the import matches on.
- A `Card` without a date is in the backlog (`isInBacklog()`), with a date it is on the planning. In a `Plan` it is a step at a position, done by a human or a runner (`Executor`), as a task or a validation (`CardKind`).
- A `Doc` has two floors: `getSummary()` (five lines a human reads) and `getDetail()` (folded, for Claude). `write()` sets both at once.
- A `Run` is one execution of a runner card. A `Machine` claims it under a lease of `Run::LEASE_SECONDS`, reports progress, then finishes it.

## The screens

`PilotController` serves five back-office pages: the Planning (`Planning` splits the open dated cards into late ones, the coming days up to `pilot-horizon-days`, then the later ones by month), a project (its objectives, dated cards, backlog, files, done cards folded), the search, a card and a file. A card shows on three lines (`_card.html.twig`), a file shows its summary with the detail folded (`_doc.html.twig`). `MenuProvider` puts the Planning and the settings (`ProjectCrudController`, `PlanTemplateCrudController` with `PlanStepType`, `ActionCrudController`, `MachineCrudController`, `CreditCrudController`) in the sidebar, the balances (`Credit`) show on the Planning, plans come from `PlanFactory` (a `PlanTemplate` is offered only on its projects, `PlanTemplateRepository::findOfferedTo()`) and move through `CardFlow`, machines call `RunnerController` and their actions are recorded as `Action` rows; the planning, the search and the projects are listed in the column on the left of every piloting page (`_layout.html.twig`, fed by `PilotController::page()`), ordered by dragging them (`assets/js/pilot-project-sort.js`, saved by `PilotController::order()` through `ProjectOrder`); a card clicked in a listing opens in a dialog (`assets/js/pilot-card-modal.js` keeps the `.pilot-dialog-page` part of its page, a file's too through `data-pilot-dialog`, posts the gestures made there and reads the page again), is added by `PilotController::newCard()` (`_card_new.html.twig`), and is dragged to a day or to the backlog by `assets/js/pilot-card-drag.js` (the `schedule` gesture). `week.html.twig` lists the cards waiting on an answer (`CardRepository::findWaiting()`, `Card::getWaitingOn()`); characters show through `_character.html.twig` and open, like any file, on `PilotController::doc()` (`doc.html.twig`, portrait served by `docImage()` from `Doc::IMAGE_DIRECTORY`); the files' markdown goes through the `pilot_markdown` filter (`Twig\PilotMarkdownExtension`, tables, HTML escaped); a checklist step's `cost` is shown against `Credit` balances on the card page and summed by month against `Credit::getMonthly()` (`Planning::spend()`); a step's `action` is launched by `CardFlow::tick()` once the step before is ticked; a project's objectives are its file of kind `DocKind::Objectives`, shown at the top of its page and created by `PilotController::objectives()` through `DocRepository::objectivesOf()` (guided project `pilot-objectives`), left out of `findFiles()`; files are chosen or created on a card (`DocRepository::findFiles()`, `newFile()`) and edited by `PilotController::docEdit()` (guided project `pilot-doc`); `PilotController::cardsOrder()` saves the order of the tiles dragged within a zone in `Card::getRank()`, `position` staying the plan's chain; `assets/js/pilot-fold.js` folds the `[data-pilot-fold]` sections. `_runs.html.twig` lists the active runs (`RunRepository::findActive()`, withdrawn by the `cancel` gesture, `CardFlow::cancel()`); `_reports.html.twig` shows the `Report` tables (`ReportRepository::findShownOn()`), filled by `ReportIngester` from a `<key>.report.json` run output and acted on by `PilotController::reportRun()`.

## The runner

```bash
php bin/console c975l:pilot:machine-add <name>
```

prints a machine's token once, only its sha256 being kept (`Machine::newToken()`, `MachineRepository::findOneByToken()`). The machine calls `RunnerController` under `/api/pilot/runner` with `Authorization: Bearer <token>`: `claim` (the actions it declares, recorded as `Action` rows by `ActionRepository::declare()`), `runs/{id}/progress`, `runs/{id}/output`, `runs/{id}/finish` and `credits`. `RunQueue` takes a run in one conditional `UPDATE` (`RunRepository::take()`), refuses news about a run the machine no longer holds (409) and puts back the runs whose lease ended. `RunOutputs` keeps the files under `private/pilot/runs/<run id>/`, each name reduced to a safe one, and `PilotController::output()` shows an image or a video under the type of its extension (`RunOutputs::mediaType()`) and downloads any other file, sandboxed.

## Importing the artifact

```bash
php bin/console c975l:pilot:import <export-folder>
```

The folder holds one sub-folder per collection of the artifact database (`settings`, `tasks`, `docs`, `characters`), one JSON file per document. `ArtifactReader` reads it into rows, `ArtifactImporter` writes them. A second import updates instead of duplicating: projects and files are matched on their slug, cards on `project:id`, each matched card getting back the artifact's title, note, date, checklist and done state.

## Do not

- Re-import once cards are dated or closed in the back office: the import puts the artifact's values back on every card it matches.
- Put a project colour in a `style` attribute: the back office's Content-Security-Policy refuses it, the colour goes through a `pilot-color-<slug>` class written in the nonced `<style>` of `_layout.html.twig`.
- Pass a machine token in a URL, or serve a run's file inline under a sniffed type: a machine can send an html or svg file, `PilotController::output()` downloads it.
