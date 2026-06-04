#include <Adafruit_GFX.h>
#include <Adafruit_ST7735.h>
#include <SPI.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

#define TFT_CS   5
#define TFT_DC   17
#define TFT_RST  16

#define TRIG 25
#define ECHO 26
#define ONE_WIRE_BUS 13
#define WATER_SIG 32

#define TANK_HEIGHT 100

const char* ssids[]     = {"realme_C11", "Arthi", "realme_C12"};
const char* passwords[] = {"artthhii", "01707275528", "aabbcc112233"};

const char* SERVER_NAME = "http://aquaticfarm.atwebpages.com/sensordata.php";
String PROJECT_API_KEY  = "iloveher143";
int station_id          = 2;

float a_ph    = 7.00;
float a_temp  = 25.00;
float a_level = 80.00;

unsigned long lastMillis = 0;
const long interval      = 5000;

OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature sensors(&oneWire);
Adafruit_ST7735 tft = Adafruit_ST7735(TFT_CS, TFT_DC, TFT_RST);

int baseline = 0;

void setup() {
  Serial.begin(115200);
  sensors.begin();

  pinMode(TRIG, OUTPUT);
  pinMode(ECHO, INPUT);

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

  tft.fillScreen(ST77XX_BLACK);
  tft.setTextColor(ST77XX_GREEN);
  tft.setCursor(10, 40);
  tft.println("Calibrated!");
  delay(1000);

  // WiFi
  tft.fillScreen(ST77XX_BLACK);
  tft.setCursor(10, 40);
  tft.println("Connecting");
  tft.println("WiFi...");
  connectToWiFi();

  tft.fillScreen(ST77XX_BLACK);
}

void loop() {
  // === Ultrasonic ===
  digitalWrite(TRIG, LOW);
  delayMicroseconds(2);
  digitalWrite(TRIG, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG, LOW);

  long duration = pulseIn(ECHO, HIGH);
  float distance = duration * 0.034 / 2;
  int percent = constrain(map(distance, TANK_HEIGHT, 0, 0, 100), 0, 100);

  // === Temp ===
  sensors.requestTemperatures();
  float tempC = sensors.getTempCByIndex(0);

  // === est. pH ===
  int raw = analogRead(WATER_SIG);
  int diff = raw - baseline;
  float pH = 7.0 - map(diff, -500, 500, -20, 20) / 10.0;
  pH = constrain(pH, 5.0, 9.0);

  // === Upload every 5s ===
  if (millis() - lastMillis > interval) {
    if (WiFi.status() == WL_CONNECTED) {
      uploadData(pH, tempC, percent);
    } else {
      connectToWiFi();
    }
    lastMillis = millis();
  }

  // === TFT ===
  tft.fillScreen(ST77XX_BLACK);

  tft.setTextColor(ST77XX_CYAN);
  tft.setTextSize(2);
  tft.setCursor(10, 5);
  tft.print("Water:");
  tft.setTextColor(ST77XX_GREEN);
  tft.setCursor(90, 5);
  tft.print(percent);
  tft.print("%");

  tft.setTextColor(ST77XX_YELLOW);
  tft.setCursor(10, 35);
  tft.print("Temp:");
  tft.setTextColor(ST77XX_WHITE);
  tft.setCursor(90, 35);
  tft.print(tempC, 1);
  tft.print("C");

  tft.setTextColor(ST77XX_MAGENTA);
  tft.setCursor(10, 65);
  tft.print("est.pH:");
  tft.setTextColor(ST77XX_WHITE);
  tft.setTextSize(3);
  tft.setCursor(10, 90);
  tft.print(pH, 1);

  // WiFi status dot
  tft.setTextSize(1);
  tft.setTextColor(WiFi.status() == WL_CONNECTED ? ST77XX_GREEN : ST77XX_RED);
  tft.setCursor(140, 5);
  tft.print(WiFi.status() == WL_CONNECTED ? "W" : "X");

  delay(500);
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
  StaticJsonDocument<256> doc;
  DeserializationError err = deserializeJson(doc, json);
  if (err) return;

  if (strcmp(doc["status"], "ok") == 0) {
    a_ph    = doc["a_ph"]    | a_ph;
    a_temp  = doc["a_temp"]  | a_temp;
    a_level = doc["a_level"] | a_level;
    Serial.printf("[Setpts] pH:%.2f Temp:%.2f Level:%.2f\n", a_ph, a_temp, a_level);
  }
}

void connectToWiFi() {
  int numNetworks = sizeof(ssids) / sizeof(ssids[0]);
  for (int i = 0; i < numNetworks; i++) {
    Serial.printf("[WiFi] Trying: %s\n", ssids[i]);
    WiFi.begin(ssids[i], passwords[i]);
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) {
      delay(500);
      Serial.print(".");
      attempts++;
    }
    if (WiFi.status() == WL_CONNECTED) {
      Serial.printf("\n[WiFi] Connected! IP: %s\n", WiFi.localIP().toString().c_str());
      return;
    }
  }
  Serial.println("\n[WiFi] All networks failed.");
}
