<?php
header('Content-Type: application/json; charset=utf-8');
$config = require __DIR__.'/config.php';

function api_get($url, $token){
  $ch = curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_HTTPHEADER=>['Authorization: FlespiToken '.$token],
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CONNECTTIMEOUT=>10,
    CURLOPT_TIMEOUT=>20,
    CURLOPT_SSL_VERIFYPEER=>false,
    CURLOPT_SSL_VERIFYHOST=>0,
  ]);
  $r = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return [$code,$err,$r];
}

$url = $config['api_base'].'/gw/devices/'.$config['device_id'].'/messages?limit=5';
[$code,$err,$body] = api_get($url, $config['flespi_token']);

$out = [
  'http_code' => $code,
  'curl_error' => $err,
  'url' => $url,
  'body_preview' => is_string($body) ? substr($body,0,2000) : null,
];

$j = json_decode($body, true);
if(is_array($j)){
  $out['decoded_type'] = isset($j['result']) ? 'wrapper_result' : 'array_or_object';
  $msgs = (isset($j['result']) && is_array($j['result'])) ? $j['result'] : $j;
  $out['messages_count'] = is_array($msgs) ? count($msgs) : 0;
  if(is_array($msgs) && count($msgs)){
    $out['first_message_keys'] = array_keys($msgs[0]);
    $out['first_message_timestamp'] = $msgs[0]['timestamp'] ?? null;
  }
}
echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
