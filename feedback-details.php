<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

redirectIfNotLoggedIn();
if (!isset($_GET['id'])) {
    header("Location: " . $_SESSION['role'] . "-dashboard.php");
    exit();
}

$feedback_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get feedback details
$stmt = $conn->prepare("
    SELECT f.*, c.name as category, u.name as submitted_by_name, u.email as submitted_by_email
    FROM feedback f
    JOIN feedback_categories c ON f.category_id = c.id
    JOIN users u ON f.submitted_by = u.id
    WHERE f.id = ?
");
$stmt->bind_param("i", $feedback_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p class='error'>Feedback not found</p>";
    exit();
}

$feedback = $result->fetch_assoc();

// Check if faculty can view this feedback (same department)
if ($role === 'faculty') {
    $stmt = $conn->prepare("
        SELECT u.department 
        FROM feedback f
        JOIN users u ON f.submitted_by = u.id
        WHERE f.id = ?
    ");
    $stmt->bind_param("i", $feedback_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['department'] !== $_SESSION['department']) {
        echo "<p class='error'>You are not authorized to view this feedback</p>";
        exit();
    }
}

// Get responses
$responses = [];
$stmt = $conn->prepare("
    SELECT r.*, u.name as responded_by_name
    FROM feedback_responses r
    JOIN users u ON r.responded_by = u.id
    WHERE r.feedback_id = ?
    ORDER BY r.created_at ASC
");
$stmt->bind_param("i", $feedback_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $responses[] = $row;
}

// Handle response submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['response'])) {
    $response = $_POST['response'];
    $status = $_POST['status'];
    
    // Insert response
    $stmt = $conn->prepare("INSERT INTO feedback_responses (feedback_id, responded_by, response) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $feedback_id, $user_id, $response);
    $stmt->execute();
    
    // Update status
    $stmt = $conn->prepare("UPDATE feedback SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $feedback_id);
    $stmt->execute();
    
    // Reload the page to show the new response
    header("Location: feedback-details.php?id=$feedback_id");
    exit();
}
?>
<div class="detail-row">
    <span class="detail-label">ID:</span>
    <span class="detail-value">FB-<?php echo str_pad($feedback['id'], 3, '0', STR_PAD_LEFT); ?></span>
</div>
<div class="detail-row">
    <span class="detail-label">Subject:</span>
    <span class="detail-value"><?php echo htmlspecialchars($feedback['subject']); ?></span>
</div>
<div class="detail-row">
    <span class="detail-label">Category:</span>
    <span class="detail-value"><?php echo htmlspecialchars($feedback['category']); ?></span>
</div>
<div class="detail-row">
    <span class="detail-label">Submitted By:</span>
    <span class="detail-value">
        <?php echo $feedback['is_anonymous'] ? 'Anonymous' : htmlspecialchars($feedback['submitted_by_name']); ?>
        <?php if (!$feedback['is_anonymous']): ?>
            (<?php echo htmlspecialchars($feedback['submitted_by_email']); ?>)
        <?php endif; ?>
    </span>
</div>
<div class="detail-row">
    <span class="detail-label">Date Submitted:</span>
    <span class="detail-value"><?php echo date('F j, Y, g:i a', strtotime($feedback['created_at'])); ?></span>
</div>
<div class="detail-row">
    <span class="detail-label">Status:</span>
    <span class="detail-value status-badge <?php echo str_replace('_', '-', $feedback['status']); ?>">
        <?php echo ucwords(str_replace('_', ' ', $feedback['status'])); ?>
    </span>
</div>

<?php if ($feedback['course_name']): ?>
<div class="detail-row">
    <span class="detail-label">Course Name:</span>
    <span class="detail-value"><?php echo htmlspecialchars($feedback['course_name']); ?></span>
</div>
<?php endif; ?>

<?php if ($feedback['faculty_name']): ?>
<div class="detail-row">
    <span class="detail-label">Faculty Name:</span>
    <span class="detail-value"><?php echo htmlspecialchars($feedback['faculty_name']); ?></span>
</div>
<?php endif; ?>

<div class="detail-row full-width">
    <span class="detail-label">Description:</span>
    <div class="detail-value"><?php echo nl2br(htmlspecialchars($feedback['description'])); ?></div>
</div>

<div class="responses-section">
    <h3>Responses</h3>
    <?php if (empty($responses)): ?>
        <p>No responses yet.</p>
    <?php else: ?>
        <?php foreach ($responses as $response): ?>
            <div class="response-item">
                <div class="response-header">
                    <span class="response-author"><?php echo htmlspecialchars($response['responded_by_name']); ?></span>
                    <span class="response-date"><?php echo date('F j, Y, g:i a', strtotime($response['created_at'])); ?></span>
                </div>
                <div class="response-content"><?php echo nl2br(htmlspecialchars($response['response'])); ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($role !== 'student'): ?>
<div class="add-response-section">
    <h3>Add Response</h3>
    <form method="POST" class="response-form">
        <div class="form-group">
            <label for="status">Update Status:</label>
            <select id="status" name="status" required>
                <option value="submitted" <?php echo $feedback['status'] === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                <option value="under_review" <?php echo $feedback['status'] === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                <option value="action_taken" <?php echo $feedback['status'] === 'action_taken' ? 'selected' : ''; ?>>Action Taken</option>
                <option value="closed" <?php echo $feedback['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
            </select>
        </div>
        <div class="form-group">
            <textarea name="response" rows="4" required placeholder="Enter your response..."></textarea>
        </div>
        <button type="submit" class="primary-btn">Submit Response</button>
    </form>
</div>
<?php endif; ?>