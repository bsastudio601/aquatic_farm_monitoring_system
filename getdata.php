<?php
require 'config.php';

header('Content-Type: application/json');

$station_id = isset($_GET['station_id']) ? intval($_GET['station_id']) : 1;

$db = connectDB();

// --- Latest reading ---
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

// --- Effective range: manual override OR averaged presets ---
$sp = $db->prepare("SELECT a_ph, a_temp, a_level, a_extra, source, updated_at FROM station_setpoints WHERE station_id = ?");
$sp->bind_param("i", $station_id);
$sp->execute();
$sp_row = $sp->get_result()->fetch_assoc();
$sp->close();

$effective = null;

// Only treat as manual if source column explicitly says 'manual'
$is_manual = ($sp_row && ($sp_row['source'] ?? '') === 'manual');

if ($is_manual) {
    $manual = $sp_row;
    // Manual override — express as min/max centered on target value
    $effective = [
        'ph_min'    => floatval($manual['a_ph'])    - 0.5,
        'ph_max'    => floatval($manual['a_ph'])    + 0.5,
        'temp_min'  => floatval($manual['a_temp'])  - 2.0,
        'temp_max'  => floatval($manual['a_temp'])  + 2.0,
        'level_min' => floatval($manual['a_level']) - 10.0,
        'level_max' => floatval($manual['a_level']) + 10.0,
        'source'    => 'manual',
        'a_ph'      => floatval($manual['a_ph']),
        'a_temp'    => floatval($manual['a_temp']),
        'a_level'   => floatval($manual['a_level']),
        'updated_at'=> $manual['updated_at']
    ];
} else {
    // No manual — average assigned presets
    $pr = $db->prepare("
        SELECT fp.id, fp.name,
               fp.ph_min, fp.ph_max,
               fp.temp_min, fp.temp_max,
               fp.level_min, fp.level_max
        FROM fish_presets fp
        INNER JOIN station_presets sp ON fp.id = sp.preset_id
        WHERE sp.station_id = ?
    ");
    $pr->bind_param("i", $station_id);
    $pr->execute();
    $presets = $pr->get_result()->fetch_all(MYSQLI_ASSOC);
    $pr->close();

    if (!empty($presets)) {
        $preset_mode = true;
        $count = count($presets);
        $ph_min = $ph_max = $temp_min = $temp_max = $level_min = $level_max = 0;
        foreach ($presets as $p) {
            $ph_min    += $p['ph_min'];
            $ph_max    += $p['ph_max'];
            $temp_min  += $p['temp_min'];
            $temp_max  += $p['temp_max'];
            $level_min += $p['level_min'];
            $level_max += $p['level_max'];
        }
        $effective = [
            'ph_min'    => round($ph_min    / $count, 2),
            'ph_max'    => round($ph_max    / $count, 2),
            'temp_min'  => round($temp_min  / $count, 2),
            'temp_max'  => round($temp_max  / $count, 2),
            'level_min' => round($level_min / $count, 2),
            'level_max' => round($level_max / $count, 2),
            'source'    => 'presets',
            'presets'   => $presets
        ];
    }
}

// --- Assigned preset IDs for this station (for modal UI) ---
$asgn = $db->prepare("SELECT preset_id FROM station_presets WHERE station_id = ?");
$asgn->bind_param("i", $station_id);
$asgn->execute();
$assigned_ids = array_column($asgn->get_result()->fetch_all(MYSQLI_ASSOC), 'preset_id');
$asgn->close();

// --- All global presets (for preset picker in modal) ---
$all_presets = $db->query("SELECT * FROM fish_presets ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// --- History ---
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
    'latest'       => $latest,
    'effective'    => $effective,
    'assigned_ids' => $assigned_ids,
    'all_presets'  => $all_presets,
    'history'      => $rows
]);
