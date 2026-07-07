# ESP32 Node Firmware

`greenhouse_node/greenhouse_node.ino` — integrated firmware for the current
hardware (DHT22, soil, gas, ultrasonic, rain, PIR, 4 relays, I2C LCD, 4 buttons).
Talks to the **local Laravel app over the LAN**; Blynk is optional/read-only.

**Full instructions** — flashing, libraries, config, the HTTP contract, and the
migration notes for the hardware developer — are in the project **[`../README.md`](../README.md)**:
- Part 2 — Firmware & how the ESP32 connects
- Part 3 — Message to the hardware developer (migrating from Blynk)

Quick config (top of the sketch): `WIFI_SSID`, `WIFI_PASS`, `SERVER`
(= `http://<PC-LAN-IP>:8000`), `DEVICE_KEY` (= device's api_key),
`RELAY_ACTIVE_LOW`, and `USE_BLYNK` (0 = LAN only, 1 = also mirror to Blynk).

**Easiest path:** don't hand-edit these — register the device on the Devices
page in the web app (admin only) and click **Download Firmware**. It fills in
`WIFI_SSID`, `WIFI_PASS`, `SERVER`, and `DEVICE_KEY` for you from what you
entered on that page, and you just open the downloaded `.ino` in Arduino IDE
and flash.

Libraries: ArduinoJson · DHT sensor library (Adafruit) + Adafruit Unified Sensor ·
NewPing · LiquidCrystal_I2C · ezButton (+ Blynk only if `USE_BLYNK 1`).
