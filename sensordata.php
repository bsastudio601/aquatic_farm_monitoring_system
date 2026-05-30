<?php
include_once('config.php');

header('Content-Type: application/json');

$correct_api_key = "iloveher143";

// --- Validate required fields ---
$required = ['api_key', 'station_id', 'ph', 'temp', 'level'];
foreach ($required as $field) {
    if (!isset($_POST[$field])) {
        echo json_encode(['status' => 'error', 'message' => "Missing field: $field"]);
        exit;
    }
}

$api_key    = $_POST['api_key'];
$station_id = intval($_POST['station_id']);
$ph         = floatval($_POST['ph']);
$temp       = floatval($_POST['temp']);
$level      = floatval($_POST['level']);
$extra      = isset($_POST['extra']) ? $_POST['extra'] : null;

if ($extra !== null) {
    json_decode($extra);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON in extra field']);
        exit;
    }
}

if ($api_key !== $correct_api_key) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid API key']);
    exit;
}

$db = connectDB();

// --- Insert sensor reading ---
$stmt = $db->prepare("
    INSERT INTO sensor_data (station_id, ph, temp, level, extra, recorded_at)
    VALUES (?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param("iddds", $station_id, $ph, $temp, $level, $extra);

if (!$stmt->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Insert failed: ' . $stmt->error]);
    $stmt->close();
    $db->close();
    exit;
}
$inserted_id = $db->insert_id;
$stmt->close();

// --- Determine effective range ---
// Check source in station_setpoints: only 'manual' uses single-value override
$ph_min = $ph_max = $temp_min = $temp_max = $level_min = $level_max = null;
$source = 'fallback';

$man = $db->prepare("SELECT a_ph, a_temp, a_level, source FROM station_setpoints WHERE station_id = ?");
$man->bind_param("i", $station_id);
$man->execute();
$sp_row = $man->get_result()->fetch_assoc();
$man->close();

if ($sp_row && ($sp_row['source'] ?? '') === 'manual') {
    // Manual override — center value ± tolerance
    $source    = 'manual';
    $ph_min    = floatval($sp_row['a_ph'])    - 0.5;
    $ph_max    = floatval($sp_row['a_ph'])    + 0.5;
    $temp_min  = floatval($sp_row['a_temp'])  - 2.0;
    $temp_max  = floatval($sp_row['a_temp'])  + 2.0;
    $level_min = floatval($sp_row['a_level']) - 10.0;
    $level_max = floatval($sp_row['a_level']) + 10.0;
} else {
    // Use actual preset min/max ranges (averaged if multiple)
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
        $source = 'preset';
        $count  = count($presets);
        $ph_min = $ph_max = $temp_min = $temp_max = $level_min = $level_max = 0;
        foreach ($presets as $p) {
            $ph_min    += floatval($p['ph_min']);
            $ph_max    += floatval($p['ph_max']);
            $temp_min  += floatval($p['temp_min']);
            $temp_max  += floatval($p['temp_max']);
            $level_min += floatval($p['level_min']);
            $level_max += floatval($p['level_max']);
        }
        $ph_min    /= $count; $ph_max    /= $count;
        $temp_min  /= $count; $temp_max  /= $count;
        $level_min /= $count; $level_max /= $count;
    } else {
        // No preset assigned and no manual override — mark as unchecked, don't flag
        $source = 'none';
    }
}

// --- Flag only if we have a valid range ---
if ($source !== 'none' && $source !== 'fallback') {
    $flags = [];
    if ($ph    < $ph_min    || $ph    > $ph_max)    $flags[] = 'ph';
    if ($temp  < $temp_min  || $temp  > $temp_max)  $flags[] = 'temp';
    if ($level < $level_min || $level > $level_max) $flags[] = 'level';
    $status = empty($flags) ? 'ok' : 'alert:' . implode(',', $flags);
} else {
    $status = 'no_preset'; // no range defined — don't flag
}

$flag_stmt = $db->prepare("UPDATE sensor_data SET status = ? WHERE id = ?");
$flag_stmt->bind_param("si", $status, $inserted_id);
$flag_stmt->execute();
$flag_stmt->close();

$db->close();

// --- Return full range to ESP ---
$response = [
    'status'    => 'ok',
    'source'    => $source,
    'a_ph'      => $ph_min !== null ? round(($ph_min + $ph_max) / 2, 2) : null,
    'a_temp'    => $temp_min !== null ? round(($temp_min + $temp_max) / 2, 2) : null,
    'a_level'   => $level_min !== null ? round(($level_min + $level_max) / 2, 2) : null,
    'ph_min'    => $ph_min !== null ? round($ph_min, 2) : null,
    'ph_max'    => $ph_max !== null ? round($ph_max, 2) : null,
    'temp_min'  => $temp_min !== null ? round($temp_min, 2) : null,
    'temp_max'  => $temp_max !== null ? round($temp_max, 2) : null,
    'level_min' => $level_min !== null ? round($level_min, 2) : null,
    'level_max' => $level_max !== null ? round($level_max, 2) : null,
    'flag_status' => $status, // debug: what status was written to DB
];
echo json_encode($response);
