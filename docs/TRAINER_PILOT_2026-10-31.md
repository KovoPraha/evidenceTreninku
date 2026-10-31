# Oddělený trenérský pilot — 31. 10. 2026

Vlastník potvrdil doručení testovací zprávy EP-TRENER-20261031 a zvolil
samostatný účet s testovací skupinou. Nejde o povolení přístupu k reálným
sportovcům. Konkrétní e-mail nového účtu se zadává až při chráněné operaci,
neukládá se do zdrojů ani důkazů v Gitu.

## Postup

1. Pouze čtením ověřit jednoznačný aktivní účet vlastníka a jeho oprávnění.
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

1. Na trenérském přihlášení zvolit „Zapomenuté heslo“, zadat výhradně e-mail
   samostatného testovacího účtu a otevřít doručený odkaz. Nastavit vlastní heslo;
   heslo ani odkaz neposílat vývojáři. Odkaz je jednorázový a platí 60 minut.
2. Přihlásit se jménem `TEST - Trenérský pilot 20261031`. Pracovní pozice má
   být pouze Trenér. Ověřit testovací soupisku a osoby TEST Alfa a TEST Beta.
3. Vyzkoušet dostupné trenérské obrazovky a práci se syntetickými daty.
   Zaznamenat očekávaný a skutečný výsledek, adresu stránky a čas chyby.
4. Ověřit, že se nezobrazují skuteční sportovci ani syntetická osoba
   TEST Mimo rozsah. Administrace a finanční agendy musejí zůstat nepřístupné.
5. Novou obnovou hesla ověřit nové přihlášení a odvolání starší relace.
   Nepředávat účet dalším trenérům; další účastníci potřebují vlastní identitu.

Tyto kroky jsou plán lidského UAT, nikoli tvrzení, že již proběhly.
Před založením účtu nelze tento postup provést. Přesný aktuální stav a doklady
nasazení jsou v [CURRENT_STATE](CURRENT_STATE.md).
