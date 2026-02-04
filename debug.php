<?php
header('Content-Type: text/plain; charset=utf-8');
$config = require __DIR__.'/config.php';

echo "ReachFar V48 PHP bridge — DEBUG\n";
echo "Time: ".date('Y-m-d H:i:s')."\n\n";

$mask = function($s){
  if(!$s) return '';
  $len = strlen($s);
  if($len<=8) return str_repeat('*',$len);
  return substr($s,0,4).str_repeat('*',$len-8).substr($s,-4);
};

echo "api_base: ".$config['api_base']."\n";
echo "device_id: ".$config['device_id']."\n";
echo "flespi_token: ".$mask($config['flespi_token'])."\n";
echo "data_dir: ".$config['data_dir']."\n";
echo "bracelet_field: ".($config['field_bracelet_removal'] ?? 'alarm.bracelet_removal')."\n";
echo "fall_field: ".($config['field_fall_alarm'] ?? 'alarm.fall')."\n";
echo "inactive_threshold_s: ".($config['inactive_threshold_s'] ?? 900)."\n\n";

$stateFile = rtrim($config['data_dir'],'/').'/_state.json';
if(file_exists($stateFile)){
  echo "_state.json:\n";
  echo file_get_contents($stateFile)."\n\n";
}else{
  echo "_state.json: (missing)\n\n";
}

$errFile = rtrim($config['data_dir'],'/').'/errors.json';
if(file_exists($errFile)){
  echo "errors.json (last 5):\n";
  $errs = json_decode(file_get_contents($errFile), true) ?: [];
  $last = array_slice($errs, -5);
  echo json_encode($last, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
}else{
  echo "errors.json: (missing)\n";
}


echo "\nTip: deschide /test_api.php pentru a vedea formatul răspunsului API.\n";
