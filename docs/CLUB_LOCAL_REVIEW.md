# Jak později zkontrolovat místní systém

Tento návod se týká výhradně izolované syntetické kopie. Zde provedená operace
nemění skutečný oddíl, produkční web, banku, e-mail ani Shoptet.

## Přístup

1. Otevřete `http://127.0.0.1:18086/login.php` na tomto počítači.
2. Přístupové údaje dvou kontrolních účtů jsou pouze v souboru
   `C:\Users\Marek\AppData\Local\EvidencePavelB1\finance-b1-20261022\local-review-access.json`.
   Hesla nejsou uložená v Gitu ani v této dokumentaci.
3. V horní navigaci přepínejte pracovní pozici. Kontrolní účty mají pro tuto
   místní prohlídku přístup ke všem pozicím. Testy s omezenými účty samostatně
   ověřují, že běžný trenér nemůže vykonávat finanční nebo registrační úkony.
4. Druhý účet použijte v jiném profilu prohlížeče nebo anonymním okně, pokud
   chcete vyzkoušet nezávislé schválení. Vlastní nárok se automaticky neschválí.

Server je vázaný na tento počítač. Jestli po restartu neběží, spouštěcí skript
je `bin/setup-finance-b1-sandbox.ps1` v pracovní kopii
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`. Skript kontroluje vlastníka
prostředí a aplikuje místní migrace; není to postup nasazení do produkce.
`bin/prepare-club-local-review.php` vytvoří kontrolní účty pouze v této
ověřené syntetické instanci. Při opakování zachová existující přístup.

Spuštění v PowerShellu z uvedené pracovní kopie:

```powershell
& .\bin\setup-finance-b1-sandbox.ps1
& C:\xampp\php\php.exe -c "$env:LOCALAPPDATA\EvidencePavelB1\finance-b1-20261022\php-web.ini" .\bin\probe-finance-b1-sandbox.php
```

Kontrola je pouze čtecí a má vrátit `status: PASS`. Neúspěch neobcházejte
jiným konfiguračním souborem. Při hlášení probíhající obnovy MariaDB vyčkejte
a setup zopakujte; nemažte databázové soubory. Nový HTTP router chrání interní
adresáře i při použití vestavěného PHP serveru. Starý server bez routeru setup
odmítne převzít. Ukončení této místní kopie zajišťuje
`bin/stop-finance-b1-sandbox.ps1`; data a kontrolní účty ponechá na disku.
Podrobnosti a meze externích služeb obsahuje
[provozní inventura](CLUB_OPERATIONS_SOURCE_INVENTORY.md).

## Doporučené pořadí prohlídky

| Pozice | Co projít | Co kontrolovat |
|---|---|---|
| Členové a KIS | Osoby, ověření identity, papírové přihlášky, osobní přijetí, prohlídky | Člověk, účet, rodič, plátce, členství a přijetí do programu mají odlišný význam. |
| Trenér / vedoucí sportu | Rozvrh, skutečná docházka, sportovní činnost, výkaz | Plán ani přihlášení nejsou skutečná účast. Oprava vytváří novou verzi. |
| Katalog | Skladové doklady a inventura | Fyzické kusy, rezervace a blokované kusy jsou oddělené. |
| Objednávky | Prodej na místě, položkové výdeje, vratky a výměny | Objednávka, skutečná platba a skutečný výdej jsou tři samostatné kroky. |
| Finance | Peněžní evidence, účetní doklady a opravy | Jeden skutečný pohyb; důvod a potvrzení změny; uzavřené období se nepřepisuje. |
| Finance | Výdaje a zálohy | Původní doklad, uznaná část, věřitel, schválení, skutečná úhrada; záloha se neuzavře s rozdílem. |
| Trenér → vedoucí sportu → finance | Výkazy a odměny | Předložení, nezávislé schválení a přiřazení výplaty; starý výkaz a částka zůstávají dohledatelné. |
| Finance | Účetní podklady | XLSX, PDF, originály, salda a výjimky; předání a převzetí jsou oddělené od stažení. |
| Systém | Příprava přechodu | Seznam zdrojů, kdo kam zapisuje, soukromé přílohy, kontrolní snímek a rozdíly po nových operacích. |

Testovací záznamy jsou záměrně označené `LOCALHOST`, `NEPLATIT` nebo `TEST`.
Některé představují zamítnutý, otevřený či neúplný případ: slouží k ověření
chybových situací. Nejde o skutečné dluhy nebo nesrovnalosti oddílu.

## Co zbývá posoudit lidmi

[Registr odložených rozhodnutí](CLUB_COMPLETION_DECISIONS.md) soustřeďuje
metodiku účetní, konkrétní sazby a schvalovatele, nejasné historické identity
a salda, fyzický sklad, úplný seznam nahrazovaných webů a převzetí provozu.
Technická připravenost neuděluje oprávnění k produkčnímu přepnutí. Migrační
zapisovač má výslovně povolené pouze syntetické prostředí; návratový náhled
neobnovuje databázi ani nezastavuje služby.
