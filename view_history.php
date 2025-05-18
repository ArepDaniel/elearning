<?php
session_start();
if (!isset($_SESSION['matrix_number']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

include('config.php');

$matrix_number = $_SESSION['matrix_number'];

// Get student's history
$sql = "SELECT sr.*, s.subject_code, s.subject_name
        FROM student_results sr
        JOIN subjects s ON sr.subject_id = s.id
        WHERE sr.matrix_number = ?
        ORDER BY sr.attempt_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $matrix_number);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Answer History - USAS E-Learning</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    :root {
        --deep-navy: #0A1F3D;
        --burgundy: #800020;
        --gold: #D4AF37;
        --white-sand: #F5F5F0;
        --light-gold: #F8E8C8;
    }

    body {
        background-color: var(--white-sand);
        color: #333;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    /* History Container */
    .history-container {
        max-width: 1000px;
        margin: 30px auto;
        padding: 30px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        border: 1px solid #e0e0e0;
    }

    /* Table Styling */
    .table {
        width: 100%;
        margin-bottom: 1rem;
        color: #212529;
    }

    .table th {
        background-color: var(--deep-navy);
        color: white;
        padding: 12px;
    }

    .table td {
        padding: 12px;
        vertical-align: middle;
        border-top: 1px solid #dee2e6;
    }

    .table-hover tbody tr:hover {
        background-color: rgba(0, 0, 0, 0.05);
    }

    /* Score Highlight */
    .score-high {
        color: #28a745;
        font-weight: bold;
    }

    .score-medium {
        color: #ffc107;
        font-weight: bold;
    }

    .score-low {
        color: #dc3545;
        font-weight: bold;
    }

    /* Buttons */
    .btn-primary {
        background-color: var(--burgundy);
        border: none;
        color: white;
        transition: all 0.3s;
    }

    .btn-primary:hover {
        background-color: #600018;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(128, 0, 32, 0.3);
    }

    /* Header */
    .d-flex.justify-content-between {
        margin-bottom: 30px;
    }

    /* Typography */
    h1, h2, h3 {
        color: var(--deep-navy);
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 40px;
    }

    /* Responsive Table */
    @media (max-width: 768px) {
        .table-responsive {
            display: block;
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
    }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center py-3 mb-4 border-bottom">
            <h2>USAS E-Learning - Student Portal</h2>
            <div>
                <span class="me-3"><?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="logout.php" class="btn btn-sm btn-danger">Logout</a>
            </div>
        </div>

        <div class="history-container">
            <h1 class="mb-4">📜 Your Answer History</h1>
            
            <?php if (empty($history)): ?>
                <div class="empty-state alert alert-info">
                    <h4>No Attempts Yet</h4>
                    <p>You haven't attempted any quizzes yet. Start by selecting a subject from the homepage.</p>
                    <a href="homepage.php" class="btn btn-primary mt-3">Browse Subjects</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Score</th>
                                <th>Correct/Total</th>
                                <th>Date Attempted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $record): 
                                $scoreClass = '';
                                if ($record['score_percentage'] >= 70) {
                                    $scoreClass = 'score-high';
                                } elseif ($record['score_percentage'] >= 40) {
                                    $scoreClass = 'score-medium';
                                } else {
                                    $scoreClass = 'score-low';
                                }
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($record['subject_code']) ?> - <?= htmlspecialchars($record['subject_name']) ?></td>
                                    <td class="<?= $scoreClass ?>"><?= $record['score_percentage'] ?>%</td>
                                    <td><?= $record['correct_answers'] ?>/<?= $record['total_questions'] ?></td>
                                    <td><?= date('d M Y, h:i A', strtotime($record['attempt_date'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-end mt-4">
                <a href="homepage.php" class="btn btn-primary">⬅ Back to Homepage</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>