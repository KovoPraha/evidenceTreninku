<section class="card card-body mb-4" id="lesson-work">
<h2 class="h4">Práce s lekcí</h2>
<?php if($plan):?>
<p><strong><?=tcH($plan['nazev'])?></strong> · <?=tcH($plan['datum'].' · '.substr((string)$plan['cas_od'],0,5).'–'.substr((string)$plan['cas_do'],0,5))?></p>
<?php if($plan['stav']==='zruseny'):?><p class="alert alert-warning">Lekce je zrušená. Její historie zůstává dostupná.</p><?php endif;?>
<ol>
<li><a href="training_rsvps_admin.php?plan_id=<?=(int)$plan['id']?>">Příprava, odpovědi účastníků a náhradníci</a> · <a href="#personal-excuse">Zapsat osobně přijatou omluvu</a></li>
<li><?php if($training):?>Skutečný trénink je zapsaný. <a href="#actual-attendance">Potvrdit nebo opravit skutečnou docházku</a><?php elseif($plan['stav']!=='zruseny'):?><a href="formular.php?plan_id=<?=(int)$plan['id']?>">Zapsat skutečný trénink z této lekce</a>. Teprve potom potvrďte skutečnou docházku.<?php else:?>Zrušený termín se nepotvrzuje jako skutečná docházka.<?php endif;?></li>
</ol><p class="small">Plán a omluvy nejsou potvrzením skutečné účasti. <a href="planovac.php?datum=<?=tcH($plan['datum'])?>">Otevřít rozvrh</a></p>
<?php endif;?>
<form method="get" class="row g-2"><label class="col-md-5">Najít lekci podle názvu<input class="form-control" name="lesson_q" maxlength="100" value="<?=tcH($lessonSearch)?>"></label><label class="col-md-4">Datum lekce<input class="form-control" type="date" name="lesson_on" value="<?=tcH($lessonOn)?>"></label><div class="col-md-3 align-self-end"><button class="btn btn-outline-primary">Vyhledat lekce</button></div></form>
<p class="small mt-2">Nejvýše 50 odpovídajících lekcí. Pro starší termín změňte datum nebo jej vymažte a hledejte podle názvu.</p>
<ul><?php foreach($lessonChoices as $choice):?><li><a href="?plan_id=<?=(int)$choice['id']?><?= $excusePerson>0?'&amp;person_id='.$excusePerson:''?>#lesson-work"><?=tcH($choice['datum'].' · '.substr((string)$choice['cas_od'],0,5).' · '.$choice['nazev'])?></a></li><?php endforeach;?></ul>
<?php if(!$lessonChoices):?><p>Žádná odpovídající lekce ve vašem oprávnění. Změňte název nebo datum.</p><?php endif;?>
</section>
