/* ============================================================================
 * Verdantia Greenhouse OS — ESP32 Node Firmware (LAN integration)
 * ----------------------------------------------------------------------------
 * Adapted to the CLIENT'S existing wiring (DHT22, soil, gas, ultrasonic, rain,
 * PIR, 4 relays, I2C LCD, 4 buttons) but talks to the LOCAL Laravel app over the
 * LAN instead of Blynk Cloud.
 *
 *   1. POSTs sensor readings  -> POST  {SERVER}/api/sensor-data
 *   2. Receives relay commands TWO ways:
 *        a) PUSH : server calls  POST http://<this-ip>/command   (instant)
 *        b) POLL : node calls     GET  {SERVER}/api/devices/commands  (fallback)
 *   3. Confirms execution     -> POST  {SERVER}/api/commands/{id}/acknowledge
 *
 * Auth on every request to the server:  header  X-Device-Key: <api_key>
 *
 * Blynk is OPTIONAL and read-only: set USE_BLYNK 1 to ALSO mirror sensor values
 * to the Blynk dashboard. Relay CONTROL always comes from the LAN app, never Blynk.
 *
 * Libraries: ArduinoJson, DHT sensor library (Adafruit), NewPing,
 *            LiquidCrystal_I2C, ezButton  (+ Blynk only if USE_BLYNK 1).
 * ==========================================================================*/

// ---- Optional cloud mirror (0 = LAN only / recommended, 1 = also send to Blynk) ----
#define USE_BLYNK 0

#if USE_BLYNK
  #define BLYNK_TEMPLATE_ID   "TMPLaovbQkLP"
  #define BLYNK_DEVICE_NAME   "Smart Green House Monitor"
  #define BLYNK_AUTH_TOKEN    "bppcw0hMZdY_2usTg9GeX60e5cevnytb"
  #define BLYNK_PRINT Serial
#endif

#include <WiFi.h>
#include <HTTPClient.h>
#include <WebServer.h>
#include <ArduinoJson.h>
#include <DHT.h>
#include <NewPing.h>
#include <LiquidCrystal_I2C.h>
#include <ezButton.h>
#if USE_BLYNK
  #include <BlynkSimpleEsp32.h>
#endif

// ----------------------------------------------------------------------------
// CONFIG — edit for your site
// ----------------------------------------------------------------------------
const char* WIFI_SSID = "SLT_FIBER_zaxz2";
const char* WIFI_PASS = "4S2ZPyRu";

// PC running `php artisan serve --host=0.0.0.0 --port=8000`. Use its LAN IP.
const char* SERVER     = "http://192.168.1.50:8000";

// Must equal this device's api_key in the Laravel DB (Devices page).
const char* DEVICE_KEY = "gh01-secret-key-0001";

const unsigned long SENSOR_INTERVAL_MS = 30000;  // push readings every 30 s
const unsigned long POLL_INTERVAL_MS   = 5000;   // poll for commands every 5 s

// Client's relay board is ACTIVE-LOW (setup() drives HIGH to switch OFF).
const bool RELAY_ACTIVE_LOW = true;

// ----------------------------------------------------------------------------
// PINS — exactly as in the client's sketch
// ----------------------------------------------------------------------------
#define DHTPIN     27
#define DHTTYPE    DHT22
#define MOIST_PIN  A0        // GPIO36 (ADC1) capacitive soil moisture
#define GAS_PIN    A3        // GPIO39 (ADC1) MQ-2
#define TRIG_PIN   16
#define ECHO_PIN   4
#define MAX_DIST   200
#define RAIN_PIN   35        // ADC1
#define MOTION_PIN 34        // PIR
#define LED_PIN    17

#define BTN1 23
#define BTN2 26
#define BTN3 32
#define BTN4 33

// actuator name (DB enum) -> relay GPIO. Client has 4 relays; no fertiliser_pump.
struct Relay { const char* name; uint8_t pin; unsigned long offAt; };
Relay relays[] = {
  { "pump",   13, 0 },   // Relay 1
  { "fan",    14, 0 },   // Relay 2
  { "valve1", 18, 0 },   // Relay 3
  { "valve2", 19, 0 },   // Relay 4
  // { "fertiliser_pump", <pin>, 0 },  // add when a 5th relay is wired
};
const int RELAY_COUNT = sizeof(relays) / sizeof(relays[0]);

DHT dht(DHTPIN, DHTTYPE);
NewPing sonar(TRIG_PIN, ECHO_PIN, MAX_DIST);
LiquidCrystal_I2C lcd(0x27, 20, 4);
ezButton button1(BTN1), button2(BTN2), button3(BTN3), button4(BTN4);
WebServer server(80);

unsigned long lastSensor = 0, lastPoll = 0;

// ----------------------------------------------------------------------------
// Relay helpers (active-low aware)
// ----------------------------------------------------------------------------
void writeRelay(uint8_t pin, bool on) {
  digitalWrite(pin, (on ^ RELAY_ACTIVE_LOW) ? HIGH : LOW);
}
Relay* findRelay(const String& name) {
  for (int i = 0; i < RELAY_COUNT; i++) if (name == relays[i].name) return &relays[i];
  return nullptr;
}
bool applyCommand(const String& actuator, const String& command, long duration) {
  Relay* r = findRelay(actuator);
  if (!r) return false;
  bool on = (command == "on");
  writeRelay(r->pin, on);
  r->offAt = (on && duration > 0) ? millis() + (duration * 1000UL) : 0;
  Serial.printf("[RELAY] %s -> %s (%lds)\n", actuator.c_str(), command.c_str(), duration);
  return true;
}

// ----------------------------------------------------------------------------
// Server calls
// ----------------------------------------------------------------------------
void acknowledge(long id) {
  if (id <= 0) return;
  HTTPClient http;
  http.begin(String(SERVER) + "/api/commands/" + String(id) + "/acknowledge");
  http.addHeader("X-Device-Key", DEVICE_KEY);
  http.addHeader("Accept", "application/json");
  http.POST("");
  http.end();
}

void postSensors() {
  float t = dht.readTemperature();
  float h = dht.readHumidity();
  int moistRaw = analogRead(MOIST_PIN);
  float moisturePct = 100.0 - (100.0 * (moistRaw - 1000) / (2600 - 1000)); // client calibration
  int gas = analogRead(GAS_PIN);
  unsigned int distance = sonar.ping_cm();           // water level proxy (cm)
  int rain = analogRead(RAIN_PIN);
  int motion = digitalRead(MOTION_PIN);

  if (isnan(t) || isnan(h)) { Serial.println("DHT read failed"); }

  StaticJsonDocument<512> doc;
  JsonObject r = doc.createNestedObject("readings");
  if (!isnan(t)) r["temperature"]   = t;
  if (!isnan(h)) r["humidity"]      = h;
  r["soil_moisture"]  = moisturePct;
  r["water_level_cm"] = distance;
  r["gas_level"]      = gas;
  r["rain"]           = rain;
  r["motion"]         = motion;

  String body; serializeJson(doc, body);
  HTTPClient http;
  http.begin(String(SERVER) + "/api/sensor-data");
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-Device-Key", DEVICE_KEY);
  http.addHeader("Accept", "application/json");
  int code = http.POST(body);
  Serial.printf("[SENSOR] -> HTTP %d\n", code);
  http.end();

#if USE_BLYNK
  // Optional read-only mirror to the existing Blynk dashboard.
  Blynk.virtualWrite(V0, t);  Blynk.virtualWrite(V1, h);
  Blynk.virtualWrite(V3, gas); Blynk.virtualWrite(V4, distance);
  Blynk.virtualWrite(V5, moisturePct); Blynk.virtualWrite(V11, rain);
  Blynk.virtualWrite(V6, motion);
#endif

  // LCD (unchanged from client)
  lcd.setCursor(0,0); lcd.print("Temp:"); lcd.print(t); lcd.print("C   ");
  lcd.setCursor(0,1); lcd.print("Humidity:"); lcd.print(h); lcd.print("% ");
  lcd.setCursor(0,2); lcd.print("Moisture:"); lcd.print(moisturePct); lcd.print("% ");
  lcd.setCursor(0,3); lcd.print("Gas:"); lcd.print(gas); lcd.print(" WL:"); lcd.print(distance); lcd.print("cm ");
}

void pollCommands() {
  HTTPClient http;
  http.begin(String(SERVER) + "/api/devices/commands");
  http.addHeader("X-Device-Key", DEVICE_KEY);
  http.addHeader("Accept", "application/json");
  if (http.GET() == 200) {
    StaticJsonDocument<1024> doc;
    if (deserializeJson(doc, http.getString()) == DeserializationError::Ok) {
      for (JsonObject c : doc["commands"].as<JsonArray>()) {
        long id = c["id"] | 0;
        if (applyCommand(c["actuator"].as<String>(), c["command"].as<String>(), c["duration"] | 0))
          acknowledge(id);
      }
    }
  }
  http.end();
}

// PUSH path: server -> node.  POST /command
void handleCommand() {
  if (server.header("X-Device-Key") != DEVICE_KEY) {
    server.send(401, "application/json", "{\"error\":\"unauthorized\"}"); return;
  }
  StaticJsonDocument<256> doc;
  if (deserializeJson(doc, server.arg("plain")) != DeserializationError::Ok) {
    server.send(400, "application/json", "{\"error\":\"bad json\"}"); return;
  }
  long id = doc["id"] | 0;
  bool ok = applyCommand(doc["actuator"].as<String>(), doc["command"].as<String>(), doc["duration"] | 0);
  if (ok) { acknowledge(id); server.send(200, "application/json", "{\"status\":\"ok\"}"); }
  else    { server.send(422, "application/json", "{\"error\":\"unknown actuator\"}"); }
}

bool relayIsOn(uint8_t pin) {
  int level = digitalRead(pin);
  return RELAY_ACTIVE_LOW ? (level == LOW) : (level == HIGH);
}

// Local manual buttons toggle the matching relay (index aligns with relays[]).
void handleButtons() {
  ezButton* btns[] = { &button1, &button2, &button3, &button4 };
  for (int i = 0; i < 4 && i < RELAY_COUNT; i++) {
    btns[i]->loop();
    if (btns[i]->isPressed()) {
      bool turnOn = !relayIsOn(relays[i].pin);   // toggle current state
      writeRelay(relays[i].pin, turnOn);
      relays[i].offAt = 0;
      Serial.printf("[BTN] %s -> %s\n", relays[i].name, turnOn ? "on" : "off");
    }
  }
}

// ----------------------------------------------------------------------------
void setup() {
  Serial.begin(115200);
  lcd.init(); lcd.backlight();
  pinMode(LED_PIN, OUTPUT);
  pinMode(MOTION_PIN, INPUT_PULLUP);
  pinMode(RAIN_PIN, INPUT);
  for (int i = 0; i < RELAY_COUNT; i++) { pinMode(relays[i].pin, OUTPUT); writeRelay(relays[i].pin, false); }
  button1.setDebounceTime(50); button2.setDebounceTime(50);
  button3.setDebounceTime(50); button4.setDebounceTime(50);
  dht.begin();

#if USE_BLYNK
  Blynk.begin(BLYNK_AUTH_TOKEN, WIFI_SSID, WIFI_PASS);   // Blynk manages WiFi
#else
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  Serial.print("WiFi");
  while (WiFi.status() != WL_CONNECTED) { delay(400); Serial.print("."); }
#endif
  Serial.printf("\nIP: %s  <-- put this in Devices > Edit > IP Address\n", WiFi.localIP().toString().c_str());

  const char* hdr[] = { "X-Device-Key" };
  server.collectHeaders(hdr, 1);
  server.on("/command", HTTP_POST, handleCommand);
  server.on("/", HTTP_GET, [](){ server.send(200, "text/plain", "Verdantia ESP32 node OK"); });
  server.begin();
}

void loop() {
#if USE_BLYNK
  Blynk.run();
#endif
  server.handleClient();
  handleButtons();

  unsigned long now = millis();
  for (int i = 0; i < RELAY_COUNT; i++)
    if (relays[i].offAt && now >= relays[i].offAt) { writeRelay(relays[i].pin, false); relays[i].offAt = 0; }

  if (now - lastSensor >= SENSOR_INTERVAL_MS) { lastSensor = now; postSensors(); }
  if (now - lastPoll   >= POLL_INTERVAL_MS)   { lastPoll   = now; pollCommands(); }
}
