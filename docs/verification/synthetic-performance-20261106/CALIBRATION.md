# Kalibrace měřidla — historický průběh této relace

## Průběžná kalibrace měřicí metody

Vlastní MariaDB 10.4.32 běží pouze na 127.0.0.1:33446, původní datadir ani
konfigurace aplikace se nepoužily. První krátký start procesu ukončil runner;
server následně běží v udržované exec session, nikoli ve Windows službě.
Schéma pro kontrolu fixture i každý měřicí běh mají vlastní náhodný název.

Měřidlo nejprve správně odmítlo neshodný HTML otisk mezi procesy: business
payload byl shodný, lišila se náhodná CSP nonce i v data-app-csp-handler.
Normalizace se nyní týká pouze této procesní nonce, nikoli výsledkových dat.
Guard pro čtecí hranici poté zachytil require navigace a vnořený legitimní
training_day_view; jejich přesný rozsah je ověřený podle skutečné stránky.
Kalibrace není chyba aplikace ani platný finální výkonový výsledek.

První průchod už doložil plánovač 112/203 SQL pro trenéra/vedoucího a růst
čtecí docházky 54 → 244 SQL při 10 → 200 účastnících. Tyto údaje zatím slouží
k přípravě finálního opakovaného běhu a nelze je vydávat za produkční SLA.
Všechny čtyři negativní autorizační případy správně odmítnuté; další výsledky
budou doplněné po dokončení celého profilu a kontrole metody.


Při kalibraci zdravotní fixture byla opravena vlastní chyba měřidla: ID politiky
se bere z návratové hodnoty doménové služby, nikoli z PDO lastInsertId po jejím
commitu. FK správně odmítl chybnou vazbu. Úklid nyní nejprve vrací rozpracovanou
syntetickou transakci, aby DROP nečekal na vlastní metadata lock. Čekající
DROP byl ukončen jen na vlastním localhost serveru a vlastní schéma uklizené.
Aplikační služba ani produkce se neopravovaly; to nebyla chyba produkčních dat.
Finální metoda měří pouze přípravu dat a render, kontrolní SHA/JSON mimo čas
/i peak měření. Klinické podklady jsou pouze syntetická metadata, žádné soubory
nebo skutečné údaje. První kalibrační hodnoty se finálním během přepíšou.
