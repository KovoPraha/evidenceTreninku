"""Build a portable, synthetic UAT guide from reviewed scenarios; never connects to DB."""
from pathlib import Path
from datetime import datetime
from collections import Counter
import html, json, re, shutil, hashlib, zipfile
from reportlab.pdfgen.canvas import Canvas
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak, KeepTogether
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_LEFT
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from pypdf import PdfReader

APP=Path(__file__).resolve().parents[1]
SRC=APP/'docs/acceptance-20261023'
OUT=APP/'output/uat-accountant-20261023'
QA=APP/'tmp/acceptance-20261023'
OUT.mkdir(parents=True,exist_ok=True);QA.mkdir(parents=True,exist_ok=True)
FILES=['operations.json','people-sport.json','commerce-finance.json']
cases=[]
for name in FILES:
    cases.extend(json.loads((SRC/name).read_text(encoding='utf-8-sig')))
ids=[c['id'] for c in cases]
assert len(set(ids))==len(ids)
required=['id','title','priority','roles','routes','minutes','depends_on','prerequisites','test_data','steps','evidence','decisions','sources','status']
for c in cases:
    assert all(k in c for k in required),c.get('id')
    assert c['status']=='NEPROVEDENO',c['id']
    assert c['priority'] in ['P0','P1','P2'] and c['minutes']>0
    assert all(i in ids for i in c['depends_on']),c['id']
    assert all((APP/r.split('?')[0].split('#')[0]).is_file() for r in c['routes']),c['id']
    assert all(s.get('action') and s.get('expected') for s in c['steps']),c['id']
    for s in c['sources']:
        assert (APP/re.sub(r':\d+(?:-\d+)?$','',s)).is_file(),(c['id'],s)
    c.setdefault('preparation_status','VYŽADUJE PŘÍPRAVU');c.setdefault('blockers',[])
counts=Counter(c['priority'] for c in cases)
# Check the dependency graph and expose inherited preparation requirements.
by_id={c['id']:c for c in cases}
resolved=set()
def prepare(c,trail=()):
    assert c['id'] not in trail,('dependency cycle',trail,c['id'])
    if c['id'] in resolved:return
    for dep in c['depends_on']:prepare(by_id[dep],trail+(c['id'],))
    pending=[dep for dep in c['depends_on'] if by_id[dep]['preparation_status']!='PŘIPRAVENO']
    if pending:
        c['blockers'].append('Nejdříve dokončit přípravu navazujících scénářů: '+', '.join(pending)+'.')
        if c['preparation_status']=='PŘIPRAVENO':c['preparation_status']='VYŽADUJE PŘÍPRAVU NÁVAZNOSTÍ'
    resolved.add(c['id'])
for c in cases:prepare(c)
for c in cases:
    c['prerequisites']=[item for item in c['prerequisites'] if item not in c['blockers']]
minutes=sum(c['minutes'] for c in cases)
sample=json.loads((SRC/'accountant-sample.json').read_text(encoding='utf-8-sig'))
case_data=json.dumps(cases,ensure_ascii=False,indent=2)
(OUT/'prijimaci-scenare.json').write_text(case_data,encoding='utf-8')
css=(APP/'tools/acceptance-assets/guide.css').read_text(encoding='utf-8')
js=(APP/'tools/acceptance-assets/guide.js').read_text(encoding='utf-8')
(OUT/'guide.css').write_text(css,encoding='utf-8');(OUT/'guide.js').write_text(js,encoding='utf-8')

def inline(text):
    text=html.escape(text)
    text=re.sub(r'\*\*(.+?)\*\*',r'<b>\1</b>',text)
    text=re.sub(r'`([^`]+)`',r'<code>\1</code>',text)
    return text

def md_blocks(text):
    lines=text.splitlines();i=0
    while i<len(lines):
        line=lines[i].strip()
        if not line:i+=1;continue
        if line.startswith('|'):
            rows=[]
            while i<len(lines) and lines[i].strip().startswith('|'):
                cells=[c.strip() for c in lines[i].strip().strip('|').split('|')]
                if not all(re.fullmatch(r':?-+:?',c.replace(' ','')) for c in cells):rows.append(cells)
                i+=1
            yield 'table',rows;continue
        if line.startswith('#'):
            yield 'heading',(len(line)-len(line.lstrip('#')),line.lstrip('#').strip());i+=1;continue
        if line.startswith('- ') or re.match(r'^\d+\. ',line):
            yield 'bullet',re.sub(r'^(?:- |\d+\. )','',line);i+=1;continue
        para=[line];i+=1
        while i<len(lines) and lines[i].strip() and not lines[i].lstrip().startswith(('#','|','- ')):
            para.append(lines[i].strip());i+=1
        yield 'paragraph',' '.join(para)

def md_html(text):
    result=[]
    for kind,v in md_blocks(text):
        if kind=='heading':level=min(v[0]+1,4);result.append(f'<h{level}>{inline(v[1])}</h{level}>')
        elif kind=='table':result.append('<table>'+''.join('<tr>'+''.join(f'<{"th" if n==0 else "td"}>{inline(c)}</{"th" if n==0 else "td"}>' for c in row)+'</tr>' for n,row in enumerate(v))+'</table>')
        else:result.append('<p>'+('• ' if kind=='bullet' else '')+inline(v)+'</p>')
    return '\n'.join(result)

accountant_md=(SRC/'accountant-sample.md').read_text(encoding='utf-8-sig')
fixtures_md=(SRC/'fixtures.md').read_text(encoding='utf-8-sig')
for name in ['fixtures.md','fixtures.json','fixtures-catalog.png']:shutil.copyfile(SRC/name,OUT/name)
(OUT/'podklady.html').write_text('<!doctype html><html lang="cs"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Testovací podklady</title><link rel="stylesheet" href="guide.css"><body><main><a href="index.html">Zpět na přejímku</a>'+md_html(fixtures_md)+'</main></body></html>',encoding='utf-8')
accountant_html='<!doctype html><html lang="cs"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ukázka pro účetní</title><link rel="stylesheet" href="../guide.css"><body><header><div class="eyebrow">Syntetická ukázka · nepřevzato</div><h1>Podklady pro účetní</h1><a href="../index.html">Zpět na přejímku</a></header><main class="accountant"><div class="links"><a class="button" href="rozbaleny-original/ucetni-podklady.xlsx">Otevřít XLSX</a><a class="button" href="rozbaleny-original/doklady/9.pdf">Faktura 250 Kč</a><a class="button" href="rozbaleny-original/doklady/10.pdf">Oprava −125 Kč</a><a class="button" href="originalni-export-22.zip">Původní ZIP aplikace</a></div>'+md_html(accountant_md)+'</main></body></html>'
(OUT/'ucetni/PRECTETE-UCETNI.html').write_text(accountant_html,encoding='utf-8')
decisions=(APP/'docs/CLUB_COMPLETION_DECISIONS.md').read_text(encoding='utf-8')
(OUT/'ODLOZENA-ROZHODNUTI.md').write_text(decisions,encoding='utf-8')
(OUT/'rozhodnuti.html').write_text('<!doctype html><html lang="cs"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Odložená rozhodnutí</title><link rel="stylesheet" href="guide.css"><body><main><a href="index.html">Zpět na přejímku</a>'+md_html(decisions)+'</main></body></html>',encoding='utf-8')
intro=f'''<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Přejímka společného systému oddílu</title><link rel="stylesheet" href="guide.css"></head><body>
<header><div class="eyebrow">Oddíl · Evidence / KIS / e-shop · 23. 10. 2026</div><h1>Přejímka společného systému</h1><p class="lead">Konkrétní kroky pro obsluhu, očekávané výsledky a záznam připomínek. Všechny scénáře čekají na skutečné provedení. Účetní podklady jsou pouze syntetickou ukázkou.</p><div class="links"><a href="PRIJIMACI-PRUVODCE.pdf">Tisknutelný úplný průvodce PDF</a><a href="ucetni/PRECTETE-UCETNI.html">Ukázkové předání účetní</a><a href="rozhodnuti.html">Odložená rozhodnutí D01–D16</a></div></header>
<main><div class="notice"><b>Pouze místní test.</b> Aplikační odkazy vedou na http://127.0.0.1:18086. Produkce, banky a rozesílání se nezapínají. Tento průvodce zapisuje pouze vaše místní poznámky; nevytváří platby ani schválení v aplikaci.</div>
<div class="cards"><div class="card"><b>{len(cases)}</b><span>připravených scénářů</span></div><div class="card"><b>{counts['P0']}</b><span>kritických scénářů P0</span></div><div class="card"><b id="done-count">0</b><span>označených jako prošlé</span></div><div class="card"><b id="fail-count">0</b><span>zaznamenaných selhání</span></div></div>
<section class="panel"><h2>Jak přejímku provést</h2><ol><li>Správce ověří izolaci a testovací účty. Začněte UAT-S01, potom pokračujte členskými a obchodními scénáři podle jejich návazností.</li><li>Používejte nové označené syntetické záznamy. Zapisujte skutečná ID; nepřebírejte identitu ani oprávnění podle shodného jména. Předpoklady a technickou přípravu dokončete před krokem testu.</li><li>Každý krok porovnejte s očekávaným výsledkem. Uveďte testera, datum, ID záznamu a důkaz. Neprovádějte další finanční opravu jako pokus o nápravu nejasného výsledku.</li><li>PROŠLO znamená skutečně provedené kroky a doložený výsledek. Chybějící data nebo účet jsou BLOKOVÁNO; nepotvrzené klubové pravidlo je ODLOŽENO s odkazem Dxx; odlišné chování je SELHALO a samostatná vada.</li><li>Kontrolujte postupně po rolích. Součet odhadů je {minutes} minut ({minutes/60:.1f} h), bez přípravy dat a opravy vad. Rozdělte práci do více bloků; nejde o závazný termín.</li><li>Před případným pilotem musí být relevantní P0 skutečně splněné, vady vyřešené či výslovně posouzené a potřebná rozhodnutí potvrzená vlastníky. Místní úspěch sám nepovoluje nasazení.</li></ol><p><b>Přístupy:</b> soukromý soubor <code>%LOCALAPPDATA%\\EvidencePavelB1\\finance-b1-20261022\\local-review-access.json</code> na testovacím počítači. Není součástí balíčku. Pro omezení rolí je potřeba zvláštní omezený účet; dva plné kontrolní účty nestačí.</p><p><b>Účetní ukázka:</b> původní export #22, revize 5, 24. 9. – 23. 10. 2026. Obsahuje záměrné testovací výjimky a není úplným účetnictvím. Dokladová ukázka patří jinému případu než souvislý příklad účtu #27.</p></section>
<h2>Scénáře a výsledky</h2><p id="storage" class="muted"></p><div class="links no-print"><button id="export">Stáhnout výsledky JSON</button><label class="button">Načíst vlastní výsledky<input id="import" type="file" accept=".json,application/json" style="display:none"></label><button id="expand" class="secondary">Rozbalit zobrazené</button><button id="collapse" class="secondary">Sbalit vše</button></div>
<div class="toolbar"><label>Hledat<input id="search" type="search" placeholder="Členství, vratka, ID…"></label><label>Role<select id="role"></select></label><label>Priorita<select id="priority"><option value="">Všechny</option><option>P0</option><option>P1</option><option>P2</option></select></label><label>Výsledek<select id="status-filter"><option value="">Všechny</option><option>NEPROVEDENO</option><option>PROŠLO</option><option>SELHALO</option><option>BLOKOVÁNO</option><option>ODLOŽENO</option></select></label></div><p id="shown" class="meta"></p><div id="case-list"></div>
<section class="panel"><h2>Závěrečné převzetí</h2><p>Vedoucí přejímky: ____________________ Datum: ____________________</p><p>Ověřená verze a rozsah: ____________________________________________________</p><p>Otevřené vady, výhrady a rozhodnutí: ________________________________________</p><p><b>Výchozí stav: NEPŘEVZATO.</b> Podpis obsluhy, účetní převzetí a povolení produkčního přechodu jsou tři samostatné kroky. Nic se tímto dokumentem automaticky nepodepisuje.</p></section></main><footer>Připraveno z lokálního kódu abbb4be9. Technické důkazy v adresáři technicke-dukazy pocházejí z předchozí automatizované kontroly; nejsou vyplněnými výsledky této lidské přejímky.</footer><script id="case-data" type="application/json">{case_data.replace('<','\\u003c')}</script><script src="guide.js"></script></body></html>'''
intro=intro.replace('<h2>Scénáře a výsledky</h2>','<section class="panel"><h2>Konkrétní testovací podklady</h2><p><a href="podklady.html">Připravené produkty, varianty, účty a dosud chybějící podklady</a>. Skladové počátky zadává až tester v příslušném scénáři.</p></section><h2>Scénáře a výsledky</h2>')
(OUT/'index.html').write_text(intro,encoding='utf-8')

# PDF: embedded Czech font, automatic pagination; every scenario starts separately.
pdfmetrics.registerFont(TTFont('UAT','C:/Windows/Fonts/arial.ttf'))
pdfmetrics.registerFont(TTFont('UAT-Bold','C:/Windows/Fonts/arialbd.ttf'))
pdfmetrics.registerFontFamily('UAT',normal='UAT',bold='UAT-Bold',italic='UAT',boldItalic='UAT-Bold')
styles=getSampleStyleSheet()
for name in ['Normal','BodyText','Heading1','Heading2','Heading3','Title']:
    styles[name].fontName='UAT';styles[name].textColor=colors.HexColor('#192f40')
styles['Normal'].fontSize=9;styles['Normal'].leading=11.5;styles['Normal'].spaceAfter=2
styles['BodyText'].fontSize=9;styles['BodyText'].leading=12.5
for name in ['Heading1','Heading2','Heading3','Title']:styles[name].fontName='UAT-Bold'
styles['Heading1'].fontSize=18;styles['Heading1'].leading=22
styles['Heading2'].fontSize=13;styles['Heading2'].leading=17;styles['Heading2'].spaceBefore=7;styles['Heading2'].spaceAfter=4
styles['Heading3'].fontSize=10;styles['Heading3'].leading=14
styles.add(ParagraphStyle('Small',parent=styles['Normal'],fontSize=8,leading=11))
W=178*mm
def p(text,style='Normal'):
    text=inline(str(text)).replace('<code>','<font face="UAT">').replace('</code>','</font>')
    return Paragraph(text,styles[style])
def bullets(items):return [p('• '+v) for v in items]
def table(rows,widths=None):
    data=[[p(c,'Small') for c in row] for row in rows]
    t=Table(data,colWidths=widths or [W/len(rows[0])]*len(rows[0]),repeatRows=1,hAlign='LEFT')
    t.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,0),colors.HexColor('#e8f1f3')),('VALIGN',(0,0),(-1,-1),'TOP'),('GRID',(0,0),(-1,-1),.4,colors.HexColor('#d8e1e7')),('LEFTPADDING',(0,0),(-1,-1),7),('RIGHTPADDING',(0,0),(-1,-1),7),('TOPPADDING',(0,0),(-1,-1),3.5),('BOTTOMPADDING',(0,0),(-1,-1),3.5)]));return t
story=[Spacer(1,14*mm),p('ODDÍL / EVIDENCE / KIS / E-SHOP','Heading3'),p('Uživatelská přejímka','Title'),p('Podrobný pracovní průvodce a ukázkové předání účetní','Heading1'),Spacer(1,8*mm),p('23. 10. 2026 · podkladová verze abbb4be9'),p('**Stav lidské přejímky: NEPROVEDENO. Stav účetního převzetí: NEPŘEVZATO.**'),p('Pouze izolované místní prostředí, syntetické údaje a ukázkové doklady. Žádné nasazení, přenos skutečných osob, bankovní operace ani odeslání účetní.'),table([['Scénáře','Kritické P0','Časový odhad'],[str(len(cases)),str(counts['P0']),f'{minutes} min bez přípravy dat']]),Spacer(1,10*mm),p('Začněte souborem index.html v rozbaleném balíčku. Nabízí filtrování podle role a priority, odkazy na místní aplikaci a záznam výsledků s exportem JSON. Tento PDF je úplná tisknutelná varianta.'),p('Originální účetní XLSX, PDF a ZIP jsou ve složce ucetni. Jejich průvodce vysvětluje jeden souvislý příklad i výjimky; původní export zůstává nezměněný.'),PageBreak(),p('Organizace a pravidla přejímky','Heading1')]
for text in ['Správce nejdříve ověří místní probe PASS, přístupy a dostupnost testovacích podkladů. Přihlašovací údaje jsou pouze v local-review-access.json v soukromém runtime; do protokolu se nepíší.','Začněte UAT-S01 a pak respektujte návaznosti jednotlivých scénářů. Vždy použijte nové označení běhu, konkrétní syntetickou osobu a zapište vzniklá ID. Nepoužívejte reálné členy ani pracovníky.','PROŠLO = všechny kroky skutečně provedené s očekávaným výsledkem a důkazem. SELHALO = jiný výsledek; BLOKOVÁNO = chybí předpoklad; ODLOŽENO = chybí rozhodnutí vlastníka. Předvyplněná technická kontrola nesmí nahrazovat testera.','Při vadě zapište ID scénáře, krok, verzi aplikace, roli, testovací ID, očekávaný/skutečný výsledek, čas a důkaz. Nepokračujte finančními opravami naslepo. Za kritickou považujte nepovolený přístup, duplicitní peníze, přepsání uzavřené historie či chybný skladový nárok.','Omezení rolí ověřte zvláštním omezeným účtem, nikoli plným kontrolním účtem přepnutým do jiné pozice. Nezávislé schválení provádí jiný oprávněný testovací pracovník.','Výchozí stav všech scénářů je NEPROVEDENO. Připravenost podkladů je samostatný údaj. Syntetické sazby, dokladové řady a pravidla nepotvrzují skutečnou metodiku oddílu.','Před pilotem doložte výsledky relevantních P0 a vyřešení jejich vad. Skutečný pilot, přijetí účetní, povolení nasazení a odstavení starých webů zůstávají samostatně odložené podle D01–D16.']:story.append(p(text))
story += [p('Záznam vady','Heading2'),table([['Pole','Doplní tester'],['ID vady / scénář / krok',''],['Role / tester / datum / verze',''],['Testovací ID a předpoklady',''],['Očekávaný a skutečný výsledek',''],['Důkaz / závažnost / odpovědnost',''],['Opakované ověření po opravě','']],[58*mm,120*mm]),PageBreak(),p('Přehled scénářů','Heading1')]
story.append(table([['ID','Scénář','Priorita / min']]+[[c['id'],c['title'],f"{c['priority']} / {c['minutes']}"] for c in cases],[24*mm,128*mm,26*mm]))
story += [PageBreak(),p('Konkrétní testovací podklady','Heading1')]
for kind,v in md_blocks(fixtures_md):
    if kind=='heading':story.append(p(v[1],'Heading2'))
    elif kind=='table':story.append(table(v))
    else:story.append(p(('• ' if kind=='bullet' else '')+v))
for c in cases:
    story += [PageBreak(),p(c['id']+' · '+c['title'],'Heading1'),p(' / '.join(c['roles'])+' · '+c['priority']+f" · přibližně {c['minutes']} min"),p('**Lidský výsledek: NEPROVEDENO.** Příprava: '+c['preparation_status']),p('Místní obrazovky: '+', '.join(c['routes']),'Small')]
    if c['depends_on']:story.append(p('Navazuje na: '+', '.join(c['depends_on'])))
    if c['blockers']:story += [p('Nejdříve dořešit','Heading2')]+bullets(c['blockers'])
    story += [p('Předpoklady','Heading2')]+bullets(c['prerequisites'])+[p('Testovací údaje','Heading2')]+bullets(c['test_data'])+[p('Kroky a očekávaný výsledek','Heading2')]
    story.append(table([['Úkon','Očekávaný výsledek']]+[[f'{i}. '+s['action'],s['expected']] for i,s in enumerate(c['steps'],1)],[92*mm,86*mm]))
    story += [p('Důkaz a záznam','Heading2')]+bullets(c['evidence'])+[p('Rozhodnutí: '+(', '.join(c['decisions']) or 'žádné nové'),'Small'),p('Výsledek: ____________________ Tester / datum: ____________________'),p('ID záznamů / číslo vady / důkaz: _____________________________________')]
story += [PageBreak(),p('Ukázkové předání účetní','Heading1')]
for kind,v in md_blocks(accountant_md):
    if kind=='heading':story.append(p(v[1],'Heading2'))
    elif kind=='table':
        n=len(v[0]);widths=([30*mm,120*mm,28*mm] if n==3 and v[0][0]=='ID' else ([30*mm,148*mm] if n==2 else None));story.append(table(v,widths))
    else:story.append(p(('• ' if kind=='bullet' else '')+v))
story += [PageBreak(),p('Závěrečné převzetí obsluhou','Heading1'),p('Výchozí stav: NEPŘEVZATO. Nejde o povolení produkčního nasazení.'),table([['Pole','Vyplní odpovědná osoba'],['Vedoucí přejímky / datum',''],['Verze aplikace / rozsah',''],['Provedené a neprovedené scénáře',''],['Nevyřešené vady a jejich odpovědnosti',''],['Odložená rozhodnutí D01–D16',''],['Závěr / výhrady / podpis','']],[67*mm,111*mm])]
def footer(canvas,doc):
    canvas.saveState();canvas.setFont('UAT',8);canvas.setFillColor(colors.HexColor('#526777'));canvas.drawString(16*mm,11*mm,'LOCALHOST / SYNTETICKÁ PŘEJÍMKA / 23. 10. 2026');canvas.drawRightString(194*mm,11*mm,str(doc.page));canvas.restoreState()
pdf=APP/'output/pdf/PRIJIMACI-PRUVODCE-20261023.pdf'
pdf.parent.mkdir(parents=True,exist_ok=True)
SimpleDocTemplate(str(pdf),pagesize=(210*mm,297*mm),leftMargin=16*mm,rightMargin=16*mm,topMargin=17*mm,bottomMargin=19*mm,title='Uživatelská přejímka a ukázkové předání účetní',author='Příprava pro oddíl').build(story,onFirstPage=footer,onLaterPages=footer)
assert len(PdfReader(str(pdf)).pages)>1
shutil.copyfile(pdf,OUT/'PRIJIMACI-PRUVODCE.pdf')
evidence=OUT/'technicke-dukazy';evidence.mkdir(exist_ok=True)
for name in ['RESULTS.md','phpunit.json','backup-restore.json','b1-recovery-summary.json','readiness.json','migrations.json']:
    shutil.copyfile(APP/'docs/verification/autonomous-followup'/name,evidence/name)
shutil.copyfile(APP/'docs/CLUB_LOCAL_REVIEW.md',OUT/'MISTNI-PROSTREDI.md')
# Preserve historical prose, but do not leave broken repository links in the portable copy.
for path in [evidence/'RESULTS.md',OUT/'MISTNI-PROSTREDI.md',OUT/'ODLOZENA-ROZHODNUTI.md']:
    def portable_link(m):
        label,target=m.group(1),m.group(2)
        if '://' in target or (path.parent/target.split('#')[0]).is_file():return m.group(0)
        return label+' (odkaz v původním repozitáři: '+target+')'
    path.write_text(re.sub(r'\[([^\]]+)\]\(([^)]+)\)',portable_link,path.read_text(encoding='utf-8')),encoding='utf-8')
for name in ['people-sport-notes.md','commerce-finance-notes.md']:
    if (SRC/name).exists():shutil.copyfile(SRC/name,OUT/name)
readme=f'''PŘEJÍMKA SPOLEČNÉHO SYSTÉMU ODDÍLU - 23. 10. 2026\n\n1. Rozbalte celý ZIP a otevřete index.html. Vše funguje bez internetu.\n2. {len(cases)} scénářů čeká na skutečné provedení; přístupy ani hesla balíček neobsahuje.\n3. Odkazy do aplikace fungují jen na připraveném testovacím počítači 127.0.0.1:18086.\n4. PRIJIMACI-PRUVODCE.pdf obsahuje celé postupy a účetní průvodce k tisku.\n5. ucetni/PRECTETE-UCETNI.html vysvětluje původní XLSX, PDF a ZIP #22. NEÚPLNÝ syntetický export není skutečné účetnictví oddílu.\n6. Výsledky v HTML jsou místní; průběžně stáhněte JSON. Nezapisují stav do aplikace ani nepotvrzují produkční přechod.\n7. Odložená pravidla D01-D16 řeší ODLOZENA-ROZHODNUTI.md.\n8. technicke-dukazy jsou historické automatizované důkazy, nikoli výsledky lidské přejímky. Oprava izolované DB a její omezení jsou popsány v b1-recovery-summary.json.\n\nSyntetická data. NEPLATIT. Účetní nebylo nic odesláno. Produkce beze změny.\n'''
(OUT/'ZACNETE-ZDE.txt').write_text(readme,encoding='utf-8')
allow=[p for p in OUT.iterdir() if p.is_file() and p.suffix in ['.html','.css','.js','.pdf','.json','.md','.txt'] and p.name not in ['MANIFEST.json']]
allow += list(evidence.glob('*'))+[OUT/'fixtures-catalog.png',OUT/'ucetni/originalni-export-22.zip',OUT/'ucetni/PRECTETE-UCETNI.md',OUT/'ucetni/PRECTETE-UCETNI.html',OUT/'ucetni/verification/accountant-sample.json']
allow += [p for p in (OUT/'ucetni/rozbaleny-original').rglob('*') if p.is_file()]
allow=sorted(set(allow));assert all(p.is_file() for p in allow)
manifest={'contract':'club-acceptance-package-v1','prepared_on':'2026-10-23','source_commit':'abbb4be9','human_uat':'NEPROVEDENO','accountant_acceptance':'NEPŘEVZATO','production_mutations':False,'scenarios':len(cases),'priorities':dict(counts),'files':[{'path':p.relative_to(OUT).as_posix(),'bytes':p.stat().st_size,'sha256':hashlib.sha256(p.read_bytes()).hexdigest()} for p in allow]}
(OUT/'MANIFEST.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2),encoding='utf-8');allow.append(OUT/'MANIFEST.json')
archive=OUT.parent/'KOVO-prejimka-a-ucetni-20261023.zip'
with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED) as z:
    for path in allow:z.write(path,path.relative_to(OUT).as_posix())
with zipfile.ZipFile(archive) as z:
    assert z.testzip() is None
    for record in manifest['files']:assert hashlib.sha256(z.read(record['path'])).hexdigest()==record['sha256']
summary={'status':'PASS','scenarios':len(cases),'steps':sum(len(c['steps']) for c in cases),'pdf_pages':len(PdfReader(str(pdf)).pages),'all_human_results':'NEPROVEDENO','archive':str(archive),'archive_files':len(allow),'archive_sha256':hashlib.sha256(archive.read_bytes()).hexdigest(),'route_and_source_files_validated':True,'manifest_hashes_verified':True,'production_mutations':False}
(QA/'build-verification.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps(summary,ensure_ascii=False,indent=2))
