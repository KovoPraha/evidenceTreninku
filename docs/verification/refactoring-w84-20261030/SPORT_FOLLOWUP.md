# Navazující sportovní kontrakty

Zdrojová kontrola 30. 10. 2026; nejde o uzavření celé W8.

| Oblast | Zjištění a rozhodnutí |
| --- | --- |
| Veřejný rozvrh | `publicTrainingSchedule` má jeden parametrizovaný dotaz, explicitní veřejná pole, validaci kalendářních dat. Bez doloženého N+1; zachovat. `PublicTrainingScheduleTest` pokrývá veřejnost a datum. |
| Sportoviště | `venueCalendarUnreservedPlans` má jeden dotaz s `NOT EXISTS` pro historickou vazbu a přímé `rezervace_id`. `VenueCalendarTest` pokrývá deduplikaci. Nepřesouvat kontrolu dostupnosti mimo ukládání. |
| Uzavření tréninku | Revize, zámky, opakovaná role a důvody hostů jsou doménový kontrakt. Existují `TrainingClosureTest`, `TrainingClosureMariaDbRace`, `TrainingClosureAuthorizationRace`. Dotazy na odměny po účastnících nejsou automaticky zbytečné: následuje audit/review. W8.5 doplní velikosti 10/50/200 a rozlišení čtení od povinných zápisů. |
| Formuláře | Existují JS kontrakty plánovače, editace, autocomplete a odesílání, plus session smoke idempotence. Browser klávesnice/mobil zatím není těmito testy nahrazen. Zůstává v technické přejímce W13/W15. |
| Původní rezervace | `booking/rezervovat.php` používá `GET_LOCK`, opakovanou kapacitu a duplicitu, transakční insert + outbox a `finally RELEASE_LOCK`. Současné testy workeru nedokazují souběh dvou požadavků formuláře. W8.5 doplní izolovaný souběh posledního místa, duplicitu, čekací listinu a rollback; skutečné transporty musí být izolované. |
| Náhrady | `ClubMakeupTest`, `TrainingRsvpTest` a kontrakt uzavřené revize již existují; v W8.5 ověřit pokrytí konkurence a storna před přijetím nové extrakce. |
| Zdravotní doklady R13 | `medicalClearanceList` již načítá záznamy dávkově, `medicalClearanceView` po účelech. Existují historie, inclusive expiry, revokace i MariaDB race. Profil většího seznamu/historie dosud otevřen; zařadit do W8.5. |

W8.4 mění pouze čtecí projekci klubového seznamu a vozidel. Zápisové kolize,
finanční potvrzení a autorizace nejsou tímto balíkem přepsány ani označeny
za kompletně provozně přijaté. Další oblasti W9–W15 zůstávají v hlavním plánu.
