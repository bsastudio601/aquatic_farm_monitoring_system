<?php
require 'config.php';

// --- Acceptable ranges ---
const PH_MIN    = 7.0;
const PH_MAX    = 8.0;
const TEMP_MIN  = 25.0;
const TEMP_MAX  = 30.0;
const LEVEL_MIN = 60.0;
const LEVEL_MAX = 90.0;

$db = connectDB();

// Fetch all rows that haven't been checked yet (status IS NULL)
$result = $db->query("
    SELECT id, station_id, ph, temp, level
    FROM sensor_data
    WHERE status IS NULL
");

if (!$result) {
    die("Query failed: " . $db->error);
}

$checked = 0;
$flagged = 0;

while ($row = $result->fetch_assoc()) {
    $flags = [];

    if ($row['ph']    < PH_MIN    || $row['ph']    > PH_MAX)    $flags[] = "ph";
    if ($row['temp']  < TEMP_MIN  || $row['temp']  > TEMP_MAX)  $flags[] = "temp";
    if ($row['level'] < LEVEL_MIN || $row['level'] > LEVEL_MAX) $flags[] = "level";

    $status = empty($flags) ? "ok" : "alert:" . implode(",", $flags);

    $stmt = $db->prepare("UPDATE sensor_data SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $row['id']);
    $stmt->execute();
    $stmt->close();

    $checked++;
    if (!empty($flags)) $flagged++;
}

$db->close();

echo "Done. Checked: $checked | Flagged: $flagged\n";
?>
