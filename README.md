# Verdantia · Greenhouse OS

If you just cloned this repo and cannot log in, run one of these commands to set up the database and seed the default users:

* **Using Docker:**
  ```bash
  docker compose exec app php artisan migrate:fresh --seed
  ```
* **Using Local PHP:**
  ```bash
  php artisan migrate:fresh --seed
  ```

Seeded logins:
* **Admin:** `admin@greenhouse.com` / `password`
* **Operator:** `operator@greenhouse.com` / `password`

---

## About
Verdantia is a local-LAN greenhouse management system. ESP32 nodes read sensors and toggle relays; a Laravel + MySQL app displays dashboards, sets alerts, controls actuators, and manages watering schedules. No cloud required.

## Getting Started

### Using Docker
1. Copy the environment template:
   ```bash
   cp .env.example .env
   ```
2. Spin up the containers:
   ```bash
   docker compose up -d --build
   ```
3. Run the migrations and seed database (command also at top):
   ```bash
   docker compose exec app php artisan migrate:fresh --seed
   ```
4. Access the app at `http://localhost:8000`.

### Local Installation (Without Docker)
1. Copy the environment file and install PHP packages:
   ```bash
   cp .env.example .env
   composer install
   php artisan key:generate
   ```
2. Create a MySQL database named `greenhouse_db`. Configure database credentials in `.env`.
3. Run migrations and seed data:
   ```bash
   php artisan migrate:fresh --seed
   ```
4. Install Node modules and compile frontend assets:
   ```bash
   npm install
   npm run build
   ```
5. Start the web server:
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```
6. Visit `http://localhost:8000`.

---

## ESP32 Firmware Setup
Firmware is located at `firmware/greenhouse_node/greenhouse_node.ino`.
1. Install ESP32 board and libraries in Arduino IDE: `ArduinoJson`, `DHT sensor library`, `NewPing`, `LiquidCrystal_I2C`, `ezButton`.
2. Edit WiFi credentials and backend URL (`SERVER -> http://<your-PC-LAN-IP>:8000`) in the sketch.
3. Find a seeded device API key from the app's **Devices** page and set it as `DEVICE_KEY` in the sketch.
4. Flash the board.
5. In the web app, go to **Devices -> Edit** and set the device's IP address to enable instant controls.
