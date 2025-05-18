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
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['delete_question'])) {
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
    elseif (isset($_POST['add_questions'])) {
        $successCount = 0;
        $errorCount = 0;

        foreach ($_POST['questions'] as $question) {
            $question_text = $conn->real_escape_string($question['question_text']);
            $option_a = $conn->real_escape_string($question['option_a']);
            $option_b = $conn->real_escape_string($question['option_b']);
            $option_c = $conn->real_escape_string($question['option_c']);
            $option_d = $conn->real_escape_string($question['option_d']);
            $correct_answer = $question['correct_answer'];

            $sql = "INSERT INTO questions (subject_id, question_text, option_a, option_b, option_c, option_d, correct_answer) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssss", $subject_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer);
            $stmt->execute() ? $successCount++ : $errorCount++;
        }

        $message = $errorCount > 0 ? 
            "Added $successCount questions successfully, but failed to add $errorCount questions." : 
            "All $successCount questions added successfully!";
        
        header("Location: lecturer_subject_question.php?subject_id=$subject_id&message=" . urlencode($message));
        exit();
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
    
    .question-group {
        margin-bottom: 20px;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 5px;
        background-color: #f9f9f9;
    }
    
    .question-group h5 {
        color: var(--burgundy);
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
            </div>

            <div class="mb-4">
                <p>Total Questions: <?= count($questions) ?></p>
            </div>

            <!-- Add Questions Form -->
            <div class="mb-5">
                <h4>Add New Questions</h4>
                <form id="questionForm" method="POST">
                    <input type="hidden" name="subject_id" value="<?= $subject_id ?>">
                    
                    <div id="questionsContainer">
                        <!-- First question will be here -->
                        <div class="question-group mb-4 p-3 border rounded">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5>Question 1</h5>
                                <button type="button" class="btn btn-sm btn-danger remove-question" style="display:none;">Remove</button>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Question Text</label>
                                <textarea class="form-control" name="questions[0][question_text]" rows="3" required></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Option A</label>
                                    <input type="text" class="form-control" name="questions[0][option_a]" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Option B</label>
                                    <input type="text" class="form-control" name="questions[0][option_b]" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Option C</label>
                                    <input type="text" class="form-control" name="questions[0][option_c]" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Option D</label>
                                    <input type="text" class="form-control" name="questions[0][option_d]" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Correct Answer</label>
                                <select class="form-control" name="questions[0][correct_answer]" required>
                                    <option value="">Select Correct Answer</option>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                    <option value="D">D</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <button type="button" id="addQuestionBtn" class="btn btn-secondary">Add Another Question</button>
                        <button type="submit" name="add_questions" class="btn btn-primary">Submit All Questions</button>
                    </div>
                </form>
            </div>

            <!-- Existing Questions List -->
            <div>
                <h4>Existing Questions</h4>
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
                                <p class="mb-4"><?= nl2br(htmlspecialchars($question['question_text'])) ?></p>
                                
                                <div class="options">
                                    <div class="option <?= $question['correct_answer'] === 'A' ? 'correct-answer' : '' ?>">
                                        <strong>A)</strong> <?= htmlspecialchars($question['option_a']) ?>
                                    </div>
                                    <div class="option <?= $question['correct_answer'] === 'B' ? 'correct-answer' : '' ?>">
                                        <strong>B)</strong> <?= htmlspecialchars($question['option_b']) ?>
                                    </div>
                                    <div class="option <?= $question['correct_answer'] === 'C' ? 'correct-answer' : '' ?>">
                                        <strong>C)</strong> <?= htmlspecialchars($question['option_c']) ?>
                                    </div>
                                    <div class="option <?= $question['correct_answer'] === 'D' ? 'correct-answer' : '' ?>">
                                        <strong>D)</strong> <?= htmlspecialchars($question['option_d']) ?>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <small class="text-muted">Correct Answer: <?= $question['correct_answer'] ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const questionsContainer = document.getElementById('questionsContainer');
        const addQuestionBtn = document.getElementById('addQuestionBtn');
        let questionCount = 1;
        
        // Add new question form
        addQuestionBtn?.addEventListener('click', function() {
            questionCount++;
            const newQuestion = document.createElement('div');
            newQuestion.className = 'question-group mb-4 p-3 border rounded';
            newQuestion.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>Question ${questionCount}</h5>
                    <button type="button" class="btn btn-sm btn-danger remove-question">Remove</button>
                </div>
                <div class="mb-3">
                    <label class="form-label">Question Text</label>
                    <textarea class="form-control" name="questions[${questionCount-1}][question_text]" rows="3" required></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Option A</label>
                        <input type="text" class="form-control" name="questions[${questionCount-1}][option_a]" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Option B</label>
                        <input type="text" class="form-control" name="questions[${questionCount-1}][option_b]" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Option C</label>
                        <input type="text" class="form-control" name="questions[${questionCount-1}][option_c]" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Option D</label>
                        <input type="text" class="form-control" name="questions[${questionCount-1}][option_d]" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Correct Answer</label>
                    <select class="form-control" name="questions[${questionCount-1}][correct_answer]" required>
                        <option value="">Select Correct Answer</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                    </select>
                </div>
            `;
            questionsContainer.appendChild(newQuestion);
            
            // Show remove button on first question if there are multiple
            if (questionCount > 1) {
                document.querySelectorAll('.remove-question').forEach(btn => btn.style.display = 'block');
            }
        });
        
        // Remove question form
        questionsContainer?.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-question')) {
                e.target.closest('.question-group').remove();
                questionCount--;
                
                // Hide remove button if only one question remains
                if (questionCount === 1) {
                    document.querySelector('.remove-question').style.display = 'none';
                }
                
                // Renumber remaining questions
                document.querySelectorAll('.question-group h5').forEach((title, index) => {
                    title.textContent = `Question ${index + 1}`;
                });
            }
        });
    });
    </script>
</body>
</html>