website : http://aquaticfarm.atwebpages.com/

A project made for my school for my juniors. Claude is used for programming. but it works fine. before launching check server files and data base.

esp32_only_display.ino is a test code DO NOT USE IT 

## TFT Display

| ESP32 Pin | TFT Pin |
|-----------|---------|
| G16 | RST |
| G5 | CS |
| G17 | D/C |
| G23 | DIN |
| 3.3V | VCC |
| 3.3V | BL |
| GND | GND |

## Ultrasonic Sensor

| ESP32 Pin | Sensor Pin |
|-----------|------------|
| 5V | VCC |
| GND | GND |
| G25 | Trig |
| G26 | Echo |

## DS18B20

| ESP32 Pin | DS18B20 Pin |
|-----------|-------------|
| 3.3V | VCC |
| GND | GND |
| G13 | Signal |

**Pull-up Resistor:** 4.7KΩ between **G13** and **3.3V**

## Water Level Sensor

| ESP32 Pin | Water Level Pin |
|-----------|-----------------|
| 3.3V | + |
| GND | - |
| G32 | S (Signal) |
