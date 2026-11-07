# Předání P11-SCALE — větší zdravotní přehled a plánovač

7. 11. 2026. ROZPRACOVÁNO, sběr výsledků běží; nejde o dokončený benchmark.
[Plán a přijaté hranice](MEDICAL_PLANNER_SCALE_2026-11-07.md).

Větev `codex/medical-planner-scale-20261107`, vlastní worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-medical-planner-scale-20261107`.
Zmrazená aplikace `69f49033`, původní WIP se nepřepíná ani nestashuje.
**Produkci neměnit, neprovádět deploy, aktivace ani transport.**

Nový vlastní MariaDB datadir `C:/xampp/tmp/kis-medical-planner-scale-20261107-ee3c4eee/data`,
port 33479, HTTP 18115. Server DB PID 7380; aktuální vlastní web PID/cesty
ověřit v ignorovaném `output/medical-planner-scale-20261107/runtime.json`.
Před zastavením ověřit konkrétní PID a marker datadir/session, nikdy cizí službu.

Hotové: standardní login 12 rolí, potom web DB účet SELECT only, základních
18 kontrol úplné doménové shody a konkrétních DOM ID PASS; základních
18 READ ONLY projekcí PASS. Aplikační zdroje ověřené proti zmrazenému main.
Časování 72 kladných a 28 záporných případů se teprve dokončuje.

Další krok: dokončit sedm sad, oddělenou slow/general-log diagnostiku a paritu;
zapsat skutečné rows examined, vyhodnotit omezení a provést úklid vlastního prostředí.
Nesmí se přepsat hotové/částečné časové soubory ani předstírat dokončené případy.

Jiný aktivní chat optimalizuje plánovač a má jiné oprávnění k nasazení.
Jeho WIP a release nepřebírat do této zmrazené studie; při integraci rozlišit
zde měřený kód od novějšího main. Registry P05/P07, export P06 a heslo/T07 platí dál.
