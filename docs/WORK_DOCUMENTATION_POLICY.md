# Průběžná souborová dokumentace práce

Trvalé zadání vlastníka ze 6. 11. 2026: změny zapisovat vždy přímo do souborů; vše,
na čem se pracuje a co se plánuje, musí být dohledatelné v repozitáři s odkazy
na související dokumenty. Platí pro kód, provozní změny, přejímku i budoucí etapy.
Pokyn je dostupný také v kořenovém [AGENTS.md](../AGENTS.md) a [CLAUDE.md](../CLAUDE.md).

## Kam zapisovat

| Obsah | Kanonické místo |
| --- | --- |
| Současné nasazení, aktivace, ověřený provozní stav a jeho meze | [CURRENT_STATE.md](CURRENT_STATE.md), stručně s odkazy na důkazy |
| Převzetí další session a rozcestník k aktuálním plánům | [HANDOFF_CURRENT.md](HANDOFF_CURRENT.md) |
| Společná rozpracovanost a další práce celého projektu | [WORK_PLAN_CURRENT.md](WORK_PLAN_CURRENT.md); konkrétní podrobnosti odkazovat do tematických plánů |
| Rozsah konkrétní práce, etapy, rozpracované i budoucí úkoly | Příslušný plán v `docs/`; při nové větší práci vytvořit datovaný plán |
| Konkrétní předání dokončené nebo přerušené session | Datovaný handoff v `docs/`, propojený s hlavním handoffem a plánem |
| Výsledky, PASS/FAIL/NOT RUN, snímky a bezpečné strojové souhrny | Tematický protokol v `docs/verification/` |
| Orientace mezi oblastmi | [README dokumentace](README.md) a odkazy mezi příslušnými plány |
| Původní stav a starší rozhodnutí | Datované protokoly a [provozní archiv](CHANGELOG-PROVOZ.md); historii nepřepisovat na nový výsledek |

## Pracovní postup

1. Při převzetí načíst aktuální main, stav pracovní kopie, hlavní handoff, společný průběžný plán a příslušný tematický plán.
   Nový požadavek nebo změnu rozsahu zaznamenat do souboru už při zahájení práce.
2. Průběžně označovat dokončené, rozpracované, plánované a neověřené kroky.
   U úkolu uvést výsledek, podmínku dokončení, závislosti či chybějící podklady a odkaz
   na příslušnou implementaci, postup nebo důkaz. Pozdější etapy mají zůstat v plánu i po dokončení první části.
3. Po skutečném ověření zapsat prostředí, rozsah a jeho meze. Implementace, úspěšný test,
   nasazení, aktivace a lidská přejímka jsou samostatné skutečnosti. Neprovedený krok uvést jako NOT RUN.
4. Před předáním, ukončením nebo kompaktací session obnovit souborový handoff:
   zadání a oprávnění, větev/pracovní kopii, hotové výsledky, ověření, otevřené kroky,
   další konkrétní postup a související odkazy. Chat slouží jako stručný doprovod.
5. Ověřit místní odkazy a dokumentační kontrakt, zahrnout dokumentaci do vlastní změny v Gitu
   a uvést související PR. Zachovat souběžné změny ostatních prací. Zápis dokumentace nevyžaduje nové potvrzení.

U drobné práce stačí rozšířit existující tematický plán/protokol a handoff;
nevytvářet souběžné zdroje pravdy. Citlivé provozní podklady zůstávají v řízeném soukromém
úložišti, v repozitáři je pouze bezpečný souhrn a postup jejich použití.

## Aktuální příklad: BAR

[Plán začlenění](BAR_INTEGRATION_PLAN_2026-11-05.md) →
[provozní přejímka](BAR_OPERATIONAL_ACCEPTANCE_2026-11-06.md) →
[publikace nabídky](BAR_MENU_PUBLICATION_2026-11-06.md) →
[session handoff a otevřené etapy](SESSION_HANDOFF_BAR_2026-11-06.md).
