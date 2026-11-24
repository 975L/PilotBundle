# (c) 2026: 975L <contact@975l.com>
# (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
#
# This source file is subject to the MIT license that is bundled
# with this source code in the file LICENSE.

# The pilot home of a machine, shared by pilot-runner.py and pilot-open.py: one folder holding runner.yaml, secrets.yaml, actions.yaml, recipes/ and work/, the same on Linux, macOS and Windows, meant to be backed up and versioned with git (secrets.yaml and work/ left out by the .gitignore it is given)

import os
from pathlib import Path

LEGACY = '~/.config/pilot-runner'
GITIGNORE = 'secrets.yaml\nwork/\n'


# The folder: $PILOT_HOME, else $PILOT_RUNNER_CONFIG or ~/.config/pilot-runner where an older install keeps its runner.yaml, else ~/Pilot
def pilot_home():
    if os.environ.get('PILOT_HOME'):
        return Path(os.path.expanduser(os.environ['PILOT_HOME']))
    if os.environ.get('PILOT_RUNNER_CONFIG'):
        return Path(os.path.expanduser(os.environ['PILOT_RUNNER_CONFIG']))
    legacy = Path(os.path.expanduser(LEGACY))
    if (legacy / 'runner.yaml').exists():
        return legacy
    return Path.home() / 'Pilot'


# Creates the folder and its recipes/ on first use, with a .gitignore keeping the tokens and the work folders out of git, the lines an existing one lacks added to it
def prepare(home):
    (home / 'recipes').mkdir(parents=True, exist_ok=True)
    ignore = home / '.gitignore'
    lines = ignore.read_text(encoding='utf-8').splitlines() if ignore.exists() else []
    missing = [line for line in GITIGNORE.splitlines() if line not in lines]
    if missing:
        ignore.write_text('\n'.join([*lines, *missing]) + '\n', encoding='utf-8')
