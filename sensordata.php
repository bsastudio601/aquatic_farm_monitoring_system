<?php
include_once('config.php');
header('Content-Type: application/json');

$correct_api_key = "iloveher143";

$required = ['api_key', 'station_id', 'ph', 'temp', 'level'];
foreach ($required as $f) {
    if (!isset($_POST[$f])) {
        echo json_encode(['status'=>'error',"message"=>"Missing $f"]);
        exit;
    }
}

if ($_POST['api_key'] !== $correct_api_key) {
    echo json_encode(['status'=>'error','message'=>'Invalid API key']);
    exit;
}

$db = connectDB();

$station_id = intval($_POST['station_id']);
$ph = floatval($_POST['ph']);
$temp = floatval($_POST['temp']);
$level = floatval($_POST['level']);
$extra = $_POST['extra'] ?? null;

/* ---------- INSERT SENSOR DATA ---------- */
$stmt = $db->prepare("
    INSERT INTO sensor_data (station_id, ph, temp, level, extra, recorded_at)
    VALUES (?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param("iddds", $station_id, $ph, $temp, $level, $extra);
$stmt->execute();
$insert_id = $db->insert_id;
$stmt->close();

/* ---------- LOAD SETPOINT SOURCE ---------- */
$manual = null;

$man = $db->prepare("
    SELECT a_ph, a_temp, a_level, source
    FROM station_setpoints
    WHERE station_id = ?
");
$man->bind_param("i", $station_id);
$man->execute();
$manual = $man->get_result()->fetch_assoc();
$man->close();

/* ---------- DEFAULT MIDPOINTS ---------- */
$ph_min = 7.0; $ph_max = 8.0;
$temp_min = 25.0; $temp_max = 30.0;
$level_min = 60.0; $level_max = 90.0;

/* ---------- APPLY MANUAL OVERRIDE ---------- */
if ($manual && $manual['source'] === 'manual') {
    $ph_mid    = floatval($manual['a_ph']);
    $temp_mid  = floatval($manual['a_temp']);
    $level_mid = floatval($manual['a_level']);
} else {

    /* ---------- PULL PRESETS ---------- */
    $pr = $db->prepare("
        SELECT fp.ph_min, fp.ph_max, fp.temp_min, fp.temp_max, fp.level_min, fp.level_max
        FROM fish_presets fp
        INNER JOIN station_presets sp ON fp.id = sp.preset_id
        WHERE sp.station_id = ?
    ");
    $pr->bind_param("i", $station_id);
    $pr->execute();
    $presets = $pr->get_result()->fetch_all(MYSQLI_ASSOC);
    $pr->close();

    if (!empty($presets)) {
        $ph_min = $ph_max = $temp_min = $temp_max = $level_min = $level_max = 0;

        foreach ($presets as $p) {
            $ph_min += $p['ph_min'];
            $ph_max += $p['ph_max'];
            $temp_min += $p['temp_min'];
            $temp_max += $p['temp_max'];
            $level_min += $p['level_min'];
            $level_max += $p['level_max'];
        }

        $count = count($presets);

        $ph_mid    = ($ph_min / $count + $ph_max / $count) / 2;
        $temp_mid  = ($temp_min / $count + $temp_max / $count) / 2;
        $level_mid = ($level_min / $count + $level_max / $count) / 2;

    } else {
        $ph_mid = ($ph_min + $ph_max) / 2;
        $temp_mid = ($temp_min + $temp_max) / 2;
        $level_mid = ($level_min + $level_max) / 2;
    }
}

/* ---------- ENSURE SETPOINT ROW EXISTS ---------- */
$db->query("
    INSERT INTO station_setpoints (station_id, a_ph, a_temp, a_level, source)
    VALUES ($station_id, 7, 28, 80, 'preset')
    ON DUPLICATE KEY UPDATE station_id=station_id
");

/* ---------- RESPONSE TO ESP ---------- */
echo json_encode([
    'status'  => 'ok',
    'a_ph'    => number_format($ph_mid, 2, '.', ''),
    'a_temp'  => number_format($temp_mid, 2, '.', ''),
    'a_level' => number_format($level_mid, 2, '.', '')
]);

$db->close();
