"""Structural proof for the packaging-only header change; no application bootstrap."""
import hashlib
import json
import os
import re
import shutil
import subprocess
from pathlib import Path

root = Path(__file__).resolve().parents[2]
fields = ["pushPublicKey", "serviceWorkerUrl", "appBaseUrl", "pushEndpoint", "csrfToken", "pushEndpoint", "csrfToken", "searchUrl", "appBaseUrl"]
reference = (root / "tests/Support/SharedHeaderScriptReference.php.txt").read_text()
metadata = json.loads((root / "tests/Support/SharedHeaderScriptReference.json").read_text())
header = (root / "hlavicka.php").read_text()
asset = (root / "assets/staff-header.js").read_text()
assert hashlib.sha256(reference.encode()).hexdigest() == metadata["script_sha256"]
start = header.index('<script type="application/json" id="appStaffHeaderConfig"')
end = header.index('</script>', header.index('<script nonce=', start)) + len('</script>')
assert hashlib.sha256(header[:start].encode()).hexdigest() == metadata['prefix_sha256'], 'Header prefix changed'
assert hashlib.sha256(header[end:].encode()).hexdigest() == metadata['suffix_sha256'], 'Header suffix changed'
body = reference[reference.index('?>">')+4:reference.index('</script>')]
expressions = re.findall(r'<\?=\s*(.*?)\s*\?>', body, re.S)
assert len(expressions) == 9
iterator = iter(fields)
expected = re.sub(r'<\?=\s*(.*?)\s*\?>', lambda m:'staffHeaderContext.'+next(iterator), body, flags=re.S)
prefix = "(function () {\n    const staffHeaderContext = JSON.parse(document.getElementById('appStaffHeaderConfig').textContent);"
assert asset == prefix + expected + '\n})();\n', 'Javascript changed beyond explicit context substitution'
region = header.split('<?= json_encode([\n',1)[1].split('\n], JSON_HEX_TAG',1)[0]
values = dict(re.findall(r"^    '(\w+)' => (.*),$", region, re.M))
assert list(values) == list(dict.fromkeys(fields)), 'Context shape changed'
old_values = []
new_values = []
for key, expr in zip(fields, expressions):
    assert expr.startswith('json_encode(') and expr.endswith(')')
    old_values.append(expr[len('json_encode('):-1].strip())
    new_values.append(values[key])
# Compare PHP tokens, retaining string contents and ignoring formatting only.
php = shutil.which('php') or ('C:/xampp/php/php.exe' if os.name == 'nt' else 'php')
code = r"""
$x=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);$out=[];
foreach($x as $source){$r=[];foreach(token_get_all('<?php '.$source) as $t){
if(is_array($t)){if(in_array($t[0],[T_OPEN_TAG,T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true))continue;
$r[]=[$t[0],$t[1]];}else{$r[]=$t;}}$out[]=$r;}echo json_encode($out,JSON_THROW_ON_ERROR);
"""
tokens=json.loads(subprocess.check_output([php,'-r',code],input=json.dumps(old_values+new_values).encode()))
assert tokens[:9] == tokens[9:], 'Server-side value expressions changed'
result=dict(baseline_sha=metadata['baseline_sha'],prefix_suffix_unchanged=True,js_core_unchanged=True,
            php_value_expressions_unchanged=True,interpolations=9,distinct_context_values=6,
            reference_block_bytes=len(reference.encode()),asset_bytes=len(asset.encode()),
            asset_sha256=hashlib.sha256(asset.encode()).hexdigest())
if __name__ == '__main__':
    print(json.dumps(result,indent=2))
