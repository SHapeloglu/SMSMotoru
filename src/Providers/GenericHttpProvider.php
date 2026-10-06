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
 public function balance():?float{return null;}
 public function iysStatus(string $phone):?string{return null;}
 protected function jsonOrEmpty(string $body):array{$x=json_decode($body,true);return is_array($x)?$x:[];}
}
