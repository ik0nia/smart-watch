<?php
$bpm=json_decode(@file_get_contents('data/bpm.json'),true)?:[];
$bp=json_decode(@file_get_contents('data/bp.json'),true)?:[];
$batt=json_decode(@file_get_contents('data/battery.json'),true)?:[];
$alarms=json_decode(@file_get_contents('data/alarms.json'),true)?:[];
$state=json_decode(@file_get_contents('data/_state.json'),true)?:[];

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

function row_ts($row){
  if(isset($row['ts'])) return (float)$row['ts'];
  if(!empty($row['time'])){
    $parsed = strtotime($row['time']);
    if($parsed !== false) return (float)$parsed;
  }
  return null;
}

function normalize_rows($rows, $valueKeys, $maxRows = null){
  $out = [];
  $seen = [];
  foreach($rows as $row){
    if(!is_array($row)) continue;
    $ts = row_ts($row);
    if($ts === null) continue;
    $keyParts = [$ts];
    foreach($valueKeys as $key){
      $keyParts[] = isset($row[$key]) ? (string)$row[$key] : '';
    }
    $dedupeKey = implode('|', $keyParts);
    if(isset($seen[$dedupeKey])) continue;
    $row['_ts'] = $ts;
    $seen[$dedupeKey] = true;
    $out[] = $row;
  }
  usort($out, function($a, $b){
    if($a['_ts'] === $b['_ts']) return 0;
    return ($a['_ts'] < $b['_ts']) ? -1 : 1;
  });
  if($maxRows !== null && count($out) > $maxRows){
    $out = array_slice($out, -$maxRows);
  }
  return $out;
}

$bpmSeries = normalize_rows($bpm, ['bpm'], 120);
$battSeries = normalize_rows($batt, ['battery'], 120);
$bpSeries = normalize_rows($bp, ['sys','dia','bpm']);
$lastBpm = $bpmSeries ? end($bpmSeries) : null;
$lastBatt = $battSeries ? end($battSeries) : null;
$lastBp = $bpSeries ? end($bpSeries) : null;
$bpTable = array_slice(array_reverse($bpSeries), 0, 10);
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
.latest{display:flex;gap:12px;align-items:baseline;flex-wrap:wrap;margin:6px 0 10px}
.latest .value{font-size:26px;font-weight:700}
.latest .label{font-size:12px;color:#667;text-transform:uppercase;letter-spacing:.04em}
.latest .time{font-size:12px;color:#667}
.table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
.chart{width:100%;height:220px}
@media (max-width: 720px){
  body{padding:12px}
  .grid{grid-template-columns:1fr;gap:12px}
  .card{padding:14px}
  h2{font-size:18px}
  th,td{padding:6px}
  .latest .value{font-size:22px}
  .chart{height:180px}
}
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

  <div class=card>
    <h2>❤️ BPM</h2>
    <?php if($lastBpm): ?>
      <div class=latest>
        <div>
          <div class=label>Ultimul BPM</div>
          <div class=value><?= (int)$lastBpm['bpm'] ?></div>
        </div>
        <div class=time><?= htmlspecialchars($lastBpm['time']) ?></div>
      </div>
    <?php endif ?>
    <canvas id=bpm class=chart></canvas>
  </div>

  <div class=card><h2>🔋 Baterie</h2>
    <?php if($lastBatt): ?>
      <div class=latest>
        <div>
          <div class=label>Ultima baterie</div>
          <div class=value><?= (int)$lastBatt['battery'] ?>%</div>
        </div>
        <div class=time><?= htmlspecialchars($lastBatt['time']) ?></div>
      </div>
    <?php endif ?>
    <canvas id=batt class=chart></canvas>
    <?php if($lastBatt): ?>
      <div style="margin-top:6px"><small>Ultima: <?=$lastBatt['battery']?>% (<?=$lastBatt['time']?>)</small></div>
    <?php endif ?>
  </div>
</div>

<div class=card style="margin-top:20px">
  <h2>🩸 Tensiune</h2>
  <?php if($lastBp): ?>
    <div class=latest>
      <div>
        <div class=label>Ultima tensiune</div>
        <div class=value><?= (int)$lastBp['sys'] ?>/<?= (int)$lastBp['dia'] ?></div>
      </div>
      <div>
        <div class=label>BPM</div>
        <div class=value><?= $lastBp['bpm'] !== null ? (int)$lastBp['bpm'] : 'N/A' ?></div>
      </div>
      <div class=time><?= htmlspecialchars($lastBp['time']) ?></div>
    </div>
  <?php endif ?>
  <div class=table-wrap>
    <table width=100%>
      <tr><th>Data</th><th>SYS</th><th>DIA</th><th>BPM</th></tr>
      <?php foreach($bpTable as $r): ?>
        <tr><td><?=$r['time']?></td><td><?=$r['sys']?></td><td><?=$r['dia']?></td><td><?=$r['bpm']?></td></tr>
      <?php endforeach ?>
    </table>
  </div>
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
    options: { responsive: true, maintainAspectRatio: false, spanGaps: true, scales: { x: { ticks: { maxTicksLimit: 6 } } } },
    data: {
      labels: <?=json_encode(array_column($bpmSeries,'time'))?>,
      datasets: [{ label: 'BPM', data: <?=json_encode(array_column($bpmSeries,'bpm'))?>, tension: .3 }]
    }
  });
}

if (elBatt) {
  new Chart(elBatt, {
    type: 'line',
    options: { responsive: true, maintainAspectRatio: false, spanGaps: true, scales: { x: { ticks: { maxTicksLimit: 6 } } } },
    data: {
      labels: <?=json_encode(array_column($battSeries,'time'))?>,
      datasets: [{ label: 'Battery %', data: <?=json_encode(array_column($battSeries,'battery'))?>, tension: .3 }]
    }
  });
}
</script>
</body></html>
