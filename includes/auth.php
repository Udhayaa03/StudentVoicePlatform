<?php
require_once 'config.php';
require_once 'functions.php';

function loginUser($username, $password, $role) {
    global $conn;
    
    $table = ($role === 'student') ? "registration_number" : (($role === 'faculty') ? "employee_id" : "username");
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE $table = ? AND role = ?");
    $stmt->bind_param("ss", $username, $role);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['department'] = $user['department'];
            
            return true;
        }
    }
    
    return false;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header("Location: index.php");
        exit();
    }
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function logoutUser() {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}
?>