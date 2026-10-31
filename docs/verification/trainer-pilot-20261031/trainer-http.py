import datetime
import json
import urllib.request
from pathlib import Path

checks = []
for path, expected in [
    ('/login.php', 'booking/zapomenute_heslo.php?pro=trener'),
    ('/booking/zapomenute_heslo.php?pro=trener', 'Obnova trenérského hesla'),
    ('/booking/nove_heslo.php?pro=trener', 'Nové heslo'),
]:
    with urllib.request.urlopen('https://kis.kovopraha.cz' + path, timeout=30) as response:
        body = response.read().decode('utf-8')
        checks.append(dict(path=path, status=response.status, content_ok=expected in body,
                           no_store='no-store' in response.headers.get('Cache-Control', ''),
                           local_test_link_exposed='Otevřít testovací odkaz pro obnovu' in body))
report = dict(at=datetime.datetime.now(datetime.timezone.utc).isoformat(),
              method='anonymous GET only; no form submitted', checks=checks,
              ok=all(c['status'] == 200 and c['content_ok'] and c['no_store']
                     and not c['local_test_link_exposed'] for c in checks))
Path('docs/verification/trainer-pilot-20261031/trainer-http.json').write_text(
    json.dumps(report, indent=2) + '\n', encoding='utf-8')
print(json.dumps(report, indent=2))
raise SystemExit(0 if report['ok'] else 1)

