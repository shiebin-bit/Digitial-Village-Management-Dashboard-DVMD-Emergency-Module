<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once('../../includes/dbconnect.php');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['status' => 'failed', 'message' => 'Method Not Allowed']);
    exit();
}

// Check if village_id is provided
if (!isset($_GET['village_id'])) {
    echo json_encode(['status' => 'failed', 'message' => 'Bad Request']);
    exit();
}

$villageId = (int)$_GET['village_id'];
$ketuaRole = 0;

// Prepare query
$stmt = $conn->prepare("SELECT phone FROM tbl_users WHERE village_id = ? AND role = ? LIMIT 1");
$stmt->bind_param("ii", $villageId, $ketuaRole);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        'status' => 'success',
        'data' => ['phone' => $row['phone']]
    ]);
} else {
    echo json_encode([
        'status' => 'failed',
        'data' => '',
        'message' => 'Ketua Kampung not found'
    ]);
}

// Close statement and connection
$stmt->close();
$conn->close();
?>
