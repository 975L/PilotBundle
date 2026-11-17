# Changelog

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
