# PilotBundle

Symfony bundle that pilots projects as cards on a weekly planning, each card able to run an action on a runner machine and come back for validation.

## Why

The Pilotage artifact notes orders but cannot run them: a Claude session has to be open. PilotBundle keeps the projects, the backlog, the planning and the files in the application, and lets a runner machine carry out the actions it allows.

## Features

- **Projects** with their colour, sites and place.
- **Cards**: in the backlog without a date, on the planning with one, a step when they belong to a plan.
- **Files on two floors**: the summary a human reads in five lines, the detail kept for Claude.
- **Plans** built from templates, **runs** claimed by a runner machine under a lease.
- **Back office**: « This week » (late cards, then the coming days up to `pilot-horizon-days`), a page per project with its backlog, a search, and the page of a card to date it or close it. The sidebar lists every project.
- **Import** of the Pilotage artifact.

## Requirements

- PHP 8.4+, Symfony 8, Doctrine ORM 3
- `c975l/core-bundle`

## Installation

The bundle is private: the application declares its repository.

```json
"repositories": [{ "type": "vcs", "url": "git@github.com:975L/PilotBundle.git" }]
```

```bash
composer require c975l/pilot-bundle
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

The migration creates the tables `pilot_project`, `pilot_card`, `pilot_doc`, `pilot_plan`, `pilot_plan_template`, `pilot_run` and `pilot_machine`. Nothing else to register by hand.

## Importing the Pilotage artifact

```bash
php bin/console c975l:pilot:import <export-folder>
```

The folder holds one sub-folder per collection of the artifact database (`settings`, `tasks`, `docs`, `characters`), one JSON file per document. The project names come from `settings/projects.json`. Run again, the import updates what it wrote instead of duplicating it, overwriting the title, note, date, checklist and done state of every card it matches.

## What this bundle deliberately does not contribute yet

| Point | Reason |
| --- | --- |
| Runner API | It comes with the runner, proven by the POC |

## Configuration

| Key | Default | Use |
| --- | --- | --- |
| `pilot-horizon-days` | 7 | Days shown in « This week », after the late cards |

## AI agent skills

`skills/c975l-pilot/SKILL.md` documents the model and the import for the coding agents of the applications installing the bundle.

## License

MIT, see `LICENSE`.
