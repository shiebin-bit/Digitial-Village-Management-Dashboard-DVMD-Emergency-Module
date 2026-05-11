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
    !isset($_POST['description']) ||
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
$urgency_level = $_POST['urgency_level'];
$description = $_POST['description'];
$lat         = $_POST['lat'];
$lng         = $_POST['lng'];
$imageData   = $_POST['image'];
$status  = "Pending";

// ===============================
// 🚫 15-MINUTE ANTI-SPAM CHECK
// ===============================
$cooldownSeconds = 900; // 15 minutes

$checkStmt = $conn->prepare("
    SELECT date_created 
    FROM tbl_incidents 
    WHERE villager_id = ?
    ORDER BY date_created DESC
    LIMIT 1
");
$checkStmt->bind_param("i", $villager_id);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($row = $result->fetch_assoc()) {
    $lastReportTime = strtotime($row['date_created']);
    $currentTime = time();

    if (($currentTime - $lastReportTime) < $cooldownSeconds) {
        echo json_encode([
            'success' => false,
            'message' => 'You can only submit one incident report every 15 minutes.'
        ]);
        exit();
    }
}
$checkStmt->close();
// ===============================

$stmt = $conn->prepare(
    "INSERT INTO tbl_incidents 
    (villager_id, village_id, type, description, latitude, longitude, urgency_level, status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);

$stmt->bind_param(
    "iissddss",
    $villager_id,
    $village_id,
    $type,
    $description,
    $lat,
    $lng,
    $urgency_level,
    $status
);

if ($stmt->execute()) {
    $incident_id = $stmt->insert_id;

    if (!empty($imageData)) {
        $decodedImage = base64_decode($imageData);
        $path = "../assets/images/";
        $imageName = "incident_" . $incident_id . ".png";
        $fullPath = $path . $imageName;

        if(file_put_contents($fullPath, $decodedImage)){
            $stmtImg = $conn->prepare("UPDATE tbl_incidents SET image = ? WHERE id = ?");
            $stmtImg->bind_param("si", $imageName, $incident_id);
            $stmtImg->execute();
            $stmtImg->close();
        }
    }

    echo json_encode(['success' => true, 'message' => 'Incident reported successfully']);
} else {
    // FIX 3: Output error for debugging
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>