<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    $role = getUserRole();
    header("Location: {$role}-dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    if (loginUser($username, $password, $role)) {
        header("Location: {$role}-dashboard.php");
        exit();
    } else {
        $error = "Invalid credentials. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Voice Platform - Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="login-container">
        <h1>STUDENT VOICE PLATFORM</h1>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <div class="login-form">
            <div class="user-type-selector">
                <button class="active" data-user-type="student">Student</button>
                <button data-user-type="faculty">Faculty/Staff</button>
                <button data-user-type="admin">Admin</button>
            </div>
            
            <form id="student-form" class="user-form active" method="POST">
                <h2>Student Login</h2>
                <input type="hidden" name="role" value="student">
                <div class="form-group">
                    <label for="student-name">Registration Number</label>
                    <input type="text" id="student-name" name="username" required>
                </div>
                <div class="form-group">
                    <label for="student-password">Password</label>
                    <input type="password" id="student-password" name="password" required>
                </div>
                <button type="submit" class="login-btn">Login</button>
            </form>
            
            <form id="faculty-form" class="user-form" method="POST">
                <h2>Faculty/Staff Login</h2>
                <input type="hidden" name="role" value="faculty">
                <div class="form-group">
                    <label for="employee-id">Employee ID</label>
                    <input type="text" id="employee-id" name="username" required>
                </div>
                <div class="form-group">
                    <label for="faculty-password">Password</label>
                    <input type="password" id="faculty-password" name="password" required>
                </div>
                <button type="submit" class="login-btn">Login</button>
            </form>
            
            <form id="admin-form" class="user-form" method="POST">
                <h2>Admin Login</h2>
                <input type="hidden" name="role" value="admin">
                <div class="form-group">
                    <label for="admin-id">Admin ID</label>
                    <input type="text" id="admin-id" name="username" required>
                </div>
                <div class="form-group">
                    <label for="admin-password">Password</label>
                    <input type="password" id="admin-password" name="password" required>
                </div>
                <button type="submit" class="login-btn">Login</button>
            </form>
        </div>
    </div>
    <script src="js/script.js"></script>
    <script src="js/auth.js"></script>
</body>
</html>