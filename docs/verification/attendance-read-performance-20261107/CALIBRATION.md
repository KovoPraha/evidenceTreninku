# Kalibrace párového R06 měření — 7. 11. 2026

První CI [42351458564](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42351458564)
nad `b98961ae` odhalilo nestejnou přípravu dat. Původní checkout se spustil
jen s `--route R06`, nový s celou 24případovou maticí. R06 nabídka lekcí
závisí i na předchozím R05 seedu: před změnou `choices=0`, po `choices=2`.
Liší se proto i HTML a šest souvisejících dotazů. Nejde o doklad změněného
chování aplikace ani platné srovnávací časy; komparátor správně odmítl oba výstupy.

Samostatná parita validátoru na MariaDB 11.4 již PASS: 84 případů, čerstvost
dat/oprávnění, shodné transakční SQL včetně zámků a úklid. To samo nenahrazuje
plnou datovou/HTML paritu GET.

Oprava: zmrazený původní měřič i nový měřič spustit v úplném pořadí všech
24 případů. Devět R06 dvojic pak porovnat na totožném stavu po R05 seedu.
Seed ani měřené tělo se nemění, neschovávají se rozdíly v komparátoru a
požadavek striktní shody celých doménových dat i HTML zůstává zachovaný.
Produkce, aplikace a původní WIP se touto opravou měřicí přípravy nemění.
