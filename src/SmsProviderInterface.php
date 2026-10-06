<?php
interface SmsProviderInterface {
 public function send(string $phone,string $message,string $sender,array $options=[]):array;
 public function balance():?float;
 public function iysStatus(string $phone):?string;
}
