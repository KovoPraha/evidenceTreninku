# MAKEUP-AUTO-01 — blokátor funkčního testu (13. 11. 2026)

Základ: `origin/main` `bc8da209` po `git fetch` 13. 11.; vydání kódu
automatických náhrad dokládá [předchozí protokol](../makeup-auto-20261112/RESULTS.md).
Dokumentační změna vznikla na větvi `codex/makeup-attendance-blocker-20261113`
v odděleném worktree `C:\xampp\evidencePavel-makeup-attendance-blocker-20261113`.
Tento záznam zachycuje hlášení vlastníka a stav ověření, nikoli nově provedený
produkční test. Osobní údaje, přístupy ani skutečná docházka se neukládaly.

| Krok / důkaz | Stav | Poznámka |
| --- | --- | --- |
| Rodičovská omluvenka se zobrazuje jako čekající na potvrzení | HLÁŠENO VLASTNÍKEM | V tomto průchodu znovu nezávisle netestováno. |
| Dostupný trenérský zápis skutečné docházky konkrétního tréninku | BLOKÁTOR HLÁŠENÝ VLASTNÍKEM | Kód obsahuje starší cestu `formular.php?plan_id=…`; její dostupnost a dokončení v požadovaném přihlášeném postupu nejsou prokázané. |
| Nasazení dokončené trenérské evidence dle E4-06 | NOT RUN | Samostatné zadání a jeho akceptace. |
| Uzavřená absence propojená s omluvenkou a právě jeden nárok | NOT RUN | Vyžaduje skutečný zápis docházky po proběhnutí lekce. |
| Nárok zobrazený rodiči a rezervace vhodného náhradního tréninku | NOT RUN | Vyžaduje předchozí krok a vhodné testovací účty/termín. |
| Úplné funkční ověření automatických náhrad | BLOKOVÁNO | Technické testy, samotná omluvenka a nasazení kódu tento krok nenahrazují. |

Po dokončení a nasazení trenérské evidence zopakovat přihlášený scénář na
oddělených účtech: rodič omluví zaplacenou lekci včas → nárok čeká → trenér po
lekci zapíše skutečnou absenci a uzavře docházku → vznikne právě jeden nárok
→ rodič jej vidí → rezervuje kompatibilní volný termín. Doložit nastavení,
role, časovou posloupnost, počet nároků, stav rezervace a úklid testovacích dat.
Zvlášť ověřit přítomné dítě, pozdní omluvu a nepřípustný nebo plný cíl.

[Tematický plán a blokátor](../../TRAINING-RSVP-AND-MAKEUPS-2026-10-14.md#blokující-závislost-úplného-funkčního-ověření-13-11-2026),
[registr práce](../../WORK_PLAN_CURRENT.md#makeup-auto-01--automatické-náhradní-lekce-12-11-2026).

## Samostatný syntetický průchod 13. 11. 2026

**Výsledek: místní přihlášený end-to-end scénář PASS; produkční UAT otevřené.**
Vlastník požádal o vlastní testovací data, celý průchod a úklid. Větev
`codex/makeup-attendance-uat-20261113` vyšla z `origin/main` `784cdc7b`
v odděleném worktree; cizí WIP se neměnilo. Test běžel přes skutečné HTTP
a prohlížeč proti vlastní MariaDB 10.4.32 a localhost PHP, bez produkčních dat,
pošty, plateb a externích transportů. Syntetické údaje a hesla nejsou v Git.
Automatický režim byl zapnut pouze v izolované DB, s lhůtou omluvy 12 hodin,
platností nároku 365 dní a kapacitou cílového termínu 5.

| Krok | Výsledek | Pozorování |
| --- | --- | --- |
| Rodič omluví dítě ze zaplaceného zdrojového termínu | PASS | Rodičovské UI ukázalo „Omluvené, čekají na potvrzení 1“, dostupné nároky 0; RSVP `not_going`, bez nároku. |
| Trenérský účet v pozici `coach` vidí lekci a zapíše docházku | PASS | Plánovač → „Lekce, omluvy a docházka“ → „Potvrdit nebo opravit skutečnou docházku“. Uzavřena verze 1; přítomné bylo druhé dítě, omluvené dítě ne. V okamžiku zápisu měl účet legacy roli `admin`. |
| Vyhodnocení omluvy po uzavření | PASS | Právě jeden nárok `available`, žádná rezervace; rodičovské UI ukázalo dostupný nárok a kompatibilní budoucí termín. |
| Rodič rezervuje náhradní termín | PASS | UI ukázalo „Náhradní trénink byl rezervován“, dostupné 0, rezervované 1. DB: jeden nárok `reserved`, jedna rezervace `booked`, cílový slot `origin=automatic`, kapacita 5. Před POST neexistoval ruční cílový slot. |
| Běžný trenér po změně legacy role `admin` → `trener` | PASS pro čtení | Nové přihlášení se stejnou aktivní pracovní pozicí `coach`: dostupná nabídka „Zadat trénink“ a stránka „Docházka a uzavření“ s verzí 1. Příprava cílového termínu ukázala rezervované dítě mezi náhradníky. Nový POST docházky v této roli nebyl proveden. |
| Produkční nastavení, skutečná role a lidská přejímka | NOT RUN | Místní syntetický PASS neprokazuje dostupnost stejné cesty konkrétnímu trenérovi v produkci ani oprávnění zapnout automatiku pro skutečné nabídky. |

Zdrojový termín byl pro test vytvořen na 14. 11.; po rodičovské omluvě byly
**jen v izolované DB** datum termínu/tréninku přesunuty na 12. 11. a čas
omluvy na 11. 11. 08:00, aby šlo uzavřít již proběhlou lekci bez čekání.
Cílový termín zůstal 16. 11. Tato řízená změna času je mez syntetického testu.
První pokus o uložení docházky vrátil 422, protože minimální fixture
neobsahovala snapshot očekávaných účastníků; druhé dítě proto formulář
považoval za hosta a vyžádal zdůvodnění. Po doplnění zdůvodnění byla docházka
úspěšně uzavřena. Tento artefakt fixture není doloženou chybou aplikace.

Úklid: tři vlastní browser relace zavřené, localhost PHP a vlastní MariaDB
zastavené, porty 18186/33486 bez listeneru. SQL `DROP DATABASE
evidence_makeup_e2e` ověřil 0 zbývajících schémat. V odděleném worktree
odstraněny ignorované `config.php`, `vendor/` a `.playwright-cli/`.
**Souborový úklid není dokončen:** automatická kontrola odmítla oba pokusy
o `Remove-Item` i po ověření přesného cíle. Zůstává vlastní dočasný adresář
`C:\xampp\tmp\kis-makeup-e2e-20261113-01a1d543` s datadirem již smazaného
schématu a lokální syntetickou fixture; neobsahuje reálné účty ani hesla.
Adresář je možné odstranit ručně po potvrzení, že patří tomuto testu.

Hlášený produkční blokátor tímto místním průchodem není nezávisle vyvrácen.
Navazující práce: na skutečném nasazení ověřit přihlášený trenérský postup
a nastavení pouze čtením, potom schválenou produkční přejímku podle pravidel
klubu. Automatické náhrady zatím neoznačovat jako plně produkčně otestované.
