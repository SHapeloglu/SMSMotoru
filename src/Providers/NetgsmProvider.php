<?php
require_once __DIR__.'/GenericHttpProvider.php';
class NetgsmProvider extends GenericHttpProvider {
 public function send(string $phone,string $message,string $sender,array $options=[]):array {
  $url=$this->credentials['send_endpoint']??'https://api.netgsm.com.tr/sms/send/xml';
  $r=$this->request($url,['usercode'=>$this->credentials['usercode']??'','password'=>$this->credentials['password']??'','msgheader'=>$sender,'gsmno'=>$phone,'message'=>$message]);
  return $this->result($r,'/^\s*0[0-2](\s|$)/');

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
