"""Read-only anonymous production smoke; no cookies, POSTs or saved HTML."""
import datetime
import html
import json
import re
import time
import sys
import urllib.error
import urllib.request
from pathlib import Path

BASE = "https://kis.kovopraha.cz"


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


opener = urllib.request.build_opener(NoRedirect())
results = []
phase = sys.argv[1] if len(sys.argv) == 2 else "post"
if phase not in ("pre", "post"):
    raise SystemExit("Expected pre or post")


def check(path, expected, content=None):
    start = time.monotonic()
    req = urllib.request.Request(BASE + path, headers={"User-Agent": "KIS-release-readonly-smoke/20261028"})
    try:
        response = opener.open(req, timeout=45)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        body = response.read().decode("utf-8", errors="replace")
        status = response.code
        item = {"path": path, "expected": expected, "status": status,
                "elapsed_ms": round((time.monotonic() - start) * 1000),
                "ok": status in expected}
        if content:
            item["content_present"] = content in body
            item["ok"] = item["ok"] and item["content_present"]
        if status in (302, 303):
            item["location"] = response.headers.get("Location", "")
            item["ok"] = item["ok"] and any(x in item["location"] for x in ("login", "prihlaseni"))
        results.append(item)
    return body


check("/index.php", [200])
catalog = check("/booking/eshop.php", [200], "produkt.php?id=")
check("/booking/prihlaseni.php", [200])
check("/booking/moje_objednavky.php", [302, 303])
check("/eshop_orders_admin.php", [302, 303])
for path in ("/booking/sportovni_prehled.php", "/booking/moje_osoby.php",
             "/booking/nahrady.php", "/booking/soupiskove_treninky.php"):
    check(path, [302, 303])
ids = list(dict.fromkeys(re.findall(r"produkt\.php\?id=(\d+)", html.unescape(catalog))))[:3]
if phase == "post":
    prior = json.loads(Path(__file__).with_name("pre-http.json").read_text(encoding="utf-8"))
    ids = prior["product_ids"]
for product_id in ids:
    check("/booking/produkt.php?id=" + product_id, [200])
check("/booking/produkt.php?id=2147483647", [404])
for path in ("/includes/shop_storefront_read.php", "/var/deployment.json",
             "/tests/Support/StorefrontReadMariaDbSmoke.php", "/composer.lock",
             "/var/private/kis-person-import.php"):
    check(path, [403, 404])
# Three sequential observations per route; indicative latency, not a load test.
for _ in range(2):
    check("/booking/eshop.php", [200], "produkt.php?id=")
    for product_id in ids:
        check("/booking/produkt.php?id=" + product_id, [200])
report = {"phase": phase, "product_ids": ids, "checked_at": datetime.datetime.now(datetime.timezone.utc).isoformat(),
          "base_url": BASE, "method": "GET only; anonymous; no cookies or redirects",
          "checks": len(results), "passed": sum(x["ok"] for x in results), "results": results}
Path(__file__).with_name(phase + "-http.json").write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
print(json.dumps(report, indent=2))
raise SystemExit(0 if all(x["ok"] for x in results) and ids else 1)
