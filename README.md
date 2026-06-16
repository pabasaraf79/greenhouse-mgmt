# Verdantia · Greenhouse OS

A **local-LAN** greenhouse management system. ESP32 nodes read sensors and drive
relays; a Laravel + MySQL web app stores readings, raises threshold alerts, lets
operators control actuators, schedule fertigation, and export reports. No cloud.

- **Stack:** Laravel 10 · MySQL 8 · Bootstrap 5 · Chart.js · dompdf
- **Roles:** `admin` (everything) and `operator` (dashboard, control, alerts)
- **Devices:** ESP32 talk to the app over HTTP/JSON on the LAN (no internet)

```
ESP32 node ──HTTP──┐                          ┌── browser (operator/admin)
 sensors + relays  │     Laravel app (PHP)    │
                   ├──►  + MySQL database  ◄───┤
 POST sensor-data  │     :8000                 │   Bootstrap UI + Chart.js
 GET  commands     │                          │
 POST acknowledge  │   push: POST http://esp/command
                   └──────────────────────────┘
```

---

# Part 1 — Run the web system (new developer setup)

You need **4 things** in this order: PHP+Composer, Node.js, MySQL, then the app.
There is **no separate frontend server** — PHP renders the pages; the "frontend"
step just compiles CSS/JS.

## 1.1 Install the toolchain

| Tool | Version | Check |
|---|---|---|
| PHP | 8.1+ (8.3 used here) with ext: `pdo_mysql mbstring xml curl zip gd bcmath` | `php -v` / `php -m` |
| Composer | 2.x | `composer --version` |
| Node.js + npm | 18+ | `node -v` |
| MySQL | 8.x | `mysql --version` |

**Ubuntu/Debian**
```bash
sudo apt update
sudo apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath unzip composer mysql-server
# Node 18 LTS:
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash - && sudo apt install -y nodejs
```

**macOS (Homebrew)**
```bash
brew install php composer node mysql
```

**Windows**
- Easiest: install **[Laragon](https://laragon.org/)** (bundles PHP, Composer, MySQL, Node) — or **XAMPP** (PHP+MySQL) plus Node from nodejs.org.
- Or WSL2 + follow the Ubuntu steps (recommended).

## 1.2 Get the MySQL database server running

The app connects as configured in `.env` (`DB_DATABASE=greenhouse_db`, `DB_USERNAME=root`, `DB_PASSWORD=`).

**Ubuntu/Debian**
```bash
sudo systemctl enable --now mysql          # start now + on every boot
sudo systemctl status mysql                # verify "active (running)"
# create DB + allow root over TCP with an empty password (dev only):
sudo mysql -e "CREATE DATABASE IF NOT EXISTS greenhouse_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY ''; FLUSH PRIVILEGES;"
```

**macOS**
```bash
brew services start mysql
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS greenhouse_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**Windows (Laragon/XAMPP)**
- Start MySQL from the Laragon/XAMPP control panel.
- Open its MySQL console / HeidiSQL and run:
  `CREATE DATABASE greenhouse_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`

> **Prefer a dedicated user over root?** Create one and edit `.env`:
> ```sql
> CREATE USER 'greenhouse'@'localhost' IDENTIFIED BY 'strong-pass';
> GRANT ALL PRIVILEGES ON greenhouse_db.* TO 'greenhouse'@'localhost'; FLUSH PRIVILEGES;
> ```
> then set `DB_USERNAME=greenhouse` and `DB_PASSWORD=strong-pass`.

## 1.3 Backend (Laravel)

```bash
cd greenhouse-mgmt
cp .env.example .env          # if .env doesn't exist yet
composer install              # install PHP dependencies
php artisan key:generate      # only if APP_KEY is empty
# edit .env: APP_NAME, DB_* (see above)
php artisan migrate:fresh --seed   # build all tables + demo data
```

Seeded logins:
- **admin@greenhouse.com / password** (admin)
- **operator@greenhouse.com / password** (operator)

Seeded device API keys (for testing): `gh01-secret-key-0001`, `gh02-secret-key-0002`.

Run it:
```bash
php artisan serve                       # local only -> http://127.0.0.1:8000
php artisan serve --host=0.0.0.0 --port=8000   # so ESP32 / other LAN devices can reach it
```
Find your PC's LAN IP (`ip addr` / `ifconfig` / `ipconfig`) — devices use `http://<that-ip>:8000`.

## 1.4 Frontend (CSS/JS build)

```bash
npm install        # first time only
npm run build      # compile resources/css + resources/js -> public/build/
```
- Re-run `npm run build` after editing styles/JS.
- While actively editing the UI, run `npm run dev` in a second terminal for hot reload (keep `php artisan serve` running too).
- "Vite manifest not found" error = you haven't built yet → `npm run build`.

## 1.5 Daily workflow

```bash
# Terminal 1 — app (MySQL already running as a service)
php artisan serve --host=0.0.0.0 --port=8000
# open http://127.0.0.1:8000  → log in
```

## 1.6 Troubleshooting

| Symptom | Fix |
|---|---|
| `SQLSTATE...Connection refused` | MySQL not running → start the service (1.2) |
| `could not find driver` | `php -m \| grep pdo_mysql` must show it; install `php8.x-mysql` |
| Unstyled page / "Vite manifest not found" | `npm run build` |
| `Address already in use` :8000 | `pkill -f "artisan serve"` or use `--port=8001` |
| Want a clean dataset | `php artisan migrate:fresh --seed` |

---

# Part 2 — Firmware & how the ESP32 connects

Firmware lives in **`firmware/greenhouse_node/greenhouse_node.ino`** (already
adapted to the current hardware wiring). It uses the **LAN** app, not the cloud.

## 2.1 Flash it
1. Arduino IDE → install the **ESP32 board package** and these libraries:
   `ArduinoJson`, `DHT sensor library` (Adafruit) + `Adafruit Unified Sensor`,
   `NewPing`, `LiquidCrystal_I2C`, `ezButton`.
2. Open the sketch and set, at the top:
   - `WIFI_SSID` / `WIFI_PASS`
   - `SERVER` → `http://<your-PC-LAN-IP>:8000`
   - `DEVICE_KEY` → the device's `api_key` from the **Devices** page
3. Upload to the ESP32, open Serial Monitor @ 115200 — it prints the device's IP.

## 2.2 Register the device for instant control
In the web app: **Devices → (device) → Edit → IP Address** = the IP from the
Serial Monitor. That enables **push** (server → device). Even without it, the
device still works via its 5-second poll, just slightly delayed.

## 2.3 How control actually flows
- Operator flips a toggle on **Control Panel** → app stores a command and
  immediately `POST`s it to `http://<device-ip>/command` → relay switches now.
- If the device is momentarily unreachable, the command stays queued and the
  device picks it up on its next `GET /api/devices/commands` poll.
- Either way the device calls `POST /api/commands/{id}/acknowledge` after acting —
  that's the confirmation the relay moved.

## 2.4 HTTP contract (every request sends `X-Device-Key: <api_key>`)
| Direction | Method · URL | Body |
|---|---|---|
| device → app | `POST /api/sensor-data` | `{"readings":{"temperature":…,"humidity":…,"soil_moisture":…,"water_level_cm":…,"gas_level":…,"rain":…,"motion":0/1}}` |
| device → app | `GET /api/devices/commands` | → `{"commands":[{"id","actuator","command","duration"}]}` |
| device → app | `POST /api/commands/{id}/acknowledge` | — |
| app → device | `POST http://<ip>/command` | `{"id","actuator","command","duration"}` → reply `{"status":"ok"}` |

---

# Part 3 — Message to the hardware developer (migrating from Blynk)

Hi — thanks for the sketch. The product is **local-LAN only (no cloud)**, so the
ESP32 must talk to our on-site Laravel server over WiFi instead of Blynk Cloud.
I've prepared an integrated sketch at `firmware/greenhouse_node/greenhouse_node.ino`
that keeps **all your wiring and sensor code**. Here's exactly what changed and
what I need you to confirm.

### What I kept (no change needed)
- Your pins, sensors and calibration: DHT22 on **27**, soil on **A0**, gas on **A3**,
  ultrasonic **TRIG 16 / ECHO 4**, rain on **35**, PIR on **34**, LED **17**, I2C LCD `0x27`.
- Your 4 relays and the 4 manual buttons (23/26/32/33) — buttons still toggle relays locally.

### What changed (Blynk → LAN)
1. **Blynk is no longer the control path.** Relay commands now come from our server,
   not virtual pins V7–V10. Blynk is **optional** and **read-only**: set
   `#define USE_BLYNK 1` to *also* mirror sensor values to your Blynk dashboard
   for diagnostics. Default is `0` (LAN only). The `BLYNK_AUTH_TOKEN` etc. are only
   compiled in when `USE_BLYNK 1`.
2. **Sensor data now POSTs to our API** (`/api/sensor-data`) every 30 s instead of
   (or in addition to) `Blynk.virtualWrite`. Field mapping:
   | Your value | Our field |
   |---|---|
   | `t` (DHT) | `temperature` |
   | `h` (DHT) | `humidity` |
   | `moisture_percent` | `soil_moisture` |
   | ultrasonic `distance` | `water_level_cm` |
   | `gas_sensorvalue` | `gas_level` |
   | `rainsensorvalue` | `rain` |
   | `motionStatus` | `motion` (0/1) |
3. **The device now receives commands over the LAN** — a tiny HTTP server on the
   ESP (`POST /command`) for instant control, plus a 5 s poll as fallback, then it
   acknowledges. Each request carries `X-Device-Key` = the device's api_key.

### What I need you to confirm / fix on the hardware side
1. **Relay polarity.** Your `setup()` writes `HIGH` to switch relays OFF, so I set
   `RELAY_ACTIVE_LOW = true` (LOW = ON). Please confirm on the bench — if your board
   is actually active-high, set that flag to `false`. (Side note: in your original
   `BLYNK_WRITE` you wrote the raw 0/1 state to an active-low relay, which inverts
   ON/OFF — the new `writeRelay()` handles polarity consistently.)
2. **Actuator names → relays.** I mapped: Relay1→`pump` (13), Relay2→`fan` (14),
   Relay3→`valve1` (18), Relay4→`valve2` (19). Confirm that matches the physical loads.
3. **Fertiliser pump.** Our system has a 5th actuator `fertiliser_pump`, but your
   board has **only 4 relays**. If there's a 5th relay, tell me its GPIO and I'll add it;
   otherwise we'll hide that control.
4. **Water level meaning.** The ultrasonic gives *distance to the water surface*, not
   depth. If you want true level we need the tank height to compute `height − distance`.
   Right now we send raw distance (cm) as `water_level_cm`.
5. **Rain field.** I send the raw analog value (0–4095). Your `rainsensorpercentage`
   line had a bug (`analogRead(100-…)`); the new code reads the pin directly. Tell me
   if you'd rather send a calibrated percentage.
6. **One WiFi network.** The ESP32 and the PC running the server must be on the **same
   LAN/subnet**. A static IP (or DHCP reservation) for the ESP keeps push working after reboots.

No Blynk account, template, or internet is required for the product to run — it's
all on the local network. Happy to hop on a call to verify relay polarity and the
actuator mapping together.
