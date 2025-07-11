<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

redirectIfNotLoggedIn();
if ($_SESSION['role'] !== 'student') {
    header("Location: " . $_SESSION['role'] . "-dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = $_POST['subject'];
    $description = $_POST['description'];
    $category_id = $_POST['category_id'];
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    $course_name = $_POST['course_name'] ?? null;
    $faculty_name = $_POST['faculty_name'] ?? null;
    $submitted_by = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("INSERT INTO feedback (subject, description, category_id, submitted_by, is_anonymous, course_name, faculty_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiiiss", $subject, $description, $category_id, $submitted_by, $is_anonymous, $course_name, $faculty_name);
    
    if ($stmt->execute()) {
        header("Location: student-dashboard.php?success=1");
    } else {
        header("Location: student-dashboard.php?error=1");
    }
    exit();
} else {
    header("Location: student-dashboard.php");
    exit();
}
?>