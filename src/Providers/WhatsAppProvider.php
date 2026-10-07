<?php
require_once __DIR__.'/GenericHttpProvider.php';
// WhatsApp Cloud API (Meta). Bilgiler panelden (Sağlayıcılar > WhatsApp) girilir ve şifreli saklanır:
//   phone_number_id, access_token, api_version (ör. v23.0), waba_id (isteğe bağlı), display_phone (bilgi amaçlı)
// Yalnız Meta'nın onayladığı şablonlarla gönderim yapılır. $message, wa_payload_json() çıktısıdır.
class WhatsAppProvider extends GenericHttpProvider {
 public const DEFAULT_API_VERSION='v23.0';

 private function endpoint():string{
  $v=$this->credentials['api_version']??'';if(!preg_match('/^v\d+\.\d+$/',$v))$v=self::DEFAULT_API_VERSION;
  $base=$this->credentials['graph_base']??'https://graph.facebook.com';   // testler için değiştirilebilir
  return rtrim($base,'/')."/$v/".rawurlencode((string)($this->credentials['phone_number_id']??'')).'/messages';
 }

 public function send(string $phone,string $message,string $sender,array $options=[]):array{
  if(empty($this->credentials['phone_number_id'])||empty($this->credentials['access_token']))
   return ['success'=>false,'provider_id'=>null,'raw'=>'','error'=>'WhatsApp Phone Number ID / Access Token girilmemiş.'];
  $tpl=json_decode($message,true);
  if(!is_array($tpl)||empty($tpl['template']))return ['success'=>false,'provider_id'=>null,'raw'=>'','error'=>'WhatsApp şablon bilgisi okunamadı.'];
  $body=['messaging_product'=>'whatsapp','to'=>$phone,'type'=>'template',
   'template'=>['name'=>$tpl['template'],'language'=>['code'=>$tpl['lang']??'tr']]];
  if(!empty($tpl['params']))$body['template']['components']=[['type'=>'body','parameters'=>array_map(fn($t)=>['type'=>'text','text'=>(string)$t],$tpl['params'])]];

  $ch=curl_init($this->endpoint());
  curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body,JSON_UNESCAPED_UNICODE),CURLOPT_RETURNTRANSFER=>true,
   CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10,
   CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->credentials['access_token'],'Content-Type: application/json']]);
  $raw=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  $j=$this->jsonOrEmpty((string)$raw);
  $id=$j['messages'][0]['id']??null;
  $ok=!$err && $code>=200 && $code<300 && $id;
  $error=$ok?null:($err?:($j['error']['message']??('HTTP '.$code)));
  return ['success'=>(bool)$ok,'provider_id'=>$id,'raw'=>(string)$raw,'error'=>$error,'http_code'=>$code];
 }
}
