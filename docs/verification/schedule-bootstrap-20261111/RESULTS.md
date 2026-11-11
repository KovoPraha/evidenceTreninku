# Plánovač a duplicitní Bootstrap CSS

Výchozí main 90745940, před implementací. Přenos, parita, CI a deploy čekají.

[PR #451](https://github.com/KovoPraha/evidenceTreninku/pull/451), první běh
[42975738756](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42975738756).
Celé HTTP na obou DB prošlo před browser krokem. MariaDB 10.3 potvrdila 350
shodných checksumů. Běh 11.4 skončil před Chrome na předpokladu o příznaku
Secure při předání syntetické session. Fixture nyní přebírá skutečný příznak.
První PHPUnit selhal na explicitním seznamu stránek se sdíleným CSS.
Seznam obsahuje i plánovač a cílený test znovu prošel. Finální CI se opakuje.

Druhý běh 42976040733 znovu potvrdil HTTP, ale Chrome před navigací odmítl koncový středník v předávané funkci. Funkce nyní prochází i kontrolou po zabalení do výrazu. Lokální sada 23 testů a 138 kontrol PASS.

Párový běh 42976287578 na 86a8107e potvrdil 21 HTTP scénářů a 350
checksumů pro každou MariaDB. Chrome PASS, 88 měření, dvě dvojice pixelů,
cold CSS 64 454 → 31 248 B. Širší běh 42976287574 odhalil historický
syntetický profil R05. Ten vypouští celou hlavičku a porovnává proti
zmrazenému HTML se značkou CDN. Při výpočtu kompatibilního hashe se nově
vloží přesně původních 215 B na jediné místo za titulkem, vedle původní
normalizace tří šířek. Surový hash, doménová data a SQL rozpočty zůstávají
v porovnání. Frozen benchmark spouští změny svého měřiče, běžná matice
zůstává pro budoucí úpravy plánovače. Release čeká na nový commit a jeho CI.

Finální c1a1491e prošel 13 CI. #451 sloučen jako d8ae4f85 s přesně
shodným Git tree. Nasazení 42979420239 spuštěno jednou s UAT a importem false.
Dokončený produkční doklad je uveden níže.

## Výsledek a vydání 11. 11. 2026

P11-ASSET8 nasazeno jako d8ae4f85, [PR #451](https://github.com/KovoPraha/evidenceTreninku/pull/451).
Testovaný c1a1491e, stejný Git tree 8be11a262a3b1503ee585bce45c5759701790f9a, 13 CI PASS.
Výchozí 90745940. Jediná runtime změna je odstranění známé značky CDN z
planovac.php. [Párový běh 42977502662](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42977502662)
ověřil 21 scénářů a 394 HTTP vzorků na každé z obou MariaDB.
Celé HTML po odstranění jedné značky a normalizaci CSP nonce, hlavičky,
přístupové stavy a 350 kontrolních součtů na databázi se shodují.
[Měření 10.3](mariadb-10.3/http.json.gz), [měření 11.4](mariadb-11.4/http.json.gz).

Chrome provedl 88 měřených načtení po 24 zahřívacích. Desktop ve světlém
a 390px mobilní rozlišení v tmavém režimu mají shodné pixely před a po.
Kopírovací dialog se v obou verzích otevřel a zavřel. Pro obě studená měření
klesla komprimovaná těla Bootstrap CSS z 64 454 na 31 248 B, tedy o 33 206 B
(51,52 %), a dekódovaná z 465 606 na 232 803 B. Teplý přenos CSS byl 0 B
v obou verzích. Dekódované HTML se zkrátilo o 215 B. Při prvním načtení
byl medián prvního vykreslení desktopu 104 → 100 ms a mobilu 96 → 88 ms.
Medián připravenosti desktopu 119,8 → 122,5 ms a mobilu 117,2 → 112,7 ms prošel časovou kontrolou.
Jde o výsledky na syntetických datech a konkrétním běhu Chromu.
[Úplné mediány a velikosti](mariadb-11.4/browser-summary.json).
[Desktop před](mariadb-11.4/light-cold-before.png), [po](mariadb-11.4/light-cold-after.png).
[Mobil před](mariadb-11.4/dark-cold-before.png), [po](mariadb-11.4/dark-cold-after.png).

[Deploy 42979420239](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42979420239) SUCCESS, 2456 testů a 56288 assertions.
Záloha 389 tabulek a 2 triggerů ověřena.
Aktivace, konfigurace, migrace, variabilní symboly, index a úklid PASS.
Přímý runner HTTP 200, předepsaný smoke HTTP 200. UAT a import false.
Čtyři čtecí produkční dvojice mají shodný stav, vybrané hlavičky a oba
Bootstrap payloady. Přihlášená produkční přejímka a fyzický mobil NOT RUN.
[Release](release.json), [produkce před](production-before.json), [po](production-after.json).
