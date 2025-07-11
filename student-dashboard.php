<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

redirectIfNotLoggedIn();
if (getUserRole() !== 'student') {
    header("Location: " . getUserRole() . "-dashboard.php");
    exit();
}

// Get feedback stats for the student
$student_id = $_SESSION['user_id'];
$stats = [
    'submitted' => 0,
    'under_review' => 0,
    'resolved' => 0
];

$stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM feedback WHERE submitted_by = ? GROUP BY status");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    if ($row['status'] === 'submitted') $stats['submitted'] = $row['count'];
    elseif ($row['status'] === 'under_review') $stats['under_review'] = $row['count'];
    elseif (in_array($row['status'], ['action_taken', 'closed'])) $stats['resolved'] += $row['count'];
}

// Get categories
$categories = [];
$result = $conn->query("SELECT * FROM feedback_categories WHERE parent_id IS NULL");
while ($row = $result->fetch_assoc()) {
    $categories[$row['id']] = $row['name'];
    
    $sub_result = $conn->query("SELECT * FROM feedback_categories WHERE parent_id = {$row['id']}");
    while ($sub_row = $sub_result->fetch_assoc()) {
        $categories[$row['id'] . '_sub'][$sub_row['id']] = $sub_row['name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Student Voice Platform</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <div class="header-content">
            <h1>Welcome to <span>STUDENT VOICE PLATFORM</span> – Your Voice, Our Future</h1>
            <div class="user-info">
                <span><?php echo $_SESSION['name']; ?> (<?php echo $_SESSION['username']; ?>)</span>
                <button id="logout-btn">Logout</button>
            </div>
        </div>
    </header>
    
    <main>
        <div class="dashboard-container">
            <!-- Feedback submission messages -->
            <?php if (isset($_GET['success'])): ?>
                <div class="feedback-success">
                    Your feedback has been submitted successfully! Thank you for your input.
                </div>
            <?php elseif (isset($_GET['error'])): ?>
                <div class="feedback-error">
                    There was an error submitting your feedback. Please try again.
                </div>
            <?php endif; ?>
            
            <div class="stats-card">
                <h3>Your Feedback Summary</h3>
                <div class="stats">
                    <div class="stat-item">
                        <span class="count"><?php echo $stats['submitted']; ?></span>
                        <span class="label">Submitted</span>
                    </div>
                    <div class="stat-item">
                        <span class="count"><?php echo $stats['under_review']; ?></span>
                        <span class="label">Under Review</span>
                    </div>
                    <div class="stat-item">
                        <span class="count"><?php echo $stats['resolved']; ?></span>
                        <span class="label">Resolved</span>
                    </div>
                </div>
                <button id="new-feedback-btn" class="primary-btn">Submit New Feedback</button>
            </div>
            
            <div class="feedback-categories">
                <h2>Feedback Categories</h2>
                
                <?php foreach ($categories as $parent_id => $category): ?>
                    <?php if (is_array($category)) continue; ?>
                    <div class="category-section">
                        <h3><?php echo $category; ?></h3>
                        <ul>
                            <?php foreach ($categories[$parent_id . '_sub'] as $sub_id => $sub_category): ?>
                                <li><?php echo $sub_category; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
    
    <div id="feedback-modal" class="modal">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h2>Submit New Feedback</h2>
            <form id="feedback-form" action="submit-feedback.php" method="POST">
                <div class="form-group">
                    <label for="feedback-category">Category</label>
                    <select id="feedback-category" name="category_id" required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $parent_id => $category): ?>
                            <?php if (is_array($category)) continue; ?>
                            <optgroup label="<?php echo $category; ?>">
                                <?php foreach ($categories[$parent_id . '_sub'] as $sub_id => $sub_category): ?>
                                    <option value="<?php echo $sub_id; ?>"><?php echo $sub_category; ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="feedback-subject">Subject</label>
                    <input type="text" id="feedback-subject" name="subject" placeholder="Brief description of your feedback" required>
                </div>
                
                <div class="form-group">
                    <label for="feedback-description">Detailed Description</label>
                    <textarea id="feedback-description" name="description" rows="5" required></textarea>
                </div>
                
                <!-- Academic-specific fields -->
                <div id="academic-fields">
                    <div class="form-group">
                        <label for="course-name">Course Name</label>
                        <input type="text" id="course-name" name="course_name" placeholder="Enter course name">
                    </div>
                    
                    <div class="form-group" id="faculty-field">
                        <label for="faculty-name">Faculty Name</label>
                        <select id="faculty-name" name="faculty_name">
                            <option value="">Select Faculty</option>
                            <?php
                            $result = $conn->query("SELECT name FROM users WHERE role = 'faculty' ORDER BY name");
                            while ($row = $result->fetch_assoc()) {
                                echo "<option value='{$row['name']}'>{$row['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-container">
                        <input type="checkbox" id="anonymous-submission" name="is_anonymous" value="1">
                        <span class="checkmark"></span>
                        Submit anonymously
                    </label>
                </div>
                
                <button type="submit" class="primary-btn">Submit Feedback</button>
            </form>
        </div>
    </div>
    
    <script src="js/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show/hide academic fields based on category selection
            const categorySelect = document.getElementById('feedback-category');
            const academicFields = document.getElementById('academic-fields');
            
            categorySelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const categoryText = selectedOption.textContent.toLowerCase();
                
                if (categoryText.includes('academic performance') || categoryText.includes('faculty feedback')) {
                    academicFields.style.display = 'block';
                    
                    // Faculty field only shown for faculty feedback
                    document.getElementById('faculty-field').style.display = 
                        categoryText.includes('faculty feedback') ? 'block' : 'none';
                } else {
                    academicFields.style.display = 'none';
                }
            });
            
            // Close messages when clicking the X button
            document.querySelectorAll('.close-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelector('.feedback-success').style.display = 'none';
                    document.querySelector('.feedback-error').style.display = 'none';
                });
            });
            
            // Logout button
            document.getElementById('logout-btn').addEventListener('click', function() {
                window.location.href = 'logout.php';
            });
            
            // Modal handling
            const modal = document.getElementById('feedback-modal');
            const btn = document.getElementById('new-feedback-btn');
            const span = document.getElementsByClassName('close-btn')[0];
            
            btn.onclick = function() {
                modal.style.display = 'block';
            }
            
            span.onclick = function() {
                modal.style.display = 'none';
            }
            
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }
        });
    </script>
</body>
</html>