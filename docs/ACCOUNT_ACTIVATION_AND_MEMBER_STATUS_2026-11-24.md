# KIS-ACCESS-STATUS-01 — účty importovaných osob a stav členství

Datum: 24. 11. 2026. Stav: **KONCEPT / čtecí analýza**. Vlastník zadání: Marek / vedení klubu; návrh a analýza: vývoj KIS. Větev `codex/account-membership-concept-20261124`, samostatný worktree z `origin/main` `b8830ea7`. [Registr](WORK_PLAN_CURRENT.md#kis-access-status-01--aktivace-a-stavy-členství-24-11-2026). Pokyn povoluje analýzu a návrh; implementace, změna účtů/dat, e-maily, merge runtime a nasazení nejsou schválené ani provedené. Návaznost: [KIS-ACT-01](IMPORTOVANE_UCTY_AKTIVACE_2026-11-12.md), [bezsezonní členský model](MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md), [kroužky](club-programs.md), [členské tarify](CLUB_MEMBERSHIP_CATALOG_2026-11-05.md).

## Zjištěný stav v kódu

| Oblast | Současné pravidlo a dopad |
| --- | --- |
| Import osob | `includes/kis_person_import.php` ukládá kontakt do `sportovci`; jen při jednoznačné adrese vytvoří samostatný vypnutý účet `verejni_uzivatele` s neznámým náhodným heslem, `aktivni=0`, `email_overeno=0`. Vazby na osoby čekají na schválení. Adresa na kartě sportovce sama není přihlašovací účet ani důkaz jejího vlastníka. |
| Rodičovský reset | `includes/password_reset.php::passwordResetResolveTarget()` vyhledá pouze aktivní ověřený veřejný účet. `booking/zapomenute_heslo.php` přijímá e-mail nebo přihlašovací jméno sportovce; obecné potvrzení chrání proti zjištění existence účtu. Nezpůsobilý účet nevytvoří resetovací e-mail. Audit důvodu pro nové žádosti zajišťuje AUTH-MAIL-04/05; historický pokus bez reference nelze zpětně přiřadit. |
| Účet sportovce | `child_access_accounts` je oddělený přístup s přihlašovacím jménem. Jeho reset vyžaduje aktivní dětský přístup a platnou schválenou vazbu `self`/`guardian` na aktivní ověřený veřejný účet; odkaz jde na adresu tohoto účtu. Import profilu takový přístup automaticky nezakládá. |
| První aktivace | KIS-ACT-01 již umí po individuálním ověření kontaktu a všech importovaných vazeb poslat jednorázovou pozvánku. Její použití nastaví první heslo, zapne účet a ověří adresu. Dosud nebyl doložen produkční pilot pozvánek ani rodičovské UAT. |
| Stav osoby | `sportovci.stav_clenstvi` má `cekajici`, `aktivni`, `neaktivni`, `archiv`. `includes/sportovec_status_lib.php` jej chápe jako evidované rozhodnutí registráře, nespojuje jej automaticky s platbou, účtem ani soupiskou. Import starého KIS zapisuje historicky aktivní/neaktivní a `stav_manualni=1`; význam je nutné ověřit proti zdroji a dnešní praxi. |
| Archiv | Archivované osoby jsou vyloučené ze zakládání/obnovy soupisek a z některých běžných seznamů (`includes/roster_hierarchy.php`, `includes/member_roster_view.php`). Neaktivní v těchto kontrolách zůstávají; rozdíl tedy dnes existuje, byť nemá ucelenou produktovou definici. |
| Jednorázový kroužek | `includes/club_program.php` povolí aktivaci placené účasti a soupisky až při `payment_status=paid`, zkontroluje schválenou vazbu osoby, souhlasy, období a kapacitu. `includes/club_program_account_status.php` do té doby ukazuje čekání na platbu. Samotný nákup dnes aktivní účast nezakládá. |
| Průběžné členství | `includes/monthly_membership.php` při platném sjednání založí členství, zařazení do soupisek a první předpis hned; platba může přijít později. Upomínky plátci po splatnosti jsou odstupňované, nikoli automatická změna `stav_clenstvi`. |
| Finance | `includes/club_finance.php::clubFinanceBalanceForAthlete()` už počítá otevřenou a po toleranci splatnosti opožděnou částku včetně alokací/kreditu. `kis_platebne_aktivni` je historický importní signál (podle dřívější úhrady), nikoli živé potvrzení bezdlužnosti. `includes/person_state.php` ukazuje členství, přístupy, soupisky, účasti a předpisy odděleně; bez předpisu výslovně nelze tvrdit bezdlužnost. |

Analýza zdroje na `b8830ea7`: PASS (čtení uvedených funkcí). Produkční počty neověřených účtů, nejednoznačných adres, přístupů sportovců, dluhů a kombinací stavů: **NOT RUN**. Přihlášený produktový průchod a doručení pozvánek: **NOT RUN**. Tento dokument neprokazuje, že konkrétní e-mail skutečně patří sportovci či rodiči.

## Význam stavů: návrh k rozhodnutí

Stav člena navrhujeme vykládat jako rozhodnutí o evidenci osoby, nikoli jako zkratku za platbu nebo možnost přihlášení. Účet, vazba na dítě, časová účast v soupisce/programu a finance zůstanou samostatné osy. Na kartě mají být vidět vedle sebe, s důvodem a datem posledního rozhodnutí.

| Stav člena | Navržený význam | Změna |
| --- | --- | --- |
| Čekající | Nová nebo importovaná osoba, u níž není dokončeno přijetí/ověření podkladů a vazeb. Není to označení „čeká na platbu“. | Registrář po kontrole, případně automaticky při přesně definované schválené e-shopové přihlášce. |
| Aktivní | Klub osobu přijal do aktuální činnosti/oddílu; přístup k tréninkům se vyhodnocuje podle konkrétní platné účasti, role, věku a souhlasu. Může být před splatností nezaplaceno nebo po splatnosti dluženo. | Přijatá přihláška či ruční registrářské rozhodnutí s auditem; ne pouhý nákup libovolného zboží. |
| Neaktivní | Osoba zůstává v běžné evidenci a historii, ale nemá aktuální klubovou činnost. Např. přestávka/ukončení; návrat je běžný ruční proces. | Ručně s důvodem, po ukončení či posouzení všech soupisek/nároků. |
| Archiv | Osoba již není v běžném výběru a nesmí dostat nové zařazení bez řízeného obnovení; historie a doklady zůstávají. Vhodné pro dávno odešlé osoby nebo chybně importované profily po přezkumu. | Ručně s důvodem a kontrolou aktivních nároků, finančních a právních záznamů. |

### Platební štítek na kartě

Jeden boolean „platící“ by zaměnil úhradu jednoho předpisu za bezdlužnost celé osoby. Doporučené štítky k dnešnímu dni: **Uhrazeno** (všechny splatné závazky daného rozsahu pokryté), **Čeká na splatnost** (otevřené, dosud v termínu), **Po splatnosti** (nenulový zůstatek po schválené toleranci), **Bez předpisu / nelze vyhodnotit**. Ukázat částku, datum nejstarší splatnosti a rozsah (členské příspěvky, kroužek, případně ostatní objednávky). Při platbě přepočítat z autoritativních předpisů, nikoli přepisovat ručně pole `kis_platebne_aktivni`. Storno, přeplatek, částečná úhrada a více dětí musejí mít jednoznačný výsledek.

### Nákup, viditelnost tréninků a splatnost

Rozlišit **okamžité zobrazení zakoupeného programu a plánovaných tréninků** od **oprávnění k účasti a docházce**. U průběžného členského tarifu již sjednání zakládá soupisku před platbou. U placeného jednorázového kroužku platí opačné pravidlo, doložené v bezsezonním plánu: účast až po úhradě nebo výslovné schválené výjimce. Doporučená první etapa: po platné objednávce ukázat dítěti/rodiči harmonogram a stav „čeká na úhradu“; současně zachovat identitu, kapacitu, souhlasy a ochranu docházky. Varianta plného vstupu na trénink ihned po objednání je produktová změna: klub musí určit rezervaci kapacity, lhůtu platby, storno, přípustnou absenci platby a dopad na náhrady. Karta osoby se nemá stát aktivní jen kvůli nákupu běžného zboží.

Po skutečné splatnosti a toleranci zůstane členství aktivní, finanční štítek přejde na „Po splatnosti“ a vznikne jedna dohledatelná úloha/upozornění pro pověřeného správce. Stávající upomínky plátci a limity dalších registrací se nesmějí zdvojit. Omezení funkcí až v samostatné etapě po rozhodnutí klubu: přesně určit funkci, lhůtu, výjimky, kdo rozhoduje, jak se odblokuje po párování a co se nikdy neskryje (dluh, doklady, kontakt, vlastní data). Převod do neaktivního nebo archivu dluhem automaticky neprovádět.

## Ověření e-mailu a obnova: varianty

1. **Doporučeno: řízená aktivace importovaných účtů.** Využít KIS-ACT-01, nejprve inventuru unikátních a sdílených adres, schválení vazeb a pilot pozvánek. Po otevření jednorázového odkazu je skutečně potvrzeno ovládání dané schránky, účet získá první heslo a od té chvíle funguje běžný reset. Pro aktivní ověřené účty reset funguje již dnes. Při nedoručení nabídnout správcem řízenou změnu kontaktu po ověření totožnosti. Odstranit provozní překážky pošty podle AUTH-MAIL-05; stav `sent` nestačí k prokázání doručení.
2. **Sportovec:** udělat inventuru, kdo má pouze profil `sportovci`, kdo `child_access_accounts` a kdo vlastní `self` veřejný účet. Pro existující dětský přístup lze navrhnout doručení resetu na samostatně ověřený a jednoznačný e-mail sportovce, je-li to vhodné podle věku a schválené vazby; jinak na ověřeného zástupce či přes administrativní obnovu. Pro dospělého sportovce řešit vlastní účet `self`. Samotný sloupec `sportovci.email` nesmí otevřít heslo ani rodinné údaje.
3. **Hromadné nastavení `email_overeno=1`: nedoporučeno jako samostatná migrace.** Z importu plyne znalost textu adresy, ne její držitel ani právo k osobě. Současně by se otevřely cesty do rodinného portálu, registrací a notifikací, které na příznak spoléhají. Ani nastavení příznaku neaktivního účtu nepovolí reset kvůli `aktivni=0`, takže by požadovaný výsledek samo nepřineslo. Pokud klub má nezávislý důkaz o potvrzení jednotlivých adres, lze navrhnout omezený auditovaný převod pouze těchto případů, s výjimkami pro kolize a technické účty.

## Návrh etap a akceptace

1. **Čtecí inventura produkce:** agregované počty osob/veřejných účtů/dětských přístupů, unikátní vs. sdílené adresy, schválené/čekající vazby, ručně/historicky evidované stavy, aktivní objednávky/soupisky, otevřené předpisy a zpožděné platby. Bez osobních exportů do Gitu. Porovnat, zda u konkrétních nahlášených účtů reset končí v auditu jako nezpůsobilý, limitovaný nebo chyba fronty.
2. **Produktová pravidla:** vedení určí věkové podmínky sportovcova samostatného resetu, rozsah „všech tréninků“, okamžik přijetí do členství, platební toleranci, příjemce administrátorské úlohy a rozdíl neaktivní/archiv. Změna současného pravidla placeného kroužku musí být výslovně potvrzena.
3. **Návrh implementace po rozhodnutí:** sjednotit čtecí projekci karty osoby se čtyřmi osami; průběžně odvozovat finanční štítek z předpisů/plateb; přidat administrátorský dohled a deduplikované upozornění; podle zvolené varianty upravit náhled tréninků a bezpečný aktivační/resetovací tok. Žádný globální přepis `email_overeno` ani `stav_clenstvi` bez inventury, zálohy, auditní stopy a odděleného schválení.
4. **Testy budoucí změny:** importovaný kontakt bez ověření nezíská reset/přístup; pozvánka schválené osobě otevře právě její účet; sportovec s vlastním/rodičovským přístupem obnoví jen správné heslo; sdílená adresa a změněná vazba jsou odmítnuty; nákup kroužku ukáže rozvrh bez cizí docházky; potvrzená/částečná/vrácená platba i překročená splatnost dávají správný štítek a jednu úlohu; archiv nepřijme nové zařazení; historické doklady zůstanou dostupné. Produkční lidské UAT a doručení e-mailu se prokazují zvlášť.

Další krok: prodiskutovat produktové volby v bodu 2 a provést čtecí inventuru; teprve poté převést KONCEPT na schválený implementační plán. Kód, data, fronta, CI, merge, nasazení a UAT: **NOT RUN v tomto řezu**.
