<?php
require_once __DIR__.'/GenericHttpProvider.php';
class VatansmsProvider extends GenericHttpProvider {
 public function send(string $phone,string $message,string $sender,array $options=[]):array {
  $url=$this->credentials['send_endpoint']??'';
  if(!$url)return ['success'=>false,'provider_id'=>null,'raw'=>'','error'=>'VatanSMS send_endpoint ayarlanmamış.'];
  $r=$this->request($url,['username'=>$this->credentials['username']??'','password'=>$this->credentials['password']??'','sender'=>$sender,'message'=>$message,'phone'=>$phone]);
  return $this->result($r);

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
