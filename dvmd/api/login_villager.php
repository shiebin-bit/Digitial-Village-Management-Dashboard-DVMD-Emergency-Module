<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once('../../includes/dbconnect.php');

// Set timezone to ensure lock times match your local time (adjust as needed, e.g., 'Asia/Kuala_Lumpur')
date_default_timezone_set('Asia/Kuala_Lumpur'); 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'failed', 'message' => 'Method Not Allowed']);
    exit();
}

if (!isset($_POST['email']) || !isset($_POST['password'])) {
    echo json_encode(['status' => 'failed', 'message' => 'Bad Request']);
    exit();
}

$email = trim($_POST['email']);
$password = $_POST['password'];

// ✅ 1. ADD: Fetch failed_attempts and lock_until in your SELECT query
$stmt = $conn->prepare(
    "SELECT id, name, email, phone, village_id, password, regdate, failed_attempts, lock_until 
     FROM tbl_villagers 
     WHERE email = ?"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();

    // ✅ 2. ADD: Check if account is currently locked
    if ($row['lock_until'] != null) {
        $lockTime = strtotime($row['lock_until']);
        $currentTime = time();
        
        if ($currentTime < $lockTime) {
            $minutesLeft = ceil(($lockTime - $currentTime) / 60);
            echo json_encode([
                'status' => 'failed', 
                'message' => "Account locked. Try again in $minutesLeft minutes."
            ]);
            exit(); // Stop execution here
        }
    }

    // Verify Password
    if (password_verify($password, $row['password'])) {

        // ✅ 3. ADD: Login Success -> Reset failed_attempts and lock_until
        $resetStmt = $conn->prepare("UPDATE tbl_villagers SET failed_attempts = 0, lock_until = NULL WHERE email = ?");
        $resetStmt->bind_param("s", $email);
        $resetStmt->execute();

        unset($row['password']); // Remove sensitive data

        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => $row
        ]);
    } else {
        // ✅ 4. ADD: Login Failed -> Increment attempts & Lock if necessary
        if ($user['failed_attempts'] >= 5) {
            $attempts = 1;
        } else {
            $attempts = $user['failed_attempts'] + 1;
        }
        
        if ($attempts >= 5) {
            // Lock for 15 minutes
            $lock_until = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            $updateStmt = $conn->prepare("UPDATE tbl_villagers SET failed_attempts = ?, lock_until = ? WHERE email = ?");
            $updateStmt->bind_param("iss", $attempts, $lock_until, $email);
            $updateStmt->execute();
            
            echo json_encode([
                'status' => 'failed', 
                'message' => 'Too many failed attempts. Account locked for 15 minutes.'
            ]);
        } else {
            // Just update the counter
            $updateStmt = $conn->prepare("UPDATE tbl_villagers SET failed_attempts = ? WHERE email = ?");
            $updateStmt->bind_param("is", $attempts, $email);
            $updateStmt->execute();
            
            $remaining = 5 - $attempts;
            echo json_encode([
                'status' => 'failed', 
                'message' => "Invalid password. $remaining attempts remaining."
            ]);
        }
    }
} else {
    echo json_encode([
        'status' => 'failed',
        'message' => 'Invalid email or password'
    ]);
}

$stmt->close();
$conn->close();
?>