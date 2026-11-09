"""Build an isolated browser fixture using the current production measurement renderer."""
from pathlib import Path
import re
root = Path(__file__).resolve().parents[2]
source = (root / 'formular.php').read_text(encoding='utf-8')
start = source.index('(() => {', source.index('// ── MĚŘENÍ:'))
end = source.index('// ── TAGY:', start)
script = re.sub(r'<\?=.*?\?>', lambda m: 'false' if 'planPrefill' in m.group() else '[]', source[start:end], flags=re.S)
output = root / 'output/playwright/training-form.html'
output.parent.mkdir(parents=True, exist_ok=True)
shell = '<!doctype html><meta charset="utf-8"><title>Trénink LOCALHOST</title>\n<form id="treninkForm"><div id="training-draft-recovery"></div><p id="training-draft-status" role="status"></p>\n<input type="hidden" name="training_draft" id="training_draft"><input name="submission_key" value="testkey">\n<label>Náplň<textarea id="napln" name="napln" required></textarea></label><label>Poznámka<textarea name="poznamka"></textarea></label>\n<input name="team_ids[]" type="checkbox" value="1" checked><div id="training-target-error" class="d-none"></div>\n<input id="ucastnici" name="ucastnici"><input id="tagy_json" name="tagy_json"><input id="mereni_json" name="mereni_json">\n<button type="button" id="btnAddMereni">Přidat měření</button><div id="mereniRows"></div><button type="submit">Uložit</button></form>\n'
tail = '<script id="training-draft-config" type="application/json">{"actor":10,"key":"testkey","context":"0:0","completed":[]}</script>\n<script src="/assets/training-form-resilience.js"></script>\n<script>window.submissions=0;document.addEventListener(\'submit\',e=>{if(!e.defaultPrevented)window.submissions++;e.preventDefault();});</script>'
output.write_text(shell + '<script>window.__ucastniciSelected=[];</script><script>' + script + '</script>' + tail, encoding='utf-8')
