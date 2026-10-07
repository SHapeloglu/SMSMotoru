<?php
require_once __DIR__.'/../SmsProviderInterface.php';
abstract class GenericHttpProvider implements SmsProviderInterface{
 protected array $credentials;
 public function __construct(array $credentials=[]){$this->credentials=$credentials;}
 protected function request(string $url,array $payload=[],array $headers=[]):array{
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($payload),
   CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10,
   CURLOPT_HTTPHEADER=>$headers]);
  $body=curl_exec($ch);$error=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  return ['ok'=>!$error&&$code>=200&&$code<300,'code'=>$code,'body'=>$body?:'','error'=>$error];
 }
 // Standart sonuç. Birçok sağlayıcı hatayı da HTTP 200 ile döner; bu yüzden HTTP durumu yetmez:
 // credentials'ta "success_regex" verilirse yanıt gövdesi bu kalıba uymalıdır (yoksa $defaultRegex kullanılır).
 protected function result(array $r,string $defaultRegex=''):array{
  $regex=$this->credentials['success_regex']??$defaultRegex;
  $ok=$r['ok'];$error=$r['error'];
  if($ok && $regex!=='' && !preg_match($regex,(string)$r['body'])){$ok=false;$error='Sağlayıcı hata döndü: '.mb_substr(trim((string)$r['body']),0,200);}
  if(!$r['ok'] && !$error)$error='HTTP '.$r['code'];
  return ['success'=>$ok,'provider_id'=>null,'raw'=>$r['body'],'error'=>$error,'http_code'=>$r['code']];
 }
 public function balance():?float{return null;}
 public function iysStatus(string $phone):?string{return null;}
 protected function jsonOrEmpty(string $body):array{$x=json_decode($body,true);return is_array($x)?$x:[];}
}
