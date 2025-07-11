<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

redirectIfNotLoggedIn();
if ($_SESSION['role'] !== 'faculty') {
    header("Location: " . $_SESSION['role'] . "-dashboard.php");
    exit();
}

// Get feedback stats for the faculty's department
$faculty_id = $_SESSION['user_id'];
$department = $_SESSION['department'];
$stats = [
    'total' => 0,
    'pending' => 0,
    'resolved' => 0
];

// Get total feedback count
$stmt = $conn->prepare("
    SELECT COUNT(*) as total, 
           SUM(status = 'submitted' OR status = 'under_review') as pending,
           SUM(status = 'action_taken' OR status = 'closed') as resolved
    FROM feedback f
    JOIN users u ON f.submitted_by = u.id
    WHERE u.department = ?
");
$stmt->bind_param("s", $department);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $stats['total'] = $row['total'];
    $stats['pending'] = $row['pending'];
    $stats['resolved'] = $row['resolved'];
}

// Get assigned feedback
$feedback = [];
$stmt = $conn->prepare("
    SELECT f.id, f.subject, c.name as category, 
           IF(f.is_anonymous, 'Anonymous', u.name) as submitted_by,
           f.created_at, f.status
    FROM feedback f
    JOIN feedback_categories c ON f.category_id = c.id
    JOIN users u ON f.submitted_by = u.id
    WHERE u.department = ?
    ORDER BY f.created_at DESC
");
$stmt->bind_param("s", $department);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $feedback[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard - Student Voice Platform</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <div class="header-content">
            <h1>Faculty Dashboard - <span>STUDENT VOICE PLATFORM</span></h1>
            <div class="user-info">
                <span><?php echo $_SESSION['name']; ?> (<?php echo $_SESSION['department']; ?>)</span>
                <button id="logout-btn">Logout</button>
            </div>
        </div>
    </header>
    
    <main>
        <div class="dashboard-container">
            <div class="stats-card">
                <h3>Department Feedback Summary</h3>
                <div class="stats">
                    <div class="stat-item">
                        <span class="count"><?php echo $stats['total']; ?></span>
                        <span class="label">Total</span>
                    </div>
                    <div class="stat-item">
                        <span class="count"><?php echo $stats['pending']; ?></span>
                        <span class="label">Pending</span>
                    </div>
                    <div class="stat-item">
                        <span class="count"><?php echo $stats['resolved']; ?></span>
                        <span class="label">Resolved</span>
                    </div>
                </div>
            </div>
            
            <div class="feedback-list">
                <h2>Assigned Feedback</h2>
                <div class="filter-options">
                    <select id="status-filter">
                        <option value="all">All Status</option>
                        <option value="submitted">Submitted</option>
                        <option value="under_review">Under Review</option>
                        <option value="action_taken">Action Taken</option>
                        <option value="closed">Closed</option>
                    </select>
                    
                    <select id="category-filter">
                        <option value="all">All Categories</option>
                        <?php
                        $result = $conn->query("SELECT DISTINCT c.id, c.name 
                                              FROM feedback f 
                                              JOIN feedback_categories c ON f.category_id = c.id
                                              JOIN users u ON f.submitted_by = u.id
                                              WHERE u.department = '$department'");
                        while ($row = $result->fetch_assoc()) {
                            echo "<option value='{$row['id']}'>{$row['name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <table id="feedback-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Subject</th>
                            <th>Category</th>
                            <th>Submitted By</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedback as $item): ?>
                            <tr data-status="<?php echo $item['status']; ?>" data-category="<?php echo $item['category']; ?>">
                                <td>FB-<?php echo str_pad($item['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo htmlspecialchars($item['subject']); ?></td>
                                <td><?php echo htmlspecialchars($item['category']); ?></td>
                                <td><?php echo htmlspecialchars($item['submitted_by']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($item['created_at'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo str_replace('_', '-', $item['status']); ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $item['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="view-btn" data-id="<?php echo $item['id']; ?>">View</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <div id="feedback-details-modal" class="modal">
        <div class="modal-content large">
            <span class="close-btn">&times;</span>
            <h2>Feedback Details</h2>
            <div class="feedback-details">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>
    
    <script src="js/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Filter functionality
            const statusFilter = document.getElementById('status-filter');
            const categoryFilter = document.getElementById('category-filter');
            const tableRows = document.querySelectorAll('#feedback-table tbody tr');
            
            function applyFilters() {
                const statusValue = statusFilter.value;
                const categoryValue = categoryFilter.value;
                
                tableRows.forEach(row => {
                    const rowStatus = row.getAttribute('data-status');
                    const rowCategory = row.getAttribute('data-category');
                    
                    const statusMatch = statusValue === 'all' || rowStatus === statusValue;
                    const categoryMatch = categoryValue === 'all' || rowCategory.toLowerCase().includes(categoryValue.toLowerCase());
                    
                    if (statusMatch && categoryMatch) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
            
            statusFilter.addEventListener('change', applyFilters);
            categoryFilter.addEventListener('change', applyFilters);
            
            // View feedback details
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const feedbackId = this.getAttribute('data-id');
                    const modal = document.getElementById('feedback-details-modal');
                    const detailsContainer = modal.querySelector('.feedback-details');
                    
                    // Show loading state
                    detailsContainer.innerHTML = '<p>Loading...</p>';
                    modal.style.display = 'block';
                    
                    // Fetch feedback details via AJAX
                    fetch(`feedback-details.php?id=${feedbackId}`)
                        .then(response => response.text())
                        .then(html => {
                            detailsContainer.innerHTML = html;
                        })
                        .catch(error => {
                            detailsContainer.innerHTML = `<p class="error">Error loading feedback details: ${error.message}</p>`;
                        });
                });
            });
            
            // Close modal
            document.querySelectorAll('.close-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.getElementById('feedback-details-modal').style.display = 'none';
                });
            });
            
            // Logout button
            document.getElementById('logout-btn').addEventListener('click', function() {
                window.location.href = 'logout.php';
            });
            
            // Close modal when clicking outside
            window.onclick = function(event) {
                const modal = document.getElementById('feedback-details-modal');
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }
        });
    </script>
</body>
</html>