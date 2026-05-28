<?php
require 'config.php';

header('Content-Type: application/json');

$station_id = isset($_GET['station_id']) ? intval($_GET['station_id']) : 1;

$db = connectDB();

// --- Latest reading for this station ---
$stmt = $db->prepare("
    SELECT id, ph, temp, level, extra, status, recorded_at
    FROM sensor_data
    WHERE station_id = ?
    ORDER BY recorded_at DESC
    LIMIT 1
");
$stmt->bind_param("i", $station_id);
$stmt->execute();
$latest = $stmt->get_result()->fetch_assoc();
$stmt->close();

// --- Current setpoints ---
$sp = $db->prepare("
    SELECT a_ph, a_temp, a_level, a_extra, updated_at
    FROM station_setpoints
    WHERE station_id = ?
");
$sp->bind_param("i", $station_id);
$sp->execute();
$setpoints = $sp->get_result()->fetch_assoc();
$sp->close();

// --- Last 30 rows for history table ---
$hist = $db->prepare("
    SELECT id, ph, temp, level, status, recorded_at
    FROM sensor_data
    WHERE station_id = ?
    ORDER BY recorded_at DESC
    LIMIT 30
");
$hist->bind_param("i", $station_id);
$hist->execute();
$rows = $hist->get_result()->fetch_all(MYSQLI_ASSOC);
$hist->close();

$db->close();

echo json_encode([
    'latest'    => $latest,
    'setpoints' => $setpoints,
    'history'   => $rows
]);
?>
