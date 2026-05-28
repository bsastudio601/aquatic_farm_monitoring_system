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
$extra      = isset($_POST['extra']) ? $_POST['extra'] : null; // optional JSON string

// Validate the extra field is valid JSON if provided
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
$stmt->close();

// --- Fetch current setpoints for this station ---
// If no setpoint row exists yet, create a default one
$sp_stmt = $db->prepare("
    INSERT INTO station_setpoints (station_id, a_ph, a_temp, a_level)
    VALUES (?, 7.00, 28.00, 80.00)
    ON DUPLICATE KEY UPDATE station_id = station_id
");
$sp_stmt->bind_param("i", $station_id);
$sp_stmt->execute();
$sp_stmt->close();

$get_stmt = $db->prepare("
    SELECT a_ph, a_temp, a_level, a_extra
    FROM station_setpoints
    WHERE station_id = ?
");
$get_stmt->bind_param("i", $station_id);
$get_stmt->execute();
$result = $get_stmt->get_result();
$setpoints = $result->fetch_assoc();
$get_stmt->close();
$db->close();

// --- Return setpoints to ESP32 ---
echo json_encode([
    'status'  => 'ok',
    'a_ph'    => floatval($setpoints['a_ph']),
    'a_temp'  => floatval($setpoints['a_temp']),
    'a_level' => floatval($setpoints['a_level']),
    'a_extra' => $setpoints['a_extra'] ? json_decode($setpoints['a_extra'], true) : null
]);
?>
