<?php
declare(strict_types=1);

require_once __DIR__.'/localhost_acceptance_hub.php';

/** Explicit, server-configured, loopback-only carrier simulation. Never enabled by request input. */
function shopPacketaLocalDemoRequestAllowed(array $server,mixed $configuredHost,bool $enabled):bool
{
    return $enabled&&localhostAcceptanceRequestIsAllowed($server,$configuredHost);
}

function shopPacketaLocalDemoEnabled():bool
{
    return shopPacketaLocalDemoRequestAllowed($_SERVER,defined('APP_HOST')?APP_HOST:getenv('APP_HOST'),
        defined('SHOP_PACKETA_LOCAL_DEMO')&&SHOP_PACKETA_LOCAL_DEMO===true);
}

/** Synthetic point only; no carrier API or genuine shipment is involved. */
function shopPacketaLocalDemoPoints():array
{
    return [['id'=>'999000001','type'=>'zbox','country'=>'cz','displayFrontend'=>1,'status'=>['statusId'=>1],
        'name'=>'LOCALHOST TEST Z-BOX – NEODESÍLAT','street'=>'Ukázková 1','zip'=>'00000','city'=>'Testovací město']];
}
