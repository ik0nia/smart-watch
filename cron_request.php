<?php
$config = require __DIR__.'/config.php';
date_default_timezone_set($config['timezone'] ?? 'Europe/Bucharest');

/**
 * Trimite comenzi către dispozitiv prin flespi commands-queue.
 * NOTĂ: activarea bracelet removal / fall se face de regulă din CONFIG/REMOVESMS/FALLDOWN.
 * Dacă ai nevoie să trimiți o comandă de setup, o poți adăuga aici.
 */

function cmd($payload,$c){
  $url=$c['api_base'].'/gw/devices/'.$c['device_id'].'/commands-queue';
  $body=json_encode([[ 'name'=>'custom','properties'=>['payload'=>$payload],'ttl'=>300 ]]);
  $ch=curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_HTTPHEADER=>['Authorization: FlespiToken '.$c['flespi_token'],'Content-Type: application/json'],
    CURLOPT_POST=>true,
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POSTFIELDS=>$body
  ]);
  curl_exec($ch);
  curl_close($ch);
}

// Cere măsurători (opțional, în funcție de ce ai activat în CONFIG)
$dataDir = $config['data_dir'] ?? (__DIR__.'/data');
$stateFile = rtrim($dataDir,'/').'/_state.json';
$state = file_exists($stateFile) ? json_decode(file_get_contents($stateFile), true) : [];
if(!is_array($state)) $state = [];

$now = time();
$bpmThreshold = (int)($config['bpm_request_threshold_s'] ?? 900);
$bpmCooldown = (int)($config['bpm_request_cooldown_s'] ?? 300);
$lastBpmTs = isset($state['last_bpm_ts']) ? (float)$state['last_bpm_ts'] : 0.0;
$lastCmd = (int)($state['bpm_cmd_last'] ?? 0);
$needsBpm = $lastBpmTs <= 0 || ($now - (int)$lastBpmTs) >= $bpmThreshold;
$canSend = ($now - $lastCmd) >= $bpmCooldown;

if($needsBpm && $canSend){
  cmd('hrtstart,1',$config);
  $state['bpm_cmd_last'] = $now;
  @file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}

cmd('BODYTEMP2',$config);

// Exemple (de folosit doar dacă știi că device-ul acceptă aceste comenzi prin canalul tău):
// cmd('REMOVESMS,1',$config);
// cmd('FALLDOWN,1,1',$config);
