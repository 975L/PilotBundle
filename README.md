# PilotBundle

Symfony bundle that pilots projects as cards on a weekly planning, each card able to run an action on a runner machine and come back for validation.

## Why

The Pilotage artifact notes orders but cannot run them: a Claude session has to be open. PilotBundle keeps the projects, the backlog, the planning and the files in the application, and lets a runner machine carry out the actions it allows.

## Features

- **Projects** with their colour, sites and place.
- **Cards**: in the backlog without a date, on the planning with one, a step when they belong to a plan.
- **Files on two floors**: the summary a human reads in five lines, the detail kept for Claude; each downloads as one markdown file.
- **Plans** built from templates, **runs** claimed by a runner machine under a lease.
- **Runner machines**: a card is handed to a machine with one of the actions it declares; the machine runs it, sends its files (up to 100 MB each, a heavier one only named), and the card waits to be validated or sent back with a comment.
- **Back office**: « Planning » (late cards, the coming days holding a card up to `pilot-horizon-days` day by day, then the later deadlines month by month), a page per project, renamed by a click on its name and coloured beside it, with its objectives ticked in place, all its cards (the dated ones first) and its references (tiles opening in the dialog, a reference only one card uses left on that card), a search, and the page of a card to date it, close it or duplicate it (the copy, its steps unticked, opened in its place). Each of these pages lists the projects in a column on its left, with their open cards, ordered by dragging them; the cards show as tiles, open in a dialog over the page (`#card-<id>` in the address) where their title, markdown description, awaited answer and reference are edited in place by a click on them, their steps ticked, edited in place (text and machine action), added or reordered by dragging (the checklist template leading: a card's new order goes to it and its other open cards, and a template saved lays its steps on its open cards, each keeping its ticks and its own steps), their machine steps queued one after the other and run in turn (each one done ticking its step, a failure stopping the rest, the card read again every few seconds while one waits or works), each step greyed out and its ▶ disabled until the steps it waits for are ticked (its box still ticked by hand) (the previous one, or those its template step names in « After the steps »), shared outside the back office by a secret link, read-only or letting whoever holds it rewrite the card in markdown, tick its steps and launch their machine actions (`/pilot/share/<token>`, `.md` to read it raw, POST to rewrite it, `/step`, `/stepup`, `/runstep`, `/cancel` or `/entrust` to act; a click copies the link, the cross beside stops it), are added in one line on a project's page, and are dragged with the mouse to another day or to the backlog. The script comes with its `importmap.php` entry, written by `php bin/console c975l:config:check-importmap`.
- **Waiting for an answer**: a card can name who it waits on (a company, a person), listed on the Planning until the field is emptied.
- **Objectives**: each project's page opens on its objectives, a file of kind objectives created by « Write the objectives » the first time and edited in the dialog; it is left out of the project's files and of those a card can be attached to.
- **Characters**: the files of kind character show as cards with their portrait (kept under `private/pilot/docs`, declared to the offsite backup), their sheet opening in the dialog with the portrait at full size to download.
- **Step costs**: a checklist step may carry `cost: {credit, amount, guess}`; the card shows it against that credit's balance, in red when it is short, and the Planning sums them by month against each credit's monthly budget.
- **Chained steps**: a checklist step may carry `action` and `params`; ticking the step before, by hand or by its machine run done, hands the card to that machine action at once, and its ▶ button launches it by hand; a card's runs are taken one at a time. Validating the run ticks that step, the card staying open for the next one.
- **Files**: chosen or created from a card's dialog, edited on their own page (title, the summary for a human, the detail for Claude).
- **Folding**: every section of a project's page, and the runs and reports of the Planning, folds by a click on its heading, remembered in the browser; the Planning's cards always show, its later months headed larger than its days.
- **Under way**: the runs waiting in the queue or held by a machine, on the Planning and on each project's page, with their progress; one still waiting can be withdrawn.
- **Reports**: a table a machine sends as a file `<key>.report.json` of a run (`{label, project, home, match, columns, rows, actions, globals}`): shown on its project's page, on the Planning when `home` is true, and on the projects whose sites hold the row's `match` column; each action names a machine action, its parameters as `{column}` templates, those asked before launching (`ask`) and the column that must be true for the row to offer it (`if`). A click creates a card dated today, handed to the machine.
- **Credits**: the balances of the paid services on top of the Planning, reported by a machine (`runs` API `credits`) or typed in there for a service without an API.
- **Settings**, as entries of the sidebar: projects, plan templates (steps by hand or by a machine, `{variables}` asked when a plan is created, offered on the projects chosen for each and linked from their page, one card per step or a single card whose steps are boxes to tick), actions (declared by the machines, offered on the projects chosen for each) and machines (a new one gets its token once) and credits.
- **Plans** created on a project's page from a template: closing a card opens the next one, a machine card leaving at once.
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

The migration creates the tables `pilot_project`, `pilot_card`, `pilot_doc`, `pilot_plan`, `pilot_plan_template`, `pilot_plan_template_project`, `pilot_run`, `pilot_machine`, `pilot_action`, `pilot_action_project`, `pilot_credit` and `pilot_report`. Then import the routes and open the runner API to the machines, which present their own token, and the shared cards to whoever holds their link:

```yaml
# config/routes.yaml
c975_l_pilot:
    resource: "@c975LPilotBundle/src/Controller/"
    type:     attribute
    prefix:   /

# config/packages/security.yaml, access_control, above the catch-all rule
- { path: ^/api/pilot/runner/, roles: PUBLIC_ACCESS }
- { path: ^/pilot/share/, roles: PUBLIC_ACCESS }
```

## Importing the Pilotage artifact

```bash
php bin/console c975l:pilot:import <export-folder>
```

The folder holds one sub-folder per collection of the artifact database (`settings`, `tasks`, `docs`, `characters`), one JSON file per document. The project names come from `settings/projects.json`. Run again, the import updates what it wrote instead of duplicating it, overwriting the title, note, date, checklist and done state of every card it matches.

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

prints the machine's token once (only its hash is kept; running it again for the same name replaces the token); the Machines screen does the same. The runner (`runner/pilot-runner.py`) polls `POST /api/pilot/runner/claim` with `Authorization: Bearer <token>` and the actions it declares (`{"actions": {"key": {"label": "…", "params": ["…"], "card": false, "help": "…"}}}`, in the order their buttons show; `card: true` keeps an action off the project's page buttons), each recorded on the Actions screen, where the projects offering it are chosen, the claimed run carrying the card's title, note, project slug and file (`doc` slug and its `docMarkdown`); it then reports on `runs/{id}/progress`, sends each file on `runs/{id}/output` (a `<key>.report.json` refreshes a report, a `<slug>.doc.md` writes that file and attaches it to the card) and closes with `runs/{id}/finish`; `POST /api/pilot/runner/credits` with `{"credits": {"key": {"label", "remaining", "total", "unit", "renewsOn"}}}` updates the balances it reads, never one typed in by hand. A run whose machine goes silent past its 60 s lease is put back in the queue. A run stopped from the card is refused at its next heartbeat (409), and the runner stops the command with everything it started. The files are kept under `private/pilot/runs/`, declared to the offsite backup.

## Configuration

| Key | Default | Use |
| --- | --- | --- |
| `pilot-horizon-days` | 7 | Days the Planning shows one by one, before grouping by month |

## AI agent skills

`skills/c975l-pilot/SKILL.md` documents the model and the import for the coding agents of the applications installing the bundle.

## License

MIT, see `LICENSE`.
