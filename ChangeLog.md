# Changelog

## v1.3.0

Runs cancelled and validated by MCP, merges kept under the ceiling

- `pilot_merge_cards` refuses two cards whose checklists would pass 300 lines together (23/11/2026)
- The README says a guest reads the file attached to a card given to it (23/11/2026)
- `pilot_cancel_run` withdraws a run queued or under way, as the card's cancel gesture (23/11/2026)
- `pilot_validate_run` accepts what a machine produced on a card under review, as the card's validate gesture (23/11/2026)
- `pilot_tick_step` ticks a comment too, by its index from `pilot_card` (23/11/2026)

## v1.2.2

Comment added by Enter sent once

- A comment added by Enter in a card's dialog sent once, no longer a second time as the card is read again (21/11/2026)

## v1.2.1

File opening links on by default, runner kept alive on a broken claim

- The address of a card an agent hands on opening it in its dialog over its space's page (`PilotUrls::shared()`) (21/11/2026)
- The config `pilot-open-scheme` set to `pilot-open` by default, the files under a step opened on the computer from the install, `none` leaving the paths as text (21/11/2026)
- The reference runner skipping a round whose claim answer is no JSON instead of stopping (21/11/2026)
- « Delete permanently » in English instead of « Delete for good » (21/11/2026)

## v1.2.0

Machine actions kept to their spaces, dashboard refreshed as runs end

- A machine action queued only on a space it is offered to, whoever names it: a step, an editing link, an agent or a report (21/11/2026)
- A card's markdown rewriting its reference only when no other card cites it, an editing link no longer changing a file of the whole space (21/11/2026)
- The machine's own data a run writes (a `.json`, a `.log`) no longer shown under its step (21/11/2026)
- The dashboard and a space's page refreshed once a run ends, the progress of the runs under way moving in place (21/11/2026)
- A `LINK <url> [words]` line of a runner's command leaving a link under its step once done, a draft post to approve for instance (`Run::getLinks()`) (21/11/2026) **Needs db update**
- « Fold all » folding only the step groups entirely ticked, those still to do left open (21/11/2026)
- Only the ticked steps a checklist starts with folded, those further down staying in place among the open ones (21/11/2026)
- Turbo Drive left out of the piloting pages again, a guided project reaching a card's own page (21/11/2026)
- A click on a guided project's panel no longer closes the + panel its next step points into (21/11/2026)
- Feedback screenshots renamed when a card's share or editing link is taken back, the addresses its former holder had answering nothing (21/11/2026)
- Shared pages sent with `Referrer-Policy: no-referrer` and a Content-Security-Policy loading only the site's images (21/11/2026)
- [BC-Break] Writes through an editing link limited to 300 per link and per hour (`c975l_pilot_share_write`), then answered 429, the page's own form shown again with the reason (21/11/2026)
- A card's checklist kept to 300 lines (`Card::MAX_CHECKLIST`), a step, a feedback or a markdown past it refused with the reason (21/11/2026)
- [BC-Break] An action worded only by the machine that declared it first, the next one taking over once it is deleted (`ActionRepository::declare()` takes the machine) (21/11/2026) **Needs db update**
- The same message whether an email added to a space's members has an account or not (21/11/2026)
- A run's files limited to 200 and 500 MB together (`RunOutputs::MAX_FILES`, `MAX_RUN_BYTES`), a file beyond answered 413 (21/11/2026)
- A report's action its space is not offered refused with the reason, no card made (21/11/2026)

## v1.1.1

Guided project handing a step to an AI agent

- `pilot-agent` guided project: a step handed to an AI agent, working on the card through the site's MCP endpoint (21/11/2026)
- MCP files tools opened to a contributor of the space at least, as their page, a guest no longer reading every file of its space (21/11/2026)

## v1.1.0

Demo automation space, php-http/discovery plugin allowed

- A demo automation space: a machine that never runs, a run already done with its report and files, an action left to launch (21/11/2026)
- `php-http/discovery` plugin allowed in `allow-plugins`, so `composer qa` no longer stops on its prompt (21/11/2026)
- The coming days of the planning laid out side by side instead of one under another (21/11/2026)

## v1.0.0

First stable release: own look, machine feedback, credits, MCP server

- [BC-Break] Unknown runner tokens limited to 20 per address and per hour (`c975l_pilot_runner_denied`), the address then refused with 429 (21/11/2026)
- The reference runner leaves aside a site in plain `http://` off this computer (21/11/2026)
- « Security of the runner API » in the README (21/11/2026)
- [BC-Break] A run debits only the credit its step's cost names, any other `spent` key ignored (21/11/2026)
- A run's end no longer reads a paid service's balance, left to the morning refresh and the Dashboard's button (21/11/2026)
- A credit's API key never shown back on its form, left empty to keep it (21/11/2026)
- A step added through the editing link to a card following a checklist template refused, the page saying why (21/11/2026)
- Merging a card with steps into a card following a checklist template refused (21/11/2026)
- A duplicated card leaves out who ticked its steps and the machine's reasons (21/11/2026)
- The open feedback handed to a machine in whole lines, those that do not fit waiting for the next pass, said beside the button (21/11/2026)
- No shared run given while an exclusive one waits, so that the machine empties for it (21/11/2026)
- A step's files listed without being read (21/11/2026)
- An icon without `viewBox` no longer breaks its markup (21/11/2026)
- Guided projects for plan templates, machine runs and credits, and a « Fold all » step (21/11/2026)
- [BC-Break] The piloting pages drawn by the bundle's own stylesheet (`sass/pilot.scss`, light or dark as the system is) and SVG icons (`pilot_icon()`), no longer by EasyAdmin, Bootstrap nor Font Awesome (21/11/2026)
- Runner buttons in blue, ticked boxes in grey, the steps to do in the strongest ink, files coloured by kind, « To redo » in yellow, bins in red (21/11/2026)
- A machine's settings form no longer fails on its title (21/11/2026)
- « Fold all » / « Unfold all » over a card's step groups when it has several (`assets/js/pilot-fold-all.js`) (21/11/2026)
- The card's dialog keeps its top where it opened, a card growing or shrinking moving its bottom only (21/11/2026)
- A card following a checklist template takes no step added by hand, from its page or the MCP (`Card::takesStepsByHand()`), its steps coming from the template; its step groups headed more clearly and its bins pale red until pointed at (21/11/2026)
- Guided projects in the 12000 block (`PilotGuidedProjectProvider`): creating a space, adding a card, giving it its steps and feedback, sharing it by a link, adding a runner machine, their narrations in `pilot_narration` (21/11/2026)
- The guided-project panel mounted on the piloting pages (`pilot_guided_project_mount()`, ConfigBundle's script loaded beside the bundle's) (21/11/2026)
- Logo, README and skill aligned with the other bundles before going public (21/11/2026)
- A demo dataset (`PilotDemoFixtureProvider`): two spaces, their collections and cards, in the site's locale (21/11/2026)
- Turbo Drive left out of the piloting pages, which failed to show a card's own page (21/11/2026)
- Requires c975l/core-bundle ^1.96 (21/11/2026)
- The runner's compiled Python no longer tracked by git (21/11/2026)
- A step no longer lists its run's markdown answer among its files, and a run recorded without a machine counts in no usual duration (21/11/2026)
- A step shows when and by whom it was made, its run's files and machine or its tick kept to the minute with the initials of who ticked it, and is marked to redo when an earlier step was redone after its files (21/11/2026)
- The paths under a step open the folder or the file on the computer through a `pilot-open://` link (`pilot-open-scheme`, `runner/pilot-open.py`) (20/11/2026)
- The paths of the files a step's run wrote shown under the step, named by `FILE` lines of its command (20/11/2026)
- A run's progress estimated from its action's usual time while its command reports less, written « ≈ » (20/11/2026)
- Several runs at once on a machine, as many as its « Runs in parallel » setting, an action marked `exclusive: true` running alone (20/11/2026)
- Piloting tools on the site's MCP endpoint (`PilotMcpProvider`, c975l/core-bundle ^1.96): an agent acting with its token's account lists, reads, adds, edits, sorts, merges, closes, deletes, ticks, runs and comments the cards of the spaces it sees, and reads and writes their files (20/11/2026)
- `Card::absorb()` merges another card into one, `CardRemoval` deletes a card from its page as from the MCP (20/11/2026)
- A card's address made absolute on demand by `PilotUrls::card()` (20/11/2026)
- The spaces' menu folded behind a burger on a phone (20/11/2026)
- A step's badges and buttons going down a line on a narrow screen instead of squeezing its text letter by letter (20/11/2026)
- A card's title in its dialog kept to a reasonable size (20/11/2026)
- A run's text wrapped to the dialog's width (20/11/2026)
- [BC-Break] Run journal removed: `Run::getLog()`, the `log` column, the `$log` argument of `Run::finish()` and `RunQueue::finish()`, the `log` field the runner sent and `label.log` (20/11/2026)
- A step's run no longer shows its output under the card, only its message on its step (20/11/2026)
- The claimed run carries the card's date as `due`, given to the runner's commands as `{due}` (20/11/2026)
- A `RESULT <url>` line of a runner's command sets the card's result address (20/11/2026)
- The space's badge on a card's tile leads to that space instead of opening the card (20/11/2026)
- A step or a feedback deleted by its bin, once confirmed (20/11/2026)
- The ticked steps of a card following a checklist template never folded (20/11/2026)
- Several ticked steps in a row shown open, folded only by a click on their heading (20/11/2026)
- [BC-Break] Send-back with a free comment replaced by the open feedback handed to the machine: the `sendback` gesture becomes `feedbackrun`, `CardFlow::sendBack()` becomes `CardFlow::treatFeedback()`, `label.comment`, `label.send_back`, `flow.comment_needed` and `flow.comment_dash` removed (20/11/2026)
- The feedback a run was handed ticked once done, a line the machine left open (`FEEDBACK <n> <reason>`) keeping its reason under it (20/11/2026)
- Credits read by the site from each service's API with the account's own key, kept encrypted, every morning (`c975l:pilot:credits-refresh`), from the Dashboard's button: ElevenLabs, D-ID and Expo builds by platform (20/11/2026)
- What a run spent (`SPENT <credit> <amount>` lines) taken off a credit typed in by hand in one atomic update (`CreditRepository::spend()`) (20/11/2026)
- The source address left off a card's tile, kept on its page (20/11/2026)
- The files a step's last run produced listed under that step, those left on the machine opened through `pilot-open://` (20/11/2026)
- [BC-Break] A step's planned day removed: `Card::setStepDue()`, the `stepdue` gesture, `@YYYY-MM-DD` after a step in the markdown and `label.step_due` (20/11/2026)
- [BC-Break] Spaces and collections out of the settings: a space made from the + of the column and set from the gear of its page, a collection in place; `CardSetType` removed (20/11/2026)
- [BC-Break] Balances no longer sent by a machine: `/api/pilot/runner/credits`, `CreditRepository::report()`, `Machine::servesAll()`, the runner's `credits` command and the `manual` column removed, a credit naming no service being typed in by hand (20/11/2026)
- The piloting opened from the back office's « Liens » in a tab of its own, no longer from a « Pilotage » section (20/11/2026)
- A settings row opening its edit form wherever it is clicked (20/11/2026)

## v0.6.0

Screenshots on the comments, ticked steps kept in place

- A screenshot on each feedback, pasted, dropped or sent, served by its random name and handed to an AI by the robot button (19/11/2026)
- Feedback called comments on screen (19/11/2026)
- [BC-Break] Feedback no longer marked still to decide: `Card::toggleAsk()`, the `stepask` gesture, the « ? » prefix and `label.feedback_ask`, `label.feedback_decided`, `label.feedback_to_decide` removed (19/11/2026)
- A ticked step left in its place, struck through, several ticked in a row folded together where they stand (19/11/2026)
- The field of a step or a feedback added by Enter kept focused for the next one (19/11/2026)
- A step or a feedback being added sent when focus leaves its field (19/11/2026)
- Fixed the robot button copying nothing, its method clashing with Stimulus's own `context`; shown as a plain icon like its neighbours (19/11/2026)
- A card downloaded as markdown or printed as a PDF from its dialog (19/11/2026)
- A reference deleted from its page, the cards citing it staying without one (19/11/2026)
- Headings of a reference's detail kept to the size of its text (19/11/2026)
- A run's file missing from the site said too heavy or erased, no longer only too heavy (19/11/2026)
- A feedback handed to an AI copied with the card's title and pitch only, not its other feedback (19/11/2026)
- The buttons and badges of a step or a feedback centred on the line of its box, a ticked step keeping the room of its handle (19/11/2026)
- The screenshot of a feedback opened full size in a dialog over the card, no longer in a new tab (19/11/2026)
- The screenshot of a feedback opened full size over the shared page as well, without a script (19/11/2026)

## v0.5.0

Piloting on its own pages at /pilot, shared by the members of each space

- [BC-Break] Piloting pages moved out of the back office to `/pilot`, on a shell of their own (19/11/2026)
- [BC-Break] Piloting routes renamed `pilot_*`, their controllers moved to `Controller\` and their templates to `pilot/` (19/11/2026)
- [BC-Break] The Planning renamed Dashboard, its route `pilot_dashboard` and its template `dashboard.html.twig` (19/11/2026)
- [BC-Break] Settings moved from EasyAdmin to the piloting pages, the CRUD controllers removed (19/11/2026)
- [BC-Break] `Management\ScriptProvider` and the guided projects removed, no piloting screen left in the back office (19/11/2026)
- [BC-Break] Objectives removed, a release being a dated card: `DocKind::Objectives`, `pilot_project_objectives`, `pilot_doc_task`, `Doc::toggleTask()`, `DocRepository::objectivesOf()` (19/11/2026)
- [BC-Break] A space's slug refused when it is one of the piloting pages' own words (19/11/2026)
- A « Dashboard » link in the back office's « Pilotage » section opening the piloting pages (19/11/2026)
- Addresses following the levels: `/pilot/{space}`, then `/pilot/{space}/{id}` for a card (19/11/2026)
- Projects called spaces on screen (19/11/2026)
- Collections in a space, each with its word for one card and its checklist template, laid on each card added to it, shown as tabs on the space's page, set up there or in the settings (19/11/2026)
- A source and a result link on each card, shown as links in its dialog, each edited by its pencil, and on its tile (19/11/2026)
- The + and gear panels closed by a click beside them or by Escape (19/11/2026)
- A planned day on each step (a release, a publication), set in place in the card's dialog, written `@YYYY-MM-DD` in its markdown and shown with the next step on its tile (19/11/2026)
- Days coloured by their nearness: purple once past, red within two weeks, orange within a month, green within two months (19/11/2026)
- A deletion asked in a dialog of its own, as the back office asks it (19/11/2026)
- The done cards folded in their collection's tab, on a darker grey, read when the fold opens (19/11/2026)
- [BC-Break] A card following a checklist template no longer reorders its steps, its template alone ordering them (`StepOrder::permute()` removed) (19/11/2026)
- A queued step no online machine serving the space declares said so beside it (`Machine::canRun()`) (19/11/2026)
- A tile naming its state only when it is not « to do » (19/11/2026)
- [BC-Break] A machine action only leaves by its button: neither a step ticked nor a step's run done queues the next one, `CardFlow::tick()` returning the card and `runFinished()` nothing (19/11/2026)
- A space's page with its open cards as tiles on a tint of its colour and the done ones folded on a grey background (19/11/2026)
- A card added from the + of its tab, in one line sent by Enter or several from a pasted list, a title already there left aside (19/11/2026)
- A card laid from a template following it when the template is saved (19/11/2026)
- Members of a space added by the email of their account, as guest, contributor, editor or owner (19/11/2026)
- Every piloting page and gesture checked against the account's role, every list kept to what it sees (19/11/2026)
- A card given to members of its space from its dialog, a guest seeing only those (19/11/2026)
- A machine serving the spaces chosen for it, every one when none is (19/11/2026)
- A card deleted for good with its runs and their files by one button, once the browser asked (19/11/2026)
- A template step whose title holds a `{variable}` recognised on the cards that filled it, its tick kept when the template is saved (19/11/2026)
- Feedback on a card (a fix, an idea), listed after its steps, ticked once dealt with, marked still to decide by a click or a « ? » first (19/11/2026)
- Feedback added from the card's dialog or through its editing link, written as `## Feedback` in its markdown (19/11/2026)
- A done card opened again once one of its boxes is to tick: a step unticked, a step or a feedback added (19/11/2026)
- Steps gathered in groups (Book, Film…) set on the template, each folding on the card, open from the start, written as `### Group` in its markdown (19/11/2026)
- A card without a date dragged onto another space of the column to move it there, a dated one keeping its place on a space's page (19/11/2026)
- A done card put back to do from its tile, the dialog naming the gesture (19/11/2026)
- Open feedback counted on the card's tile (19/11/2026)
- `PlanFactory::steps()` gives a checklist template's steps as a card's boxes (19/11/2026)
- A long step or feedback wraps beside its box, aligned on its first line, instead of dropping below it (19/11/2026)
- A robot button on each open step and feedback copies it with the card's markdown, for an AI session (19/11/2026)
- Ticked steps folded at the end of their group, closed by default and no longer dragged (19/11/2026)
- A card's editing link lent only by an account that may hand it to a machine (19/11/2026)
- A report's action launched on the page's space only when the report shows it there (19/11/2026)
- The piloting column saying what the piloting pages hold (19/11/2026)
- The reason a failed step run stopped written under the step (19/11/2026)
- A runner action renaming its card, by a `title` on `runs/{id}/finish` (a `TITLE <text>` line for the reference runner) (19/11/2026)
- The AI copy button last on a step's line, and gone from the steps of a card following a template (19/11/2026)
- The ticked steps of a card following a template kept in place, the others folded together under the step field (19/11/2026)
- A card dropped on the collections' + opening its name, the card going into the new collection (19/11/2026)
- [BC-Break] Characters become cards of a collection of sheets: `DocKind::Character`, `Doc::getImage()`, `Doc::IMAGE_DIRECTORY`, the route `pilot_doc_image`, `_character.html.twig` and the import of the artifact's character sheets removed (19/11/2026)
- Collections of sheets: their cards as tiles with a picture, without steps nor state (19/11/2026)
- A card's picture sent, replaced or removed, kept under `private/pilot/cards` (19/11/2026)
- Collection tabs ordered by dragging and renamed by a double click (19/11/2026)
- [BC-Break] Every card in a collection of its space, one saved without any going to the space's first, made when missing (`CardHomeListener`) (19/11/2026)
- A deleted collection handing its cards to another, from its tab or the settings, the last one holding cards kept (19/11/2026)
- The « waiting on » badge of a card shown only once set (19/11/2026)

## v0.4.0

Cards edited in place and shared by a reading or editing link

- A checklist step naming an action is launched by hand from its ▶ button (18/11/2026)
- The machine work under way shown first on the Planning and on each project (18/11/2026)
- A step's machine action launched from a named button right after its text, its run under way shown in its place (18/11/2026)
- A failed step run shown as a badge on that step instead of a block (18/11/2026)
- Validating the run of a checklist step ticks that step and goes on to the next, instead of closing the card (18/11/2026)
- Enum cases quoted by the shipped skill recognised by its test (18/11/2026)
- Planning without « Later » nor folding of its cards, the later months headed larger than the days (18/11/2026)
- Planning listing only the days holding a card, cards added from a project's page only (18/11/2026)
- Planned spending of each later month titled above its badges (18/11/2026)
- A card's date and its done tick, shown small like on the tiles, right under its badges (18/11/2026)
- A card's title, note, awaited answer and file edited in place by a click on them, the « Edit » form removed (18/11/2026)
- A card's file set back to « None » without an error (18/11/2026)
- A card shared outside the back office by a secret link, read-only or editing (18/11/2026)
- A share link copied by a click, kept until the cross beside stops it (18/11/2026)
- A project's page listing all its cards together, the dated ones first (18/11/2026)
- Files renamed references in the piloting pages (18/11/2026)
- Guided project « Share a card » (18/11/2026)
- A plan template can make a single card whose checklist is its steps (18/11/2026)
- The templates offered on a project linked from its page to their form (18/11/2026)
- A project's objectives ticked on its page with the tick of the cards, each box dated when reached (18/11/2026)
- A project renamed in place by a click on its name, its colour picked beside it (18/11/2026)
- `ActionOffer` gives the machine actions a project offers, for the back office and the shared card (18/11/2026)
- A project's references shown as tiles opening in the dialog, one used by a single open card left on that card (18/11/2026)
- A reference's page listing the cards pointing to it, the open ones first, then the done ones, and adding a card already pointing to it (18/11/2026)
- A card's description written in markdown and shown laid out, its steps renamed, removed and added in place, a new card opened at once (18/11/2026)
- A step's text and machine action edited by a click on it, like the card's other fields, its button named after the action alone (18/11/2026)
- [BC-Break] Runs hold their own action, parameters and step (18/11/2026)
- A card's machine steps queued one after the other, each one done ticking its step, a failure withdrawing the rest (18/11/2026)
- A checklist template saved lays its steps on its open cards, each keeping its ticks and its own steps (18/11/2026)
- Shared card page laid out, steps first, a long description and the markdown rewrite folded (18/11/2026)
- A step moved up from a card's editing link, and a markdown rewrite that only moves the steps, carried to the template like a drag (18/11/2026)
- A card's steps reordered by dragging their handle, the order carried to its checklist template and its other open cards (18/11/2026)
- A card linking the template its plan comes from (18/11/2026)
- A step's run under way no longer repeated in the machine block at the bottom of the card (18/11/2026)
- Reference runner shipped in `runner/` (`pilot-runner.py`, example `runner.yaml` and `actions.yaml`), serving several sites (18/11/2026)
- A run withdrawn on its own as `RunStatus::Cancelled` (18/11/2026)
- A run held by a machine stopped at its next heartbeat, the reference runner stopping the command and its children (18/11/2026)
- The card's launch button kept only when no step carries its action, a withdrawn run no longer shown as failed (18/11/2026)
- A card duplicated from its page, its steps unticked, the copy opened in its place (18/11/2026)
- A field saved by the click closing the card's dialog, the page reloaded once it is (18/11/2026)
- A card refreshed every few seconds while one of its runs waits or works (18/11/2026)
- A checklist step greyed out and its ▶ disabled until the steps it waits for are ticked, the previous one unless its template step names others, its box still ticked by hand (18/11/2026)
- A step's machine run done queues the next step's action, like a tick by hand (18/11/2026)
- A card's runs taken by the machines one at a time (18/11/2026)
- A step removed withdraws its run under way, the runs of the other steps following them (18/11/2026)
- Steps of the same text each kept with their tick when a template or a markdown rewrite lays them again (18/11/2026)
- A checklist template saved leaves the plans of a card per step alone (18/11/2026)
- A note's headings and field lines kept by a markdown rewrite (18/11/2026)
- Objectives boxes counted like the page draws them: numbered or quoted ones included, code blocks left out (18/11/2026)
- A card's title cut to the length of its column (18/11/2026)
- Steps' runs read once per card page (18/11/2026)
- Repositories sorting with `SortDirection` (18/11/2026)
- Guided project « Edit a card in place » (18/11/2026)

## v0.3.0

Characters, answers awaited and step costs in the piloting pages

- A card can wait on someone's answer, listed under « Waiting for an answer » on the Planning (17/11/2026)
- Characters shown as cards with their portrait, their sheet opening in the dialog with the portrait at full size to download (17/11/2026)
- A file's picture kept under `private/pilot/docs`, declared to the offsite backup (17/11/2026)
- A checklist step can spend a credit, shown against the balance left and in red when it is short (17/11/2026)
- The dialog opens files as well as cards, their address kept as `#doc-<slug>` (17/11/2026)
- Files' markdown rendered with tables and task lists, typed HTML escaped (17/11/2026)
- Actions declared by a machine as a card's own never show as buttons of the project's page (17/11/2026)
- Actions in the machine's order, with what they do shown on hover (17/11/2026)
- Sections of the Planning and of a project's page folded by a click on their heading, remembered in the browser (17/11/2026)
- A charter unfolded on its project's page (17/11/2026)
- A checklist step can name a machine action, the card handed to it as soon as the step before is ticked (17/11/2026)
- A card's file chosen among its project's, or a new one created, from its dialog; a file's title and two floors edited on its page (17/11/2026)
- Cards ordered by hand inside a backlog or a day, by dragging them (17/11/2026)
- A credit's monthly budget, compared on the Planning with what the planned steps spend each month (17/11/2026)
- Guided project « Write a file »: a file found, opened on its own page, then rewritten (17/11/2026)
- A project's objectives written at the top of its page, in a file of kind objectives created from there the first time and edited in the dialog (17/11/2026)
- Guided project « Write a project's objectives » (17/11/2026)

## v0.2.0

Projects listed in the piloting pages, runner API

- Projects listed in a column on the left of the piloting pages, out of the EasyAdmin sidebar (17/11/2026)
- Runner API under `/api/pilot/runner`: claim, progress, files and finish, each machine with its own token (17/11/2026)
- A card handed to a machine from its page, among the actions offered on its project, then validated or sent back with a comment (17/11/2026)
- Settings screens for projects, plan templates, actions, machines and credits, as sidebar entries (17/11/2026)
- Credits of the paid services on the Planning, reported by a machine or typed in by hand (17/11/2026)
- Plans created on a project's page from a template, their cards following each other (17/11/2026)
- Plan templates offered only on the projects chosen for them (17/11/2026)
- Recurring actions of a project as buttons on its page (17/11/2026)
- « To validate » card status (17/11/2026)
- Planning deadlines of the artifact imported as dated cards (17/11/2026)
- « This week » becomes « Planning »: late cards, the coming days, then the later deadlines by month (17/11/2026)
- Project colours shown under a Content-Security-Policy refusing style attributes, in the piloting pages and the list of projects (17/11/2026)
- « Planning » opens the piloting section of the sidebar, with `c975l/core-bundle` 1.93 (17/11/2026)
- Card notes keep their line breaks and their links clickable (17/11/2026)
- The project badge left out of the cards on the project's own page (17/11/2026)
- Cards shown as tiles side by side in the project's colour, lifted on hover, their date in red once late and amber within two weeks, with the count of steps done (17/11/2026)
- A card opened in a dialog over the page, its address kept as `#card-<id>` (17/11/2026)
- Projects of the left column ordered by drag and drop (17/11/2026)
- A card added in one line on a project's page or under a day of the Planning, every coming day listed (17/11/2026)
- Title, note and checklist of a card edited in its dialog, which stays open (17/11/2026)
- A card dragged to another day of the Planning or to its project's backlog (17/11/2026)
- Plan template steps edited one under the other, each titled by its title (17/11/2026)
- « Hand to a machine » folded on the card page, with what it does (17/11/2026)
- A file downloads as one markdown file (17/11/2026)
- A file's detail unfolded by a click on its title and summary (17/11/2026)
- « Under way » on the Planning and a project's page: the runs waiting or held by a machine, with their progress, one still waiting withdrawn (17/11/2026)
- Reports: a table a machine sends as a `<key>.report.json` file of a run, shown on its project's page (and on the Planning or on the projects matching its sites), each row offering the actions it names (17/11/2026)
- A gesture refused in a card's dialog says why, the dialog staying open (17/11/2026)
- The actions asking nothing offered as one-click buttons on a card (17/11/2026)
- A claimed run carries its card's note, project and file, and a `<slug>.doc.md` file it sends writes that file and attaches it to the card (17/11/2026)
- A card opened on its own page during a guided project, whose panel a modal dialog would block (17/11/2026)
- `c975l:pilot:machine-add`: registers a machine and prints its token once (17/11/2026)
- Files sent by the machines kept under `private/pilot/runs` and declared to the offsite backup (17/11/2026)
- Guided projects to start a plan from a template and to register a machine (17/11/2026)

## v0.1.0

Piloting screens in the back office, with the import of the Pilotage artifact

- Projects, cards (backlog without a date, planning with one), files on two floors, plans, runs and runner machines (17/11/2026)
- « This week », a page per project with its backlog, the search and the page of a card, in the sidebar with one entry per project (17/11/2026)
- `pilot-horizon-days`: days shown in « This week » (17/11/2026)
- Guided project « Schedule a card » (17/11/2026)
- `c975l:pilot:import`: imports an export of the Pilotage artifact, a second import updating the first (17/11/2026)
