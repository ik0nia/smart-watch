<?php
$config = require __DIR__.'/config.php';

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
cmd('hrtstart,1',$config);
cmd('BODYTEMP2',$config);

// Exemple (de folosit doar dacă știi că device-ul acceptă aceste comenzi prin canalul tău):
// cmd('REMOVESMS,1',$config);
// cmd('FALLDOWN,1,1',$config);
