<?php
$config = require __DIR__.'/config.php';
date_default_timezone_set($config['timezone'] ?? 'Europe/Bucharest');

function api_post($url, $token, $body){
  $ch = curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_HTTPHEADER=>['Authorization: FlespiToken '.$token,'Content-Type: application/json'],
    CURLOPT_POST=>true,
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POSTFIELDS=>$body,
    CURLOPT_CONNECTTIMEOUT=>10,
    CURLOPT_TIMEOUT=>20,
    // multe shared hostings au probleme cu CA bundle
    CURLOPT_SSL_VERIFYPEER=>false,
    CURLOPT_SSL_VERIFYHOST=>0,
  ]);
  $resp = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return [
    'ok' => $resp !== false && $code < 400,
    'code' => $code,
    'err' => $err,
    'body' => $resp,
  ];
}

function send_cmd($payload, $config){
  $url = rtrim($config['api_base'],'/').'/gw/devices/'.$config['device_id'].'/commands-queue';
  $body = json_encode([[ 'name'=>'custom','properties'=>['payload'=>$payload],'ttl'=>300 ]]);
  return api_post($url, $config['flespi_token'], $body);
}

function load_json($file){
  if(!file_exists($file)) return [];
  $data = json_decode(file_get_contents($file), true);
  return is_array($data) ? $data : [];
}

function last_row($rows){
  if(!is_array($rows) || !count($rows)) return null;
  return end($rows);
}

function update_state_cmd($stateFile, $payload){
  $state = load_json($stateFile);
  $now = time();
  $state['last_cmd'] = $payload;
  $state['last_cmd_ts'] = $now;
  if(preg_match('/^hrtstart/i', $payload)){
    $state['bpm_cmd_last'] = $now;
  }
  @file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}

$dataDir = $config['data_dir'] ?? (__DIR__.'/data');
$dataDir = rtrim($dataDir,'/');
$stateFile = $dataDir.'/_state.json';
$state = load_json($stateFile);

$bpmLast = last_row(load_json($dataDir.'/bpm.json'));
$spo2Last = last_row(load_json($dataDir.'/spo2.json'));
$battLast = last_row(load_json($dataDir.'/battery.json'));
$bpLast = last_row(load_json($dataDir.'/bp.json'));

$notices = [];

if($_SERVER['REQUEST_METHOD'] === 'POST'){
  $action = $_POST['action'] ?? '';
  if($action === 'send'){
    $payload = trim($_POST['payload'] ?? '');
    if($payload === ''){
      $notices[] = ['type'=>'error','text'=>'Comanda lipsa.'];
    } else {
      $res = send_cmd($payload, $config);
      if($res['ok']){
        $notices[] = ['type'=>'ok','text'=>'Comanda trimisa: '.$payload];
        update_state_cmd($stateFile, $payload);
      } else {
        $notices[] = ['type'=>'error','text'=>'Eroare la trimitere: '.$payload.' (HTTP '.$res['code'].') '.$res['err']];
      }
    }
  }

  if($action === 'send_multi'){
    $raw = trim($_POST['payloads'] ?? '');
    if($raw === ''){
      $notices[] = ['type'=>'error','text'=>'Nu exista comenzi de trimis.'];
    } else {
      $lines = preg_split("/\r\n|\n|\r/", $raw);
      $sent = 0;
      $errors = 0;
      foreach($lines as $line){
        $line = trim($line);
        if($line === '') continue;
        $res = send_cmd($line, $config);
        if($res['ok']){
          $sent++;
          update_state_cmd($stateFile, $line);
        } else {
          $errors++;
          $notices[] = ['type'=>'error','text'=>'Eroare la '.$line.' (HTTP '.$res['code'].') '.$res['err']];
        }
      }
      if($sent > 0){
        $notices[] = ['type'=>'ok','text'=>'Comenzi trimise: '.$sent];
      }
    }
  }
}

$lastMsgTs = (int)($state['last_msg_ts'] ?? 0);
$lastMsg = $lastMsgTs > 0 ? date('Y-m-d H:i', $lastMsgTs) : 'N/A';
$lastBpmTs = isset($state['last_bpm_ts']) ? (int)$state['last_bpm_ts'] : 0;
$bpmAge = $lastBpmTs > 0 ? (time() - $lastBpmTs) : null;
?><!doctype html>
<html>
<head>
  <meta charset=utf-8>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Setari dispozitiv</title>
  <style>
    body{font-family:system-ui;background:#f4f6f8;padding:20px}
    .topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;gap:12px}
    .topbar h1{font-size:20px;margin:0}
    .btn{display:inline-block;padding:8px 12px;border-radius:8px;background:#1976d2;color:#fff;text-decoration:none;font-weight:600;border:none;cursor:pointer}
    .btn.secondary{background:#455a64}
    .card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px}
    .latest .label{font-size:12px;color:#667;text-transform:uppercase;letter-spacing:.04em}
    .latest .value{font-size:22px;font-weight:700}
    .latest .time{font-size:12px;color:#667}
    .notice{padding:10px 12px;border-radius:8px;margin-bottom:8px}
    .notice.ok{background:#e8f5e9;color:#1b5e20}
    .notice.error{background:#ffebee;color:#b71c1c}
    textarea,input[type=text]{width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font:inherit}
    form{margin:0}
    .stack{display:flex;gap:8px;flex-wrap:wrap}
    @media (max-width: 900px){
      body{padding:12px}
      .topbar{flex-direction:column;align-items:flex-start}
    }
  </style>
</head>
<body>
  <div class=topbar>
    <h1>Setari dispozitiv</h1>
    <a class="btn secondary" href="index.php">Inapoi la dashboard</a>
  </div>

  <?php foreach($notices as $n): ?>
    <div class="notice <?=htmlspecialchars($n['type'])?>"><?=htmlspecialchars($n['text'])?></div>
  <?php endforeach ?>

  <div class="card" style="margin-bottom:16px">
    <h2>Status date</h2>
    <div class=grid>
      <div class=latest>
        <div class=label>Ultimul mesaj</div>
        <div class=value><?=htmlspecialchars($lastMsg)?></div>
      </div>
      <div class=latest>
        <div class=label>Ultimul BPM</div>
        <div class=value><?= $bpmLast ? (int)$bpmLast['bpm'] : 'N/A' ?></div>
        <div class=time><?= $bpmLast ? htmlspecialchars($bpmLast['time']) : '' ?></div>
      </div>
      <div class=latest>
        <div class=label>Ultimul SpO2</div>
        <div class=value><?= $spo2Last ? (int)$spo2Last['spo2'] : 'N/A' ?></div>
        <div class=time><?= $spo2Last ? htmlspecialchars($spo2Last['time']) : '' ?></div>
      </div>
      <div class=latest>
        <div class=label>Baterie</div>
        <div class=value><?= $battLast ? (int)$battLast['battery'].'%' : 'N/A' ?></div>
        <div class=time><?= $battLast ? htmlspecialchars($battLast['time']) : '' ?></div>
      </div>
      <div class=latest>
        <div class=label>Tensiune</div>
        <div class=value><?= $bpLast ? (int)$bpLast['sys'].'/'.(int)$bpLast['dia'] : 'N/A' ?></div>
        <div class=time><?= $bpLast ? htmlspecialchars($bpLast['time']) : '' ?></div>
      </div>
      <div class=latest>
        <div class=label>BPM age</div>
        <div class=value><?= $bpmAge !== null ? (int)($bpmAge/60).' min' : 'N/A' ?></div>
      </div>
    </div>
  </div>

  <div class="card" style="margin-bottom:16px">
    <h2>Comenzi rapide</h2>
    <form method="post">
      <input type="hidden" name="action" value="send">
      <div class=stack>
        <button class=btn name="payload" value="hrtstart,1">Reforteaza BPM (hrtstart,1)</button>
        <button class=btn name="payload" value="BODYTEMP2">Temperatura (BODYTEMP2)</button>
        <button class=btn name="payload" value="REMOVESMS,1">Alertare bratara (REMOVESMS,1)</button>
        <button class=btn name="payload" value="FALLDOWN,1,1">Detectie cadere (FALLDOWN,1,1)</button>
      </div>
    </form>
    <p><small>Comenzile sunt trimise ca payload custom in flespi. Foloseste manualul pentru parametri exacti.</small></p>
  </div>

  <div class="card" style="margin-bottom:16px">
    <h2>Comanda custom</h2>
    <form method="post">
      <input type="hidden" name="action" value="send">
      <input type="text" name="payload" placeholder="Ex: CONFIG,HR=1,BO=1,TB=1 sau setari numere telefon">
      <div style="margin-top:8px">
        <button class=btn type="submit">Trimite comanda</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>Comenzi multiple (una pe linie)</h2>
    <form method="post">
      <input type="hidden" name="action" value="send_multi">
      <textarea name="payloads" rows="6" placeholder="CONFIG,...&#10;REMOVESMS,1&#10;FALLDOWN,1,1"></textarea>
      <div style="margin-top:8px">
        <button class=btn type="submit">Trimite comenzi</button>
      </div>
    </form>
  </div>
</body>
</html>
