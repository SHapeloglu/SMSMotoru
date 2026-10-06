<?php
require_once __DIR__.'/GenericHttpProvider.php';
class IletimerkeziProvider extends GenericHttpProvider {
 public function send(string $phone,string $message,string $sender,array $options=[]):array {
  $url=$this->credentials['send_endpoint']??'';
  if(!$url)return ['success'=>false,'provider_id'=>null,'raw'=>'','error'=>'İletiMerkezi send_endpoint ayarlanmamış.'];
  $r=$this->request($url,['key'=>$this->credentials['key']??'','hash'=>$this->credentials['hash']??'','sender'=>$sender,'message'=>$message,'receivers'=>$phone,'iys'=>$options['iys']??'Y']);
  return ['success'=>$r['ok'],'provider_id'=>null,'raw'=>$r['body'],'error'=>$r['error'],'http_code'=>$r['code']];

 }
 public function balance():?float {
  $url=$this->credentials['balance_endpoint']??''; if(!$url)return null;
  $r=$this->request($url,$this->credentials['balance_payload']??[]);
  if(!$r['ok'])return null; $j=$this->jsonOrEmpty($r['body']);
  return isset($j['balance'])?(float)$j['balance']:null;
 }
 public function iysStatus(string $phone):?string {
  $url=$this->credentials['iys_endpoint']??''; if(!$url)return null;
  $r=$this->request($url,array_merge($this->credentials['iys_payload']??[],['phone'=>$phone]));
  if(!$r['ok'])return null; $j=$this->jsonOrEmpty($r['body']);
  return $j['status']??null;
 }
}
