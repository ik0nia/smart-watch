# Smart Watch (RF-V48)

Dashboard PHP pentru afișarea ultimelor date din flespi (ReachFar V48),
Command Center și pagină de debug.

## Cerințe
- PHP 7.4+ (recomandat 8.1+)
- Un canal flespi cu mesaje de la ceas (sau device)
- ID dispozitiv (pentru comenzi)
- Token API flespi
- User/Parolă pentru API intern (recomandat)

## Configurare rapidă
1. Creează un fișier local de configurare:
   ```bash
   cp config.php config.local.php
   ```
2. Editează `config.local.php` și setează:
   - `token`
   - `channel_id`
   - `device_id`
   - `auth.user` / `auth.pass` (pentru API intern)
   - comenzi presetate în `command.presets`
3. (Opțional) Poți folosi variabile de mediu:
   ```bash
   export FLESPI_TOKEN="..."
   export FLESPI_CHANNEL_ID="..."
   export FLESPI_DEVICE_ID="..."
   ```
4. Pornește serverul local:
   ```bash
   php -S localhost:8000
   ```
5. Deschide în browser:
   - Dashboard: `http://localhost:8000`
   - Command Center: `http://localhost:8000/commands.php`
   - Setări ceas: `http://localhost:8000/settings.php`
   - Debug: `http://localhost:8000/debug.php`

## Trimiterea comenzilor
- Comenzile sunt trimise către:
  `/gw/devices/{id}/commands` sau `/gw/devices/{id}/commands-queue`
- Payload-ul ajunge în `properties.payload` (format cerut de ReachFar).
- Documentația protocolului:
  https://flespi.com/protocols/reachfar#parameters

## Endpoint-uri API interne
Endpoint-urile din `api/` sunt protejate cu Basic Auth (user/pass din config).
Recomandare: folosește `auth.user` + `auth.pass` în `config.local.php`.

## Storage
Log-urile se salvează în `storage/` (protejat cu .htaccess):
- `storage/commands.log`
- `storage/api_errors.log`
