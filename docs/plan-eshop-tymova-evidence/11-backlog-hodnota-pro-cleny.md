# 11 – Backlog hodnoty pro členy

Stav: vytříděný produktový backlog navazující na M2

Aktualizováno: 5. 8. 2026

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
| V3 | připomínky splatných předpisů | M2.7, technicky implementováno | opt-in 3/7/14 dní, idempotentní auditovaná fronta, nejvýše jedna zpráva za 20 hodin na účet, admin náhled/retry a localhost testovací outbox; produkční transport a CRON čekají na akceptaci |
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

V3 je implementováno v `29e3d5d`. Nastavení je v rodinném sportovním přehledu
a bez výslovného opt-in nevznikne žádná zpráva. Generátor používá pouze
aktuální schválené vazby účtu na osobu, aktivní ověřený e-mail a čekající
předpis ve zvoleném okně. Unikátní klíč brání duplicitě; vypnutí zruší
neodeslané zprávy. Worker má atomické převzetí, ochranu proti souběhu,
dvacetihodinový limit, pět pokusů a audit stavu. Text obsahuje jméno, částku a
splatnost pouze v těle e-mailu; URL vede na přihlášený přehled bez tokenu, ID osoby
nebo ID předpisu. Automatické testy používají falešný transport. Před produkcí
Admin obsluha v `66b4241` přidává přehled pěti stavů a auditované retry bez
odesílání z webu. `68e1199` přidává no-store náhled uloženého textu a explicitní
localhost transport `--transport=local-outbox`; jeho soubory jsou ignorované,
nepřístupné přes web a test ověřuje odmítnutí produkčního hostu. Zbývá schválit
text, otestovat doručení na určenou testovací adresu a teprve potom samostatně
rozhodnout o produkčním CRONu s explicitním `--send`.
