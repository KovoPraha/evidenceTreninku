# 11 – Backlog hodnoty pro členy

Stav: vytříděný produktový backlog navazující na M2

Aktualizováno: 4. 8. 2026

Zdrojové podklady: `docs/AUDIT-PRILEZITOSTI-A-NAPADY.md` a
`docs/AUDIT-PRILEZITOSTI-HLOUBKOVE.md`. Auditní odhady nejsou automaticky
schválené zadání. Tento dokument zaznamenává, co z nich dává smysl pro současnou
architekturu, v jakém pořadí a s jakou bránou.

## Přijaté principy

1. Nejdřív vracet uživateli hodnotu z dat, která aplikace bezpečně sbírá už dnes.
2. Veřejné výstupy smějí obsahovat jen údaje, které jsou už výslovně veřejné.
3. Personalizované přehledy musí být odvozené z aktivní session nebo
   revokovatelného náhodného tokenu; ID osoby nesmí být autorizační prostředek.
4. Funkce nad nezletilými nesmějí vytvářet zdravotní diagnózy, veřejné žebříčky
   nebo automatická negativní rozhodnutí bez samostatného rozhodnutí a souhlasu.
5. Peněžní a kreditní funkce zůstávají za finančními a účetními branami.

## Zařazené řezy

| ID | Funkce | Zařazení | Brána / poznámka |
|---|---|---|---|
| V1 | veřejný ICS kalendář | M2.7, první řez implementován | pouze zveřejněné plánované tréninky, otevřené klubové akce a aktivní veřejné hodiny velodromu; bez osob, docházky, rezervací a interních popisů |
| V2 | osobní rodinný ICS kalendář | M2.7, technicky implementován | revokovatelný 256bitový náhodný token uložený jen jako hash, oddělený kalendář pro každý účet, rotace, audit, `no-store` a zákaz indexace |
| V3 | připomínky splatných předpisů | po ověření členských předpisů v M2 | opt-in, idempotentní fronta, omezení četnosti a bezpečný odkaz bez údajů v URL |
| V4 | roční přehled zaplacených klubových služeb | po finanční akceptaci | nejdřív přesný read-only přehled; označení „daňové potvrzení“ až po právním a účetním ověření textu a náležitostí |
| V5 | osobní progres a osobní rekordy | budoucí datový milník | nejprve normalizovat volný čas měření do číselné hodnoty, opravit kvalitu dat a definovat soukromí nezletilých |
| V6 | rodičovský týdenní souhrn | po V3 | opt-in, pouze fakta, která rodič už smí číst; možnost okamžitého odhlášení |
| V7 | retenční přehled pro trenéra | pozdější provozní pilot | pouze podpůrný signál s lidským posouzením; žádné automatické vyřazování nebo profilování dítěte |

## Odložené nebo blokované návrhy

- Reward/cash wallet je blokovaný rozhodnutím D-009 a účetním/právním návrhem.
- Automatické Fio, Stripe, Packeta a další externí integrace zůstávají za
  stávajícími finančními a provozními branami.
- TrainingPeaks, Strava a `.FIT` vyžadují samostatný datový kontrakt, souhlas,
  retenční pravidla a určení vlastníka dat.
- Predikce zranění, AI doporučení pro nezletilé a veřejné žebříčky nejsou součástí
  M2. Případný návrh musí projít privacy a safeguarding revizí.
- Auditní myšlenka přímého napojení na Velocotu se nepřebírá. Platí D-014:
  projekty jsou oddělené; případná budoucí výměna výsledků musí mít samostatný
  kontrakt a výslovné rozhodnutí vlastníka.
- Počasí, carpooling, sponzorské přehledy a multi-klub režim zůstávají nápady bez
  přiděleného milníku.

## M2.7 – kalendář a vracení hodnoty

První řez je anonymní veřejný ICS feed. Stabilní UID umožní kalendářovým
aplikacím aktualizovat položky bez duplicit, časy se exportují v UTC z pásma
Europe/Prague a výstup používá standardní CRLF, escapování a skládání řádků.

Akceptace prvního řezu:

- veřejný trénink, otevřený termín klubové akce a veřejná hodina velodromu jsou
  v jednom feedu,
- soukromý nebo zrušený záznam se neexportuje,
- feed neobsahuje sportovce, docházku, rezervující osobu, interní popis ani
  poznámku,
- odkaz je dostupný bez registrace z veřejného rozvrhu, kroužků i velodromu,
- neplatný nebo nepřiměřeně dlouhý rozsah skončí bezpečnou chybou bez SQL detailu.

Personalizovaný kalendář V2 je implementován v `004e4a6` bez `sportovec_id` v
URL. Oprávnění vzniká jen z aktivního tokenu účtu a při každém načtení se
znovu vyhodnotí schválené vazby na osoby. Odvolaný profil okamžitě zmizí, token
lze otočit nebo zrušit a starý odkaz poté vrací 404. Feed zahrnuje cílené
tréninky aktivních soupisek, přihlášené termíny akcí, rezervace a splatnosti
členských předpisů všech aktuálně oprávněných profilů. Automatický test
potvrzuje izolaci dvou rodin; otevřená je už jen zkouška odběru a aktualizace v
reálné kalendářové aplikaci.
