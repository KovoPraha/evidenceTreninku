# Fio: skutečný plánovač a čtecí posouzení pohybů

Vlastník 5. 11. výslovně zadal autonomní ověření automatického Fio a všech
45 čekajících pohybů, včetně případných oprav. Heslo a T07 vynechat.
Výchozí main je 65969bb8, živá aplikace 436b1f85; produkční aktivace zůstává platná.

1. Zkontrolovat skutečné schedule události a aktuální frontu, nikoli jen flags.
2. GitHub plán zatím vykazuje několikahodinové rozestupy a žádný doložený běh
   po aktivaci. Využít již existující CRON.1minuta v prostoru KOVO; nevytvářet
   ani neměnit hostingový plán nebo jiné cron soubory. Samostatný Fio skript
   poběží abecedně za existujícím trenérským workerem a interně nejvýše jednou
   za 600 sekund, včetně chyby/nejistého transportu.
3. Použít stávající kanonické fioSynchronize a společný databázový importní
   zámek. Stav/tick/import oddělit; status nesmí vytvářet heartbeat. Po skutečném
   ověření hostingu vypnout redundantní GitHub schedule repo proměnnou.
4. Pro všech 45 pohybů spustit READ ONLY transakci a znovu vyhodnotit kanonickou
   shodu, počet plateb se stejným VS, pouze částkou podobné čekající platby a
   časovou hranici. Nikdy nepárovat jen podle částky nebo podobnosti jména.
   Do reportu pouze technická ID, klasifikace a počty, nikoli bankovní údaje.
5. Otestovat interval/souběh/výpadek, cizí soubor, zdroj a čerstvost heartbeat,
   neměnnost fronty/plateb a neexportování osobních polí; CI a chráněné main
   operace. Samostatný provozní nástroj nemění webový runtime ani schéma.
6. Doložit skutečné hostingové časy, výsledek každého posouzeného pohybu a
   otevřené meze. Žádné platební příkazy, poplatky, vratky nebo vymyšlené úhrady.

Adresářové spouštění, pořadí skriptů a práva 0700 potvrzuje
[Český hosting](https://www.cesky-hosting.cz/napoveda/skripty-na-serveru/cron/).
Existující minutový prostor a předchozí problém GitHub plánovače dokládá
[produkční protokol trenérského workeru](../trainer-recovery-20261031/scheduler/RESULTS.md).
