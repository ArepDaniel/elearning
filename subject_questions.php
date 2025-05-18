<?php
session_start();
if (!isset($_SESSION['matrix_number']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

include('config.php');

$subject_id = intval($_GET['subject_id'] ?? 0);

// Get subject details
$sql = "SELECT * FROM subjects WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    header("Location: homepage.php");
    exit();
}
$subject = $result->fetch_assoc();

// Get questions for this subject
$sql = "SELECT * FROM questions WHERE subject_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($subject['subject_code']) ?> Questions - USAS E-Learning</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    :root {
        --deep-navy: #0A1F3D;
        --burgundy: #800020;
        --gold: #D4AF37;
        --white-sand: #F5F5F0;
        --pure-white: #FFFFFF;
    }

    body {
        background-color: var(--white-sand);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .form-container {
        max-width: 800px;
        margin: 30px auto;
        padding: 30px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .page-header {
        background-color: var(--deep-navy);
        color: white;
        padding: 30px;
        border-radius: 10px;
        margin-bottom: 30px;
    }

    .subject-title-display {
        color: var(--pure-white);
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }
    
    .question-card {
        background: white;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .question-text-en {
        font-weight: 500;
        margin-bottom: 5px;
    }

    .question-text-ms {
        color: #555;
        margin-bottom: 20px;
        font-style: italic;
    }

    .option-item {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
        padding: 10px;
        border-radius: 5px;
        transition: background 0.2s;
    }

    .option-item:hover {
        background: #f8f9fa;
    }

    .option-text {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin-left: 10px;
    }

    .option-en {
        flex: 1;
    }

    .option-ms {
        flex: 1;
        color: #666;
    }

    .option-separator {
        color: #aaa;
    }

    .submit-btn {
        background-color: var(--burgundy);
        border: none;
        padding: 12px 0;
        font-size: 18px;
        border-radius: 8px;
        margin-top: 20px;
    }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center py-3 mb-4 border-bottom">
            <h2 class="m-0">USAS E-Learning</h2>
            <div>
                <span class="me-3"><?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="logout.php" class="btn btn-sm btn-danger">Logout</a>
            </div>
        </div>

        <div class="page-header">
            <h1 class="subject-title-display m-0">
                <?= htmlspecialchars($subject['subject_code']) ?>: <?= htmlspecialchars($subject['subject_name']) ?>
            </h1>
            <p class="text-white-50 mb-2">Year: <?= htmlspecialchars($subject['year']) ?></p>
            <a href="homepage.php" class="btn btn-outline-light">Back to Subjects</a>
        </div>

        <?php if (empty($questions)): ?>
            <div class="alert alert-info">No questions found for this subject.</div>
        <?php else: ?>
            <form action="submit_answers.php" method="POST" class="form-container">
                <input type="hidden" name="subject_id" value="<?= $subject_id ?>">

                <?php foreach ($questions as $index => $question): ?>
                    <?php 
                    $qParts = explode(' / ', $question['question_text'], 2);
                    ?>
                    <div class="question-card">
                        <h4 class="mb-3">Question <?= $index + 1 ?></h4>
                        
                        <div class="question-text-en"><?= htmlspecialchars($qParts[0]) ?></div>
                        <?php if (count($qParts) > 1): ?>
                            <div class="question-text-ms"><?= htmlspecialchars($qParts[1]) ?></div>
                        <?php endif; ?>

                        <div class="options-container mt-3">
                            <?php foreach (['a', 'b', 'c', 'd'] as $option): ?>
                                <?php if (!empty($question['option_' . $option])): ?>
                                    <?php 
                                    $optParts = explode(' / ', $question['option_' . $option], 2);
                                    ?>
                                    <div class="option-item">
                                        <input class="form-check-input" type="radio" 
                                               name="answers[<?= $question['id'] ?>]" 
                                               value="<?= strtoupper($option) ?>" 
                                               id="q<?= $question['id'] ?><?= $option ?>" required>
                                        <label class="option-text" for="q<?= $question['id'] ?><?= $option ?>">
                                            <span class="fw-bold"><?= strtoupper($option) ?>.</span>
                                            <span class="option-en"><?= htmlspecialchars($optParts[0]) ?></span>
                                            <?php if (count($optParts) > 1): ?>
                                                <span class="option-separator">/</span>
                                                <span class="option-ms"><?= htmlspecialchars($optParts[1]) ?></span>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="btn btn-primary submit-btn w-100">Submit Answers</button>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>