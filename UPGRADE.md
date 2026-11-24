# UPGRADE

## v1.7.0

Recipes followed by the runner's agent, pilot home folder.

- Without a `workdir` in `runner.yaml`, the reference runner now keeps the card folders under `work/` of its pilot home instead of `~/.local/share/pilot-runner`: move `~/.local/share/pilot-runner/*` to `<pilot home>/work/`, or write `workdir: ~/.local/share/pilot-runner` in `runner.yaml`, so that a run reading earlier work and the `pilot-open://` links of earlier runs still find it.
- The sites' tokens go to `secrets.yaml` (`tokens: {url: token}`), kept out of git by the pilot home's `.gitignore`; a token still in `runner.yaml` is read, and pointed out at start: move it before versioning the pilot home.
- A recipe with `browser: true` now runs alone on its machine: write `exclusive: false` in its front matter to let it share the machine.

## v1.6.0

Share links removed, runs named after who asked.

- The read-only and editing links of a card are gone: `ShareController`, its routes `pilot_share*`, the `share`, `unshare`, `lend` and `unlend` gestures and the `c975l_pilot_share_write` limiter are removed. An agent reads a card by its number through `pilot_card`. Run `doctrine:migrations:diff` then `migrate`, which drops `pilot_card.share_token`, `pilot_card.edit_token` and `pilot_card.note_shared`, and adds `pilot_run.requester` (nullable). Keep the `^/pilot/share/` rule of `access_control`: the feedback's screenshots are still served under `/pilot/share/image/`.
- `Card::share()`, `unshare()`, `getShareToken()`, `lend()`, `unlend()`, `getEditToken()`, `isNoteShared()` and `shareNote()`, the second argument of `Card::addFeedback()`, `CardMarkdown::read()`, `CardMarkdown::framed()`, `CardMarkdown::SHARED_TAG`, the second argument of `CardMarkdown::write()`, `CardImages::renewFeedback()`, `DocRepository::isCitedByAnotherCard()` and `PilotController::SHARE_HEADER` are removed; `CardMarkdown`'s constructor no longer takes a `DocRepository`.
- A custom runner reads who asked for a run in the `requester` of the claim answer, empty when nobody signed in did.

## v1.4.0

What the editing link writes kept apart for agents.

- A card records whether its note was written through its editing link: run `doctrine:migrations:diff` then `migrate`, which adds `pilot_card.note_shared` (boolean, `false` by default). `Card::addFeedback()` takes a second argument, `true` for a feedback coming from outside the account.

## v1.2.1

File opening links on by default.

- `pilot-open-scheme` now declares `pilot-open`: an empty row takes it at the next config load, so the paths under a step become `pilot-open://` links; set it to `none` to keep them as text.

## v1.2.0

Shared links and machines held to what they were given.

- An action now records the machine that declared it first, the only one whose later calls change its label, help, parameters and rank: run `doctrine:migrations:diff` then `migrate`, which adds `pilot_action.machine_id` (a foreign key to `pilot_machine`, `ON DELETE SET NULL`). The existing actions have none, so the first machine to claim after the update becomes their declarer. `ActionRepository::declare()` takes the `Machine` first.
- The writes through an editing link are counted per link: past 300 in an hour, the link gets 429 until the hour ends; redefine `c975l_pilot_share_write` under `framework.rate_limiter` to change it.
- The shared pages send their own `Content-Security-Policy` (`ShareController::POLICY`: the site's images, their inline style), which NelmioSecurityBundle then leaves as it is: an image of a note from another site no longer shows there.
- A checklist holds 300 lines at most (`Card::MAX_CHECKLIST`): `Card::addStep()` and `addFeedback()` throw a `FlowException` beyond, so a caller outside the bundle catches it.
- `runs/{id}/output` answers 413 once the run keeps 200 files or 500 MB (`RunOutputs::MAX_FILES`, `MAX_RUN_BYTES`); a custom runner treats it as a file left on the machine.
- A run keeps the links its command left: run `doctrine:migrations:diff` then `migrate`, which adds `pilot_run.links` (JSON, `[]` by default). A custom runner may forward `LINK <url> [words]` lines as `links: [{"url": "…", "label": "…"}]` in `runs/{id}/finish`, `RunQueue::finish()`'s last argument.
- The `label.member_unknown` key is replaced by `label.member_maybe_added`, shown whether the email has an account or not.

## v1.0.0

A card's output goes back to the machine through its open feedback instead of a free comment.

- The runner API counts the unknown tokens by address: past 20 in an hour, the address gets 429, a right token included, until the hour ends; redefine `c975l_pilot_runner_denied` under `framework.rate_limiter` to change it. The reference runner refuses a site in plain `http://` off `127.0.0.1`, `localhost` or `::1`: serve the site over https. A file left on the machine opens through `pilot-open://` only (`pilot-open-scheme`), no longer as a `vscode://file` link.

- The piloting pages carry their own stylesheet and icons: run `assets:install` so that `public/bundles/c975lpilot/` holds them. A template overridden from the bundle's must use the `pilot-*` classes and `pilot_icon()` instead of Bootstrap's classes and Font Awesome's `<i>`; the layout no longer loads EasyAdmin's `app.css` nor its colour scheme script, the pages following the system's light or dark setting.

- The balances are read by the site itself, no longer sent by a machine: `POST /api/pilot/runner/credits`, `CreditRepository::report()` and `Machine::servesAll()` are removed, and so is the `credits` command of the runner's `runner.yaml` (drop it, with the script it named). Run `doctrine:migrations:diff` then `migrate`: it adds `pilot_credit.provider`, `account` and `api_key` and drops `manual`. Then, in the credits' settings, give each credit the service it is read from and its account's API key (an access token of expo.dev and the account's name for Expo); the site reads them every morning and from the dashboard's button, `C975L_VAULT_KEY` encrypting the keys, the key never shown back on the form. A runner's command may print `SPENT <credit> <amount>` lines, forwarded as `spent: {"key": amount}` in `runs/{id}/finish` (`RunQueue::finish()` takes it last): the amount comes off a credit typed in by hand, only for the credit the cost of the run's step names, any other key ignored.
- The spaces and the collections leave the settings: a space is made from the + of the column and set from the gear of its page, a collection in place; `CardSetType` and the `collection` kind are removed, `/pilot/settings` opens on the plan templates.

- A machine takes several runs at once: run `doctrine:migrations:diff` then `migrate`, which adds `pilot_machine.parallel` (1 by default) `pilot_run.started_at` and `pilot_run.files`, then set it on the Machines screen. A custom runner reads the `X-Pilot-Parallel` header of the claim answers and may send `only` (the action keys it can start now); `RunQueue::claim()` takes it last. Mark `exclusive: true` in `actions.yaml` an action that must run alone.
- The `sendback` gesture is now `feedbackrun` and takes no field: it hands the card's open feedback, numbered, as the `comment` parameter; `CardFlow::sendBack()` is replaced by `CardFlow::treatFeedback()`.
- An action receiving that comment does only what its lines ask; a line it leaves open is reported by a `FEEDBACK <n> <reason>` line of the command, sent by the runner as `feedback: {"n": "reason"}` in `runs/{id}/finish` (`RunQueue::finish()` takes it after the title). A custom runner should forward these lines, and may forward a `RESULT <url>` line as `result`, `RunQueue::finish()`'s last argument.

## v0.5.0

The piloting leaves the back office for `/pilot`, its pages and its settings on a shell of their own, shared by the members of each space.

- Routes renamed from `management_pilot_*` to `pilot_*`, their paths from `/management/pilot…` to `/pilot…` following the levels: the dashboard at `/pilot` (`pilot_dashboard`, the former Planning), a space at `/pilot/{space}`, a card at `/pilot/{space}/{id}` (`/pilot/card/{id}` redirecting there), the gestures on a space under `/pilot/space/{slug}/…`, the settings under `/pilot/settings/{kind}`.
- `PilotController` moves from `c975L\PilotBundle\Controller\Management` to `c975L\PilotBundle\Controller`, the templates from `@c975LPilot/management/` to `@c975LPilot/pilot/`.
- The EasyAdmin CRUD controllers (`ProjectCrudController`, `PlanTemplateCrudController`, `ActionCrudController`, `MachineCrudController`, `CreditCrudController`), `Management\ScriptProvider`, `Management\PilotGuidedProjectProvider` and the `pilot_narration` catalogue are removed: the settings live on `SettingsController`, the back office only links to `/pilot`.
- A space's slug can no longer be one of the words the piloting pages take (`Project::SLUG`: `search`, `share`, `card`, `cards`, `projects`, `credit`, `report`, `doc`, `run`, `space`, `settings`): rename such a project before updating.
- `access_control`: replace the `^/pilot` rule, after `^/pilot/share/`, by `- { path: ^/pilot, roles: IS_AUTHENTICATED_FULLY }`; each page then checks the account's role in the space, the site's admin seeing everything. A site whose catch-all asks for the admin role keeps the piloting to the admin until this rule is added.
- The objectives are removed: `DocKind::Objectives`, the route `pilot_project_objectives`, the ticking of a file's task list (`pilot_doc_task`, `Doc::toggleTask()`, the `pilot-tasks` controller) and `DocRepository::objectivesOf()`. Before updating, carry their dates onto the cards (a release is a dated card) then delete them: `DELETE FROM pilot_doc WHERE kind = 'objectives'`, a row left behind no longer loading.
- Run `doctrine:migrations:diff` then `migrate`: the tables `pilot_member`, `pilot_card_assignee` and `pilot_machine_project`, and the table `pilot_card_set` (collections) and the columns `pilot_card.template_id`, `pilot_card.card_set_id`, `pilot_card.source_url` and `pilot_card.result_url` are new.
- Characters are no longer files: `DocKind::Character`, `Doc::getImage()`, `Doc::IMAGE_DIRECTORY` and the route `pilot_doc_image` are removed. Run `doctrine:migrations:diff`: it adds `pilot_card.image` and `pilot_card_set.sheets` and drops `pilot_doc.image`; before `migrate`, add to the migration, ahead of that drop, the move of each character into a card of a collection of sheets of its space (its image column copied into `pilot_card.image`, the rows of kind `character` then deleted), and move the portraits from `private/pilot/docs` to `private/pilot/cards`.
- Every card now lives in a collection of its space (`CardHomeListener`): add to the migration the move of the cards without one into a new collection of their space, e.g. `INSERT INTO pilot_card_set (project_id, name, position, sheets) SELECT project_id, 'Cards', 99, 0 FROM pilot_card WHERE card_set_id IS NULL GROUP BY project_id` then `UPDATE pilot_card c SET card_set_id = (SELECT MAX(id) FROM pilot_card_set s WHERE s.project_id = c.project_id AND s.name = 'Cards') WHERE card_set_id IS NULL`.
- A space no longer has a word for a card nor a template (`Project::getItem()`, `getTemplate()`, `getChecklistTemplate()` removed): both belong to its collections (`CardSet`), and `CardFiller::card()` and `fill()` take the collection.
