<?php
session_start();

// Redirect if not lecturer
if (!isset($_SESSION['matrix_number']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: login.php");
    exit();
}

include('config.php');

// Get subject ID from URL
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

// Fetch subject details
$subject = [];
$sql = "SELECT * FROM subjects WHERE id = ? AND matrix_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $subject_id, $_SESSION['matrix_number']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $subject = $result->fetch_assoc();
} else {
    header("Location: lecturer_homepage.php");
    exit();
}

// Fetch all questions for this subject
$questions = [];
$sql = "SELECT * FROM questions WHERE subject_id = ? ORDER BY id";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $questions[] = $row;
}

// Handle question deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_question'])) {
    $question_id = intval($_POST['question_id']);
    
    $sql = "DELETE FROM questions WHERE id = ? AND subject_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $question_id, $subject_id);
    
    if ($stmt->execute()) {
        header("Location: lecturer_subject_question.php?subject_id=$subject_id&message=Question+deleted+successfully");
        exit();
    } else {
        $message = "Error deleting question: " . $conn->error;
    }
}

$message = isset($_GET['message']) ? $_GET['message'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Questions - USAS E-Learning</title>
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

    .question-container {
        max-width: 1000px;
        margin: 30px auto;
        padding: 30px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        border: 1px solid #e0e0e0;
    }

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
        color: white;
    }

    .btn-sm {
        transition: all 0.3s;
    }

    .btn-sm:hover {
        transform: scale(1.05);
    }

    .question-card {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        transition: all 0.3s;
    }

    .question-card:hover {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .correct-answer {
        background-color: rgba(212, 175, 55, 0.1);
        border-left: 3px solid var(--gold);
    }

    .option {
        padding: 8px 12px;
        margin-bottom: 5px;
        border-radius: 4px;
    }

    .header-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }

    .question-list {
        max-height: 600px;
        overflow-y: auto;
        padding-right: 10px;
    }
    
    /* Bilingual display styles */
    .question-text {
        margin-bottom: 15px;
    }
    
    .malay-text {
        color: #555;
        margin-top: 5px;
    }
    
    .option-content {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .english-option {
        flex: 1;
    }
    
    .malay-option {
        flex: 1;
        color: #555;
    }
    
    .option-separator {
        color: #999;
    }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center py-3 mb-4 border-bottom">
            <h2>USAS E-Learning - View Questions</h2>
            <div>
                <span class="me-3"><?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="lecturer_homepage.php" class="btn btn-sm btn-primary me-2">Back to Homepage</a>
                <a href="logout.php" class="btn btn-sm btn-danger">Logout</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= strpos($message, 'Error') === false ? 'success' : 'danger' ?>">
                <?= urldecode($message) ?>
            </div>
        <?php endif; ?>

        <div class="question-container">
            <div class="header-section">
                <div>
                    <h3><?= htmlspecialchars($subject['subject_code']) ?> - <?= htmlspecialchars($subject['subject_name']) ?></h3>
                    <p class="text-muted">Year: <?= $subject['year'] ?></p>
                </div>
                <div>
                    <a href="add_question.php?subject_id=<?= $subject_id ?>" class="btn btn-primary">Add New Question</a>
                </div>
            </div>

            <div class="mb-4">
                <p>Total Questions: <?= count($questions) ?></p>
            </div>

            <?php if (empty($questions)): ?>
                <div class="alert alert-info">No questions found for this subject.</div>
            <?php else: ?>
                <div class="question-list">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="question-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5>Question <?= $index + 1 ?></h5>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this question?');">
                                    <input type="hidden" name="question_id" value="<?= $question['id'] ?>">
                                    <button type="submit" name="delete_question" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                            
                            <!-- Question Text -->
                            <div class="question-text">
                                <?php 
                                $questionParts = explode(' / ', $question['question_text'], 2);
                                echo htmlspecialchars($questionParts[0]);
                                if (count($questionParts) > 1): ?>
                                    <div class="malay-text"><?= htmlspecialchars($questionParts[1]) ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Options -->
                            <div class="options">
                                <?php foreach (['a', 'b', 'c', 'd'] as $option): ?>
                                    <?php if (!empty($question['option_' . $option])): ?>
                                        <div class="option <?= $question['correct_answer'] === strtoupper($option) ? 'correct-answer' : '' ?>">
                                            <strong><?= strtoupper($option) ?>)</strong>
                                            <?php 
                                            $optionParts = explode(' / ', $question['option_' . $option], 2);
                                            ?>
                                            <div class="option-content">
                                                <div class="english-option"><?= htmlspecialchars($optionParts[0]) ?></div>
                                                <span class="option-separator">/</span>
                                                <div class="malay-option"><?= isset($optionParts[1]) ? htmlspecialchars($optionParts[1]) : '' ?></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Correct Answer -->
                            <div class="mt-3">
                                <small class="text-muted">Correct Answer: <?= $question['correct_answer'] ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>