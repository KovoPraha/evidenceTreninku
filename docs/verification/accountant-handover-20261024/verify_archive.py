from pathlib import Path, PurePosixPath
from decimal import Decimal
import zipfile, json, hashlib, stat, sys, io
import xml.etree.ElementTree as ET
from pypdf import PdfReader

source = Path(sys.argv[1]).resolve()
dest = Path(sys.argv[2]).resolve()
dest.mkdir(parents=True, exist_ok=True)
checks = []
def check(ok, name):
    if not ok: raise AssertionError(name)
    checks.append(name)
def sha(data): return hashlib.sha256(data).hexdigest()
def minor(value):
    rounded=value.quantize(Decimal('0.01'))
    # XLSX numeric values use IEEE doubles; reject material sub-cent differences.
    if abs(value-rounded)>Decimal('0.000000001'): raise AssertionError('Non-currency precision: '+str(value))
    return rounded*100
with zipfile.ZipFile(source) as z:
    check(z.testzip() is None, 'ZIP CRC integrity')
    names = z.namelist()
    check(len(names) == len(set(names)), 'No duplicate archive paths')
    for item in z.infolist():
        p = PurePosixPath(item.filename)
        check(not p.is_absolute() and '..' not in p.parts and '\\' not in item.filename
              and ':' not in item.filename and not stat.S_ISLNK(item.external_attr >> 16), 'Safe archive member: '+item.filename)
    manifest = json.loads(z.read('manifest.json'))
    data = json.loads(z.read('data.json'))
    check(data['status'] == 'NEÚPLNÝ', 'Synthetic incomplete status remains explicit')
    for f in manifest['files']:
        content = z.read(f['path'])
        check(len(content) == f['bytes'] and sha(content) == f['sha256'], 'Manifest digest: '+f['path'])
    for name in names:
        target = (dest/name).resolve()
        check(target.is_relative_to(dest), 'Extraction stays inside owned destination: '+name)
        target.parent.mkdir(parents=True, exist_ok=True)
        if not name.endswith('/'): target.write_bytes(z.read(name))

ns = {'s':'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
sheets = {}; types = {}; formulas = 0; errors = []
with zipfile.ZipFile(dest/'ucetni-podklady.xlsx') as z:
    strings = [''.join(si.itertext()) for si in ET.fromstring(z.read('xl/sharedStrings.xml'))]
    rels = {r.attrib['Id']:r.attrib['Target'] for r in ET.fromstring(z.read('xl/_rels/workbook.xml.rels'))}
    for sheet in ET.fromstring(z.read('xl/workbook.xml')).findall('s:sheets/s:sheet',ns):
        name=sheet.attrib['name']; rid=sheet.attrib['{http://schemas.openxmlformats.org/officeDocument/2006/relationships}id']
        target=rels[rid]; target=target.lstrip('/') if target.startswith('/') else 'xl/'+target
        rows=[]; celltypes={}
        tree=ET.fromstring(z.read(target))
        for row in tree.findall('s:sheetData/s:row',ns):
            cells={}
            for c in row.findall('s:c',ns):
                coord=c.attrib['r']; kind=c.attrib.get('t','n'); v=c.find('s:v',ns)
                raw=v.text if v is not None else ''
                value=strings[int(raw)] if kind=='s' else ''.join(c.find('s:is',ns).itertext()) if kind=='inlineStr' else Decimal(raw) if kind=='n' and raw else raw
                cells[''.join(x for x in coord if x.isalpha())]=value; celltypes[coord]=kind
                if kind=='e': errors.append([name,coord,raw])
                formulas += c.find('s:f',ns) is not None
            rows.append(cells)
        sheets[name]=rows; types[name]=celltypes
        check(tree.find('s:sheetViews/s:sheetView/s:pane',ns) is not None, 'Header freeze: '+name)
        check(tree.find('s:autoFilter',ns) is not None, 'Filter: '+name)
check(not errors and formulas==0,'Workbook contains literal export values, no formula injection/errors')
check(set(sheets)=={'Manifest','Doklady','Polozky','Penize','Parovani','Cleneni_penez','Predpisy','Saldo','Vydaje','Sklad'},'All ten expected worksheets present')
money=sheets['Penize'][1:]; byid={str(m['id']):m for m in data['movements']}
check(len(money)==len(byid)==len(set(r['A'] for r in money)), 'Every money movement exported exactly once')
for i,r in enumerate(money,2):
    m=byid[r['A']]
    check(types['Penize']['F'+str(i)]=='n' and minor(r['F'])==Decimal(m['amount_minor']), 'Signed numeric money amount: '+r['A'])
    check(r['L']==('V OBDOBÍ' if m['effective_on']>=data['period_from'] else 'MIMO OBRAT'),'Period cutoff: '+r['A'])
docs=sheets['Doklady'][1:]; docmap={str(d['id']):d for d in data['documents']}
check(len(docs)==len(docmap), 'Document row count matches snapshot')
pdf_summary=[]
for i,r in enumerate(docs,2):
    d=docmap[r['A']]; frozen=json.loads(d['snapshot_json'])
    check(r['B']==d['number'] and minor(r['J'])==Decimal(d['total_minor']), 'Document identity and total: '+r['A'])
    expected_vs=str(frozen.get('variable_symbol') or '')
    check((r.get('O') or '')==expected_vs and (not expected_vs or types['Doklady']['O'+str(i)]=='s'),'VS preserved as literal text: '+r['A'])
    check(sha((dest/d['archive_path']).read_bytes())==d['pdf_sha256'], 'PDF matches document hash: '+r['A'])
    check(sum((minor(r2['E']) for r2 in sheets['Polozky'][1:] if r2['A']==r['A']), Decimal(0))==Decimal(d['total_minor']), 'Frozen line sum matches document: '+r['A'])
    text='\n'.join(p.extract_text() or '' for p in PdfReader(dest/d['archive_path']).pages)
    check(d['number'] in text,'Document number readable in PDF: '+r['A'])
for r in sheets['Saldo'][1:]:
    if r['A']!='Účet': continue
    a=next(a for a in data['accounts'] if str(a['id'])==r['B'])
    movement=[m for m in data['movements'] if m['account_id']==a['id']]
    total=Decimal(a['opening_minor'])+sum((Decimal(m['amount_minor']) for m in movement),Decimal(0))
    check(minor(r['F'])==total,'Account balance reconciles once to money movements: '+r['B'])
for file in sorted(dest.rglob('*.pdf')):
    reader=PdfReader(file); texts=[p.extract_text() or '' for p in reader.pages]
    check(len(texts)>0 and all(t.strip() for t in texts),'Readable PDF pages: '+str(file.relative_to(dest)))
    pdf_summary.append({'path':str(file.relative_to(dest)),'pages':len(texts)})
report={'status':'PASS','source':str(source),'archive_sha256':sha(source.read_bytes()),'revision':data['revision'],
        'predecessor_id':data['predecessor_id'],'period':[data['period_from'],data['period_to']],
        'manifest_members':len(manifest['files']),'pdfs':pdf_summary,
        'sheets':{n:len(rows)-1 for n,rows in sheets.items()},'checks_count':len(checks),'checks':checks,
        'limits':'Synthetic technical acceptance; not approval of actual club classification or a real accountant receipt.'}
(dest.parent/(dest.name+'-verification.json')).write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps({k:v for k,v in report.items() if k not in ('checks','pdfs')},ensure_ascii=False))
