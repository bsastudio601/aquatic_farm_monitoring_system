#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <ArduinoJson.h>

// ----- WiFi Credentials -----
const char* ssids[]     = {"realme_C11", "Arthi", "realme_C12"};
const char* passwords[] = {"artthhii", "01707275528", "aabbcc112233"};

// ----- Server -----
const char* SERVER_NAME = "http://aquaticfarm.atwebpages.com/sensordata.php";
String PROJECT_API_KEY  = "iloveher143";
int station_id          = 1;

// ----- Setpoints (updated from server response) -----
float a_ph    = 7.00;
float a_temp  = 25.00;
float a_level = 80.00;

// ----- Timer -----
unsigned long lastMillis = 0;
const long interval      = 5000;

// ----- Fake sensor state (smooth random drift) -----
float fake_ph    = 7.0;
float fake_temp  = 25.0;
float fake_level = 80.0;

// Returns a random float between lo and hi
float randomFloat(float lo, float hi) {
  return lo + (float)random(0, 10000) / 10000.0 * (hi - lo);
}

void setup() {
  Serial.begin(115200);
  delay(100);
  randomSeed(analogRead(A0)); // floating pin for seed

  Serial.println("\n===== Fish Farm Monitor =====");
  connectToWiFi();
  Serial.println("Ready! Uploading every 5s...\n");
}

void loop() {
  if (WiFi.status() == WL_CONNECTED) {
    if (millis() - lastMillis > interval) {

      // Smooth random drift on fake readings
      fake_ph    = constrain(fake_ph    + randomFloat(-0.05, 0.05), 5.5,  9.0);
      fake_temp  = constrain(fake_temp  + randomFloat(-0.3,  0.3),  20.0, 35.0);
      fake_level = constrain(fake_level + randomFloat(-1.0,  1.0),  30.0, 100.0);

      Serial.println("-----------------------------");
      Serial.printf("[Sensor]  pH: %.2f  Temp: %.2f C  Level: %.2f%%\n",
                    fake_ph, fake_temp, fake_level);

      uploadData(fake_ph, fake_temp, fake_level);

      lastMillis = millis();
    }
  } else {
    Serial.println("[WiFi] Connection lost, reconnecting...");
    connectToWiFi();
  }

  delay(100);
}

void uploadData(float ph, float temp, float level) {
  WiFiClient client;
  HTTPClient http;

  String postData = "api_key=" + PROJECT_API_KEY;
  postData += "&station_id=" + String(station_id);
  postData += "&ph="    + String(ph,    2);
  postData += "&temp="  + String(temp,  2);
  postData += "&level=" + String(level, 2);
  // postData += "&extra=" + String("{\"do2\":6.1}"); // uncomment when needed

  Serial.println("[HTTP]    Sending POST...");
  http.begin(client, SERVER_NAME);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");

  int httpCode = http.POST(postData);
  Serial.printf("[HTTP]    Response code: %d\n", httpCode);

  if (httpCode == 200) {
    String payload = http.getString();
    Serial.println("[HTTP]    Payload: " + payload);
    parseSetpoints(payload);
  } else {
    Serial.printf("[HTTP]    Upload failed (code %d)\n", httpCode);
  }

  http.end();
}

void parseSetpoints(String json) {
  StaticJsonDocument<256> doc;
  DeserializationError err = deserializeJson(doc, json);

  if (err) {
    Serial.print("[JSON]    Parse error: ");
    Serial.println(err.c_str());
    return;
  }

  if (strcmp(doc["status"], "ok") == 0) {
    a_ph    = doc["a_ph"]    | a_ph;
    a_temp  = doc["a_temp"]  | a_temp;
    a_level = doc["a_level"] | a_level;

    Serial.printf("[Setpts]  pH: %.2f  Temp: %.2f C  Level: %.2f%%\n",
                  a_ph, a_temp, a_level);
  } else {
    Serial.println("[Setpts]  Server returned error status");
  }
}

void connectToWiFi() {
  int numNetworks = sizeof(ssids) / sizeof(ssids[0]);
  for (int i = 0; i < numNetworks; i++) {
    Serial.printf("[WiFi]    Trying: %s\n", ssids[i]);
    WiFi.begin(ssids[i], passwords[i]);

    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) {
      delay(500);
      Serial.print(".");
      attempts++;
    }
    Serial.println();

    if (WiFi.status() == WL_CONNECTED) {
      Serial.printf("[WiFi]    Connected! IP: %s\n", WiFi.localIP().toString().c_str());
      return;
    }
    Serial.printf("[WiFi]    Failed: %s\n", ssids[i]);
  }

  Serial.println("[WiFi]    All networks failed. Halting.");
  while (1) delay(1000);
}
