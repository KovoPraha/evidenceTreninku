"""Read tracked source metadata only; no application bootstrap or database access."""
import collections
import hashlib
import json
import re
import subprocess
from pathlib import Path

root = Path(__file__).resolve().parents[3]
paths = subprocess.check_output(['git', 'ls-files', '-z'], cwd=root).decode().split('\0')
paths = [p for p in paths if p]
sha = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=root).decode().strip()
runtime = []
documents = []
mail = []
for name in paths:
    path = root / name
    if path.suffix not in ('.php', '.md'):
        continue
    data = path.read_bytes()
    text = data.decode('utf-8-sig', errors='replace')
    if path.suffix == '.md':
        documents.append({'path': name, 'bytes': len(data), 'sha256': hashlib.sha256(data).hexdigest(),
                          'headings': [l for l in text.splitlines() if re.match(r'^#{1,3} ', l)]})
    if path.suffix == '.php' and not name.startswith(('tests/', 'docs/', 'outputs/', 'output/', 'vendor/', 'migrations/', 'tools/')):
        runtime.append({'path': name, 'lines': len(text.splitlines())})
        mail.extend({'path': name, 'line': i} for i, line in enumerate(text.splitlines(), 1)
                    if re.search(r'\bmail\s*\(', line))
baseline = (root / 'phpstan-baseline.neon').read_text(encoding='utf-8')
entries = re.findall(r'identifier:\s*(\S+)\s+count:\s*(\d+)\s+path:\s*([^\r\n]+)', baseline)
by_file = collections.Counter()
for identifier, count, path in entries:
    by_file[path] += int(count)
counts = {'tracked_files': len(paths), 'runtime_php_files': len(runtime),
          'runtime_php_lines': sum(x['lines'] for x in runtime),
          'markdown_files': len(documents),
          'unit_test_files': sum(p.startswith('tests/Unit/') and p.endswith('Test.php') for p in paths),
          'integration_test_files': sum(p.startswith('tests/Integration/') and p.endswith('Test.php') for p in paths),
          'mariadb_support_files': sum(p.startswith('tests/Support/') and 'MariaDb' in p and p.endswith('.php') for p in paths),
          'http_support_files': sum(p.startswith('tests/Support/') and 'HttpAcceptance' in p and p.endswith('.php') for p in paths),
          'versioned_php_migrations': sum(bool(re.match(r'migrations/\d+.*\.php$', p)) for p in paths)}
report = {'source_sha': sha, 'date': '2026-10-29', 'method': 'Tracked source metadata; runtime excludes tests/docs/outputs/vendor/migrations/tools. Counts are not performance, coverage or severity measurements.',
          'counts': counts, 'largest_runtime_php': sorted(runtime, key=lambda x: (-x['lines'], x['path']))[:25],
          'phpstan_baseline': {'entries': len(entries), 'occurrences': sum(int(x[1]) for x in entries),
                              'top_files': [{'path': p, 'occurrences': n} for p, n in by_file.most_common(15)]},
          'mail_call_locations': mail}
folder = Path(__file__).parent
(folder / 'source-inventory.json').write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
(folder / 'documentation-inventory.json').write_text(json.dumps({'source_sha': sha, 'method': 'Read and hash all tracked Markdown; headings inventory is not manual review of every line.', 'documents': documents}, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
print(json.dumps(report, ensure_ascii=False, indent=2))
