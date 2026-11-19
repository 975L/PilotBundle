# PilotBundle

Symfony bundle that pilots spaces of cards on pages of their own, shared between the members of each space, each card able to run an action on a runner machine and come back for validation.

## Why

The Pilotage artifact notes orders but cannot run them: a Claude session has to be open. PilotBundle keeps the projects, the backlog, the planning and the files in the application, and lets a runner machine carry out the actions it allows.

## Features

- **Spaces** (the `Project` entity, called « Espace » on screen) with their colour, sites and place. A space gathers its cards in **collections** (the stories, the replies), each with the word for one of its cards and the checklist template a card added to it gets, its `{variables}` filled with the card's title; its owner adds them from the + at the end of the tabs, sets them up and deletes them from the gear of their tab, the admin also in the settings. A space's page shows its open cards as tiles, a tab per collection, every card living in one (saved without any, it goes to the space's first collection, one being made for a space without any; a collection deleted hands its cards to another) (a tile dropped on another tab changes collection, dropped on the + it asks the name of a new one and goes there), the open ones on a tint of their space's colour and the done ones folded in their tab on a darker grey, read when the fold opens, each put back to do from its tile, a tile naming its state only when it is not « to do »; a step may carry the day it is planned for (a release, a publication), every day coloured by its nearness (purple once past, red within two weeks, orange within a month, green within two months); a deletion is asked in a dialog; a + or gear panel closes on a click beside it or on Escape; a card is added from the + of its tab, in one line (Enter sends it) or several from a pasted list (500 at most, a title already there left aside).
- **Cards**: in the backlog without a date, on the planning with one, a step when they belong to a plan.
- **Files on two floors**: the summary a human reads in five lines, the detail kept for Claude, its headings kept to the size of its text; each downloads as one markdown file and is deleted from its page by an editor of its space (the admin for a file of no space), the cards citing it staying without a reference.
- **Plans** built from templates, **runs** claimed by a runner machine under a lease.
- **Runner machines**: a card is handed to a machine with one of the actions it declares; the machine runs it, sends its files (up to 100 MB each, a heavier one only named), and the card waits to be validated or sent back with a comment. A machine takes only the cards of the spaces it serves, fills only their reports and writes only files of its card's space; only one serving every space reports the balances.
- **Addresses following the levels**: the dashboard at `/pilot`, a space at `/pilot/{space}`, a card at `/pilot/{space}/{id}` (`/pilot/card/{id}` and any outdated address redirecting there); a space's slug never takes one of the pages' own words (`search`, `share`, `card`, `doc`, `settings`…).
- **Piloting pages** at `/pilot`, outside the back office on a shell of their own (the back office's stylesheet, icons and light or dark scheme, the bundle's script, no sidebar), linked from the back office's « Pilotage » section: the dashboard, the spaces, the search, a card's page, and the settings.
- **Pages**: the dashboard (late cards, the coming days holding a card up to `pilot-horizon-days` day by day, then the later deadlines month by month), a page per space, renamed by a click on its name and coloured beside it by its owner, with its references (tiles opening in the dialog, a reference only one card uses left on that card), a search, and the page of a card to date it, close it or duplicate it (the copy, its steps unticked, opened in its place). Each of these pages lists the spaces in a column on its left, with their open cards, the admin ordering them by dragging; the cards show as tiles, open in a dialog over the page (`#card-<id>` in the address) where their title, markdown description, awaited answer and reference are edited in place by a click on them, their steps ticked, edited in place (text and machine action), added or reordered by dragging on a card of its own (a card following a checklist template takes its order from it alone: a template saved lays its steps on its open cards, each keeping its ticks and its own steps), their machine steps queued one after the other and run in turn (each one done ticking its step, a machine action only ever leaving by its ▶ button, never by a tick or a run done, a failure stopping the rest, the card read again every few seconds while one waits or works, a queued one no online machine can take saying so), each step greyed out and its ▶ disabled until the steps it waits for are ticked (its box still ticked by hand) (the previous one, or those its template step names in « After the steps »), shared outside the piloting pages by a secret link, read-only or letting whoever holds it rewrite the card in markdown, tick its steps and launch their machine actions (`/pilot/share/<token>`, `.md` to read it raw, POST to rewrite it, `/step`, `/stepup`, `/feedback`, `/runstep`, `/cancel` or `/entrust` to act; a click copies the link, the cross beside stops it), are added on a space's page, and are dragged with the mouse to another day or to the backlog. The script comes with its `importmap.php` entry, written by `php bin/console c975l:config:check-importmap`.
- **Members**: an owner adds an account of the site to a space by its email, with a role: a guest sees and ticks the cards given to it, a contributor works on every card, an editor also launches the machines, lends a card's editing link and deletes cards, an owner sets the space up and chooses its members. Every page and gesture checks it (a page the account does not see answers 404), every list shows only what it sees, and the site's admin sees and does everything. A card is given to members from its dialog.
- **Deleting a card** for good from its dialog, with its runs and the files they kept, by one button the browser asks to confirm (an editor or an owner).
- **Feedback**: a card takes feedback after its steps (a fix, an idea), added in one line from its dialog or through its editing link (`/feedback`), ticked once dealt with; it waits for no step, stays after the steps when they are reordered, shows counted on the tile while open, and is written as `## Feedback` in the card's markdown. Each feedback takes a screenshot (PNG, JPEG, WebP or GIF up to 10 MB), pasted or dropped on the field while it is written or sent by its picture button, shown small beside it and taken off by its cross; it is kept under `private/pilot/feedback` (declared to the offsite backup) under a 128-bit random name and served without signing in at `/pilot/share/image/<name>`, the name being the permission, so that the robot button can hand its address to an AI along with the feedback and the card's title and pitch (not its other feedback) (`  ![screenshot](<address>)` under the feedback). A copied card takes none, and a screenshot is deleted with its feedback or its card.
- **Ticked steps folded in place**: on a card, a ticked step stays in its place, struck through, and is no longer dragged; several ticked in a row fold together where they stand, closed by default, the order of the steps kept.
- **Sharing with the AI**: each open step and feedback of a card has a robot button copying it with the card's markdown, ready to paste into a Claude session to discuss it then carry it out.
- **Export**: whoever reads a card downloads it as one markdown file (`/pilot/card/<id>.md`, the text the robot button hands over) or prints it, as a PDF through the browser's print, the card alone without the spaces, the buttons nor the fields.
- **Source and result**: each card may carry a source link (a reply, a page) and a result link (the published page), edited in its dialog, shown on its tile and written as `- Source:` and `- Result:` in its markdown; only http(s) addresses are kept.
- **Step groups**: a template step may name a group (Book, Film…); the steps of one group follow each other and fold together on the card, open from the start, and its markdown writes them under `### Group`.
- **Moving a card**: a card without a date is dragged onto another space of the column to move it there (a card of a plan stays with it); on a space's page a dated card keeps its place.
- **Reopened when unticked**: a done card opens again once one of its boxes is to tick, a step unticked, a step or a feedback added, or a markdown rewrite bringing one.
- **Waiting for an answer**: a card can name who it waits on (a company, a person), written in its markdown (`- Waiting on:`), shown on the card and listed on the Dashboard until the field is emptied.
- **Sheets**: a collection set to show its cards as sheets (characters, places, apps) lays them as tiles with their picture and the first line of their pitch, without steps nor state; the picture is sent, replaced or removed on the card (PNG, JPEG, WebP or GIF up to 10 MB, kept under `private/pilot/cards`, declared to the offsite backup) and shown at full size to download as a reference for an image generator.
- **Tabs**: the space's owner drags the collection tabs into a new order and renames one by a double click on its tab.
- **Step costs**: a checklist step may carry `cost: {credit, amount, guess}`; the card shows it against that credit's balance, in red when it is short, and the Dashboard sums them by month against each credit's monthly budget.
- **Machine steps**: a checklist step may carry `action` and `params`, launched only by its ▶ button, once the steps it waits for are ticked; a run done ticks its step and launches nothing more; a card's runs are taken one at a time. Validating the run ticks that step, the card staying open for the next one.
- **Files**: chosen or created from a card's dialog, edited on their own page (title, the summary for a human, the detail for Claude).
- **Folding**: every section of a project's page, and the runs and reports of the Dashboard, folds by a click on its heading, remembered in the browser; the Dashboard's cards always show, its later months headed larger than its days.
- **Under way**: the runs waiting in the queue or held by a machine, on the Dashboard and on each project's page, with their progress; one still waiting can be withdrawn.
- **Reports**: a table a machine sends as a file `<key>.report.json` of a run (`{label, project, home, match, columns, rows, actions, globals}`): shown on its project's page, on the Dashboard when `home` is true, and on the projects whose sites hold the row's `match` column; each action names a machine action, its parameters as `{column}` templates, those asked before launching (`ask`) and the column that must be true for the row to offer it (`if`). A click creates a card dated today, handed to the machine.
- **Credits**: the balances of the paid services on top of the Dashboard, reported by a machine (`runs` API `credits`) or typed in there for a service without an API.
- **Settings** on the piloting pages, the admin's: spaces, plan templates (steps by hand or by a machine added and removed in place, `{variables}` asked when a plan is created, offered on the spaces chosen for each, one card per step or a single card whose steps are boxes to tick), machine actions (declared by the machines, offered on the spaces chosen for each), machines (the spaces each serves, every one when none is chosen; a new one gets its token once) and credits.
- **Plans** created on a project's page from a template: closing a card opens the next one, a machine card waiting for its button.
- **Recurring actions**: the actions offered on a project show as buttons on its page, each click a new card handed to the machine.
- **Import** of the Pilotage artifact.

## Requirements

- PHP 8.4+, Symfony 8, Doctrine ORM 3
- `c975l/core-bundle`

## Installation

The bundle is private: the application declares its repository.

```json
"repositories": [{ "type": "composer", "url": "https://bundles.975l.com/composer" }]
```

with the key of the account in Composer's auth (`composer config bearer.bundles.975l.com <key>`).

```bash
composer require c975l/pilot-bundle
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

The migration creates the tables `pilot_project`, `pilot_member`, `pilot_card_set`, `pilot_card`, `pilot_card_assignee`, `pilot_doc`, `pilot_plan`, `pilot_plan_template`, `pilot_plan_template_project`, `pilot_run`, `pilot_machine`, `pilot_machine_project`, `pilot_action`, `pilot_action_project`, `pilot_credit` and `pilot_report`. Then import the routes and open the runner API to the machines, which present their own token, and the shared cards to whoever holds their link:

```yaml
# config/routes.yaml
c975_l_pilot:
    resource: "@c975LPilotBundle/src/Controller/"
    type:     attribute
    prefix:   /

# config/packages/security.yaml, access_control, above the catch-all rule
- { path: ^/api/pilot/runner/, roles: PUBLIC_ACCESS }
- { path: ^/pilot/share/, roles: PUBLIC_ACCESS }
# The piloting pages, after the shared cards, for any signed-in account: each page then checks what its role in the space holds
- { path: ^/pilot, roles: IS_AUTHENTICATED_FULLY }
```

## Importing the Pilotage artifact

```bash
php bin/console c975l:pilot:import <export-folder>
```

The folder holds one sub-folder per collection of the artifact database (`settings`, `tasks`, `docs`), one JSON file per document, the character sheets left aside (make them cards of a collection of sheets). The project names come from `settings/projects.json`. Run again, the import updates what it wrote instead of duplicating it, overwriting the title, note, date, checklist and done state of every card it matches.

## What this bundle deliberately does not contribute yet

| Point | Reason |
| --- | --- |
| Recurring deadlines (periodic cards) | The recurring actions are launched by hand for now |

## Runner machines

A runner is any program, on a workstation or a server, that polls `/api/pilot/runner/claim` with its machine's token: any language able to send HTTP and read JSON will do, and the site never sends a command, only an action key and the values of its parameters. The link between an action and a runner is that key: at each call the machine declares the keys it can run, which fills the Actions screen; a card or a step names a key (picked in the step's form on the card), and the queue gives a run only to a machine that declared it. The reference runner ships in `runner/` (Python 3 with `requests` and `PyYAML`): copy the folder to the machine, write `runner.yaml` from `runner.yaml.dist` (the sites served, each with its token, and an optional command printing the balances) and `actions.yaml` from `actions.yaml.dist` in `~/.config/pilot-runner` (or `$PILOT_RUNNER_CONFIG`), then run `pilot-runner.py --once` to try it and `pilot-runner.py --install` for a systemd user service. It is generic: its `actions.yaml` maps each key to a command (bash, Python or anything else), so writing an action means writing a script and declaring it there:

```yaml
catalog-build:
  label: Build the catalog
  help: Builds the catalog and sends the report
  argv: [~/scripts/catalog-build.sh, '{item}']
  params: { item: 'item-\d+' }
  outputs: ['{workdir}/report.md']
```

```bash
php bin/console c975l:pilot:machine-add <name>
```

prints the machine's token once (only its hash is kept; running it again for the same name replaces the token); the Machines screen does the same. The runner (`runner/pilot-runner.py`) polls `POST /api/pilot/runner/claim` with `Authorization: Bearer <token>` and the actions it declares (`{"actions": {"key": {"label": "…", "params": ["…"], "card": false, "help": "…"}}}`, in the order their buttons show; `card: true` keeps an action off the project's page buttons), each recorded on the Actions screen, where the projects offering it are chosen, the claimed run carrying the card's title, note, project slug and file (`doc` slug and its `docMarkdown`); it then reports on `runs/{id}/progress`, sends each file on `runs/{id}/output` (a `<key>.report.json` refreshes a report, a `<slug>.doc.md` writes that file and attaches it to the card) and closes with `runs/{id}/finish` (a `title` there renames the card, sent by the reference runner when the command prints a `TITLE <text>` line); `POST /api/pilot/runner/credits` with `{"credits": {"key": {"label", "remaining", "total", "unit", "renewsOn"}}}` updates the balances it reads, never one typed in by hand. A run whose machine goes silent past its 60 s lease is put back in the queue. A run stopped from the card is refused at its next heartbeat (409), and the runner stops the command with everything it started. The files are kept under `private/pilot/runs/`, declared to the offsite backup.

## Configuration

| Key | Default | Use |
| --- | --- | --- |
| `pilot-horizon-days` | 7 | Days the Dashboard shows one by one, before grouping by month |

## AI agent skills

`skills/c975l-pilot/SKILL.md` documents the model and the import for the coding agents of the applications installing the bundle.

## License

MIT, see `LICENSE`.
