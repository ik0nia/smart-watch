<?php
$config = require __DIR__.'/config.php';
date_default_timezone_set($config['timezone'] ?? 'Europe/Bucharest');

function api_get($url, $token){
  $ch = curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_HTTPHEADER=>['Authorization: FlespiToken '.$token],
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CONNECTTIMEOUT=>10,
    CURLOPT_TIMEOUT=>25,
    // multe shared hostings au probleme cu CA bundle
    CURLOPT_SSL_VERIFYPEER=>false,
    CURLOPT_SSL_VERIFYHOST=>0,
  ]);
  $r = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if($r===false || $code>=400){
    return [ 'ok'=>false, 'code'=>$code, 'err'=>$err, 'body'=>$r ];
  }
  $j = json_decode($r,true);
  return [ 'ok'=>true, 'json'=>$j ];
}

function unwrap_result($json){
  // flespi răspunde de obicei cu {"result":[...]}
  if(is_array($json) && array_key_exists('result',$json) && is_array($json['result'])) return $json['result'];
  return $json;
}

function write_json($file,$data){
  @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}

function add_row($file,$row){
  $d = file_exists($file) ? json_decode(file_get_contents($file),true) : [];
  if(!is_array($d)) $d = [];
  $d[] = $row;
  write_json($file,$d);
}

function send_alert($config, $subject, $message){
  if(empty($config['smtp']['enabled'])) return;
  @mail($config['smtp']['to'], $subject, $message, "From: ".$config['smtp']['from']);
}

$dataDir = $config['data_dir'] ?? (__DIR__.'/data');
if(!is_dir($dataDir)) @mkdir($dataDir, 0775, true);

$stateFile = rtrim($dataDir,'/').'/_state.json';
$state = file_exists($stateFile) ? json_decode(file_get_contents($stateFile),true) : [];
if(!is_array($state)) $state = [];

// --- 0) praguri / fields ---
$fieldBracelet = $config['field_bracelet_removal'] ?? 'alarm.bracelet_removal';
$fieldFall     = $config['field_fall_alarm'] ?? 'alarm.fall';

$inactiveThreshold = (int)($config['inactive_threshold_s'] ?? 900);
$inactiveCooldown  = (int)($config['inactive_cooldown_s'] ?? 3600);
$braceletCooldown  = (int)($config['bracelet_cooldown_s'] ?? $config['alert_cooldown_s'] ?? 3600);
$fallCooldown      = (int)($config['fall_cooldown_s'] ?? $config['alert_cooldown_s'] ?? 3600);

// --- 1) Citește MESSAGES (history) ---
$lastTs = isset($state['last_ts']) ? (float)$state['last_ts'] : 0.0;

// folosește un "from" ușor peste lastTs ca să nu duplici ultimele mesaje
$url = rtrim($config['api_base'],'/').'/gw/devices/'.$config['device_id'].'/messages?limit=200';
if($lastTs > 0) $url .= '&from='.($lastTs + 0.0001);

$res = api_get($url, $config['flespi_token']);
if(!$res['ok']){
  add_row(rtrim($dataDir,'/').'/errors.json', [
    'time'=>date('Y-m-d H:i'),
    'code'=>$res['code'],
    'err'=>$res['err'],
    'body'=> is_string($res['body']) ? substr($res['body'],0,500) : null,
    'url'=>$url,
  ]);
  exit;
}

$msgs = unwrap_result($res['json']);
if(!is_array($msgs)) $msgs = [];

$maxTs = $lastTs;
$any = false;

foreach($msgs as $m){
  // timestamp în flespi e de obicei float (secunde cu fracție)
  $ts = isset($m['timestamp']) ? (float)$m['timestamp'] : 0.0;
  if($ts <= 0) continue;

  $any = true;
  if($ts > $maxTs) $maxTs = $ts;
  $t = date('Y-m-d H:i', (int)$ts);

  // --- vitale ---
  if(isset($m['heart.rate'])) add_row(rtrim($dataDir,'/').'/bpm.json', ['time'=>$t,'bpm'=>$m['heart.rate']]);
  if(isset($m['blood.oxygen.saturation'])) add_row(rtrim($dataDir,'/').'/spo2.json', ['time'=>$t,'spo2'=>$m['blood.oxygen.saturation']]);
  if(isset($m['blood.pressure.systolic'], $m['blood.pressure.diastolic'], $m['heart.rate'])){
    add_row(rtrim($dataDir,'/').'/bp.json', ['time'=>$t,'sys'=>$m['blood.pressure.systolic'],'dia'=>$m['blood.pressure.diastolic'],'bpm'=>$m['heart.rate']]);
  }
  if(isset($m['battery.level'])) add_row(rtrim($dataDir,'/').'/battery.json', ['time'=>$t,'battery'=>$m['battery.level']]);

  // temperatură (dacă o ai ca field)
  if(isset($m['body.temperature'])) add_row(rtrim($dataDir,'/').'/temperature.json', ['time'=>$t,'temp'=>$m['body.temperature']]);

  // --- bracelet removal ---
// 1) Cel mai sigur semnal la tine pare să fie wristband.connected.status, DAR în practică unele device-uri îl raportează invers sau "stale".
//    Ca să evităm fals-pozitive: dacă avem vitale proaspete (heart.rate / oxygen / bphrt), considerăm brățara "OK" chiar dacă statusul e false.
if(array_key_exists('wristband.connected.status',$m)){
  $rawRemoved = ($m['wristband.connected.status'] === false || $m['wristband.connected.status'] === 0 || $m['wristband.connected.status'] === 'false');

  // Heuristică anti-false: dacă există măcar un vital în același mesaj, presupunem că e pe mână
  $hasVitals = (isset($m['heart.rate']) && (int)$m['heart.rate'] > 0) || isset($m['blood.oxygen.saturation']) || isset($m['blood.pressure.systolic']) || isset($m['blood.pressure.diastolic']);
  $isRemoved = $rawRemoved && !$hasVitals;

  $prev = $state['bracelet_state'] ?? null;

  // alert doar pe tranziția OK -> SCOASA
  if($prev !== true && $isRemoved){
    $now=time();
    $last=(int)($state['bracelet_last'] ?? 0);
    if($now-$last > $braceletCooldown){
      $state['bracelet_last']=$now;
      send_alert($config,'ALERTA: Bratara scoasa',"Dispozitiv: {$config['device_id']}
Timp: $t
Eveniment: Wristband disconnected
");
    }
    add_row(rtrim($dataDir,'/').'/alarms.json', ['time'=>$t,'type'=>'bracelet_removal','value'=>true]);
  }

  $state['bracelet_state'] = $isRemoved ? true : false;
}

// --- fallback: alarm.bracelet_removal / alarm.fall dacă apar în messages ---
  if(!empty($m[$fieldBracelet])){
    $now=time(); $last=(int)($state['bracelet_last'] ?? 0);
    if($now-$last > $braceletCooldown){
      $state['bracelet_last']=$now;
      send_alert($config,'ALERTA: Bratara scoasa',"Dispozitiv: {$config['device_id']}
Timp: $t
Eveniment: Bracelet removal
");
    }
    add_row(rtrim($dataDir,'/').'/alarms.json', ['time'=>$t,'type'=>'bracelet_removal','value'=>true]);
    $state['bracelet_state'] = true;
  }

  if(!empty($m[$fieldFall])){
    $prev = $state['fall_state'] ?? null;
    if($prev !== true){
      $now=time(); $last=(int)($state['fall_last'] ?? 0);
      if($now-$last > $fallCooldown){
        $state['fall_last']=$now;
        send_alert($config,'ALERTA: Cadere detectata',"Dispozitiv: {$config['device_id']}
Timp: $t
Eveniment: Fall alarm
");
      }
      add_row(rtrim($dataDir,'/').'/alarms.json', ['time'=>$t,'type'=>'fall_alarm','value'=>true]);
    }
    $state['fall_state'] = true;
  }
}

// --- 2) Update state timestamps ---
if($maxTs > 0){
  $state['last_ts'] = $maxTs;
  $state['last_msg_ts'] = $maxTs;
}

// --- 3) Inactivitate ---
$lastMsgTs = isset($state['last_msg_ts']) ? (float)$state['last_msg_ts'] : 0.0;
if($lastMsgTs > 0){
  $age = time() - (int)$lastMsgTs;
  $state['inactive_age_s'] = $age;
  $isInactive = $age >= $inactiveThreshold;
  $state['inactive'] = $isInactive;

  if($isInactive){
    $last = (int)($state['inactive_last'] ?? 0);
    if(time() - $last > $inactiveCooldown){
      $state['inactive_last'] = time();
      $t = date('Y-m-d H:i');
      send_alert($config,'ALERTA: Dispozitiv inactiv',"Dispozitiv: {$config['device_id']}
Timp: $t
Lipsa update de {$age}s (prag {$inactiveThreshold}s)
");
      add_row(rtrim($dataDir,'/').'/alarms.json', ['time'=>$t,'type'=>'inactive','value'=>true,'age_s'=>$age]);
    }
  }
} else {
  // încă nu avem mesaje
  $state['inactive'] = true;
}

write_json($stateFile, $state);
