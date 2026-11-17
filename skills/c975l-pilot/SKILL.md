---
name: c975l-pilot
description: "Use this skill when working with project piloting in a Symfony application built on the c975L ecosystem with c975l/pilot-bundle. Covers projects, cards (backlog without a date, planning with one, steps of a plan), files written on two floors (a short summary for a human, the detail for Claude), plans built from templates, runs executed by a runner machine under a lease, and the import of the Pilotage artifact. Triggers on: pilot, this week, horizon, pilot-horizon-days, PilotController, Planning, MenuProvider, sidebar, PilotGuidedProjectProvider, piloting, backlog, planning, card, Card, Project, Doc, summary, detail, Plan, PlanTemplate, Run, Machine, runner, lease, CardStatus, RunStatus, Executor, DocKind, ArtifactReader, ArtifactImporter, c975l:pilot:import, import the artifact, Pilotage artifact."
---

# c975L PilotBundle

> Projects as cards on a weekly planning, each card able to run an action on a runner machine and come back for validation.

**Package:** `c975l/pilot-bundle` (private) · **Namespace:** `c975L\PilotBundle\` · **Tables:** `pilot_*`

**Key source paths** (relative to the package root):
`src/Entity/`, `src/Enum/`, `src/Repository/`, `src/Service/`, `src/Command/`, `src/Controller/Management/`, `src/Management/`, `templates/management/`, `config/services.yaml`, `config/configs.json`

**Related skills:** `c975l-config` (the `pilot-horizon-days` key), `c975l-management` (sidebar, onboarding tour, guided projects)

**Related documentation:** this package's `README.md` is the reference. The ecosystem's rules live in `c975l/core-bundle`.

## The model

- A `Project` holds cards and files. Its `slug` is the key the import matches on.
- A `Card` without a date is in the backlog (`isInBacklog()`), with a date it is on the planning. In a `Plan` it is a step at a position, done by a human or a runner (`Executor`), as a task or a validation (`CardKind`).
- A `Doc` has two floors: `getSummary()` (five lines a human reads) and `getDetail()` (folded, for Claude). `write()` sets both at once.
- A `Run` is one execution of a runner card. A `Machine` claims it under a lease of `Run::LEASE_SECONDS`, reports progress, then finishes it.

## The screens

`PilotController` serves four back-office pages: this week (`Planning` splits the open dated cards into late ones and the coming days, up to `pilot-horizon-days`), a project (dated cards, backlog, files, done cards folded), the search, and a card. A card shows on three lines (`_card.html.twig`), a file shows its summary with the detail folded (`_doc.html.twig`). `MenuProvider` adds one sidebar link per project.

## Importing the artifact

```bash
php bin/console c975l:pilot:import <export-folder>
```

The folder holds one sub-folder per collection of the artifact database (`settings`, `tasks`, `docs`, `characters`), one JSON file per document. `ArtifactReader` reads it into rows, `ArtifactImporter` writes them. A second import updates instead of duplicating: projects and files are matched on their slug, cards on `project:id`, each matched card getting back the artifact's title, note, date, checklist and done state.

## Do not

- Re-import once cards are dated or closed in the back office: the import puts the artifact's values back on every card it matches.
- Add a `<style>` block to a template: the project colour goes inline, from `Project::getColor()`.
