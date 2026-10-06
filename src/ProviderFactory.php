<?php
require_once __DIR__.'/Providers/NetgsmProvider.php';
require_once __DIR__.'/Providers/MutlucellProvider.php';
require_once __DIR__.'/Providers/VatansmsProvider.php';
require_once __DIR__.'/Providers/IletimerkeziProvider.php';
function provider_factory(string $name,array $credentials):SmsProviderInterface{
 return match(strtolower($name)){
 'netgsm'=>new NetgsmProvider($credentials),
 'mutlucell'=>new MutlucellProvider($credentials),
 'vatansms'=>new VatansmsProvider($credentials),
 'iletimerkezi'=>new IletimerkeziProvider($credentials),
 default=>throw new InvalidArgumentException('Desteklenmeyen sağlayıcı')
 };
}
