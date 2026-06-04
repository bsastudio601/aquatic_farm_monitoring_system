#include <Adafruit_GFX.h>
#include <Adafruit_ST7735.h>
#include <SPI.h>
#include <OneWire.h>
#include <DallasTemperature.h>

#define TFT_CS   5
#define TFT_DC   17
#define TFT_RST  16

#define TRIG 25
#define ECHO 26
#define ONE_WIRE_BUS 13
#define WATER_SIG 32

#define TANK_HEIGHT 100
#define DRY_THRESHOLD 50

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

  Serial.print("Baseline: ");
  Serial.println(baseline);

  tft.fillScreen(ST77XX_BLACK);
  tft.setTextColor(ST77XX_GREEN);
  tft.setCursor(10, 40);
  tft.println("Calibrated!");
  tft.setCursor(10, 60);
  tft.print("Base: ");
  tft.println(baseline);
  delay(2000);
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

  // === Conductivity → est. pH ===
  int raw = analogRead(WATER_SIG);
  int diff = raw - baseline;

  Serial.print("Water level: "); Serial.print(percent); Serial.println("%");
  Serial.print("Temp: "); Serial.print(tempC); Serial.println(" C");
  Serial.print("Raw conductivity: "); Serial.println(raw);

  // === TFT ===
  tft.fillScreen(ST77XX_BLACK);

  // Water level
  tft.setTextColor(ST77XX_CYAN);
  tft.setTextSize(2);
  tft.setCursor(10, 5);
  tft.print("Water:");
  tft.setTextColor(ST77XX_GREEN);
  tft.setTextSize(2);
  tft.setCursor(90, 5);
  tft.print(percent);
  tft.print("%");

  // Temp
  tft.setTextColor(ST77XX_YELLOW);
  tft.setTextSize(2);
  tft.setCursor(10, 35);
  tft.print("Temp:");
  tft.setTextColor(ST77XX_WHITE);
  tft.setCursor(90, 35);
  tft.print(tempC, 1);
  tft.print("C");

  // pH
  tft.setTextColor(ST77XX_MAGENTA);
  tft.setTextSize(2);
  tft.setCursor(10, 65);
  tft.print("est.pH:");
  tft.setTextColor(ST77XX_WHITE);
  tft.setTextSize(3);
  tft.setCursor(10, 90);

  if (raw < DRY_THRESHOLD) {
    tft.print("---");
    Serial.println("Est. pH: sensor dry");
  } else {
    float pH = 7.0 - map(diff, -500, 500, -20, 20) / 10.0;
    pH = constrain(pH, 5.0, 9.0);
    tft.print(pH, 1);
    Serial.print("Est. pH: "); Serial.println(pH);
  }

  delay(500);
}
