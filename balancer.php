<?php
require 'config.php';

$db = connectDB();

// --- Build effective range for every station that has unchecked rows ---
// First get all distinct station_ids with NULL status rows
$stations_result = $db->query("
    SELECT DISTINCT station_id FROM sensor_data WHERE status IS NULL
");

if (!$stations_result) {
    die("Query failed: " . $db->error);
}

$station_ids = [];
while ($row = $stations_result->fetch_assoc()) {
    $station_ids[] = intval($row['station_id']);
}

if (empty($station_ids)) {
    echo "Nothing to check.\n";
    $db->close();
    exit;
}

// --- For each station, resolve its effective range ---
$ranges = []; // station_id => [ph_min, ph_max, temp_min, temp_max, level_min, level_max, source]

foreach ($station_ids as $sid) {
    // Check manual override first
    $man = $db->prepare("SELECT a_ph, a_temp, a_level, source FROM station_setpoints WHERE station_id = ?");
    $man->bind_param("i", $sid);
    $man->execute();
    $sp_row = $man->get_result()->fetch_assoc();
    $man->close();

    if ($sp_row && ($sp_row['source'] ?? '') === 'manual') {
        $ranges[$sid] = [
            'ph_min'    => floatval($sp_row['a_ph'])    - 0.5,
            'ph_max'    => floatval($sp_row['a_ph'])    + 0.5,
            'temp_min'  => floatval($sp_row['a_temp'])  - 2.0,
            'temp_max'  => floatval($sp_row['a_temp'])  + 2.0,
            'level_min' => floatval($sp_row['a_level']) - 10.0,
            'level_max' => floatval($sp_row['a_level']) + 10.0,
            'source'    => 'manual'
        ];
        continue;
    }

    // Otherwise average assigned presets
    $pr = $db->prepare("
        SELECT fp.ph_min, fp.ph_max, fp.temp_min, fp.temp_max, fp.level_min, fp.level_max
        FROM fish_presets fp
        INNER JOIN station_presets sp ON fp.id = sp.preset_id
        WHERE sp.station_id = ?
    ");
    $pr->bind_param("i", $sid);
    $pr->execute();
    $presets = $pr->get_result()->fetch_all(MYSQLI_ASSOC);
    $pr->close();

    if (!empty($presets)) {
        $count = count($presets);
        $ph_min = $ph_max = $temp_min = $temp_max = $level_min = $level_max = 0;
        foreach ($presets as $p) {
            $ph_min    += floatval($p['ph_min']);
            $ph_max    += floatval($p['ph_max']);
            $temp_min  += floatval($p['temp_min']);
            $temp_max  += floatval($p['temp_max']);
            $level_min += floatval($p['level_min']);
            $level_max += floatval($p['level_max']);
        }
        $ranges[$sid] = [
            'ph_min'    => $ph_min    / $count,
            'ph_max'    => $ph_max    / $count,
            'temp_min'  => $temp_min  / $count,
            'temp_max'  => $temp_max  / $count,
            'level_min' => $level_min / $count,
            'level_max' => $level_max / $count,
            'source'    => 'preset'
        ];
    } else {
        // No preset, no manual — mark as no_preset, skip flagging
        $ranges[$sid] = ['source' => 'none'];
    }
}

// --- Now process all unchecked rows using per-station ranges ---
$result = $db->query("
    SELECT id, station_id, ph, temp, level
    FROM sensor_data
    WHERE status IS NULL
");

$checked = 0;
$flagged = 0;

while ($row = $result->fetch_assoc()) {
    $sid   = intval($row['station_id']);
    $range = $ranges[$sid] ?? ['source' => 'none'];

    if ($range['source'] === 'none') {
        $status = 'no_preset';
    } else {
        $flags = [];
        if ($row['ph']    < $range['ph_min']    || $row['ph']    > $range['ph_max'])    $flags[] = 'ph';
        if ($row['temp']  < $range['temp_min']  || $row['temp']  > $range['temp_max'])  $flags[] = 'temp';
        if ($row['level'] < $range['level_min'] || $row['level'] > $range['level_max']) $flags[] = 'level';
        $status = empty($flags) ? 'ok' : 'alert:' . implode(',', $flags);
    }

    $stmt = $db->prepare("UPDATE sensor_data SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $row['id']);
    $stmt->execute();
    $stmt->close();

    $checked++;
    if (!empty($flags ?? [])) $flagged++;
}

$db->close();
echo "Done. Stations checked: " . count($station_ids) . " | Rows checked: $checked | Flagged: $flagged\n";
?>
