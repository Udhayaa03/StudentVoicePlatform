<?php
require_once 'config.php';

/**
 * Sanitize input data to prevent XSS attacks
 * 
 * @param string $data The input data to sanitize
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a specified URL with optional status code
 * 
 * @param string $url The URL to redirect to
 * @param int $statusCode HTTP status code (default: 303)
 */
function redirect($url, $statusCode = 303) {
    header('Location: ' . $url, true, $statusCode);
    exit();
}

/**
 * Generate a random password
 * 
 * @param int $length Length of the password (default: 12)
 * @return string Generated password
 */
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+;:,.?';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * Format date for display
 * 
 * @param string $dateString The date string to format
 * @param string $format The format to use (default: 'F j, Y, g:i a')
 * @return string Formatted date
 */
function formatDate($dateString, $format = 'F j, Y, g:i a') {
    $date = new DateTime($dateString);
    return $date->format($format);
}

/**
 * Get the current academic year
 * 
 * @return string Academic year in format "YYYY-YYYY"
 */
function getAcademicYear() {
    $currentYear = date('Y');
    $nextYear = $currentYear + 1;
    
    // If current month is August or later, consider it the start of the next academic year
    if (date('n') >= 8) {
        return $currentYear . '-' . $nextYear;
    } else {
        return ($currentYear - 1) . '-' . $currentYear;
    }
}

/**
 * Get department name by ID
 * 
 * @param int $departmentId The department ID
 * @return string Department name or empty string if not found
 */
function getDepartmentName($departmentId) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT name FROM departments WHERE id = ?");
    $stmt->bind_param("i", $departmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['name'];
    }
    
    return '';
}

/**
 * Get feedback status options
 * 
 * @return array Array of status options with keys and display values
 */
function getFeedbackStatusOptions() {
    return [
        'submitted' => 'Submitted',
        'under_review' => 'Under Review',
        'action_taken' => 'Action Taken',
        'closed' => 'Closed'
    ];
}

/**
 * Get feedback categories as hierarchical array
 * 
 * @return array Hierarchical array of categories
 */
function getFeedbackCategories() {
    global $conn;
    
    $categories = [];
    
    // Get parent categories
    $result = $conn->query("SELECT * FROM feedback_categories WHERE parent_id IS NULL ORDER BY name");
    while ($row = $result->fetch_assoc()) {
        $categories[$row['id']] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'children' => []
        ];
    }
    
    // Get child categories
    $result = $conn->query("SELECT * FROM feedback_categories WHERE parent_id IS NOT NULL ORDER BY name");
    while ($row = $result->fetch_assoc()) {
        if (isset($categories[$row['parent_id']])) {
            $categories[$row['parent_id']]['children'][$row['id']] = [
                'id' => $row['id'],
                'name' => $row['name']
            ];
        }
    }
    
    return $categories;
}

/**
 * Log an action to the database
 * 
 * @param int $userId The user ID performing the action
 * @param string $action The action being performed
 * @param string $details Additional details about the action
 * @return bool True if logging was successful, false otherwise
 */
function logAction($userId, $action, $details = '') {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $userId, $action, $details);
    return $stmt->execute();
}

/**
 * Send an email notification
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body content
 * @param string $from Sender email address (default: system email)
 * @return bool True if email was sent successfully, false otherwise
 */
function sendEmailNotification($to, $subject, $body, $from = 'no-reply@studentvoiceplatform.edu') {
    // In a production environment, you would use a mail library like PHPMailer
    $headers = [
        'From' => $from,
        'Reply-To' => $from,
        'X-Mailer' => 'PHP/' . phpversion(),
        'MIME-Version' => '1.0',
        'Content-type' => 'text/html; charset=UTF-8'
    ];
    
    $headerString = '';
    foreach ($headers as $key => $value) {
        $headerString .= "$key: $value\r\n";
    }
    
    return mail($to, $subject, $body, $headerString);
}

/**
 * Get user's full name by ID
 * 
 * @param int $userId The user ID
 * @return string User's full name or empty string if not found
 */
function getUserFullName($userId) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['name'];
    }
    
    return '';
}

/**
 * Validate email address
 * 
 * @param string $email The email address to validate
 * @return bool True if valid, false otherwise
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Get the count of feedback items by status for a specific user
 * 
 * @param int $userId The user ID
 * @return array Array with counts for each status
 */
function getUserFeedbackCounts($userId) {
    global $conn;
    
    $counts = [
        'submitted' => 0,
        'under_review' => 0,
        'action_taken' => 0,
        'closed' => 0,
        'total' => 0
    ];
    
    $stmt = $conn->prepare("
        SELECT status, COUNT(*) as count 
        FROM feedback 
        WHERE submitted_by = ? 
        GROUP BY status
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $counts[$row['status']] = $row['count'];
        $counts['total'] += $row['count'];
    }
    
    return $counts;
}

/**
 * Get the count of feedback items by status for a department
 * 
 * @param string $department The department name
 * @return array Array with counts for each status
 */
function getDepartmentFeedbackCounts($department) {
    global $conn;
    
    $counts = [
        'total' => 0,
        'submitted' => 0,
        'under_review' => 0,
        'action_taken' => 0,
        'closed' => 0
    ];
    
    $stmt = $conn->prepare("
        SELECT f.status, COUNT(*) as count 
        FROM feedback f
        JOIN users u ON f.submitted_by = u.id
        WHERE u.department = ?
        GROUP BY f.status
    ");
    $stmt->bind_param("s", $department);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $counts[$row['status']] = $row['count'];
        $counts['total'] += $row['count'];
    }
    
    return $counts;
}

/**
 * Check if a string contains any profanity
 * 
 * @param string $text The text to check
 * @return bool True if profanity is found, false otherwise
 */
function containsProfanity($text) {
    // In a real application, you would use a more comprehensive profanity filter
    $profanityWords = ['badword1', 'badword2', 'badword3']; // Add actual profane words
    
    foreach ($profanityWords as $word) {
        if (stripos($text, $word) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get the current term/semester
 * 
 * @return string Current term (e.g., "Fall 2023")
 */
function getCurrentTerm() {
    $month = date('n');
    $year = date('Y');
    
    if ($month >= 1 && $month <= 4) {
        return 'Spring ' . $year;
    } elseif ($month >= 5 && $month <= 8) {
        return 'Summer ' . $year;
    } else {
        return 'Fall ' . $year;
    }
}

/**
 * Generate a CSV file from data and force download
 * 
 * @param array $data The data to export
 * @param string $filename The filename for the download
 */
function exportToCSV($data, $filename = 'export.csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Output headers if data exists
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
    }
    
    // Output data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}