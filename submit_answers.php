<?php
session_start();
if (!isset($_SESSION['matrix_number']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

include('config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['answers'])) {
    header("Location: homepage.php");
    exit();
}

$matrix_number = $_SESSION['matrix_number'];
$subject_id = intval($_POST['subject_id']);
$answers = $_POST['answers']; // Array: question_id => chosen_option

// 1. Get the correct answers from DB
$sql = "SELECT id, correct_answer FROM questions WHERE subject_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();

$correctAnswers = [];
while ($row = $result->fetch_assoc()) {
    $correctAnswers[$row['id']] = $row['correct_answer'];
}

// 2. Compare submitted answers
$totalQuestions = count($correctAnswers);
$correctCount = 0;

foreach ($correctAnswers as $question_id => $correct_option) {
    if (isset($answers[$question_id]) && $answers[$question_id] === $correct_option) {
        $correctCount++;
    }
}

// 3. Calculate score
$score = round(($correctCount / $totalQuestions) * 100, 2);

// 4. Insert the result into the student_results table
$sql = "INSERT INTO student_results (matrix_number, subject_id, total_questions, correct_answers, score_percentage, attempt_date)
        VALUES (?, ?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("siiid", $matrix_number, $subject_id, $totalQuestions, $correctCount, $score);
$stmt->execute();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Result - USAS E-Learning</title>
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

    /* Result Card */
    .result-card {
        background: white;
        padding: 40px;
        border-radius: 15px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        border: 1px solid #e0e0e0;
        margin: 80px auto;
        max-width: 600px;
        text-align: center;
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

    .btn-success {
        background-color: var(--deep-navy);
        border: none;
        transition: all 0.3s;
    }

    .btn-success:hover {
        background-color: #0A1F3D;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(10, 31, 61, 0.3);
    }

    /* Header */
    .page-header {
        background-color: var(--deep-navy);
        color: white;
        padding: 30px;
        border-radius: 10px;
        margin-bottom: 30px;
    }

    /* Typography */
    h1, h2, h3 {
        color: var(--deep-navy);
    }

    .score-display {
        font-size: 3rem;
        color: var(--burgundy);
        font-weight: bold;
        margin: 20px 0;
    }

    .correct-count {
        font-size: 2rem;
        color: var(--deep-navy);
        margin-bottom: 20px;
    }

    /* Button Container */
    .button-container {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-top: 30px;
    }

    /* Header */
    .d-flex.justify-content-between {
        margin-bottom: 30px;
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

        <div class="result-card">
            <h1>🎯 Your Result</h1>
            <div class="score-display"><?= $score ?>%</div>
            <div class="correct-count"><?= $correctCount ?> out of <?= $totalQuestions ?> correct</div>
            
            <div class="button-container">
                <a href="homepage.php" class="btn btn-primary">🏠 Back to Homepage</a>
                <a href="view_history.php" class="btn btn-success">📜 View Your History</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>