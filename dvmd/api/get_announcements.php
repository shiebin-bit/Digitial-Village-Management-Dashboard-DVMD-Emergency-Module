<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once('../../includes/dbconnect.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed'
    ]);
    exit();
}

// ===== VALIDATION =====
if (!isset($_POST['village_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing village_id'
    ]);
    exit();
}

$village_id = $_POST['village_id'];

// ===== FETCH ACTIVE ANNOUNCEMENTS =====
$stmt = $conn->prepare(
    "SELECT 
        id,
        title,
        message,
        type,
        created_at
     FROM tbl_announcements
     WHERE village_id = ?
     ORDER BY created_at DESC
     LIMIT 5"
);

$stmt->bind_param("i", $village_id);
$stmt->execute();
$result = $stmt->get_result();

$announcements = [];

while ($row = $result->fetch_assoc()) {
    $announcements[] = $row;
}

echo json_encode([
    'success' => true,
    'data' => $announcements
]);

$stmt->close();
$conn->close();
?>
