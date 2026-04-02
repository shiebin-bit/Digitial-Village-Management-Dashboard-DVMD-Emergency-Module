<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once('../../includes/dbconnect.php');

// ================= METHOD CHECK =================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed'
    ]);
    exit();
}

// ================= PARAM VALIDATION =================
if (!isset($_POST['villager_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing villager_id'
    ]);
    exit();
}

$villager_id = intval($_POST['villager_id']);

// ================= FETCH INCIDENTS =================
$stmt = $conn->prepare(
    "SELECT 
        id,
        villager_id,
        village_id,
        type,
        description,
        latitude,
        longitude,
        image,
        urgency_level,
        status,
        date_created
     FROM tbl_incidents
     WHERE villager_id = ?
     ORDER BY date_created DESC"
);

$stmt->bind_param("i", $villager_id);
$stmt->execute();
$result = $stmt->get_result();

$incidents = [];

// ================= BUILD RESPONSE =================
while ($row = $result->fetch_assoc()) {
    $incidents[] = $row;
}

// ================= RETURN JSON =================
echo json_encode([
    'success' => true,
    'data' => $incidents
]);

$stmt->close();
$conn->close();
?>
