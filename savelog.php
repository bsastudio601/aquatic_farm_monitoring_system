<?php
require 'config.php';
header('Content-Type: application/json');
$db = connectDB();

$action = $_POST['action'] ?? '';

if ($action === 'save') {
    $sid       = intval($_POST['station_id']);
    $date      = $_POST['log_date'];
    $work      = $_POST['work_done'];
    $amount    = $_POST['amount'] ?? '';
    $situation = $_POST['situation'] ?? '';
    $cost      = floatval($_POST['cost'] ?? 0);
    $earning   = floatval($_POST['earning'] ?? 0);

    $stmt = $db->prepare("INSERT INTO daily_logs (station_id, log_date, work_done, amount, situation, cost, earning) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("issssdd", $sid, $date, $work, $amount, $situation, $cost, $earning);
    echo $stmt->execute()
        ? json_encode(['status'=>'ok'])
        : json_encode(['status'=>'error','message'=>$db->error]);
    $stmt->close();

} elseif ($action === 'delete') {
    $id = intval($_POST['id']);
    $stmt = $db->prepare("DELETE FROM daily_logs WHERE id=?");
    $stmt->bind_param("i", $id);
    echo $stmt->execute()
        ? json_encode(['status'=>'ok'])
        : json_encode(['status'=>'error','message'=>$db->error]);
    $stmt->close();

} elseif ($action === 'fetch') {
    $sid   = intval($_POST['station_id']);
    $from  = $_POST['from'];
    $to    = $_POST['to'];
    $stmt  = $db->prepare("SELECT * FROM daily_logs WHERE station_id=? AND log_date BETWEEN ? AND ? ORDER BY log_date DESC");
    $stmt->bind_param("iss", $sid, $from, $to);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['status'=>'ok','logs'=>$rows]);
    $stmt->close();
}

$db->close();
