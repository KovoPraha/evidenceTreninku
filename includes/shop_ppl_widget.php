<?php
declare(strict_types=1);

require_once __DIR__.'/shop_ppl.php';
require_once __DIR__.'/shop_packeta.php';

function shopPplWidgetH(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }

/** Shared pickup/PPL/Packeta selector for member and guest checkout. */
function shopPplDeliveryFields(string $formId,array $settings,int $baseTotalMinor,string $selectedMethod='personal_pickup',string $selectedCode='',string $phone='',int $guestUnitMinor=0,bool $externalPhone=false,array $packetaSettings=[]):void
{
    if($packetaSettings===[]&&isset($settings['_packeta'])&&is_array($settings['_packeta']))$packetaSettings=$settings['_packeta'];
    $pplAvailable=shopPplAvailable($settings);$packetaAvailable=$packetaSettings!==[]&&shopPacketaAvailable($packetaSettings);
    if(!$pplAvailable&&!$packetaAvailable)return;
    $pplPrice=(int)($settings['price_minor']??0);$packetaPrice=(int)($packetaSettings['price_minor']??0);
    $pplSelected=$selectedMethod==='ppl_box';$packetaSelected=$selectedMethod==='packeta_zbox';
    $packetaDemo=shopPacketaLocalDemoEnabled();
    if($pplSelected&&isset($_POST['ppl_access_point_code']))$selectedCode=(string)$_POST['ppl_access_point_code'];
    $postedPacketaId=(string)($_POST['packeta_point_id']??'');
    $widgetKey=shopPplWidgetH($settings['widget_api_key']??'');$packetaKey=shopPplWidgetH($packetaSettings['widget_api_key']??'');
    $activePrice=$pplSelected?$pplPrice:($packetaSelected?$packetaPrice:0);
    $displayTotal=number_format(($baseTotalMinor+$activePrice)/100,2,',',' ');
    ?>
<fieldset class="border rounded p-3 my-3" data-box-delivery data-base-total="<?=$baseTotalMinor?>" data-guest-unit-price="<?=$guestUnitMinor?>" data-ppl-price="<?=$pplPrice?>" data-packeta-price="<?=$packetaPrice?>">
<legend class="float-none w-auto px-2 fs-6 fw-semibold">Jak objednávku předáme?</legend>
<label class="d-block mb-2"><input type="radio" name="delivery_method" value="personal_pickup" <?=(!$pplSelected&&!$packetaSelected?'checked':'')?>> Osobní odběr na velodromu · zdarma</label>
<?php if($pplAvailable):?><label class="d-block mb-2"><input type="radio" name="delivery_method" value="ppl_box" <?=$pplSelected?'checked':''?>> PPL Parcelbox · <?=number_format($pplPrice/100,2,',',' ')?> Kč</label><?php endif;?>
<?php if($packetaAvailable):?><label class="d-block mb-2"><input type="radio" name="delivery_method" value="packeta_zbox" <?=$packetaSelected?'checked':''?>> Zásilkovna Z-BOX · <?=number_format($packetaPrice/100,2,',',' ')?> Kč</label><?php endif;?>
<div data-ppl-panel <?=$pplSelected?'':'hidden'?> class="border-top pt-3 mt-2">
<?php if(!$externalPhone):?><label class="form-label" for="<?=$formId?>-ppl-phone">Telefon pro doručení do boxu</label><input id="<?=$formId?>-ppl-phone" type="tel" name="ppl_phone" class="form-control mb-2" maxlength="50" autocomplete="tel" value="<?=shopPplWidgetH($phone)?>" <?=$pplSelected?'required':''?> pattern="\+?[0-9][0-9 ()-]{7,22}"><?php else:?><p class="small text-muted mb-2">Pro doručení použijeme telefon z kontaktních údajů výše.</p><?php endif;?>
<button type="button" class="btn btn-outline-primary btn-sm" data-ppl-open>Vybrat PPL box na mapě</button><span class="small ms-2" data-ppl-selected aria-live="polite"><?= $selectedCode!==''?'Vybraný box '.shopPplWidgetH($selectedCode):'Box zatím není vybraný.' ?></span><div class="small text-danger mt-2 d-none" data-ppl-error role="alert"></div>
<input type="hidden" name="ppl_access_point_code" value="<?=shopPplWidgetH($pplSelected?$selectedCode:'')?>"><input type="hidden" name="shipping_price_minor" value="<?=$pplPrice?>">
<ppl-access-point-widget api-key="<?=$widgetKey?>" config='{"viewMode":"modal","allowedCountries":["CZ"],"disabledAccessPointTypes":["ParcelShop","AlzaBox"]}'></ppl-access-point-widget>
</div>
<div data-packeta-panel <?=$packetaSelected?'':'hidden'?> class="border-top pt-3 mt-2">
<?php if(!$externalPhone):?><label class="form-label" for="<?=$formId?>-packeta-phone">Telefon pro doručení do Z-BOXu</label><input id="<?=$formId?>-packeta-phone" type="tel" name="ppl_phone" class="form-control mb-2" maxlength="50" autocomplete="tel" value="<?=shopPplWidgetH($phone)?>" <?=$packetaSelected?'required':''?> pattern="\+?[0-9][0-9 ()-]{7,22}"><?php else:?><p class="small text-muted mb-2">Pro doručení použijeme telefon z kontaktních údajů výše.</p><?php endif;?>
<?php if($packetaDemo):?>
<div class="alert alert-warning">LOCALHOST TEST: syntetický výběr boxu. Bez spojení se Zásilkovnou a bez skutečného odeslání.</div>
<label for="<?=$formId?>-demo-box" class="form-label">Testovací Z-BOX</label><select id="<?=$formId?>-demo-box" name="packeta_point_id" class="form-select"><option value="">Vyberte testovací box</option><?php foreach(shopPacketaLocalDemoPoints()as$point):?><option value="<?=shopPplWidgetH($point['id'])?>" <?=($postedPacketaId!==''?$postedPacketaId:$selectedCode)===$point['id']?'selected':''?>><?=shopPplWidgetH($point['name'])?></option><?php endforeach;?></select>
<?php else:?>
<button type="button" class="btn btn-outline-primary btn-sm" data-packeta-open>Vybrat Z-BOX na mapě Zásilkovny</button><span class="small ms-2" data-packeta-selected aria-live="polite"><?= $packetaSelected&&$selectedCode!==''?'Vybraný Z-BOX '.shopPplWidgetH($selectedCode):'Box zatím není vybraný.' ?></span>
<input type="hidden" name="packeta_point_id" value="<?=shopPplWidgetH($packetaSelected?($postedPacketaId!==''?$postedPacketaId:$selectedCode):'')?>">
<?php endif;?><div class="small text-danger mt-2 d-none" data-packeta-error role="alert"></div><input type="hidden" name="packeta_shipping_price_minor" value="<?=$packetaPrice?>">
</div>
<div class="d-flex justify-content-between fw-semibold border-top pt-2 mt-2"><span>K úhradě včetně předání</span><span data-box-total><?=$displayTotal?> Kč</span></div>
</fieldset>
<?php if($pplAvailable):?><script src="https://www.ppl.cz/accesspointwidget/loader.js" nonce="<?=shopPplWidgetH(app_csp_nonce())?>" async></script><?php endif;?>
<?php if($packetaAvailable&&!$packetaDemo):?><script src="https://widget.packeta.com/v6/www/js/library.js" nonce="<?=shopPplWidgetH(app_csp_nonce())?>" async></script><?php endif;?>
<script nonce="<?=shopPplWidgetH(app_csp_nonce())?>">
document.addEventListener('DOMContentLoaded',()=>{
 const form=document.getElementById(<?=json_encode($formId,JSON_THROW_ON_ERROR)?>);if(!form)return;
 const section=form.querySelector('[data-box-delivery]'),pplPanel=section.querySelector('[data-ppl-panel]'),packetaPanel=section.querySelector('[data-packeta-panel]');
 const selection=()=>form.querySelector('[name="delivery_method"]:checked')?.value||'personal_pickup';
 const pplPhone=section.querySelector('[data-ppl-panel] [name="ppl_phone"]'),packetaPhone=section.querySelector('[data-packeta-panel] [name="ppl_phone"]'),guestPhone=form.querySelector('[name="phone"]');
 const pplCode=section.querySelector('[name="ppl_access_point_code"]'),packetaId=section.querySelector('[name="packeta_point_id"]');
 const pplError=section.querySelector('[data-ppl-error]'),packetaError=section.querySelector('[data-packeta-error]');
 const render=()=>{const method=selection(),ppl=method==='ppl_box',packeta=method==='packeta_zbox';if(pplPanel)pplPanel.hidden=!ppl;if(packetaPanel)packetaPanel.hidden=!packeta;if(pplPhone){pplPhone.disabled=!ppl;pplPhone.required=ppl;}if(packetaPhone){packetaPhone.disabled=!packeta;packetaPhone.required=packeta;}if(guestPhone)guestPhone.required=ppl||packeta;
  const qty=form.querySelector('[name="quantity"]'),base=Number(section.dataset.guestUnitPrice)>0&&qty?Number(section.dataset.guestUnitPrice)*Math.max(1,Number(qty.value)||1):Number(section.dataset.baseTotal);
  const amount=base+(ppl?Number(section.dataset.pplPrice):packeta?Number(section.dataset.packetaPrice):0);section.querySelector('[data-box-total]').textContent=new Intl.NumberFormat('cs-CZ',{minimumFractionDigits:2,maximumFractionDigits:2}).format(amount/100)+' Kč';};
 form.querySelectorAll('[name="delivery_method"]').forEach(el=>el.addEventListener('change',render));form.querySelector('[name="quantity"]')?.addEventListener('input',render);render();
 const pplWidget=section.querySelector('ppl-access-point-widget');section.querySelector('[data-ppl-open]')?.addEventListener('click',()=>{if(typeof pplWidget?.open==='function')pplWidget.open();else{pplError.textContent='Mapa PPL se nenačetla. Zkuste stránku obnovit.';pplError.classList.remove('d-none');}});
 pplWidget?.addEventListener('ppl-accesspointwidget-select',event=>{const point=event.detail||{},value=String(point.code||point.id||'');if(!/^KM[0-9]{7}$/.test(value)||String(point.type||'')!=='ParcelBox'){pplCode.value='';pplError.textContent='Vyberte prosím PPL Parcelbox.';pplError.classList.remove('d-none');return;}pplCode.value=value;section.querySelector('[data-ppl-selected]').textContent=[point.name||'PPL Parcelbox',point.street||'',point.city||''].filter(Boolean).join(', ')+' ('+value+')';pplError.classList.add('d-none');});
 pplWidget?.addEventListener('ppl-accesspointwidget-error',()=>{pplError.textContent='Mapa PPL je nyní nedostupná. Zkuste to později nebo zvolte osobní odběr.';pplError.classList.remove('d-none');});
 section.querySelector('[data-packeta-open]')?.addEventListener('click',()=>{if(!window.Packeta?.Widget){packetaError.textContent='Mapa Zásilkovny se nenačetla. Zkuste stránku obnovit.';packetaError.classList.remove('d-none');return;}window.Packeta.Widget.pick(<?=json_encode((string)($packetaSettings['widget_api_key']??''),JSON_THROW_ON_ERROR)?>,point=>{if(!point)return;const id=String(point.id||'');if(!/^[0-9]{1,12}$/.test(id)){packetaId.value='';packetaError.textContent='Vybraný bod není platný Z-BOX. Vyberte prosím Z-BOX.';packetaError.classList.remove('d-none');return;}packetaId.value=id;section.querySelector('[data-packeta-selected]').textContent=[point.name||'Z-BOX',point.street||'',point.city||'',point.zip||''].filter(Boolean).join(', ')+' ('+id+')';packetaError.classList.add('d-none');},{vendors:[{country:'cz',carrierId:'czzbox'}],language:'cs'});});
 form.addEventListener('submit',event=>{const method=selection();if(method==='ppl_box'&&!/^KM[0-9]{7}$/.test(pplCode?.value||'')){event.preventDefault();pplError.textContent='Nejdříve vyberte PPL Parcelbox na mapě.';pplError.classList.remove('d-none');section.scrollIntoView({block:'center'});}if(method==='packeta_zbox'&&!/^[0-9]{1,12}$/.test(packetaId?.value||'')){event.preventDefault();packetaError.textContent='Nejdříve vyberte Z-BOX na mapě Zásilkovny.';packetaError.classList.remove('d-none');section.scrollIntoView({block:'center'});}});
});
</script>
<?php
}
