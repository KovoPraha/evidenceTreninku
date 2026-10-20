# Převod soupisek starého KIS do školního roku 2026/27

Administrace `kis_current_rosters_admin.php` zpracuje vazby vytvořené z exportu
starého KIS 2025/26. Automaticky otevře aktivní členství v roce 2026/27 pouze
tehdy, když je historická vazba potvrzena, původní skupina má jediný doslovný
protějšek, aktuální skupina je aktivní, její nabídka má jednoznačný rozsah
ročníků a ročník sportovce do něj patří. Datum začátku členství je den převodu;
export neprokazuje účast od září. Stávající aktivní členství se nepřepisuje.

Ostatní záznamy se uloží do `kis_current_roster_claims` ke kontrole. Patří sem
nepotvrzené historické vazby, věkový rozpor, chybějící protějšek či nabídka,
archivovaná osoba a rozdělení Točňáků 1× týdně na úterý a čtvrtek. Chybějící
oddílové a závodní soupisky se při zápisu založí jako skupiny 2026/27 bez
prodejních nabídek; nezdědí staré ceny. Správce může
po ověření vybrat libovolnou aktivní soupisku 2026/27, zapsat důvod a potvrdit
přiřazení, nebo záznam ponechat bez vazby. Historicky spornou vazbu musí nejprve
rozhodnout na stránce starého KIS.

Kontrolní náhled má otisk. Zápis používá databázovou transakci a aplikační
zámek; opakovaný běh se stejným otiskem je idempotentní. Nová členství se
auditují v `club_roster_events`, úplný stav lze stáhnout jako JSON. Aktivní
členství ovlivňuje tréninkové soupisky a členské výhody; převod netvoří objednávky,
platby, přihlášky ani zprávy rodičům.

Offline srovnání produkčního historického protokolu a katalogu z 20. 10. 2026
naznačuje 137 přímých vazeb do existujících skupin a dalších až 298 do osmi
nově založených oddílových a závodních skupin. Rozhodující je
náhled nad živou databází těsně před zápisem.
