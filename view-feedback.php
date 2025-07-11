<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

redirectIfNotLoggedIn();
if ($_SESSION['role'] !== 'faculty' && $_SESSION['role'] !== 'admin') {
    header("Location: " . $_SESSION['role'] . "-dashboard.php");
    exit();
}

// Get feedback based on user role
$feedback = [];
$params = [];
$types = '';

if ($_SESSION['role'] === 'faculty') {
    // Faculty can only see feedback from their department
    $query = "
        SELECT f.id, f.subject, c.name as category, 
               IF(f.is_anonymous, 'Anonymous', u.name) as submitted_by,
               f.created_at, f.status
        FROM feedback f
        JOIN feedback_categories c ON f.category_id = c.id
        JOIN users u ON f.submitted_by = u.id
        WHERE u.department = ?
        ORDER BY f.created_at DESC
    ";
    $params[] = $_SESSION['department'];
    $types .= 's';
} else {
    // Admin can see all feedback
    $query = "
        SELECT f.id, f.subject, c.name as category, 
               IF(f.is_anonymous, 'Anonymous', u.name) as submitted_by,
               u.department, f.created_at, f.status
        FROM feedback f
        JOIN feedback_categories c ON f.category_id = c.id
        JOIN users u ON f.submitted_by = u.id
        ORDER BY f.created_at DESC
    ";
}

// Apply filters if any
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$department_filter = $_GET['department'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where_clauses = [];
if ($status_filter && $status_filter !== 'all') {
    $where_clauses[] = "f.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($category_filter && $category_filter !== 'all') {
    $where_clauses[] = "f.category_id = ?";
    $params[] = $category_filter;
    $types .= 'i';
}

if ($department_filter && $department_filter !== 'all' && $_SESSION['role'] === 'admin') {
    $where_clauses[] = "u.department = ?";
    $params[] = $department_filter;
    $types .= 's';
}

if ($date_from) {
    $where_clauses[] = "DATE(f.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if ($date_to) {
    $where_clauses[] = "DATE(f.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

if (!empty($where_clauses)) {
    $query = str_replace('ORDER BY', 'WHERE ' . implode(' AND ', $where_clauses) . ' ORDER BY', $query);
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $feedback[] = $row;
}

// Get categories for filter
$categories = [];
$result = $conn->query("SELECT id, name FROM feedback_categories WHERE parent_id IS NOT NULL ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Get departments for filter (admin only)
$departments = [];
if ($_SESSION['role'] === 'admin') {
    $result = $conn->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL ORDER BY department");
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row['department'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Feedback - Student Voice Platform</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <div class="header-content">
            <h1>View Feedback - <span>STUDENT VOICE PLATFORM</span></h1>
            <div class="user-info">
                <span><?php echo $_SESSION['name']; ?> (<?php echo $_SESSION['role'] === 'admin' ? 'Admin' : $_SESSION['department']; ?>)</span>
                <button id="logout-btn">Logout</button>
            </div>
        </div>
    </header>
    
    <main>
        <div class="dashboard-container">
            <div class="feedback-list">
                <h2>Feedback List</h2>
                
                <div class="filter-section">
                    <form id="filter-form" method="GET" action="view-feedback.php">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="status-filter">Status:</label>
                                <select id="status-filter" name="status">
                                    <option value="all">All Status</option>
                                    <option value="submitted" <?php echo $status_filter === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                    <option value="under_review" <?php echo $status_filter === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                                    <option value="action_taken" <?php echo $status_filter === 'action_taken' ? 'selected' : ''; ?>>Action Taken</option>
                                    <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="category-filter">Category:</label>
                                <select id="category-filter" name="category">
                                    <option value="all">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                            <div class="filter-group">
                                <label for="department-filter">Department:</label>
                                <select id="department-filter" name="department">
                                    <option value="all">All Departments</option>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?php echo htmlspecialchars($department); ?>" <?php echo $department_filter === $department ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($department); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="date-from">From Date:</label>
                                <input type="date" id="date-from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            
                            <div class="filter-group">
                                <label for="date-to">To Date:</label>
                                <input type="date" id="date-to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            
                            <div class="filter-group">
                                <button type="submit" class="primary-btn">Apply Filters</button>
                                <a href="view-feedback.php" class="secondary-btn">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <?php if (empty($feedback)): ?>
                    <div class="no-results">
                        <p>No feedback found matching your criteria.</p>
                    </div>
                <?php else: ?>
                    <table id="feedback-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Subject</th>
                                <th>Category</th>
                                <th>Submitted By</th>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                <th>Department</th>
                                <?php endif; ?>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feedback as $item): ?>
                                <tr>
                                    <td>FB-<?php echo str_pad($item['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($item['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                                    <td><?php echo htmlspecialchars($item['submitted_by']); ?></td>
                                    <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <td><?php echo htmlspecialchars($item['department'] ?? 'N/A'); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo date('Y-m-d', strtotime($item['created_at'])); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo str_replace('_', '-', $item['status']); ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $item['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="feedback-details.php?id=<?php echo $item['id']; ?>" class="view-btn">View</a>
                                        <?php if ($_SESSION['role'] === 'admin'): ?>
                                        <a href="#" class="assign-btn" data-id="<?php echo $item['id']; ?>">Assign</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Assign Faculty Modal (Admin only) -->
    <?php if ($_SESSION['role'] === 'admin'): ?>
    <div id="assign-modal" class="modal">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h2>Assign Feedback</h2>
            <form id="assign-form">
                <input type="hidden" id="feedback-id">
                <div class="form-group">
                    <label for="assign-to">Assign To:</label>
                    <select id="assign-to" required>
                        <option value="">Select Faculty</option>
                        <?php
                        $result = $conn->query("SELECT id, name, department FROM users WHERE role = 'faculty' ORDER BY department, name");
                        while ($row = $result->fetch_assoc()) {
                            echo "<option value='{$row['id']}' data-dept='{$row['department']}'>{$row['name']} ({$row['department']})</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="assign-status">Status:</label>
                    <select id="assign-status" required>
                        <option value="under_review">Under Review</option>
                        <option value="action_taken">Action Taken</option>
                    </select>
                </div>
                <button type="submit" class="primary-btn">Assign</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="js/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Logout button
            document.getElementById('logout-btn').addEventListener('click', function() {
                window.location.href = 'logout.php';
            });
            
            <?php if ($_SESSION['role'] === 'admin'): ?>
            // Assign feedback modal
            const assignModal = document.getElementById('assign-modal');
            const assignButtons = document.querySelectorAll('.assign-btn');
            const feedbackIdInput = document.getElementById('feedback-id');
            
            assignButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const feedbackId = this.getAttribute('data-id');
                    feedbackIdInput.value = feedbackId;
                    assignModal.style.display = 'block';
                });
            });
            
            // Assign form submission
            document.getElementById('assign-form').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const feedbackId = feedbackIdInput.value;
                const facultyId = document.getElementById('assign-to').value;
                const status = document.getElementById('assign-status').value;
                
                if (!facultyId) {
                    alert('Please select a faculty member');
                    return;
                }
                
                fetch('update-feedback.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${feedbackId}&assigned_to=${facultyId}&status=${status}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Feedback assigned successfully');
                        window.location.reload();
                    } else {
                        alert('Error assigning feedback: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    alert('Error assigning feedback: ' + error.message);
                });
            });
            
            // Close modal
            document.querySelector('#assign-modal .close-btn').addEventListener('click', function() {
                assignModal.style.display = 'none';
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === assignModal) {
                    assignModal.style.display = 'none';
                }
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>