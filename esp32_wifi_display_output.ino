#include <Adafruit_GFX.h>
#include <Adafruit_ST7735.h>
#include <SPI.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// ===== TFT =====
#define TFT_CS  5
#define TFT_DC  17
#define TFT_RST 16

// ===== Sensors =====
#define TRIG         25
#define ECHO         26
#define ONE_WIRE_BUS 13
#define WATER_SIG    32
#define LED_PIN      27

#define TANK_HEIGHT 100

// ===== WiFi =====
const char* ssids[]     = {"realme_C11", "Arthi", "realme_C12"};
const char* passwords[] = {"artthhii", "01707275528", "aabbcc112233"};

// ===== Server =====
const char* SERVER_NAME = "http://aquaticfarm.atwebpages.com/sensordata.php";
String PROJECT_API_KEY  = "iloveher143";
int station_id          = 2;

// ===== Setpoints (start at 0) =====
float a_ph    = 0;
float a_temp  = 0;
float a_level = 0;

// ===== Ranges from server =====
float ph_min    = 0; float ph_max    = 0;
float temp_min  = 0; float temp_max  = 0;
float level_min = 0; float level_max = 0;

// ===== Last known readings (start at 0) =====
float currentPH    = 0;
float currentTemp  = 0;
float currentLevel = 0;

// ===== Timers =====
unsigned long lastSensor  = 0;
unsigned long lastUpload  = 0;
unsigned long lastDisplay = 0;

bool firstReadDone = false;

// ===== Baseline for pH =====
int baseline = 0;

OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature sensors(&oneWire);
Adafruit_ST7735 tft = Adafruit_ST7735(TFT_CS, TFT_DC, TFT_RST);

void setup() {
  Serial.begin(115200);
  sensors.begin();

  pinMode(TRIG, OUTPUT);
  pinMode(ECHO, INPUT);
  pinMode(LED_PIN, OUTPUT);
  digitalWrite(LED_PIN, LOW);

  tft.initR(INITR_BLACKTAB);
  tft.setRotation(1);
  tft.fillScreen(ST77XX_BLACK);

  // Calibration
  tft.setTextColor(ST77XX_WHITE);
  tft.setTextSize(2);
  tft.setCursor(10, 20);
  tft.println("Calibrating");
  tft.setCursor(10, 50);
  tft.println("Place sensor");
  tft.setCursor(10, 70);
  tft.println("in tap water");
  delay(5000);

  int sum = 0;
  for (int i = 0; i < 10; i++) {
    sum += analogRead(WATER_SIG);
    delay(100);
  }
  baseline = sum / 10;
  Serial.printf("[Cal] Baseline: %d\n", baseline);

  tft.fillScreen(ST77XX_BLACK);
  tft.setTextColor(ST77XX_GREEN);
  tft.setCursor(10, 40);
  tft.println("Calibrated!");
  tft.setCursor(10, 60);
  tft.print("Base: ");
  tft.println(baseline);
  delay(1500);

  // WiFi
  tft.fillScreen(ST77XX_BLACK);
  tft.setTextColor(ST77XX_WHITE);
  tft.setCursor(10, 40);
  tft.println("Connecting");
  tft.setCursor(10, 60);
  tft.println("WiFi...");
  connectToWiFi();

  tft.fillScreen(ST77XX_BLACK);
}

void loop() {
  unsigned long now = millis();

  // ===== Read sensors every 5s =====
  if (now - lastSensor > 5000) {
    lastSensor = now;

    // Ultrasonic
    delay(10);
    digitalWrite(TRIG, LOW);
    delayMicroseconds(2);
    digitalWrite(TRIG, HIGH);
    delayMicroseconds(10);
    digitalWrite(TRIG, LOW);
    long duration = pulseIn(ECHO, HIGH, 30000);
    if (duration > 0) {
      float distance = duration * 0.034 / 2;
      currentLevel = constrain(map(distance, TANK_HEIGHT, 0, 0, 100), 0, 100);
    }

    // Temp (offset 500ms)
    delay(500);
    sensors.requestTemperatures();
    float t = sensors.getTempCByIndex(0);
    if (t != -127.0) currentTemp = t;

    // pH (offset 500ms)
    delay(500);
    int raw = analogRead(WATER_SIG);
    if (raw > 0) {
      int diff = raw - baseline;
      float pH = 7.0 - map(diff, -500, 500, -20, 20) / 10.0;
      currentPH = constrain(pH, 5.0, 9.0);
    }

    firstReadDone = true;

    // LED — on if level exceeds level_max from server
    digitalWrite(LED_PIN, (level_max > 0 && currentLevel > level_max) ? HIGH : LOW);

    Serial.printf("[Sensor] Level:%.1f%% Temp:%.1fC pH:%.2f\n",
                  currentLevel, currentTemp, currentPH);
  }

  // ===== Upload every 5s offset 2s from sensor =====
  if (firstReadDone && now - lastUpload > 5000 && now - lastSensor > 2000) {
    lastUpload = now;
    if (WiFi.status() == WL_CONNECTED) {
      uploadData(currentPH, currentTemp, currentLevel);
    } else {
      connectToWiFi();
    }
  }

  // ===== Display every 5s offset 1s from sensor =====
  if (now - lastDisplay > 5000 && now - lastSensor > 1000) {
    lastDisplay = now;

    tft.fillScreen(ST77XX_BLACK);

    // Water level
    tft.setTextColor(ST77XX_CYAN);
    tft.setTextSize(2);
    tft.setCursor(10, 5);
    tft.print("Water:");
    tft.setTextColor(ST77XX_GREEN);
    tft.setCursor(90, 5);
    tft.print((int)currentLevel);
    tft.print("%");

    // Temp
    tft.setTextColor(ST77XX_YELLOW);
    tft.setCursor(10, 35);
    tft.print("Temp:");
    tft.setTextColor(ST77XX_WHITE);
    tft.setCursor(90, 35);
    tft.print(currentTemp, 1);
    tft.print("C");

    // pH
    tft.setTextColor(ST77XX_MAGENTA);
    tft.setCursor(10, 65);
    tft.print("est.pH:");
    tft.setTextColor(ST77XX_WHITE);
    tft.setTextSize(3);
    tft.setCursor(10, 90);
    tft.print(currentPH, 1);

    // WiFi indicator
    tft.setTextSize(1);
    tft.setTextColor(WiFi.status() == WL_CONNECTED ? ST77XX_GREEN : ST77XX_RED);
    tft.setCursor(140, 5);
    tft.print(WiFi.status() == WL_CONNECTED ? "W" : "X");
  }

  delay(100);
}

void uploadData(float ph, float temp, float level) {
  HTTPClient http;
  String postData = "api_key=" + PROJECT_API_KEY;
  postData += "&station_id=" + String(station_id);
  postData += "&ph="    + String(ph,    2);
  postData += "&temp="  + String(temp,  2);
  postData += "&level=" + String(level, 2);

  http.begin(SERVER_NAME);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  int httpCode = http.POST(postData);
  Serial.printf("[HTTP] Code: %d\n", httpCode);

  if (httpCode == 200) {
    String payload = http.getString();
    Serial.println("[HTTP] " + payload);
    parseSetpoints(payload);
  }
  http.end();
}

void parseSetpoints(String json) {
  StaticJsonDocument<512> doc;
  DeserializationError err = deserializeJson(doc, json);
  if (err) return;

  if (strcmp(doc["status"], "ok") == 0) {
    a_ph    = doc["a_ph"]    | a_ph;
    a_temp  = doc["a_temp"]  | a_temp;
    a_level = doc["a_level"] | a_level;

    ph_min    = doc["ph_min"]    | ph_min;
    ph_max    = doc["ph_max"]    | ph_max;
    temp_min  = doc["temp_min"]  | temp_min;
    temp_max  = doc["temp_max"]  | temp_max;
    level_min = doc["level_min"] | level_min;
    level_max = doc["level_max"] | level_max;

    Serial.printf("[Setpts] a_ph:%.2f a_temp:%.2f a_level:%.2f\n",
                  a_ph, a_temp, a_level);
    Serial.printf("[Ranges] pH:%.2f-%.2f Temp:%.2f-%.2f Level:%.2f-%.2f\n",
                  ph_min, ph_max, temp_min, temp_max, level_min, level_max);
  }
}

void connectToWiFi() {
  int numNetworks = sizeof(ssids) / sizeof(ssids[0]);
  for (int i = 0; i < numNetworks; i++) {
    Serial.printf("[WiFi] Trying: %s\n", ssids[i]);
    WiFi.disconnect();
    delay(500);
    WiFi.begin(ssids[i], passwords[i]);

    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 30) {
      delay(500);
      Serial.print(".");
      attempts++;
    }
    Serial.println();

    if (WiFi.status() == WL_CONNECTED) {
      Serial.printf("[WiFi] Connected! IP: %s\n", WiFi.localIP().toString().c_str());
      return;
    }
    Serial.printf("[WiFi] Failed: %s\n", ssids[i]);
  }
  Serial.println("[WiFi] All networks failed.");
}
