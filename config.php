<?php
return [
  'flespi_token' => 'PvNsEDTQWuMSfSjPSTqnC50Ryd5gs6aXK0UA1LiMccuvLOSP8HZvGLh6xKqRcLL8',
  'device_id'    => '7687639',
  'channel_id'   => '1344918',
  'api_base'     => 'https://flespi.io',
  'timezone'     => 'Europe/Bucharest',

  // email / smtp
  'smtp' => [
    'enabled' => false,
    'host' => '',
    'port' => 587,
    'user' => '',
    'pass' => '',
    'from' => 'monitor@domeniu.ro',
    'to'   => 'email@domeniu.ro'
  ],

  'alert_cooldown_s' => 3600,
  'data_dir' => __DIR__ . '/data',
// ---- Alarme ReachFar V48 (parsate de flespi) ----
// Numele exact al câmpurilor poate varia în funcție de parserul protocolului.
// Dacă în mesajele tale apare alt nume, schimbă aici.
'field_bracelet_removal' => 'alarm.bracelet_removal', // bit 20
'field_fall_alarm'       => 'alarm.fall',             // bit 21

// ---- Detectare inactivitate (lipsă mesaje) ----
'inactive_threshold_s' => 900,   // 15 min
'inactive_cooldown_s'  => 3600,  // 1h între alerte

// ---- Cerere BPM dacă nu sunt date recente ----
'bpm_request_threshold_s' => 900, // 15 min fără BPM => trimite hrtstart,1
'bpm_request_cooldown_s'  => 300, // 5 min între comenzi

// ---- Reaplica periodic setari dispozitiv ----
'device_presets_enabled' => true,
'device_presets_cooldown_s' => 21600, // 6h intre reaplicari

// ---- Cooldown pe alarme ----
'bracelet_cooldown_s' => 3600,
'fall_cooldown_s'     => 3600,

// ---- E-mail (folosește mail() de pe hosting) ----
// Dacă hostingul nu permite mail(), pune 'enabled' => false și folosește alt canal.
];