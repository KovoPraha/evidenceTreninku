"""Read-only anonymous checks of the two isolated synthetic servers."""
import hashlib
import html
import json
import re
import statistics
import time
import urllib.request
from pathlib import Path

ROOT = Path(__file__).parent
PORTS = {"before": 18087, "after": 18086}
PATHS = {
    "catalog": "/booking/eshop.php",
    "program_category": "/booking/eshop.php?kategorie=LOCALHOST%20%3E%20Programy",
    "search": "/booking/eshop.php?q=Program&razeni=name",
    "price_sort": "/booking/eshop.php?razeni=price",
    "second_page": "/booking/eshop.php?strana=2",
    "empty_search": "/booking/eshop.php?q=not-a-synthetic-product",
}
opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))

def normalize_asset(url):
    if re.fullmatch(r"/assets/[^?]+\?v=\d+", url):
        path = url.split("?")[0].lstrip("/")
        roots = [Path("C:/Users/Marek/Documents/Codex/evidencePavel-refactoring-wave4-20261028"), Path.cwd()]
        assert roots[0].joinpath(path).read_bytes() == roots[1].joinpath(path).read_bytes()
        return "/" + path
    return url


def read(port, path):
    start = time.monotonic()
    with opener.open(f"http://127.0.0.1:{port}{path}", timeout=90) as response:
        body = response.read().decode("utf-8")
        assert response.status == 200
    elapsed = (time.monotonic() - start) * 1000
    stripped = re.sub(r"<(script|style)\b[^>]*>.*?</\1>", "", body, flags=re.S | re.I)
    visible = re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]*>", "", stripped))).strip()
    links = [normalize_asset(url) for url in re.findall(r'\bhref="([^"]*)"', body)]
    images = [normalize_asset(url) for url in re.findall(r'\bsrc="([^"]*)"', stripped)]
    digest = hashlib.sha256(json.dumps([visible, links, images], ensure_ascii=False).encode()).hexdigest()
    return elapsed, digest, visible


checks = []
for name, path in PATHS.items():
    a = read(PORTS['before'], path)
    b = read(PORTS['after'], path)
    assert a[1] == b[1], f"Rendered text, links or images changed: {name}"
    if name == "program_category":
        assert "20 výsledků" in b[2] and "100 volných míst" in b[2]
    checks.append({"profile": name, "path": path, "before_ms": a[0], "after_ms": b[0],
                   "semantic_sha256": b[1], "equal": True})
ROOT.joinpath('http-checks.json').write_text(json.dumps({"synthetic_only": True,
    "checks": checks, "passed": len(checks)}, indent=2) + '\n', encoding='utf-8')
samples = {key: [] for key in PORTS}
hashes = {}
for i in range(20):
    for key in (['before', 'after'] if i % 2 == 0 else ['after', 'before']):
        elapsed, digest, _ = read(PORTS[key], PATHS['catalog'])
        hashes.setdefault(key, digest)
        assert digest == hashes[key]
        if i >= 5:
            samples[key].append(elapsed)
assert hashes['before'] == hashes['after']
report = {"synthetic_only": True, "warmup": 5, "measured": 15,
          "normalization": "Ignore only numeric /assets/ cache versions after checking identical asset bytes in both worktrees.",
          "order": "alternating before/after", "active_products": 71, "program_products": 20,
          "metric": "full HTTP body including local network; not browser rendering",
          "results": [{"version": key, "median_ms": statistics.median(values),
                       "samples_ms": values,
                       "semantic_sha256": hashes[key]} for key, values in samples.items()]}
ROOT.joinpath('http-benchmark.json').write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
print(json.dumps(report, indent=2))
