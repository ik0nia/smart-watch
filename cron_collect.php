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

$rowsCache = [];
$dirtyFiles = [];

function get_rows($file){
  global $rowsCache;
  if(!array_key_exists($file, $rowsCache)){
    $rows = file_exists($file) ? json_decode(file_get_contents($file),true) : [];
    if(!is_array($rows)) $rows = [];
    $rowsCache[$file] = $rows;
  }
  return $rowsCache[$file];
}

function set_rows($file, $rows){
  global $rowsCache, $dirtyFiles;
  $rowsCache[$file] = $rows;
  $dirtyFiles[$file] = true;
}

function add_row($file, $row, $maxRows = null){
  $rows = get_rows($file);
  $rows[] = $row;
  if($maxRows !== null && count($rows) > $maxRows){
    $rows = array_slice($rows, -$maxRows);
  }
  set_rows($file, $rows);
}

function flush_rows(){
  global $rowsCache, $dirtyFiles;
  foreach($dirtyFiles as $file => $_){
    write_json($file, $rowsCache[$file]);
  }
}

function row_ts_from_time($row){
  if(isset($row['ts'])) return (float)$row['ts'];
  if(!empty($row['time'])){
    $parsed = strtotime($row['time']);
    if($parsed !== false) return (float)$parsed;
  }
  return 0.0;
}

function init_state_ts(&$state, $key, $file){
  if(isset($state[$key])) return;
  $rows = get_rows($file);
  for($i=count($rows)-1; $i>=0; $i--){
    $ts = row_ts_from_time($rows[$i]);
    if($ts > 0){
      $state[$key] = $ts;
      return;
    }
  }
  $state[$key] = 0.0;
}

function send_alert($config, $subject, $message){
  if(empty($config['smtp']['enabled'])) return;
  @mail($config['smtp']['to'], $subject, $message, "From: ".$config['smtp']['from']);
}

$dataDir = $config['data_dir'] ?? (__DIR__.'/data');
if(!is_dir($dataDir)) @mkdir($dataDir, 0775, true);
$dataDir = rtrim($dataDir,'/');

$lockFile = $dataDir.'/.collect.lock';
$lockHandle = @fopen($lockFile, 'c');
if($lockHandle){
  if(!flock($lockHandle, LOCK_EX|LOCK_NB)){
    exit;
  }
  register_shutdown_function(function() use ($lockHandle){
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
  });
}

$stateFile = $dataDir.'/_state.json';
$state = file_exists($stateFile) ? json_decode(file_get_contents($stateFile),true) : [];
if(!is_array($state)) $state = [];

$bpmFile = $dataDir.'/bpm.json';
$bpFile = $dataDir.'/bp.json';
$battFile = $dataDir.'/battery.json';
$spo2File = $dataDir.'/spo2.json';
$tempFile = $dataDir.'/temperature.json';
$alarmsFile = $dataDir.'/alarms.json';
$errorsFile = $dataDir.'/errors.json';
$maxRows = isset($config['max_rows_per_file']) ? (int)$config['max_rows_per_file'] : null;
if($maxRows !== null && $maxRows <= 0) $maxRows = null;

init_state_ts($state, 'last_bpm_ts', $bpmFile);
init_state_ts($state, 'last_bp_ts', $bpFile);
init_state_ts($state, 'last_batt_ts', $battFile);
init_state_ts($state, 'last_spo2_ts', $spo2File);
init_state_ts($state, 'last_temp_ts', $tempFile);

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
  add_row($errorsFile, [
    'time'=>date('Y-m-d H:i'),
    'code'=>$res['code'],
    'err'=>$res['err'],
    'body'=> is_string($res['body']) ? substr($res['body'],0,500) : null,
    'url'=>$url,
  ], $maxRows);
  flush_rows();
  exit;
}

$msgs = unwrap_result($res['json']);
if(!is_array($msgs)) $msgs = [];
usort($msgs, function($a, $b){
  $ta = isset($a['timestamp']) ? (float)$a['timestamp'] : 0.0;
  $tb = isset($b['timestamp']) ? (float)$b['timestamp'] : 0.0;
  if($ta === $tb) return 0;
  return ($ta < $tb) ? -1 : 1;
});

$maxTs = $lastTs;
$any = false;

foreach($msgs as $m){
  // timestamp în flespi e de obicei float (secunde cu fracție)
  $ts = isset($m['timestamp']) ? (float)$m['timestamp'] : 0.0;
  if($ts <= 0) continue;

  $any = true;
  if($ts > $maxTs) $maxTs = $ts;
  $t = date('Y-m-d H:i', (int)$ts);

  $hasBpm = isset($m['heart.rate']) && (int)$m['heart.rate'] > 0;
  $hasBp = isset($m['blood.pressure.systolic'], $m['blood.pressure.diastolic']) &&
    (int)$m['blood.pressure.systolic'] > 0 && (int)$m['blood.pressure.diastolic'] > 0;
  $hasSpo2 = (isset($m['blood.oxygen.saturation']) && (int)$m['blood.oxygen.saturation'] > 0) ||
    (isset($m['blood.oxygen.level']) && (int)$m['blood.oxygen.level'] > 0);
  $hasVitals = $hasBpm || $hasBp || $hasSpo2;
  $braceletAlarmAdded = false;

  // --- vitale ---
  if($hasBpm && $ts > (float)($state['last_bpm_ts'] ?? 0)){
    add_row($bpmFile, ['time'=>$t,'bpm'=>$m['heart.rate'],'ts'=>$ts], $maxRows);
    $state['last_bpm_ts'] = $ts;
  }
  if($hasSpo2 && $ts > (float)($state['last_spo2_ts'] ?? 0)){
    $spo2Value = isset($m['blood.oxygen.saturation']) ? $m['blood.oxygen.saturation'] : $m['blood.oxygen.level'];
    add_row($spo2File, ['time'=>$t,'spo2'=>$spo2Value,'ts'=>$ts], $maxRows);
    $state['last_spo2_ts'] = $ts;
  }
  if($hasBp && $ts > (float)($state['last_bp_ts'] ?? 0)){
    add_row($bpFile, [
      'time'=>$t,
      'sys'=>$m['blood.pressure.systolic'],
      'dia'=>$m['blood.pressure.diastolic'],
      'bpm'=> $hasBpm ? $m['heart.rate'] : null,
      'ts'=>$ts
    ], $maxRows);
    $state['last_bp_ts'] = $ts;
  }
  if(isset($m['battery.level']) && $ts > (float)($state['last_batt_ts'] ?? 0)){
    add_row($battFile, ['time'=>$t,'battery'=>$m['battery.level'],'ts'=>$ts], $maxRows);
    $state['last_batt_ts'] = $ts;
  }

  // temperatură (dacă o ai ca field)
  if(isset($m['body.temperature']) && $ts > (float)($state['last_temp_ts'] ?? 0)){
    add_row($tempFile, ['time'=>$t,'temp'=>$m['body.temperature'],'ts'=>$ts], $maxRows);
    $state['last_temp_ts'] = $ts;
  }

  // --- bracelet removal ---
// 1) Cel mai sigur semnal la tine pare să fie wristband.connected.status, DAR în practică unele device-uri îl raportează invers sau "stale".
//    Ca să evităm fals-pozitive: dacă avem vitale proaspete (heart.rate / oxygen / bphrt), considerăm brățara "OK" chiar dacă statusul e false.
  if($hasVitals){
    $state['bracelet_state'] = false;
  }
  if(array_key_exists('wristband.connected.status',$m)){
  $rawRemoved = ($m['wristband.connected.status'] === false || $m['wristband.connected.status'] === 0 || $m['wristband.connected.status'] === 'false');

  // Heuristică anti-false: dacă există măcar un vital în același mesaj, presupunem că e pe mână
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
    add_row($alarmsFile, ['time'=>$t,'type'=>'bracelet_removal','value'=>true,'ts'=>$ts], $maxRows);
    $braceletAlarmAdded = true;
  }

  $state['bracelet_state'] = $isRemoved ? true : false;
  }

// --- fallback: alarm.bracelet_removal / alarm.fall dacă apar în messages ---
  if(!$braceletAlarmAdded && !empty($m[$fieldBracelet]) && !$hasVitals){
    $now=time(); $last=(int)($state['bracelet_last'] ?? 0);
    if($now-$last > $braceletCooldown){
      $state['bracelet_last']=$now;
      send_alert($config,'ALERTA: Bratara scoasa',"Dispozitiv: {$config['device_id']}
Timp: $t
Eveniment: Bracelet removal
");
    }
    add_row($alarmsFile, ['time'=>$t,'type'=>'bracelet_removal','value'=>true,'ts'=>$ts], $maxRows);
    $braceletAlarmAdded = true;
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
      add_row($alarmsFile, ['time'=>$t,'type'=>'fall_alarm','value'=>true,'ts'=>$ts], $maxRows);
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
      add_row($alarmsFile, ['time'=>$t,'type'=>'inactive','value'=>true,'age_s'=>$age,'ts'=>time()], $maxRows);
    }
  }
} else {
  // încă nu avem mesaje
  $state['inactive'] = true;
}

flush_rows();
write_json($stateFile, $state);
