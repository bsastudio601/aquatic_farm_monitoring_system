<?php
require 'config.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db = connectDB();

// --- CREATE preset ---
if ($action === 'create') {
    $fields = ['name','ph_min','ph_max','temp_min','temp_max','level_min','level_max'];
    foreach ($fields as $f) {
        if (!isset($_POST[$f])) {
            echo json_encode(['status'=>'error','message'=>"Missing: $f"]); exit;
        }
    }
    $name      = trim($_POST['name']);
    $ph_min    = floatval($_POST['ph_min']);
    $ph_max    = floatval($_POST['ph_max']);
    $temp_min  = floatval($_POST['temp_min']);
    $temp_max  = floatval($_POST['temp_max']);
    $level_min = floatval($_POST['level_min']);
    $level_max = floatval($_POST['level_max']);
    $image_url = isset($_POST['image_url']) ? trim($_POST['image_url']) : null;

    $stmt = $db->prepare("
        INSERT INTO fish_presets (name, ph_min, ph_max, temp_min, temp_max, level_min, level_max, image_url)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sdddddds", $name, $ph_min, $ph_max, $temp_min, $temp_max, $level_min, $level_max, $image_url);
    if ($stmt->execute()) {
        echo json_encode(['status'=>'ok', 'id'=>$db->insert_id, 'name'=>$name, 'image_url'=>$image_url]);
    } else {
        echo json_encode(['status'=>'error','message'=>$stmt->error]);
    }
    $stmt->close();

// --- DELETE preset ---
} elseif ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id === 0) { echo json_encode(['status'=>'error','message'=>'Invalid id']); exit; }

    // Remove all station assignments first
    $d = $db->prepare("DELETE FROM station_presets WHERE preset_id = ?");
    $d->bind_param("i", $id);
    $d->execute();
    $d->close();

    // Then delete the preset itself
    $stmt = $db->prepare("DELETE FROM fish_presets WHERE id = ?");
    $stmt->bind_param("i", $id);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => $ok ? 'ok' : 'error', 'message' => $ok ? '' : $db->error]);

// --- ASSIGN presets to station (full replace) ---
} elseif ($action === 'assign') {
    $station_id = intval($_POST['station_id'] ?? 0);
    $raw        = $_POST['preset_ids'] ?? '';
    $preset_ids = $raw !== '' ? array_filter(array_map('intval', explode(',', $raw))) : [];

    $del = $db->prepare("DELETE FROM station_presets WHERE station_id = ?");
    $del->bind_param("i", $station_id);
    $del->execute();
    $del->close();

    if (!empty($preset_ids)) {
        $ins = $db->prepare("INSERT INTO station_presets (station_id, preset_id) VALUES (?, ?)");
        foreach ($preset_ids as $pid) {
            $ins->bind_param("ii", $station_id, $pid);
            $ins->execute();
        }
        $ins->close();
    }

    // Recalculate averaged setpoints and write to station_setpoints
    if (!empty($preset_ids)) {
        $placeholders = implode(',', array_fill(0, count($preset_ids), '?'));
        $types = str_repeat('i', count($preset_ids));
        $avg_stmt = $db->prepare("
            SELECT AVG(ph_min) as ph_min, AVG(ph_max) as ph_max,
                   AVG(temp_min) as temp_min, AVG(temp_max) as temp_max,
                   AVG(level_min) as level_min, AVG(level_max) as level_max
            FROM fish_presets WHERE id IN ($placeholders)
        ");
        $avg_stmt->bind_param($types, ...$preset_ids);
        $avg_stmt->execute();
        $avg = $avg_stmt->get_result()->fetch_assoc();
        $avg_stmt->close();

        $a_ph    = round(($avg['ph_min']    + $avg['ph_max'])    / 2, 2);
        $a_temp  = round(($avg['temp_min']  + $avg['temp_max'])  / 2, 2);
        $a_level = round(($avg['level_min'] + $avg['level_max']) / 2, 2);

        $up = $db->prepare("
            INSERT INTO station_setpoints (station_id, a_ph, a_temp, a_level, source)
            VALUES (?, ?, ?, ?, 'preset')
            ON DUPLICATE KEY UPDATE a_ph=VALUES(a_ph), a_temp=VALUES(a_temp), a_level=VALUES(a_level), source='preset'
        ");
        $up->bind_param("iddd", $station_id, $a_ph, $a_temp, $a_level);
        $up->execute();
        $up->close();
    }

    echo json_encode(['status'=>'ok']);

// --- UNASSIGN single preset from station ---
} elseif ($action === 'unassign') {
    $station_id = intval($_POST['station_id'] ?? 0);
    $preset_id  = intval($_POST['preset_id']  ?? 0);

    $del = $db->prepare("DELETE FROM station_presets WHERE station_id = ? AND preset_id = ?");
    $del->bind_param("ii", $station_id, $preset_id);
    $del->execute();
    $del->close();

    // Recalculate averaged setpoints after removal
    $pr = $db->prepare("
        SELECT fp.ph_min, fp.ph_max, fp.temp_min, fp.temp_max, fp.level_min, fp.level_max
        FROM fish_presets fp
        INNER JOIN station_presets sp ON fp.id = sp.preset_id
        WHERE sp.station_id = ?
    ");
    $pr->bind_param("i", $station_id);
    $pr->execute();
    $remaining = $pr->get_result()->fetch_all(MYSQLI_ASSOC);
    $pr->close();

    if (!empty($remaining)) {
        $count = count($remaining);
        $ph_min = $ph_max = $temp_min = $temp_max = $level_min = $level_max = 0;
        foreach ($remaining as $p) {
            $ph_min += $p['ph_min']; $ph_max += $p['ph_max'];
            $temp_min += $p['temp_min']; $temp_max += $p['temp_max'];
            $level_min += $p['level_min']; $level_max += $p['level_max'];
        }
        $a_ph    = round(($ph_min/$count + $ph_max/$count) / 2, 2);
        $a_temp  = round(($temp_min/$count + $temp_max/$count) / 2, 2);
        $a_level = round(($level_min/$count + $level_max/$count) / 2, 2);

        $up = $db->prepare("
            INSERT INTO station_setpoints (station_id, a_ph, a_temp, a_level, source)
            VALUES (?, ?, ?, ?, 'preset')
            ON DUPLICATE KEY UPDATE a_ph=VALUES(a_ph), a_temp=VALUES(a_temp), a_level=VALUES(a_level), source='preset'
        ");
        $up->bind_param("iddd", $station_id, $a_ph, $a_temp, $a_level);
        $up->execute();
        $up->close();
    }
    echo json_encode(['status'=>'ok']);

// --- SAVE manual override ---
} elseif ($action === 'manual') {
    $station_id = intval($_POST['station_id'] ?? 0);
    $a_ph       = floatval($_POST['a_ph']);
    $a_temp     = floatval($_POST['a_temp']);
    $a_level    = floatval($_POST['a_level']);

    $stmt = $db->prepare("
        INSERT INTO station_setpoints (station_id, a_ph, a_temp, a_level, source)
        VALUES (?, ?, ?, ?, 'manual')
        ON DUPLICATE KEY UPDATE a_ph=VALUES(a_ph), a_temp=VALUES(a_temp), a_level=VALUES(a_level), source='manual'
    ");
    $stmt->bind_param("iddd", $station_id, $a_ph, $a_temp, $a_level);
    echo json_encode(['status' => $stmt->execute() ? 'ok' : 'error']);
    $stmt->close();

// --- CLEAR manual override ---
} elseif ($action === 'clear_manual') {
    $station_id = intval($_POST['station_id'] ?? 0);
    $stmt = $db->prepare("DELETE FROM station_setpoints WHERE station_id = ?");
    $stmt->bind_param("i", $station_id);
    echo json_encode(['status' => $stmt->execute() ? 'ok' : 'error']);
    $stmt->close();

} elseif ($action === 'rename') {
    $station_id = intval($_POST['station_id'] ?? 0);
    $name       = trim($_POST['name'] ?? '');
    $stmt = $db->prepare("
        INSERT INTO stations (station_id, name) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE name = VALUES(name)
    ");
    $stmt->bind_param("is", $station_id, $name);
    echo json_encode(['status' => $stmt->execute() ? 'ok' : 'error']);
    $stmt->close();

} else {
    echo json_encode(['status'=>'error','message'=>'Unknown action: '.$action]);
}

$db->close();
