# Kalibrace párového R06 měření — 7. 11. 2026

První CI [42351458564](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42351458564)
nad `b98961ae` odhalilo nestejnou přípravu dat. Původní checkout se spustil
jen s `--route R06`, nový s celou 24případovou maticí. R06 nabídka lekcí
závisí i na předchozím R05 seedu: před změnou `choices=0`, po `choices=2`.
Liší se proto i HTML a šest souvisejících dotazů. Nejde o doklad změněného
chování aplikace ani platné srovnávací časy; komparátor správně odmítl oba výstupy.

Samostatná parita validátoru na MariaDB 10.3/11.4 již PASS: 84 případů, čerstvost
dat/oprávnění, shodné transakční SQL včetně zámků a úklid. To samo nenahrazuje
plnou datovou/HTML paritu GET.

Oprava: zmrazený původní měřič i nový měřič spustit v úplném pořadí všech
24 případů. Devět R06 dvojic pak porovnat na totožném stavu po R05 seedu.
Seed ani měřené tělo se nemění, neschovávají se rozdíly v komparátoru a
požadavek striktní shody celých doménových dat i HTML zůstává zachovaný.
Produkce, aplikace a původní WIP se touto opravou měřicí přípravy nemění.


Při následné kontrole přípravy odhalen také automatický `closed_at` každé
syntetické revize (`DEFAULT CURRENT_TIMESTAMP`). HTML jej legitimně zobrazuje;
dvě nezávislá seedování by měla různý čas i při shodných počtech a obsahu.
Oba měřiče proto v seedu výslovně nastaví stejné datum/čas `CURRENT_DATE 18:00:00`
pro revize tréninku 101. Jde o deterministické testovací vstupy **mimo měřený
interval**, nikoli maskování časů ve výsledném HTML nebo změnu aplikace.
Původní měřič se od `89edfdbe` liší pouze tímto doplněním v seedu; aktuální
navíc zaznamenává otisky dvou služeb. Měřená těla i všechny ostatní seedovací
kroky jsou zachované. Samostatné původní časy s odlišnými vstupy se nepoužijí.


[Strojový doklad prvního odmítnutého porovnání](calibration-42351458564.json).
[Druhý běh 42351925475](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42351925475)
po opravě pořadí již má celá doménová data shodná, HTML stále odlišné;
[doklad MariaDB 11.4](calibration-42351925475.json). Je rovněž vyřazený
z platných srovnávacích výsledků. Aplikační změna `b98961ae` se při těchto
kalibracích neupravovala, měnily se pouze testovací vstupy/orchestrace.
