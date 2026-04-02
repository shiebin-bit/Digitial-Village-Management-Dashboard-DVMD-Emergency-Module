<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once('../../includes/dbconnect.php');
date_default_timezone_set('Asia/Kuala_Lumpur');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit();
}

if (
    !isset($_POST['villager_id']) ||
    !isset($_POST['village_id']) ||
    !isset($_POST['incident_type']) ||
    !isset($_POST['urgency_level']) ||
    !isset($_POST['lat']) ||
    !isset($_POST['lng']) ||
    !isset($_POST['image'])
) {
    echo json_encode(['success' => false, 'message' => 'Missing Parameters']);
    exit();
}

$villager_id = $_POST['villager_id'];
$village_id  = $_POST['village_id'];
$type        = $_POST['incident_type'];
$urgency     = $_POST['urgency_level'];
$lat         = $_POST['lat'];
$lng         = $_POST['lng'];
$imageData   = $_POST['image'];
$status  = "Pending";

// ===============================
// 🚨 5-MINUTE SOS ANTI-SPAM CHECK
// ===============================
$cooldownSeconds = 300; // 5 minutes

$checkStmt = $conn->prepare("
    SELECT created_at
    FROM tbl_sos
    WHERE villager_id = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$checkStmt->bind_param("i", $villager_id);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($row = $result->fetch_assoc()) {
    $lastTime = strtotime($row['created_at']);
    $now = time();

    if (($now - $lastTime) < $cooldownSeconds) {
        $checkStmt->close();
        echo json_encode([
            'success' => false,
            'message' => 'You can only send SOS once every 5 minutes.'
        ]);
        exit();
    }
}
$checkStmt->close();
// ===============================

$stmt = $conn->prepare(
    "INSERT INTO tbl_sos 
    (villager_id, village_id, type, latitude, longitude, urgency_level, status) 
    VALUES (?, ?, ?, ?, ?, ?, ?)"
);

$stmt->bind_param(
    "iisddss",
    $villager_id,
    $village_id,
    $type,
    $lat,
    $lng,
    $urgency,
    $status
);

if ($stmt->execute()) {
    $SOS_id = $stmt->insert_id;

    if (!empty($imageData)) {
        $decodedImage = base64_decode($imageData);
        $path = "../assets/images/";
        $imageName = "sos_" . $SOS_id . ".png";
        $fullPath = $path . $imageName;

        if(file_put_contents($fullPath, $decodedImage)){
            $stmtImg = $conn->prepare("UPDATE tbl_sos SET image = ? WHERE id = ?");
            $stmtImg->bind_param("si", $imageName, $SOS_id);
            $stmtImg->execute();
            $stmtImg->close();
        }
    }

    echo json_encode(['success' => true, 'message' => 'SOS sent successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>