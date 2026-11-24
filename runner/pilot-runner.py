#!/usr/bin/env python3
# (c) 2026: 975L <contact@975l.com>
# (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
#
# This source file is subject to the MIT license that is bundled
# with this source code in the file LICENSE.

# Reference runner of c975l/pilot-bundle, claiming the runs queued for this machine and running them from the allowlist of $PILOT_RUNNER_CONFIG (default ~/.config/pilot-runner): polling loop, --once, --install (systemd user service) or --demo-hello <file> <text> [comment]; the site sends only an action key and values checked against their pattern, never a command, a "PROGRESS n message" line the command prints shows on the card a "TITLE text" line renames it, a "RESULT url" line sets its result address, a "FEEDBACK n reason" line leaves the feedback numbered n open a "SPENT credit amount" line tells what the run spent on that credit and a "FILE /path" line names a file it wrote, shown on its step, and a "LINK url [words]" line leaves a link under its step

import glob
import os
import re
import shutil
import signal
import subprocess
import sys
import threading
import time
import urllib.parse
from collections import deque
from pathlib import Path

import requests
import yaml

CONFIG = Path(os.path.expanduser(os.environ.get('PILOT_RUNNER_CONFIG', '~/.config/pilot-runner')))
UNIT = Path.home() / '.config/systemd/user/pilot-runner.service'
INTERVAL = 10
HEARTBEAT = 20
PLACEHOLDER = re.compile(r'\{(\w+)\}')
PROGRESS = re.compile(r'^PROGRESS (\d{1,3})(?: (.*))?$')
TITLE = re.compile(r'^TITLE (.+)$')
RESULT = re.compile(r'^RESULT (https?://\S+)$')
FEEDBACK = re.compile(r'^FEEDBACK (\d+) (.+)$')
SPENT = re.compile(r'^SPENT ([a-z0-9-]{1,50}) (\d{1,9})$')
FILE = re.compile(r'^FILE (/.+)$')
LINK = re.compile(r'^LINK (https?://\S+)(?: (.+))?$')

# Sites already reported unreachable, so a site switched off does not fill the log every few seconds
UNREACHABLE = set()


# One YAML file of the configuration, empty when missing
def load(name):
    path = CONFIG / name
    return (yaml.safe_load(path.read_text(encoding='utf-8')) or {}) if path.exists() else {}


# Call to the site's runner API with the machine's token, None when the site does not answer (reported once until it is back)
def api(site, path, payload=None):
    try:
        response = requests.post(site['url'].rstrip('/') + '/api/pilot/runner' + path, json=payload or {},
                                 headers={'Authorization': 'Bearer ' + site['token']}, timeout=15)
    except requests.RequestException as error:
        if site['url'] not in UNREACHABLE:
            print(f'Site unreachable ({site["url"]}): {error}', flush=True)
            UNREACHABLE.add(site['url'])
        return None
    if site['url'] in UNREACHABLE:
        print(f'Site reachable again: {site["url"]}', flush=True)
        UNREACHABLE.discard(site['url'])
    return response


# Sites served at each round, each with its own work folder since two sites may number their cards alike; a site over plain http elsewhere than on this computer left aside, its token would cross the network readable
def sites(config):
    served = []
    for index, site in enumerate(config.get('sites') or []):
        if not site.get('url') or not site.get('token'):
            continue
        if not secure(site['url']):
            print(f'Site left aside, https required off this computer: {site["url"]}', flush=True)
            continue
        workdir = site.get('workdir') or os.path.join(config.get('workdir', '~/.local/share/pilot-runner'), f'site-{index}')
        served.append({'url': site['url'], 'token': site['token'], 'workdir': os.path.expanduser(workdir)})
    return served


# Whether a site's address carries the token safely: https, or http to this computer only
def secure(url):
    parsed = urllib.parse.urlsplit(url)
    return parsed.scheme == 'https' or (parsed.scheme == 'http' and parsed.hostname in ('127.0.0.1', 'localhost', '::1'))


# Replaces the {name} of an argument by the known values, a missing value becoming empty
def fill(arg, values):
    return PLACEHOLDER.sub(lambda m: values.get(m.group(1), ''), arg)


# Parameters of a run checked against those actions.yaml declares, with an error message otherwise
def check_params(action, params):
    declared = action.get('params', {})
    for name, value in params.items():
        if name not in declared:
            return None, f'Undeclared parameter: {name}'
        if not isinstance(value, str) or value.startswith('-') or '\0' in value or len(value) > 2000:
            return None, f'Refused parameter: {name}'
        if not re.fullmatch(declared[name], value, re.DOTALL):
            return None, f'Parameter out of its pattern: {name}'
    return dict(params), None


# On Linux the command gets SIGTERM if the runner dies, so an action never goes on with nobody following it
def die_with_parent():
    if sys.platform.startswith('linux'):
        import ctypes
        ctypes.CDLL('libc.so.6').prctl(1, signal.SIGTERM)


# Regular sign of life renewing the lease; the site refusing it (lease lost) stops the command
def heartbeat(site, run_id, process, stop):
    while not stop.wait(HEARTBEAT):
        response = api(site, f'/runs/{run_id}/progress')
        if response is not None and response.status_code == 409:
            print(f'Run {run_id} taken back by the site, command stopped', flush=True)
            stop_group(process)
            return


# Stops the command and everything it started (its process group), so a run stopped from the site leaves nothing running
def stop_group(process):
    if not hasattr(os, 'killpg'):
        process.terminate()
        return
    try:
        os.killpg(process.pid, signal.SIGTERM)
    except ProcessLookupError:
        pass


# Runs a claimed run and returns (status, message, outputs, said), said holding the title a "TITLE text" line renamed the card to, the address a "RESULT url" line gave its result, the feedback a "FEEDBACK n reason" line left open and what "SPENT credit amount" lines spent
def execute(site, actions, run):
    action = actions.get(run['action'])
    if action is None:
        return 'blocked', f'Unknown action on this machine: {run["action"]}', [], {}
    params, error = check_params(action, run.get('params') or {})
    if error:
        return 'blocked', error, [], {}
    workdir = Path(site['workdir']) / f'card-{run["card"]}'
    workdir.mkdir(parents=True, exist_ok=True)

    # The card's context as {title}, {note}, {due}, {project}, {doc}, a parameter of the same name winning
    context = {key: str(run.get(key) or '') for key in ('title', 'note', 'due', 'project', 'doc', 'requester')}
    values = {**context, **params, 'workdir': str(workdir)}
    argv = [os.path.expanduser(fill(arg, values)) for arg in action['argv']]
    outputs = [('?' if o.startswith('?') else '') + os.path.expanduser(fill(o.removeprefix('?'), values)) for o in action.get('outputs', [])]

    # The card's file laid in the work folder, for the action to read it and send it back if it changes it
    if run.get('doc') and run.get('docMarkdown'):
        (workdir / f'{run["doc"]}.doc.md').write_text(run['docMarkdown'], encoding='utf-8')
    start = time.time()
    process = subprocess.Popen(argv, cwd=workdir, env={**os.environ, 'PILOT_URL': site['url']}, start_new_session=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                               text=True, bufsize=1, preexec_fn=die_with_parent if os.name == 'posix' else None)
    stop = threading.Event()
    threading.Thread(target=heartbeat, args=(site, run['id'], process, stop), daemon=True).start()
    log = deque(maxlen=200)
    said = {'title': None, 'result': None, 'feedback': {}, 'spent': {}, 'files': [], 'links': []}
    for line in process.stdout:
        line = line.rstrip('\n')
        log.append(line)
        heard(line, said)
        match = PROGRESS.match(line)
        if match:
            api(site, f'/runs/{run["id"]}/progress', {'progress': int(match.group(1)), 'message': match.group(2) or ''})
    code = process.wait()
    stop.set()
    if code != 0:
        # The last line the command printed is its reason, shown on the card
        reason = next((line for line in reversed(log) if line.strip() and not any(pattern.match(line) for pattern in (PROGRESS, TITLE, RESULT, FEEDBACK, SPENT, FILE, LINK))), f'Exit code {code}')
        return 'failed', reason[:255], [], said
    found, missing = collect(outputs, workdir, run['id'], start)
    if missing:
        return 'failed', 'Missing output: ' + ', '.join(missing), [], said
    upload(site, run, found)
    return 'done', f'Done in {round(time.time() - start)} s', found, said


# What a line of the command tells the site beyond its log: the card's new title, a feedback it leaves open with the reason, an amount spent on a credit, a file it wrote (FILE <absolute path>, only named on its step), a link left under its step (LINK <url> [words])
def heard(line, said):
    renamed = TITLE.match(line)
    if renamed:
        said['title'] = renamed.group(1).strip()
    result = RESULT.match(line.strip())
    if result:
        said['result'] = result.group(1)
    left = FEEDBACK.match(line)
    if left:
        said['feedback'][left.group(1)] = left.group(2).strip()
    spent = SPENT.match(line.strip())
    if spent:
        said['spent'][spent.group(1)] = said['spent'].get(spent.group(1), 0) + int(spent.group(2))
    written = FILE.match(line.strip())
    if written and written.group(1) not in said['files']:
        said['files'].append(written.group(1))
    linked = LINK.match(line.strip())
    if linked:
        said['links'].append({'url': linked.group(1), 'label': (linked.group(2) or '').strip()})


# Sends the site each output under the size it accepts, to show it on the card; a heavier one stays on the machine, the card showing its path
def upload(site, run, paths):
    for path in paths:
        if os.path.getsize(path) > run.get('maxBytes', 0):
            continue
        try:
            with open(path, 'rb') as handle:
                requests.post(site['url'].rstrip('/') + f'/api/pilot/runner/runs/{run["id"]}/output', files={'file': (os.path.basename(path), handle)},
                              headers={'Authorization': 'Bearer ' + site['token']}, timeout=300)
        except requests.RequestException as error:
            print(f'Could not send {path}: {error}', flush=True)


# Actions declared to the site in the file's order: label, parameters typed on the card (the comment coming only from the card's open feedback), card: true for one working on the card itself, help shown on hover; the site chooses on which projects each one is offered
def declared(actions):
    return {key: {'label': action.get('label', key), 'params': [name for name in action.get('params', {}) if name != 'comment'],
                  'card': bool(action.get('card')), 'help': action.get('help')}
            for key, action in actions.items()}


# Fresh outputs of the run (glob accepted, the newest kept, a leading "?" for an optional one); one outside the card's folder is copied in, so the card keeps what the run produced even if a later run rewrites the original
def collect(patterns, workdir, run_id, start):
    found, missing = [], []
    for pattern in patterns:
        optional = pattern.startswith('?')
        pattern = pattern.removeprefix('?')
        fresh = [m for m in glob.glob(pattern) if os.path.isfile(m) and os.path.getmtime(m) >= start - 1]
        if not fresh:
            if not optional:
                missing.append(pattern)
            continue
        source = Path(max(fresh, key=os.path.getmtime)).resolve()
        if source.is_relative_to(workdir.resolve()):
            found.append(str(source))
            continue
        copy = workdir / f'{run_id}-{source.name}'
        shutil.copyfile(source, copy)
        found.append(str(copy))
    return found, missing


# Runs under way on this machine, whichever site gave them: each with its thread and whether its action runs alone
RUNNING = []


# Polling loop: as many runs at once as the site's setting for this machine allows (X-Pilot-Parallel, one until told), an action marked "exclusive: true" in actions.yaml (a browser, a heavy export) only started on an idle machine and holding it alone; once runs a single round to its end
def loop(once=False):
    config, actions = load('runner.yaml'), load('actions.yaml')
    served = sites(config)
    if not served:
        sys.exit(f'No site (url and token) under sites: in {CONFIG}/runner.yaml')
    print(f'Runner ready: {len(actions)} action(s) ({", ".join(actions)}), site(s) {", ".join(s["url"] for s in served)}', flush=True)
    while True:
        busy = False
        for site in served:
            busy = claim(site, actions) or busy
        if once:
            for thread, _ in list(RUNNING):
                thread.join()
            return
        if not busy:
            time.sleep(INTERVAL)


# One run claimed on a site when the machine has room for it, then run in its own thread and reported to that same site; False when nothing was taken
def claim(site, actions):
    RUNNING[:] = [(thread, alone) for thread, alone in RUNNING if thread.is_alive()]
    if any(alone for _, alone in RUNNING) or len(RUNNING) >= site.get('parallel', 1):
        return False
    shared = [key for key, action in actions.items() if not action.get('exclusive')]
    response = api(site, '/claim', {'actions': declared(actions), **({'only': shared} if RUNNING else {})})
    if response is None:
        return False
    site['parallel'] = max(1, int(response.headers.get('X-Pilot-Parallel', '1') or 1))
    if response.status_code == 204:
        return False
    if response.status_code != 200:
        print(f'Claim refused ({site["url"]}): HTTP {response.status_code}', flush=True)
        return False
    # An answer that is no JSON (a site error, a login page) skips the round instead of stopping the runner
    try:
        run = response.json()
    except ValueError:
        print(f'Claim unreadable ({site["url"]}): the answer is no JSON', flush=True)
        return False
    print(f'Run {run["id"]} ({site["url"]}): {run["action"]} ({run["title"]})', flush=True)
    thread = threading.Thread(target=carry_out, args=(site, actions, run), daemon=True)
    RUNNING.append((thread, bool((actions.get(run['action']) or {}).get('exclusive'))))
    thread.start()
    return True


# A claimed run executed then reported, in its own thread
def carry_out(site, actions, run):
    status, message, outputs, said = execute(site, actions, run)
    print(f'Run {run["id"]}: {status}, {message}', flush=True)
    finish(site, run['id'], {'status': status, 'message': message, 'outputs': outputs, **said})


# End of a run reported until the site answers, within the lease: unacknowledged, the lease would end and the run be done again
def finish(site, run_id, payload):
    deadline = time.time() + 40
    while True:
        response = api(site, f'/runs/{run_id}/finish', payload)
        if response is not None and response.status_code < 500:
            return
        if time.time() > deadline:
            print(f'Run {run_id}: end not reported, the site will queue it again', flush=True)
            return
        time.sleep(5)


# Demo action: writes the text (and the open feedback handed as its comment) in about fifteen seconds, with its progress
def demo_hello(path, text, comment=''):
    for step in range(1, 11):
        time.sleep(1.5)
        print(f'PROGRESS {step * 10} Step {step}/10', flush=True)
    Path(path).write_text(text + ('\nFixed: ' + comment if comment else '') + '\n', encoding='utf-8')


# Systemd user service restarted on failure
def install():
    UNIT.parent.mkdir(parents=True, exist_ok=True)
    UNIT.write_text(f'''[Unit]
Description=Runner of c975l/pilot-bundle
After=network-online.target

[Service]
ExecStart={sys.executable} {Path(__file__).resolve()}
Restart=on-failure
RestartSec=30
Environment=PYTHONUNBUFFERED=1
Environment=PILOT_RUNNER_CONFIG={CONFIG}

[Install]
WantedBy=default.target
''', encoding='utf-8')
    subprocess.run(['systemctl', '--user', 'daemon-reload'], check=True)
    subprocess.run(['systemctl', '--user', 'enable', '--now', 'pilot-runner'], check=True)
    print(f'Service written to {UNIT} and started: journalctl --user -u pilot-runner -f')


if __name__ == '__main__':
    args = sys.argv[1:]
    if args == ['--install']:
        install()
    elif args[:1] == ['--demo-hello'] and len(args) in (3, 4):
        demo_hello(*args[1:])
    elif args in ([], ['--once']):
        loop(once=bool(args))
    else:
        sys.exit('Usage: pilot-runner.py [--once | --install | --demo-hello <file> <text> [comment]]')
