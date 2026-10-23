# Rodičovské a sportovní účty, osoby a nákup kurzů — 23. 10. 2026

## Rozsah a hranice

Produkční prohlížeč: pouze čtení již přihlášeného veřejného/pracovního účtu.
Veškeré nové žádosti, vazby, objednávka a simulovaná úhrada vznikly přes běžné UI
v B1 `http://127.0.0.1:18086`, DB `evidence_b1` na portu 33426.
Externí transporty B1 jsou vypnuté. Žádné produkční propojení osoby, objednávka,
platba, změna hesla, import ani aktivace integrace nebyly součástí zkoušky.

Před zápisy: read-only sandbox probe PASS (326 vlastněných tabulek), záloha
`evidence_2026-10-23_180559_a99dcbcc.sql.gz`, 326 tabulek / 2 triggery,
SHA256 `5ed078e4f3aab84033b481beb12e36dfb9fb1df69d7bb602eead630290cd5573`.
Záloha je mimo webroot v místním runtime `backups-family-uat-20261023`.

## Nalezené a opravené chyby

1. **Příjemce v košíku nekontroloval aktuální stav účtu.** Schválená vazba sama
   stačila i po odebrání ověření e-mailu. Dokončení objednávky ověřovalo účet,
   ale mezikrok přiřazení příjemce nebyl konzistentní s rodinným přehledem.
   `shopBeneficiaryActiveRelation` nyní vyžaduje také aktivní a ověřený účet;
   při transakční kontrole se zamyká i odpovídající řádek účtu.
2. **Přidání stejného období kurzu pro druhé dítě tiše přepisovalo první dítě.**
   Přidání nyní bezpečně odmítne a zachová původní košík. Opakování pro stejné
   dítě je idempotentní. Výslovná změna účastníka přímo v košíku zůstává možná.
   Aktuální datový kontrakt má jednu položku na variantu: stejné období pro
   další dítě se objednává samostatně. Toto omezení nyní vysvětluje hláška;
   nebyla provedena neplánovaná migrace košíku na více příjemců stejné varianty.
3. **Sportovní účet nerozlišoval potvrzenou docházku od běžného záznamu.**
   Přehled nyní označuje potvrzení pouze podle aktuální uzavřené revize,
   která obsahuje daného sportovce. Oprava docházky a znovuotevření uzávěrky
   odeberou potvrzení. Historický/neuzavřený záznam zůstává viditelný s vysvětlením.

První dvě chyby byly nejprve reprodukovány selhávajícími regresními testy.
Opravy nemění schema ani historické doménové záznamy.

## Prohlížečová přejímka

| Scénář | Výsledek a důkaz |
|---|---|
| Produkce: účet bez vazeb | Moje osoby i Sportovní přehled zobrazují prázdný stav; žádný cizí profil. |
| Produkce: Moje kroužky | Bez schválené osoby se nezobrazí cizí účast ani soupiska. |
| Produkce: omezený sportovní vstup | `muj_sport.php` bez sportovní identity přesměruje na `sportovec_prihlaseni.php`; pracovní/veřejná identita tuto hranici neobchází. |
| Produkce: nabídka a návrat | Aligátoři mají pololetní/celoroční možnosti, prázdný výběr dítěte a odkaz na propojení. Návrat zachoval produkt247, variantu813 a kotvu konkrétního termínu. Žádný formulář nebyl odeslán. |
| B1: žádost rodiče | Účet72 odeslal žádost1 k syntetické osobě91; před schválením žádné oprávnění. |
| B1: schválení | Registrář schválil vazbu86. Rodinný přehled ukázal právě osobu91, její tréninky, soupisku a historickou uhrazenou položku, i když tento účet nebyl původním plátcem. |
| B1: výběr příjemce | Produkt27 / varianta31 nabídl pouze schválenou osobu91. Košík držel jednoho účastníka a 125 Kč. |
| B1: objednávka | Objednávka32 `KP261023678C565F1E`, VS2610000015, vznikla z košíku se syntetickými LOCALHOST podmínkami; čekající platba neaktivovala novou účast. |
| B1: úhrada a aktivace | Hospodář zaznamenal simulovanou bankovní úhradu 125 Kč s důvodem. Moje kroužky zobrazily Zaplaceno, Účast aktivována a novou aktivní soupisku. Viz `local-paid-course.png`. |
| B1: odvolání | Registrář odvolal vazbu86. Moje kroužky skryly účasti a rodinné položky. Přímé otevření `nahrady.php?person_id=91` odmítnuto: K profilu nemáte oprávnění. Viz `local-revoked-course.png`. |
| B1: plátce po odvolání | Vlastní zaplacená objednávka32 zůstala v detailu dostupná, bez přístupu k aktuálním sportovním údajům. |
| B1: opětovné schválení | Vazba86 obnovena běžným UI s důvodem; historie schválení/odvolání zůstala zachována. |
| B1: pracovní hranice | Hospodář nemůže otevřít administraci objednávek pro zákaznickou péči; platbu potvrdil ve své finanční agendě. |

## Automatizované ověření

- Cílená sada účtů, osob, rodiny, kurzů, registrací a session: **229 testů / 2596 assertions PASS**.
- Celá místní Windows sada: 1656 testů / 32976 assertions, 3 skipped; jediné
  selhání je dříve známý statický scanner `posix_geteuid` na Windows
  (`GlobalFunctionAvailabilityTest`). Není to zelený celosystémový výsledek.
- Nové regrese: neaktivní/neověřený účet nesmí přiřadit příjemce; druhé dítě
  nesmí přepsat první; explicitní oprava příjemce funguje; potvrzená docházka
  vyžaduje aktuální uzávěrku a skutečnou účast daného sportovce.
- Stávající testy dále pokrývají sourozence/cizí osobu, revoked/pending/expired
  vazby, atomické schválení, revalidaci při checkoutu, kapacitu a platební
  aktivaci, vzájemné vyloučení omezené sportovní identity s rodinnou/pracovní,
  zneplatnění session a obnovu hesel přes oprávněného rodiče.
- Linux CI, MariaDB a chráněný release: doplnit po dokončení běhu.

## Přesně odložené části

- B1 nemá žádný existující `child_access_accounts` účet (read-only kontrola:
  počet0). Přímé přihlášení dítěte, jeho prohlížečový pohled a obnova hesla se
  neprohlašují za ověřené. Nové přihlašovací údaje ani reset hesla agent nevytvářel;
  jejich zadání v prohlížeči vyžaduje předání uživateli. Backend a session mají
  automatizované pokrytí; tato mez není zaměnitelná s browser PASS.
- Nová registrace účtu s ověřením doručeného e-mailu, ostré doručení, bankovní
  import a reálná platba zůstávají mimo tuto přejímku.
- Více dětí ve stejném období jedné varianty v jednom košíku není podporováno;
  bezpečná cesta jsou samostatné objednávky. Rozšíření datového modelu je samostatný balík.
- Lidská přejímka a účetní převzetí zůstávají otevřené. Technické výsledky
  se zapisují do B1 hubu jako PARTIAL, nikoli jako hotové lidské přijetí.
