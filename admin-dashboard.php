<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

redirectIfNotLoggedIn();
if ($_SESSION['role'] !== 'admin') {
    header("Location: " . $_SESSION['role'] . "-dashboard.php");
    exit();
}

// Get overall stats
$stats = [
    'total' => 0,
    'submitted' => 0,
    'under_review' => 0,
    'action_taken' => 0,
    'closed' => 0
];

$result = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(status = 'submitted') as submitted,
        SUM(status = 'under_review') as under_review,
        SUM(status = 'action_taken') as action_taken,
        SUM(status = 'closed') as closed
    FROM feedback
");

if ($row = $result->fetch_assoc()) {
    $stats = $row;
}

// Get all feedback
$feedback = [];
$result = $conn->query("
    SELECT f.id, f.subject, c.name as category, 
           IF(f.is_anonymous, 'Anonymous', u.name) as submitted_by,
           u.department, f.created_at, f.status
    FROM feedback f
    JOIN feedback_categories c ON f.category_id = c.id
    JOIN users u ON f.submitted_by = u.id
    ORDER BY f.created_at DESC
");

while ($row = $result->fetch_assoc()) {
    $feedback[] = $row;
}

// Get departments for filter
$departments = [];
$result = $conn->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL");
while ($row = $result->fetch_assoc()) {
    $departments[] = $row['department'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Student Voice Platform</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <div class="header-content">
            <h1>Admin Dashboard - <span>STUDENT VOICE PLATFORM</span></h1>
            <div class="user-info">
                <span><?php echo $_SESSION['name']; ?></span>
                <button id="logout-btn">Logout</button>
            </div>
        </div>
    </header>
    
    <main>
        <div class="dashboard-container">
            <div class="stats-card">
                <h3>Feedback Summary</h3>
                <div class="stats">
                    <div class="stat-item">
                        <span class="count"><?php echo $stats['total']; ?></span>
                        <span class="label">Total</span>
                    </div>
                    <div class="stat-item">
                        <span class="count"><?php echo $stats['submitted']; ?></span>
                        <span class="label">Submitted</span>
                    </div>
                    <div class="stat-item">
                        <span class="count"><?php echo $stats['under_review']; ?></span>
                        <span class="label">Under Review</span>
                    </div>
                    <div class="stat-item">
                        <span class="count"><?php echo $stats['action_taken']; ?></span>
                        <span class="label">Action Taken</span>
                    </div>
                    <div class="stat-item">
                        <span class="count"><?php echo $stats['closed']; ?></span>
                        <span class="label">Closed</span>
                    </div>
                </div>
            </div>
            
            <div class="admin-actions">
                <h2>Quick Actions</h2>
                <div class="action-buttons">
                    <button id="view-all-feedback" class="action-btn">View All Feedback</button>
                    <button id="manage-users" class="action-btn">Manage Users</button>
                    <button id="generate-reports" class="action-btn">Generate Reports</button>
                </div>
            </div>
            
            <div class="feedback-list">
                <h2>All Feedback</h2>
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
                        $result = $conn->query("SELECT id, name FROM feedback_categories WHERE parent_id IS NOT NULL");
                        while ($row = $result->fetch_assoc()) {
                            echo "<option value='{$row['id']}'>{$row['name']}</option>";
                        }
                        ?>
                    </select>
                    
                    <select id="department-filter">
                        <option value="all">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="date" id="date-from" placeholder="From Date">
                    <input type="date" id="date-to" placeholder="To Date">
                </div>
                
                <table id="feedback-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Subject</th>
                            <th>Category</th>
                            <th>Submitted By</th>
                            <th>Department</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedback as $item): ?>
                            <tr data-status="<?php echo $item['status']; ?>" 
                                data-category="<?php echo $item['category']; ?>"
                                data-department="<?php echo htmlspecialchars($item['department']); ?>"
                                data-date="<?php echo date('Y-m-d', strtotime($item['created_at'])); ?>">
                                <td>FB-<?php echo str_pad($item['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo htmlspecialchars($item['subject']); ?></td>
                                <td><?php echo htmlspecialchars($item['category']); ?></td>
                                <td><?php echo htmlspecialchars($item['submitted_by']); ?></td>
                                <td><?php echo htmlspecialchars($item['department']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($item['created_at'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo str_replace('_', '-', $item['status']); ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $item['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="view-btn" data-id="<?php echo $item['id']; ?>">View</button>
                                    <button class="assign-btn" data-id="<?php echo $item['id']; ?>">Assign</button>
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
    
    <div id="assign-modal" class="modal">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h2>Assign Feedback</h2>
            <form id="assign-form">
                <div class="form-group">
                    <label for="assign-to">Assign To:</label>
                    <select id="assign-to">
                        <option value="">Select Faculty</option>
                        <?php
                        $result = $conn->query("SELECT id, name, department FROM users WHERE role = 'faculty' ORDER BY department, name");
                        while ($row = $result->fetch_assoc()) {
                            echo "<option value='{$row['id']}' data-dept='{$row['department']}'>{$row['name']} ({$row['department']})</option>";
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" class="primary-btn">Assign</button>
            </form>
        </div>
    </div>
    
    <script src="js/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Filter functionality
            function applyFilters() {
                const statusValue = document.getElementById('status-filter').value;
                const categoryValue = document.getElementById('category-filter').value;
                const departmentValue = document.getElementById('department-filter').value;
                const dateFrom = document.getElementById('date-from').value;
                const dateTo = document.getElementById('date-to').value;
                
                document.querySelectorAll('#feedback-table tbody tr').forEach(row => {
                    const rowStatus = row.getAttribute('data-status');
                    const rowCategory = row.getAttribute('data-category');
                    const rowDepartment = row.getAttribute('data-department');
                    const rowDate = row.getAttribute('data-date');
                    
                    const statusMatch = statusValue === 'all' || rowStatus === statusValue;
                    const categoryMatch = categoryValue === 'all' || rowCategory.toLowerCase().includes(categoryValue.toLowerCase());
                    const departmentMatch = departmentValue === 'all' || rowDepartment === departmentValue;
                    const dateMatch = (!dateFrom || rowDate >= dateFrom) && (!dateTo || rowDate <= dateTo);
                    
                    if (statusMatch && categoryMatch && departmentMatch && dateMatch) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
            
            document.getElementById('status-filter').addEventListener('change', applyFilters);
            document.getElementById('category-filter').addEventListener('change', applyFilters);
            document.getElementById('department-filter').addEventListener('change', applyFilters);
            document.getElementById('date-from').addEventListener('change', applyFilters);
            document.getElementById('date-to').addEventListener('change', applyFilters);
            
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
            
            // Assign feedback
            let currentFeedbackId = null;
            document.querySelectorAll('.assign-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    currentFeedbackId = this.getAttribute('data-id');
                    document.getElementById('assign-modal').style.display = 'block';
                });
            });
            
            // Handle assign form submission
            document.getElementById('assign-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const facultyId = document.getElementById('assign-to').value;
                
                if (!facultyId) {
                    alert('Please select a faculty member');
                    return;
                }
                
                fetch('update-feedback.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${currentFeedbackId}&assigned_to=${facultyId}&status=under_review`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Feedback assigned successfully');
                        location.reload();
                    } else {
                        alert('Error assigning feedback: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    alert('Error assigning feedback: ' + error.message);
                });
            });
            
            // Close modals
            document.querySelectorAll('.close-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.getElementById('feedback-details-modal').style.display = 'none';
                    document.getElementById('assign-modal').style.display = 'none';
                });
            });
            
            // Logout button
            document.getElementById('logout-btn').addEventListener('click', function() {
                window.location.href = 'logout.php';
            });
            
            // Close modals when clicking outside
            window.onclick = function(event) {
                if (event.target == document.getElementById('feedback-details-modal')) {
                    document.getElementById('feedback-details-modal').style.display = 'none';
                }
                if (event.target == document.getElementById('assign-modal')) {
                    document.getElementById('assign-modal').style.display = 'none';
                }
            }
        });
    </script>
</body>
</html>