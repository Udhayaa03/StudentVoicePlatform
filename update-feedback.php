<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

redirectIfNotLoggedIn();
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'faculty') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $feedback_id = $_POST['id'] ?? null;
    $assigned_to = $_POST['assigned_to'] ?? null;
    $status = $_POST['status'] ?? null;
    $response = $_POST['response'] ?? null;
    
    if (!$feedback_id) {
        echo json_encode(['success' => false, 'message' => 'Feedback ID is required']);
        exit();
    }
    
    // Build update query based on provided fields
    $updates = [];
    $params = [];
    $types = '';
    
    if ($assigned_to) {
        $updates[] = 'assigned_to = ?';
        $params[] = $assigned_to;
        $types .= 'i';
    }
    
    if ($status) {
        $updates[] = 'status = ?';
        $params[] = $status;
        $types .= 's';
    }
    
    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        exit();
    }
    
    $params[] = $feedback_id;
    $types .= 'i';
    
    $query = "UPDATE feedback SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
} else {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>