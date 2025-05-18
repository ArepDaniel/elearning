<?php
session_start();

// Redirect if not logged in or not a student
if (!isset($_SESSION['matrix_number']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

include('config.php'); // Connect to database

// Initialize variables
$subject_filter = '';
$year_filter = '';
$semester_filter = '';

// Get the student's matrix number from the session
$student_matrix_number = $_SESSION['matrix_number'];

// Build base SQL to fetch answers and score_percentage
$answers = [];
// Build base SQL for answer history
$sql_answers = "SELECT 
                s.id as subject_id,
                s.subject_code, 
                s.subject_name, 
                sr.score_percentage, 
                sr.correct_answers,
                sr.total_questions,
                sr.attempt_date 
            FROM student_results sr
            JOIN subjects s ON sr.subject_id = s.id
            WHERE sr.matrix_number = ?";

// Add subject filter if set
if (isset($_GET['history_subject_filter']) && !empty($_GET['history_subject_filter'])) {
    $history_subject_id = intval($_GET['history_subject_filter']);
    $sql_answers .= " AND sr.subject_id = ?";
}

$sql_answers .= " ORDER BY sr.attempt_date DESC";  // Keep this to ensure proper sorting
$stmt = $conn->prepare($sql_answers);

if (isset($history_subject_id)) {
    $stmt->bind_param("si", $student_matrix_number, $history_subject_id);
} else {
    $stmt->bind_param("s", $student_matrix_number);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $answers[] = $row;  // Store results in the $answers array
}
// Calculate overall statistics
$totalAttempts = count($answers);
$totalCorrect = 0;
$totalQuestions = 0;

foreach ($answers as $answer) {
    $totalCorrect += $answer['correct_answers'];
    $totalQuestions += $answer['total_questions'];
}

$overallPercentage = $totalQuestions > 0 ? round(($totalCorrect / $totalQuestions) * 100, 2) : 0;


// Build base SQL for subjects
$sql_subjects = "SELECT * FROM subjects WHERE 1";

// Apply filters if set
if (isset($_GET['subject_filter']) && !empty(trim($_GET['subject_filter']))) {
    $subject_filter = trim($_GET['subject_filter']);
    $sql_subjects .= " AND subject_name LIKE ?";
}
if (isset($_GET['year_filter']) && !empty($_GET['year_filter'])) {
    $year_filter = intval($_GET['year_filter']);
    $sql_subjects .= " AND year = ?";
}
if (isset($_GET['semester_filter']) && !empty(trim($_GET['semester_filter']))) {
    $semester_filter = trim($_GET['semester_filter']);
    $sql_subjects .= " AND semester LIKE ?";
}

$stmt = $conn->prepare($sql_subjects);

// Bind parameters based on filters
if (!empty($subject_filter) && !empty($year_filter) && !empty($semester_filter)) {
    $like_subject = "%$subject_filter%";
    $like_semester = "%$semester_filter%";
    $stmt->bind_param("sis", $like_subject, $year_filter, $like_semester);
} elseif (!empty($subject_filter) && !empty($year_filter)) {
    $like_subject = "%$subject_filter%";
    $stmt->bind_param("si", $like_subject, $year_filter);
} elseif (!empty($subject_filter) && !empty($semester_filter)) {
    $like_subject = "%$subject_filter%";
    $like_semester = "%$semester_filter%";
    $stmt->bind_param("ss", $like_subject, $like_semester);
} elseif (!empty($year_filter) && !empty($semester_filter)) {
    $like_semester = "%$semester_filter%";
    $stmt->bind_param("is", $year_filter, $like_semester);
} elseif (!empty($subject_filter)) {
    $like_subject = "%$subject_filter%";
    $stmt->bind_param("s", $like_subject);
} elseif (!empty($year_filter)) {
    $stmt->bind_param("i", $year_filter);
} elseif (!empty($semester_filter)) {
    $like_semester = "%$semester_filter%";
    $stmt->bind_param("s", $like_semester);
}
$stmt->execute();
$result = $stmt->get_result();

$subjects = [];
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Homepage - USAS E-Learning</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
    --deep-navy: #0A1F3D;
    --burgundy: #800020;
    --gold: #D4AF37;
    --light-gold: #F8E8C8;
    --white-sand: #F5F5F0;
    --navy-shadow: rgba(10, 31, 61, 0.15);
    --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

body {
    background-color: var(--white-sand);
    color: #333;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    line-height: 1.6;
    margin: 0;
    padding: 0;
    background-image: 
        radial-gradient(circle at 10% 20%, rgba(212, 175, 55, 0.05) 0%, transparent 20%),
        radial-gradient(circle at 90% 80%, rgba(128, 0, 32, 0.05) 0%, transparent 20%);
    background-attachment: fixed;
}

/* Header Styles */
.header-bar {
    background: linear-gradient(135deg, var(--deep-navy) 0%, #0e2a4d 100%);
    color: var(--white-sand);
    padding: 18px 0;
    margin-bottom: 30px;
    box-shadow: 0 4px 18px var(--navy-shadow);
    position: relative;
    z-index: 100;
}

.header-bar::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--burgundy) 0%, var(--gold) 100%);
    opacity: 0.8;
}

.header-title {
    font-weight: 700;
    font-size: 1.9rem;
    margin: 0;
    letter-spacing: 0.5px;
    background: linear-gradient(to right, var(--white-sand) 0%, var(--light-gold) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    padding: 5px 0;
}

/* Main Container Styles */
.container {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    box-sizing: border-box;
}

/* Form Container Styles - Removed the left border */
.form-container {
    width: 100%;
    max-width: 100%;
    margin: 25px auto;
    padding: 28px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    box-sizing: border-box;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.form-container:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12);
}

/* Button Styles */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    text-align: center;
    white-space: nowrap;
    vertical-align: middle;
    user-select: none;
    border: 1px solid transparent;
    padding: 10px 20px;
    font-size: 1rem;
    line-height: 1.5;
    border-radius: 8px;
    transition: var(--transition);
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.btn i {
    margin-right: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--burgundy) 0%, #6a001a 100%);
    color: white;
    border: none;
    box-shadow: 0 4px 12px rgba(128, 0, 32, 0.25);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #6a001a 0%, var(--burgundy) 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 16px rgba(128, 0, 32, 0.3);
}

.btn-primary:active {
    transform: translateY(1px);
}

.btn-outline-secondary {
    color: #6c757d;
    border-color: #6c757d;
    background-color: transparent;
    transition: var(--transition);
}

.btn-outline-secondary:hover {
    background-color: #6c757d;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(108, 117, 125, 0.2);
}

.btn-sm {
    padding: 8px 14px;
    font-size: 0.9rem;
}

.btn-danger {
    background: linear-gradient(135deg, #dc3545 0%, #a71d2a 100%);
    box-shadow: 0 4px 8px rgba(220, 53, 69, 0.2);
}

.btn-danger:hover {
    background: linear-gradient(135deg, #a71d2a 0%, #dc3545 100%);
}

/* Table Styles */
.table-responsive {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin-bottom: 25px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.table {
    width: 100%;
    max-width: 100%;
    margin-bottom: 1rem;
    background-color: white;
    border-collapse: separate;
    border-spacing: 0;
    border-radius: 10px;
    overflow: hidden;
}

.table th,
.table td {
    padding: 14px;
    vertical-align: middle;
    border-top: 1px solid #f0f0f0;
    text-align: left;
    transition: var(--transition);
}

.table thead th {
    vertical-align: middle;
    border-bottom: none;
    background-color: var(--deep-navy);
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.table tbody tr {
    transition: var(--transition);
}

.table tbody tr:nth-of-type(even) {
    background-color: rgba(0, 0, 0, 0.01);
}

.table tbody tr:hover {
    background-color: rgba(10, 31, 61, 0.03);
    transform: translateX(5px);
}

.table tbody tr:hover td {
    border-color: var(--light-gold);
}

/* List Group Styles */
.list-group {
    display: flex;
    flex-direction: column;
    padding-left: 0;
    margin-bottom: 0;
    border-radius: 10px;
    overflow: hidden;
}

.list-group-item {
    position: relative;
    display: block;
    padding: 20px;
    margin-bottom: 12px;
    background-color: white;
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 8px;
    transition: var(--transition);
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.03);
}

.list-group-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}

.list-group-item h5 {
    color: var(--deep-navy);
    font-weight: 600;
    margin-bottom: 8px;
}

.list-group-item small {
    color: #666;
    display: block;
    margin-bottom: 12px;
}

/* Form Control Styles */
.form-control,
.form-select {
    display: block;
    width: 100%;
    padding: 12px 16px;
    font-size: 1rem;
    line-height: 1.5;
    color: #495057;
    background-color: white;
    background-clip: padding-box;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    transition: var(--transition);
    margin-bottom: 16px;
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.03);
}

.form-control:focus,
.form-select:focus {
    border-color: var(--gold);
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(212, 175, 55, 0.2), inset 0 1px 3px rgba(0, 0, 0, 0.05);
}

/* Input Group Styles */
.input-group {
    position: relative;
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    width: 100%;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.input-group > .form-control,
.input-group > .form-select {
    position: relative;
    flex: 1 1 auto;
    width: 1%;
    margin-bottom: 0;
    border-radius: 0;
    box-shadow: none;
}

.input-group > .btn {
    position: relative;
    z-index: 2;
    border-radius: 0;
}

/* Badge Styles */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 8px 14px;
    font-size: 0.85rem;
    font-weight: 600;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 12px;
    transition: var(--transition);
}

.badge i {
    margin-right: 5px;
}

.bg-primary {
    background: linear-gradient(135deg, var(--burgundy) 0%, #6a001a 100%) !important;
    color: white;
}

.bg-success {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%) !important;
    color: white;
}

.bg-info {
    background: linear-gradient(135deg, #17a2b8 0%, #117a8b 100%) !important;
    color: white;
}

/* Alert Styles */
.alert {
    position: relative;
    padding: 16px 20px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 8px;
    transition: var(--transition);
}

.alert-info {
    color: #0c5460;
    background-color: #f0f9fa;
    border-color: #bee5eb;
    background-image: linear-gradient(to right, rgba(209, 236, 241, 0.7), rgba(209, 236, 241, 0.9));
}

.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
    background-image: linear-gradient(to right, rgba(248, 215, 218, 0.7), rgba(248, 215, 218, 0.9));
}

.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
    background-image: linear-gradient(to right, rgba(212, 237, 218, 0.7), rgba(212, 237, 218, 0.9));
}

/* Grid System */
.row {
    display: flex;
    flex-wrap: wrap;
    margin-right: -15px;
    margin-left: -15px;
}

.col-md-4,
.col-md-2,
.col-md-3 {
    position: relative;
    width: 100%;
    padding-right: 15px;
    padding-left: 15px;
}

@media (min-width: 768px) {
    .col-md-4 {
        flex: 0 0 33.333333%;
        max-width: 33.333333%;
    }
    .col-md-2 {
        flex: 0 0 16.666667%;
        max-width: 16.666667%;
    }
    .col-md-3 {
        flex: 0 0 25%;
        max-width: 25%;
    }
}

/* Show More/Less Button Styles */
#showMoreBtn,
#showLessBtn {
    margin: 15px 8px;
    transition: var(--transition);
    min-width: 180px;
}

#showLessBtn {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
    color: white;
    border: none;
    box-shadow: 0 4px 8px rgba(108, 117, 125, 0.2);
}

#showLessBtn:hover {
    background: linear-gradient(135deg, #495057 0%, #6c757d 100%);
    transform: translateY(-3px);
}

/* Custom Styles for Answer History */
.overall-stats {
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.overall-stats .badge {
    margin-right: 0;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.history-row td {
    vertical-align: middle;
}

.extra-row {
    display: none;
    opacity: 0;
    transition: opacity 0.5s ease;
}

/* Custom Styles for Subject Filter */
.subject-filter-form .form-control {
    margin-bottom: 15px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.subject-filter-form .btn {
    margin-top: 8px;
}

/* Utility Classes */
.mb-3 {
    margin-bottom: 1.2rem !important;
}

.mb-4 {
    margin-bottom: 2rem !important;
}

.mt-2 {
    margin-top: 0.6rem !important;
}

.mt-3 {
    margin-top: 1.2rem !important;
}

.mt-4 {
    margin-top: 2rem !important;
}

.ms-2 {
    margin-left: 0.6rem !important;
}

.text-center {
    text-align: center !important;
}

.text-muted {
    color: #6c757d !important;
}

.d-flex {
    display: flex !important;
}

.justify-content-between {
    justify-content: space-between !important;
}

.align-items-center {
    align-items: center !important;
}

/* Animation Effects */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.fade-in {
    animation: fadeIn 0.6s ease forwards;
}

/* Responsive Adjustments */
@media (max-width: 992px) {
    .header-title {
        font-size: 1.7rem;
    }
    
    .form-container {
        padding: 22px;
    }
    
    .btn {
        padding: 9px 16px;
    }
}

@media (max-width: 768px) {
    .header-title {
        font-size: 1.5rem;
    }
    
    .form-container {
        padding: 18px;
        border-radius: 10px;
    }
    
    .table th,
    .table td {
        padding: 10px;
        font-size: 0.9rem;
    }
    
    .list-group-item {
        padding: 16px;
    }
    
    .btn {
        padding: 8px 14px;
        font-size: 0.9rem;
    }
    
    .form-control,
    .form-select {
        padding: 10px 14px;
        font-size: 0.9rem;
    }

    .overall-stats {
        flex-direction: column;
        gap: 8px;
    }
}

@media (max-width: 576px) {
    .header-title {
        font-size: 1.4rem;
    }
    
    .form-container {
        padding: 16px;
    }
    
    .table th,
    .table td {
        padding: 8px;
        font-size: 0.85rem;
    }
}

/* Floating Action Button */
.fab {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--burgundy) 0%, #6a001a 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 6px 20px rgba(128, 0, 32, 0.3);
    z-index: 99;
    transition: var(--transition);
    border: none;
}

.fab:hover {
    transform: translateY(-5px) scale(1.05);
    box-shadow: 0 10px 25px rgba(128, 0, 32, 0.4);
}
    </style>
</head>
<body>
    <!-- Header Bar -->
    <div class="header-bar">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="header-title">USAS E-Learning</h1>
                <div>
                    <span class="me-3 text-light"><?= htmlspecialchars($_SESSION['username']) ?></span>
                    <a href="logout.php" class="btn btn-sm btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Answer History Section -->
        <div class="form-container mb-4 fade-in">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3><i class="fas fa-history text-burgundy"></i> Your Answer History</h3>
                    <?php if (!empty($answers)): ?>
                        <div class="overall-stats mt-3">
                            <span class="badge bg-primary">
                                <i class="fas fa-chart-line"></i> Overall: <?= $overallPercentage ?>%
                            </span>
                            <span class="badge bg-success">
                                <i class="fas fa-check-circle"></i> <?= $totalCorrect ?>/<?= $totalQuestions ?> Correct
                            </span>
                            <span class="badge bg-info">
                                <i class="fas fa-clipboard-list"></i> <?= $totalAttempts ?> Attempts
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                <form method="GET" class="mb-3">
                    <div class="input-group" style="width: 320px;">
                        <select name="history_subject_filter" class="form-select">
                            <option value="">All Subjects</option>
                            <?php
                            $subjects_sql = "SELECT DISTINCT s.id, s.subject_code, s.subject_name 
                                            FROM student_results sr
                                            JOIN subjects s ON sr.subject_id = s.id
                                            WHERE sr.matrix_number = ?
                                            ORDER BY s.subject_name";
                            $stmt = $conn->prepare($subjects_sql);
                            $stmt->bind_param("s", $student_matrix_number);
                            $stmt->execute();
                            $subject_results = $stmt->get_result();
                            
                            while ($subject_row = $subject_results->fetch_assoc()) {
                                $selected = (isset($_GET['history_subject_filter']) && $_GET['history_subject_filter'] == $subject_row['id']) ? 'selected' : '';
                                echo "<option value='{$subject_row['id']}' $selected>{$subject_row['subject_code']} - {$subject_row['subject_name']}</option>";
                            }
                            ?>
                        </select>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <?php if (isset($_GET['history_subject_filter'])): ?>
                            <a href="homepage.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if (empty($answers)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> You haven't answered any questions yet.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table" id="historyTable">
                        <thead>
                            <tr>
                                <th><i class="fas fa-book me-2"></i>Subject</th>
                                <th><i class="fas fa-percentage me-2"></i>Score</th>
                                <th><i class="fas fa-check-double me-2"></i>Correct Answers</th>
                                <th><i class="fas fa-calendar-alt me-2"></i>Date Attempted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($answers, 0, 3) as $answer): ?>
                                <tr class="history-row always-visible">
                                    <td><?= htmlspecialchars($answer['subject_code']) ?> - <?= htmlspecialchars($answer['subject_name']) ?></td>
                                    <td><span class="badge bg-primary"><?= $answer['score_percentage'] ?>%</span></td>
                                    <td><?= $answer['correct_answers'] ?>/<?= $answer['total_questions'] ?></td>
                                    <td><?= date('d M Y, h:i A', strtotime($answer['attempt_date'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($answers) > 3): ?>
                                <?php foreach (array_slice($answers, 3) as $answer): ?>
                                    <tr class="history-row extra-row" style="display: none;">
                                        <td><?= htmlspecialchars($answer['subject_code']) ?> - <?= htmlspecialchars($answer['subject_name']) ?></td>
                                        <td><span class="badge bg-primary"><?= $answer['score_percentage'] ?>%</span></td>
                                        <td><?= $answer['correct_answers'] ?>/<?= $answer['total_questions'] ?></td>
                                        <td><?= date('d M Y, h:i A', strtotime($answer['attempt_date'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (count($answers) > 3): ?>
                    <div class="text-center mt-4" id="historyButtons">
                        <button id="showMoreBtn" class="btn btn-primary">
                            <i class="fas fa-chevron-down me-2"></i>Show More History
                        </button>
                        <button id="showLessBtn" class="btn btn-outline-secondary" style="display: none;">
                            <i class="fas fa-chevron-up me-2"></i>Show Less
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Subject Filter Form -->
        <div class="form-container fade-in">
            <h3 class="mb-4"><i class="fas fa-book-open text-burgundy me-2"></i>Available Subjects</h3>

            <form method="GET" class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fas fa-search"></i></span>
                        <input type="text" name="subject_filter" class="form-control" placeholder="Search by subject name..." value="<?= htmlspecialchars($subject_filter) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="year_filter" class="form-select">
                        <option value="">All Years</option>
                        <?php
                        $current_year = date("Y");
                        for ($i = $current_year; $i >= $current_year - 5; $i--) {
                            $selected = ($year_filter == $i) ? "selected" : "";
                            echo "<option value='$i' $selected>$i</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" name="semester_filter" class="form-control" placeholder="Filter by semester..." value="<?= htmlspecialchars($semester_filter) ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Filter
                    </button>
                    <?php if (!empty($subject_filter) || !empty($year_filter) || !empty($semester_filter)): ?>
                        <a href="homepage.php" class="btn btn-outline-secondary w-100 mt-2">
                            <i class="fas fa-broom me-2"></i>Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if (empty($subjects)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No subjects found. Try adjusting your filter.
                </div>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($subjects as $subject): ?>
                        <div class="list-group-item">
                            <div>
                                <h5 class="mb-2"><?= htmlspecialchars($subject['subject_code']) ?> - <?= htmlspecialchars($subject['subject_name']) ?></h5>
                                <small class="d-block mb-2">
                                    <i class="fas fa-calendar-alt text-muted me-1"></i> Year: <?= $subject['year'] ?> | 
                                    <i class="fas fa-layer-group text-muted me-1"></i> Semester: <?= htmlspecialchars($subject['semester']) ?> | 
                                    <i class="fas fa-clock text-muted me-1"></i> Created: <?= date('d M Y', strtotime($subject['created_at'])) ?>
                                </small>
                                <a href="subject_questions.php?subject_id=<?= $subject['id'] ?>" class="btn btn-primary mt-2">
                                    <i class="fas fa-pencil-alt me-2"></i>Answer Now
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Floating Action Button -->
    <button class="fab" title="Quick Actions">
        <i class="fas fa-bolt"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const showMoreBtn = document.getElementById('showMoreBtn');
            const showLessBtn = document.getElementById('showLessBtn');
            const extraRows = document.querySelectorAll('.extra-row');
            
            if (showMoreBtn) {
                showMoreBtn.addEventListener('click', function() {
                    extraRows.forEach(row => {
                        row.style.display = 'table-row';
                        setTimeout(() => { row.style.opacity = '1'; }, 10);
                    });
                    showMoreBtn.style.display = 'none';
                    showLessBtn.style.display = 'inline-block';
                });
            }
            
            if (showLessBtn) {
                showLessBtn.addEventListener('click', function() {
                    extraRows.forEach(row => {
                        row.style.opacity = '0';
                        setTimeout(() => { row.style.display = 'none'; }, 300);
                    });
                    showMoreBtn.style.display = 'inline-block';
                    showLessBtn.style.display = 'none';
                });
            }

            // Add animation to elements
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((el, index) => {
                el.style.animationDelay = `${index * 0.1}s`;
            });

            // Floating action button functionality
            const fab = document.querySelector('.fab');
            if (fab) {
                fab.addEventListener('click', function() {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }
        });
    </script>
</body>
</html>