"""Build and apply a guarded, file-only WordPress release. Python 3 stdlib."""
import base64
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import subprocess
import sys
import tempfile
import time

ROOT = Path('/www/wwwroot/daututuonglai.vn')
PREFIXES = ('wp-content/themes/daututuonglai/', 'wp-content/plugins/aim-trading/',
            'wp-content/plugins/dttl-ssi-data/', 'wp-content/plugins/dttl-ai-assistant/')


def digest(data):
    return hashlib.sha256(data).hexdigest()


def allowed(name):
    return (name.startswith(PREFIXES) and not any(p.startswith('.') or p == '..'
            for p in PurePosixPath(name).parts) and
            PurePosixPath(name).suffix.lower() in {'.php', '.js', '.css', '.html', '.json', '.svg', '.png', '.jpg', '.webp', '.woff', '.woff2'})


def git(*args):
    return subprocess.check_output(['git', *args])


def build(before, after, output):
    assert re.fullmatch('[0-9a-f]{40}', before) and re.fullmatch('[0-9a-f]{40}', after)
    entries = []
    names = git('diff', '--name-only', '-z', '--no-renames', before, after, '--', 'wp-content').split(b'\0')
    for raw in filter(None, names):
        name = raw.decode()
        if not allowed(name):
            raise RuntimeError('Path outside deployment allowlist: ' + name)
        new_info = git('ls-tree', after, '--', name).decode()
        if not new_info.startswith('100644 blob '):
            raise RuntimeError('Deletion, executable or symlink requires separate review: ' + name)
        old_info = git('ls-tree', before, '--', name).decode()
        if old_info and not old_info.startswith('100644 blob '):
            raise RuntimeError('Unsupported old file mode: ' + name)
        old = git('show', before + ':' + name) if old_info else None
        data = git('show', after + ':' + name)
        with tempfile.TemporaryDirectory() as tmp:
            candidate = Path(tmp) / Path(name).name
            candidate.write_bytes(data)
            if name.endswith('.php'):
                subprocess.run(['php', '-l', str(candidate)], check=True)
            if name.endswith('.js'):
                subprocess.run(['node', '--check', str(candidate)], check=True)
        entries.append({'path': name, 'before': digest(old) if old is not None else None,
                        'after': digest(data), 'data': base64.b64encode(data).decode()})
    Path(output).write_text(json.dumps({'commit': after, 'files': entries}))
    print('Prepared files:', len(entries))


def check(root):
    if not (root / 'wp-load.php').is_file():
        raise RuntimeError('WordPress root not found')
    if not os.access(root / 'wp-content', os.W_OK):
        raise RuntimeError('Deployment user cannot write wp-content')
    if not Path('/www/server/php/83/bin/php').is_file():
        raise RuntimeError('Expected aaPanel PHP 8.3 executable not found')
    print('SSH, WordPress path and PHP 8.3: OK')


def apply(bundle, root=ROOT, backup_root=Path('/www/backup/dttl-deploy')):
    import fcntl
    check(root)
    backup_root.mkdir(parents=True, exist_ok=True, mode=0o700)
    with (backup_root / 'deploy.lock').open('w') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        payload = json.loads(Path(bundle).read_text())
        assert re.fullmatch('[0-9a-f]{40}', payload['commit'])
        plan = []
        seen = set()
        for entry in payload['files']:
            name = entry['path']
            if not allowed(name) or name in seen:
                raise RuntimeError('Invalid or duplicate path: ' + name)
            seen.add(name)
            dest = root / name
            if dest.resolve() != root.resolve() / name:
                raise RuntimeError('Symlink destination rejected: ' + name)
            data = base64.b64decode(entry['data'], validate=True)
            if digest(data) != entry['after']:
                raise RuntimeError('Payload checksum mismatch')
            current = dest.read_bytes() if dest.exists() else None
            actual = digest(current) if current is not None else None
            if actual == entry['after']:
                continue
            if actual != entry['before']:
                raise RuntimeError('Live differs from GitHub; synchronize first: ' + name)
            plan.append((dest, data, current))
        if not plan:
            print('No files need deployment')
            return
        backup = Path(tempfile.mkdtemp(prefix=time.strftime('%Y%m%d-%H%M%S-'), dir=backup_root))
        (backup / 'release.json').write_text(json.dumps({'commit': payload['commit'],
             'files': [{'path': str(p.relative_to(root)), 'existed': old is not None} for p, _, old in plan]}))
        for dest, data, old in plan:
            if old is not None:
                saved = backup / dest.relative_to(root)
                saved.parent.mkdir(parents=True, exist_ok=True)
                shutil.copy2(dest, saved)
            if dest.suffix == '.php':
                with tempfile.NamedTemporaryFile(suffix='.php') as candidate:
                    candidate.write(data)
                    candidate.flush()
                    subprocess.run(['/www/server/php/83/bin/php', '-l', candidate.name], check=True)
        written = []
        try:
            for dest, data, old in plan:
                # Recheck immediately before replacement to catch intervening edits.
                if (dest.read_bytes() if dest.exists() else None) != old:
                    raise RuntimeError('Concurrent live change: ' + str(dest))
                dest.parent.mkdir(parents=True, exist_ok=True)
                stat = dest.stat() if dest.exists() else (root / 'wp-content').stat()
                fd, tmp = tempfile.mkstemp(prefix='.dttl-', dir=dest.parent)
                try:
                    with os.fdopen(fd, 'wb') as out:
                        out.write(data)
                        out.flush()
                        os.fsync(out.fileno())
                    os.chmod(tmp, (stat.st_mode & 0o777) if old is not None else 0o644)
                    if os.geteuid() == 0:
                        os.chown(tmp, stat.st_uid, stat.st_gid)
                    os.replace(tmp, dest)
                finally:
                    if os.path.exists(tmp):
                        os.unlink(tmp)
                written.append((dest, old))
        except Exception:
            for dest, old in reversed(written):
                if old is None:
                    dest.unlink()
                else:
                    shutil.copy2(backup / dest.relative_to(root), dest)
            raise
        print('Deployed files:', len(plan), 'Backup:', backup)


if __name__ == '__main__':
    if sys.argv[1] == 'build':
        build(*sys.argv[2:])
    elif sys.argv[1] == 'check':
        check(ROOT)
    elif sys.argv[1] == 'apply':
        apply(sys.argv[2])
    else:
        raise SystemExit('Unknown command')
