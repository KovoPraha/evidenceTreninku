# Oddělený trenérský pilot — 31. 10. 2026

Vlastník potvrdil doručení testovací zprávy EP-TRENER-20261031 a zvolil
samostatný účet s testovací skupinou. Nejde o povolení přístupu k reálným
sportovcům. Konkrétní e-mail nového účtu se zadává až při chráněné operaci,
neukládá se do zdrojů ani důkazů v Gitu.

## Postup

1. Pouze čtením ověřit jednoznačný aktivní účet vlastníka a jeho oprávnění.
   Pokud vlastník používá pouze veřejný účet bez pracovní vazby, použít
   [výslovně schválenou provozní variantu](TRAINER_PILOT_OPERATIONAL_2026-11-01.md)
   s neaktivní technickou auditní identitou; nepovyšovat veřejný účet.
2. Připravit úzce omezený CLI provisioner: nový účet pouze s pozicí `coach`,
   vlastní testovací sezona/soupiska a syntetičtí sportovci. Vše v jediné
   transakci s auditem a identifikačním záznamem, bez převzetí cizích dat.
3. Udělit výhradně testovací soupisku na 14 dní. Vytvořit také jednoho
   syntetického sportovce mimo rozsah pro negativní test. Žádná finanční data,
   veřejná nabídka, rodinné vazby ani automatické zprávy.
4. Ověřit idempotenci, kolize účtů, rollback při chybě a přesné omezení rozsahu.
   Změny sloučit po CI, nasadit chráněným workflow a vytvořit čerstvou zálohu
   bezprostředně před založením pilotních dat.
5. Nastavit náhodné neznámé heslo; vlastník použije samoobslužnou obnovu
   na samostatném e-mailu. Heslo se nepřenáší přes chat, Git ani workflow log.
6. Produkčně ověřit novou identitu a rozsah pouze čtením, předat přihlášení
   a scénáře. Úplné interaktivní UAT provede vlastník pod testovacím účtem.

## Podmínky

- Běžný účet vlastníka se při této operaci nemění.
- E-mail testovacího účtu musí být jednoznačný i vůči veřejným účtům.
- Existující data se nepřepisují; opakované spuštění ověří vlastní záznam,
  neobnovuje heslo, neprodlužuje grant a neopravuje zásahy člověka.
- Konec čtrnáctidenního grantu omezí přístup ke sportovcům. Není automatickým
  smazáním účtu ani dat; případné ukončení se provede samostatně.
- EvidencePavel zůstává oddělená od Velocoty. Integrační přepínače se nemění.

## Předání testujícímu po založení účtu

Scénáře T01–T08, očekávané výsledky a formulář chyb jsou v
[podrobném postupu](TRAINER_UAT_POSTUP_2026-11-01.md).
Pro přeposlání použít [krátký checklist](TRAINER_UAT_CHECKLIST_2026-11-01.md).
Jiné vlákno dostane [samostatné browser zadání](TRAINER_UAT_BROWSER_2026-11-01.md)
se stejnými scénáři a povinným rozlišením automatických/asistovaných výsledků.

Testovací dokumenty jsou připravené, samotný browser běh jimi není proveden.
Každý trenér potřebuje vlastní identitu a rozsah; pilotní účet se nesdílí.
Přesný aktuální stav a doklady nasazení jsou v [CURRENT_STATE](CURRENT_STATE.md).
