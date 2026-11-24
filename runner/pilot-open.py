#!/usr/bin/env python3
# Opening handler of c975l/pilot-bundle: a "pilot-open:///path" link clicked under a step opens that folder in the file manager or that file in its usual application, on the computer of whoever clicks it, only under one of the folders the "open:" list of runner.yaml names, in the pilot home (pilot_home.py: $PILOT_HOME, default ~/Pilot), never a program or a script; --install declares the handler to the desktop for the current user.

import os
import subprocess
import sys
from pathlib import Path
from urllib.parse import unquote, urlparse

import yaml

from pilot_home import pilot_home

CONFIG = pilot_home()
SCHEME = 'pilot-open'
DESKTOP = Path.home() / '.local/share/applications/pilot-open.desktop'
# Files that would run something rather than show it
REFUSED = {'.desktop', '.sh', '.bash', '.py', '.pl', '.rb', '.php', '.js', '.jar', '.exe', '.bat', '.cmd', '.com', '.msi', '.appimage', '.run', '.bin', '.deb', '.rpm'}


# The folders whose files may be opened, as runner.yaml lists them under "open:"
def roots():
    path = CONFIG / 'runner.yaml'
    config = (yaml.safe_load(path.read_text(encoding='utf-8')) or {}) if path.exists() else {}
    return [Path(os.path.expanduser(str(root))).resolve() for root in config.get('open') or []]


# The local path a link names, None for a link of another scheme
def path_of(link):
    parsed = urlparse(link)
    if SCHEME != parsed.scheme:
        return None
    return Path(unquote(parsed.netloc + parsed.path)).resolve()


# Why a path may not be opened, None when it may: outside the folders allowed, missing, or a program
def refusal(path, allowed):
    if not any(path == root or path.is_relative_to(root) for root in allowed):
        return f'{path} is outside the folders allowed in {CONFIG}/runner.yaml (open:)'
    if not path.exists():
        return f'{path} does not exist'
    if path.is_file() and (path.suffix.lower() in REFUSED or os.access(path, os.X_OK)):
        return f'{path} is a program, it is not opened'
    return None


# Hands the folder or the file to the desktop's own opener
def open_path(path):
    opener = {'darwin': ['open'], 'win32': ['explorer']}.get(sys.platform, ['xdg-open'])
    subprocess.Popen([*opener, str(path)], stdin=subprocess.DEVNULL, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True)


# Declares the handler of pilot-open:// links for the current user, the configuration folder kept for the clicks to come
def install():
    DESKTOP.parent.mkdir(parents=True, exist_ok=True)
    DESKTOP.write_text('\n'.join([
        '[Desktop Entry]',
        'Type=Application',
        'Name=Pilot open',
        f'Exec=env PILOT_HOME={CONFIG} python3 {Path(__file__).resolve()} %u',
        'NoDisplay=true',
        f'MimeType=x-scheme-handler/{SCHEME};',
    ]) + '\n', encoding='utf-8')
    subprocess.run(['xdg-mime', 'default', DESKTOP.name, f'x-scheme-handler/{SCHEME}'], check=True)
    subprocess.run(['update-desktop-database', str(DESKTOP.parent)], check=False)
    print(f'{SCHEME}:// links now open through {DESKTOP}; list the folders allowed under "open:" in {CONFIG}/runner.yaml')


def main():
    if len(sys.argv) != 2:
        sys.exit('Usage: pilot-open.py <pilot-open:///path> | --install')
    if '--install' == sys.argv[1]:
        install()
        return
    path = path_of(sys.argv[1])
    if path is None:
        sys.exit(f'Not a {SCHEME}:// link: {sys.argv[1]}')
    reason = refusal(path, roots())
    if reason:
        subprocess.run(['notify-send', 'Pilot', reason], check=False)
        sys.exit(reason)
    open_path(path)


if __name__ == '__main__':
    main()
