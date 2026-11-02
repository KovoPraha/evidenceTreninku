"""Verify a public production asset against the deployed Git blob; no cookies or HTML export."""
import hashlib
import json
import urllib.request
from pathlib import Path

directory = Path(__file__).parent
manifest = json.loads((directory / 'release-manifest.json').read_text(encoding='utf-8'))
expected = next(item['expected_sha256'] for item in manifest['runtime_files'] if item['file'] == 'assets/app-ui.css')
url = 'https://kis.kovopraha.cz/assets/app-ui.css?release=' + manifest['release']
request = urllib.request.Request(url, headers={'User-Agent': 'KIS-release272-readonly', 'Accept-Encoding': 'identity'})
with urllib.request.urlopen(request, timeout=45) as response:
    body = response.read()
    result = {'url': url, 'http': response.status, 'bytes': len(body), 'expected_sha256': expected,
              'actual_sha256': hashlib.sha256(body).hexdigest()}
result['match'] = result['actual_sha256'] == result['expected_sha256']
(directory / 'css-runtime.json').write_text(json.dumps(result, indent=2) + '\n', encoding='utf-8')
print(json.dumps(result, indent=2))
raise SystemExit(0 if result['http'] == 200 and result['match'] else 1)
