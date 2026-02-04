<?php
$bpm=json_decode(@file_get_contents('data/bpm.json'),true)?:[];
$bp=json_decode(@file_get_contents('data/bp.json'),true)?:[];
$temp=json_decode(@file_get_contents('data/temperature.json'),true)?:[];
$batt=json_decode(@file_get_contents('data/battery.json'),true)?:[];
$alarms=json_decode(@file_get_contents('data/alarms.json'),true)?:[];
$state=json_decode(@file_get_contents('data/_state.json'),true)?:[];

$lastTemp=$temp?end($temp):null;
$lastBatt=$batt?end($batt):null;

$inactive = !empty($state['inactive']);
$inactiveAge = (int)($state['inactive_age_s'] ?? 0);
$braceletState = !empty($state['bracelet_state']);
$fallState = !empty($state['fall_state']);
$lastMsgTs = (int)($state['last_msg_ts'] ?? 0);
$hasData = $lastMsgTs > 0;
$lastMsg = $hasData ? date('Y-m-d H:i', $lastMsgTs) : 'N/A';

function badge($on,$textOn,$textOff){
  $bg = $on ? '#d32f2f' : '#2e7d32';
  $txt = $on ? $textOn : $textOff;
  return '<span style="display:inline-block;padding:6px 10px;border-radius:999px;color:#fff;background:'.$bg.';font-weight:600">'.$txt.'</span>';
}
?><!doctype html><html><head><meta charset=utf-8>
<title>ReachFar V48 — Dashboard</title>
<script src=https://cdn.jsdelivr.net/npm/chart.js></script>
<style>
body{font-family:system-ui;background:#f4f6f8;padding:20px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px}
.card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
h2{margin-top:0}
small{color:#667}
table{border-collapse:collapse}
th,td{padding:8px;border-bottom:1px solid #eee;text-align:left}
</style></head><body>

<div class=grid>
  <div class=card>
    <h2>🟢 Status</h2>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <?=badge(!$hasData || $inactive, !$hasData ? 'FARA DATE' : 'INACTIV', 'ONLINE')?>
      <?=badge($braceletState,'BRATARA SCOASA','BRATARA OK')?>
      <?=badge($fallState,'CADERE','FARA CADERE')?>
    </div>
    <div style="margin-top:10px"><small>Ultimul mesaj: <?=$lastMsg?></small></div>
    <?php if($inactive): ?>
      <div style="margin-top:6px"><small>Inactivitate: <?=$inactiveAge?> sec</small></div>
    <?php endif ?>
  </div>

  <div class=card><h2>❤️ BPM</h2><canvas id=bpm></canvas></div>

  <div class=card><h2>🔋 Baterie</h2>
    <canvas id=batt></canvas>
    <?php if($lastBatt): ?>
      <div style="margin-top:10px"><small>Ultima: <?=$lastBatt['battery']?>% (<?=$lastBatt['time']?>)</small></div>
    <?php endif ?>
  </div>

  <div class=card><h2>🌡️ Temperatura</h2>
    <?php if($lastTemp): ?>
      <div style="font-size:36px;font-weight:700"><?=$lastTemp['temp']?> °C</div>
      <div><small><?=$lastTemp['time']?></small></div>
    <?php else: ?>N/A<?php endif ?>
  </div>
</div>

<div class=card style="margin-top:20px">
  <h2>🩸 Tensiune</h2>
  <table width=100%>
    <tr><th>Data</th><th>SYS</th><th>DIA</th><th>BPM</th></tr>
    <?php foreach(array_slice(array_reverse($bp),0,10) as $r): ?>
      <tr><td><?=$r['time']?></td><td><?=$r['sys']?></td><td><?=$r['dia']?></td><td><?=$r['bpm']?></td></tr>
    <?php endforeach ?>
  </table>
</div>

<div class=card style="margin-top:20px">
  <h2>🚨 Ultimele alarme</h2>
  <table width=100%>
    <tr><th>Data</th><th>Tip</th><th>Detalii</th></tr>
    <?php foreach(array_slice(array_reverse($alarms),0,15) as $a): ?>
      <tr>
        <td><?=htmlspecialchars($a['time'] ?? '')?></td>
        <td><?=htmlspecialchars($a['type'] ?? '')?></td>
        <td>
          <?php if(isset($a['age_s'])): ?>
            <?= (int)$a['age_s'] ?> sec
          <?php else: ?>
            <?= !empty($a['value']) ? 'true' : 'false' ?>
          <?php endif ?>
        </td>
      </tr>
    <?php endforeach ?>
  </table>
</div>

<script>
const elBpm = document.getElementById('bpm');
const elBatt = document.getElementById('batt');

if (elBpm) {
  new Chart(elBpm, {
    type: 'line',
    options: { spanGaps: true, scales: { x: { ticks: { maxTicksLimit: 6 } } } },
    data: {
      labels: <?=json_encode(array_column($bpm,'time'))?>,
      datasets: [{ label: 'BPM', data: <?=json_encode(array_column($bpm,'bpm'))?>, tension: .3 }]
    }
  });
}

if (elBatt) {
  new Chart(elBatt, {
    type: 'line',
    options: { spanGaps: true, scales: { x: { ticks: { maxTicksLimit: 6 } } } },
    data: {
      labels: <?=json_encode(array_column($batt,'time'))?>,
      datasets: [{ label: 'Battery %', data: <?=json_encode(array_column($batt,'battery'))?>, tension: .3 }]
    }
  });
}
</script>
</body></html>
