<?php
session_start();

// Include PHPWord manually (no Composer)
require_once __DIR__ . '/vendor/phpoffice/PHPWord-master/src/PhpWord/Exception/Exception.php';
require_once __DIR__ . '/vendor/phpoffice/PHPWord-master/src/PhpWord/PhpWord.php';
require_once __DIR__ . '/vendor/phpoffice/PHPWord-master/src/PhpWord/IOFactory.php';
require_once __DIR__ . '/vendor/phpoffice/PHPWord-master/src/PhpWord/Settings.php';

if (!isset($_SESSION['matrix_number']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: login.php");
    exit();
}

include(__DIR__ . '/config.php');

$message = '';
$subjects = [];

// Fetch subjects by lecturer
$lecturer_matrix = $_SESSION['matrix_number'];
$sql = "SELECT * FROM subjects WHERE matrix_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $lecturer_matrix);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}

// Get filter parameters
$matrix_filter = isset($_GET['matrix_filter']) ? $_GET['matrix_filter'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 4; // Number of subjects per page

// Get student statistics with filtering and pagination
$stats_sql = "SELECT 
                s.id as subject_id,
                s.subject_code, 
                s.subject_name,
                COUNT(sr.id) as total_attempts,
                SUM(sr.correct_answers) as total_correct,
                SUM(sr.total_questions) as total_questions,
                ROUND(AVG(sr.score_percentage), 2) as avg_score
            FROM subjects s
            LEFT JOIN student_results sr ON s.id = sr.subject_id
            WHERE s.matrix_number = ? ";

// Add matrix number filter if provided
if (!empty($matrix_filter)) {
    $stats_sql .= " AND sr.matrix_number = ? ";
}

$stats_sql .= "GROUP BY s.id
            ORDER BY s.subject_name
            LIMIT ?, ?";

$stmt = $conn->prepare($stats_sql);

// Bind parameters based on whether filter is applied
if (!empty($matrix_filter)) {
    $offset = ($page - 1) * $per_page;
    $stmt->bind_param("ssii", $lecturer_matrix, $matrix_filter, $offset, $per_page);
} else {
    $offset = ($page - 1) * $per_page;
    $stmt->bind_param("sii", $lecturer_matrix, $offset, $per_page);
}

$stmt->execute();
$stats_result = $stmt->get_result();
$subject_stats = $stats_result->fetch_all(MYSQLI_ASSOC);

// Get total count of subjects for pagination
$count_sql = "SELECT COUNT(DISTINCT s.id) as total 
              FROM subjects s
              LEFT JOIN student_results sr ON s.id = sr.subject_id
              WHERE s.matrix_number = ?";

if (!empty($matrix_filter)) {
    $count_sql .= " AND sr.matrix_number = ?";
}

$count_stmt = $conn->prepare($count_sql);

if (!empty($matrix_filter)) {
    $count_stmt->bind_param("ss", $lecturer_matrix, $matrix_filter);
} else {
    $count_stmt->bind_param("s", $lecturer_matrix);
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_subjects = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_subjects / $per_page);

// Calculate overall statistics with filter
$overall_sql = "SELECT 
                COUNT(sr.id) as total_attempts,
                SUM(sr.correct_answers) as total_correct,
                SUM(sr.total_questions) as total_questions
            FROM subjects s
            JOIN student_results sr ON s.id = sr.subject_id
            WHERE s.matrix_number = ?";

if (!empty($matrix_filter)) {
    $overall_sql .= " AND sr.matrix_number = ?";
}

$overall_stmt = $conn->prepare($overall_sql);

if (!empty($matrix_filter)) {
    $overall_stmt->bind_param("ss", $lecturer_matrix, $matrix_filter);
} else {
    $overall_stmt->bind_param("s", $lecturer_matrix);
}

$overall_stmt->execute();
$overall_result = $overall_stmt->get_result();
$overall_stats = $overall_result->fetch_assoc();

$overallAttempts = $overall_stats['total_attempts'] ?? 0;
$overallCorrect = $overall_stats['total_correct'] ?? 0;
$overallQuestions = $overall_stats['total_questions'] ?? 0;
$overallPercentage = $overallQuestions > 0 ? round(($overallCorrect / $overallQuestions) * 100, 2) : 0;

$overallPercentage = $overallQuestions > 0 ? round(($overallCorrect / $overallQuestions) * 100, 2) : 0;

// Add subject
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_subject'])) {
    $subject_code = $conn->real_escape_string($_POST['subject_code']);
    $subject_name = $conn->real_escape_string($_POST['subject_name']);
    $year = intval($_POST['year']);
    $semester = $conn->real_escape_string($_POST['semester']);
    $matrix_number = $_SESSION['matrix_number'];

    $sql = "INSERT INTO subjects (subject_code, subject_name, year, semester, matrix_number) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssis", $subject_code, $subject_name, $year, $semester, $matrix_number);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Subject added successfully.";
        $_SESSION['message_type'] = 'success';
        header("Location: lecturer_homepage.php");
        exit();
    } else {
        $message = "Error adding subject: " . $conn->error;
    }
}

// Add manual questions with bilingual support
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_questions'])) {
    $subject_id = intval($_POST['subject_id']);
    $successCount = 0;
    $errorCount = 0;

    // Verify subject ownership
    $verify_sql = "SELECT id FROM subjects WHERE id = ? AND matrix_number = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("is", $subject_id, $lecturer_matrix);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        $message = "Error: You can only add questions to your own subjects.";
    } else {
        foreach ($_POST['questions'] as $question) {
            // Process bilingual fields with / separator
            $question_text = $conn->real_escape_string(trim($question['question_text']));
            $option_a = $conn->real_escape_string(trim($question['option_a']));
            $option_b = $conn->real_escape_string(trim($question['option_b']));
            $option_c = $conn->real_escape_string(trim($question['option_c']));
            $option_d = $conn->real_escape_string(trim($question['option_d']));
            $correct_answer = $question['correct_answer'];

            $sql = "INSERT INTO questions (subject_id, question_text, option_a, option_b, option_c, option_d, correct_answer) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssss", $subject_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer);
            $stmt->execute() ? $successCount++ : $errorCount++;
        }

        $_SESSION['message'] = $errorCount > 0 ? 
            "Added $successCount questions successfully, but failed to add $errorCount questions." : 
            "All $successCount questions added successfully!";
        $_SESSION['message_type'] = $errorCount > 0 ? 'warning' : 'success';
        header("Location: lecturer_homepage.php");
        exit();
    }
}

// Handle file upload with bilingual support
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['question_file'])) {
    $subject_id = intval($_POST['file_subject_id']);
    $upload_dir = __DIR__ . "/uploads/";

    // Verify subject ownership
    $verify_sql = "SELECT id FROM subjects WHERE id = ? AND matrix_number = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("is", $subject_id, $lecturer_matrix);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        $_SESSION['message'] = "Error: You can only upload questions to your own subjects.";
        $_SESSION['message_type'] = 'danger';
        header("Location: lecturer_homepage.php");
        exit();
    }

    try {
        // Create upload directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                throw new Exception("Failed to create upload directory.");
            }
        }

        // Validate file
        $filename = basename($_FILES['question_file']['name']);
        $target_file = $upload_dir . uniqid() . '_' . $filename;
        $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Check file type
        if (!in_array($fileType, ['docx', 'pdf'])) {
            throw new Exception("Only DOCX or PDF files are allowed.");
        }

        // Check file size (max 5MB)
        if ($_FILES['question_file']['size'] > 5000000) {
            throw new Exception("File size must be less than 5MB.");
        }

        // Move uploaded file
        if (!move_uploaded_file($_FILES['question_file']['tmp_name'], $target_file)) {
            throw new Exception("Error uploading file: " . $_FILES['question_file']['error']);
        }

        // Process file based on type
        $text = '';
        
        if ($fileType == 'docx') {
            require_once __DIR__ . '/vendor/phpoffice/PHPWord-master/src/PhpWord/Autoloader.php';
            \PhpOffice\PhpWord\Autoloader::register();
            require_once __DIR__ . '/vendor/phpoffice/PHPWord-master/src/PhpWord/Reader/Word2007.php';

            $reader = new \PhpOffice\PhpWord\Reader\Word2007();
            $phpWord = $reader->load($target_file);

            foreach ($phpWord->getSections() as $section) {
                $elements = $section->getElements();
                foreach ($elements as $element) {
                    if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                        foreach ($element->getElements() as $textElement) {
                            if ($textElement instanceof \PhpOffice\PhpWord\Element\Text) {
                                $text .= $textElement->getText() . "\n";
                            }
                        }
                    } elseif ($element instanceof \PhpOffice\PhpWord\Element\Text) {
                        $text .= $element->getText() . "\n";
                    }
                }
            }
        } elseif ($fileType == 'pdf') {
            $escaped_file = escapeshellarg($target_file);
            $pdftotext_path = 'C:\\poppler\\Release-24.08.0-0\\poppler-24.08.0\\Library\\bin\\pdftotext.exe';

            if (!file_exists($pdftotext_path)) {
                throw new Exception("pdftotext.exe not found at: $pdftotext_path");
            }

            $command = "\"$pdftotext_path\" -layout -nopgbrk $escaped_file - 2>&1";
            $text = shell_exec($command);

            if ($text === null) {
                throw new Exception("Failed to execute pdftotext command.");
            }
        }

        // Store file info in database
        $sql = "INSERT INTO question_documents (subject_id, filename, filepath) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $subject_id, $filename, $target_file);
        if (!$stmt->execute()) {
            throw new Exception("Error saving file info: " . $conn->error);
        }

        // Parse questions with improved format
        $questions = [];
        $blocks = preg_split('/(?=\n\s*Q\d+\.)/i', $text);
        
        foreach ($blocks as $block) {
            if (empty(trim($block))) continue;
            
            $lines = preg_split('/\r\n|\n|\r/', trim($block));
            $currentQuestion = [
                'question_text' => '',
                'options' => [],
                'correct_answer' => ''
            ];
            
            $currentOption = null;
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // Question detection
                if (preg_match('/^(Q\d+\.|Question\s*\d+\.?)\s*(.*)/i', $line, $qMatches)) {
                    $currentQuestion['question_text'] = $qMatches[2];
                } 
                // Option detection
                elseif (preg_match('/^([A-Da-d])[\.\)]\s*(.*)/', $line, $optMatches)) {
                    $currentOption = strtoupper($optMatches[1]);
                    $currentQuestion['options'][$currentOption] = $optMatches[2];
                }
                // Answer detection
                elseif (preg_match('/^(Correct\s+answer|Jawapan)\s*:?\s*([A-Da-d])/i', $line, $ansMatches)) {
                    $currentQuestion['correct_answer'] = strtoupper($ansMatches[2]);
                }
                // Malay question part
                elseif (preg_match('/^(Antara berikut|Manakah|Bagaimanakah|Apakah)/i', $line)) {
                    if (!empty($currentQuestion['question_text'])) {
                        $currentQuestion['question_text'] .= ' / ' . $line;
                    } else {
                        $currentQuestion['question_text'] = $line;
                    }
                }
                // Additional option text
                elseif ($currentOption) {
                    $currentQuestion['options'][$currentOption] .= ' ' . $line;
                }
                // Additional question text
                else {
                    if (!empty($currentQuestion['question_text'])) {
                        if (strpos($currentQuestion['question_text'], '/') !== false) {
                            $parts = explode('/', $currentQuestion['question_text']);
                            $currentQuestion['question_text'] = trim($parts[0]) . ' / ' . trim($parts[1]) . ' ' . $line;
                        } else {
                            $currentQuestion['question_text'] .= ' ' . $line;
                        }
                    } else {
                        $currentQuestion['question_text'] = $line;
                    }
                }
            }
            
            // Clean up the parsed question
            if (!empty($currentQuestion['question_text'])) {
                // Clean up question text
                $currentQuestion['question_text'] = preg_replace('/\s+/', ' ', trim($currentQuestion['question_text']));
                $currentQuestion['question_text'] = preg_replace('/\s*\/\s*/', ' / ', $currentQuestion['question_text']);
                
                // Clean up options
                foreach ($currentQuestion['options'] as &$option) {
                    $option = preg_replace('/\s+/', ' ', trim($option));
                    $option = preg_replace('/\s*\/\s*/', ' / ', $option);
                }
                
                // Validate we have all required components
                if (!empty($currentQuestion['question_text']) && 
                    count($currentQuestion['options']) >= 4 && 
                    !empty($currentQuestion['correct_answer']) &&
                    in_array($currentQuestion['correct_answer'], ['A','B','C','D'])) {
                    $questions[] = $currentQuestion;
                }
            }
        }

        // Insert questions into database
        $inserted = 0;
        $errors = 0;
        foreach ($questions as $q) {
            $question_text = $q['question_text'];
            $option_a = $q['options']['A'] ?? '';
            $option_b = $q['options']['B'] ?? '';
            $option_c = $q['options']['C'] ?? '';
            $option_d = $q['options']['D'] ?? '';
            $correct_answer = $q['correct_answer'];

            $sql = "INSERT INTO questions (subject_id, question_text, option_a, option_b, option_c, option_d, correct_answer) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssss", $subject_id, 
                $question_text, 
                $option_a, 
                $option_b, 
                $option_c, 
                $option_d, 
                $correct_answer);
            if ($stmt->execute()) {
                $inserted++;
            } else {
                $errors++;
                error_log("Error inserting question: " . $stmt->error);
            }
        }

        // Set success message and redirect
        if ($inserted > 0) {
            $msg = "File uploaded successfully. $inserted questions added.";
            if ($errors > 0) {
                $msg .= " Failed to add $errors questions.";
                $_SESSION['message_type'] = 'warning';
            } else {
                $_SESSION['message_type'] = 'success';
            }
            $_SESSION['message'] = $msg;
        } else {
            $_SESSION['message'] = "No valid questions found in the file.";
            $_SESSION['message_type'] = 'warning';
        }

        header("Location: lecturer_homepage.php");
        exit();

    } catch (Exception $e) {
        // Clean up if error occurred
        if (isset($target_file) && file_exists($target_file)) {
            unlink($target_file);
        }
        
        $_SESSION['message'] = "Error processing file: " . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
        error_log("File processing error: " . $e->getMessage());
        header("Location: lecturer_homepage.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Portal - USAS E-Learning</title>
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

        .container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 30px;
            box-sizing: border-box;
        }

        .form-container {
            width: 100%;
            max-width: 100%;
            margin: 25px auto;
            padding: 28px 40px;
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

        .stats-container {
            width: 100%;
            max-width: 100%;
            margin: 25px auto;
            padding: 28px 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-sizing: border-box;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .stats-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12);
        }

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
            padding: 18px;
            vertical-align: middle;
            border-top: 1px solid #f0f0f0;
            text-align: left;
            transition: var(--transition);
            font-size: 1rem;
            white-space: normal;
            word-break: break-word;
        }

        .table thead th {
            vertical-align: middle;
            border-bottom: none;
            background-color: var(--deep-navy);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            padding: 16px;
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
            padding: 24px;
            margin-bottom: 16px;
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
            font-size: 1.2rem;
            margin-bottom: 12px;
        }

        .list-group-item small {
            color: #666;
            display: block;
            margin-bottom: 12px;
        }

        .form-control,
        .form-select {
            display: block;
            width: 100%;
            padding: 14px 18px;
            font-size: 1.05rem;
            line-height: 1.5;
            color: #495057;
            background-color: white;
            background-clip: padding-box;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            transition: var(--transition);
            margin-bottom: 20px;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.03);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--gold);
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(212, 175, 55, 0.2), inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }

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

        .nav-tabs {
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #495057;
            padding: 12px 20px;
            font-weight: 500;
            border-radius: 0;
            margin-right: 5px;
            transition: var(--transition);
        }

        .nav-tabs .nav-link:hover {
            border-color: transparent;
            color: var(--burgundy);
        }

        .nav-tabs .nav-link.active {
            color: var(--burgundy);
            background-color: transparent;
            border-bottom: 2px solid var(--burgundy);
            font-weight: 600;
        }

        .tab-content {
            padding: 20px 0;
        }

        .tab-pane {
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .progress {
            height: 8px;
            margin-top: 8px;
            background-color: #f0f0f0;
        }

        .progress-bar {
            background-color: var(--burgundy);
            transition: width 0.6s ease;
        }

        .question-group {
            margin-bottom: 25px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
            transition: var(--transition);
        }

        .question-group:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .question-group h5 {
            color: var(--burgundy);
            font-size: 1.15rem;
            margin-bottom: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .remove-question {
            display: none;
        }

        .format-card {
            font-size: 0.85rem;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        }

        .format-card .card-header {
            padding: 10px 15px;
            font-weight: 600;
            background-color: var(--deep-navy);
            color: white;
        }

        .format-card .card-body {
            padding: 15px;
        }

        .format-card code {
            display: block;
            white-space: pre-wrap;
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            font-family: 'Courier New', Courier, monospace;
            margin-bottom: 10px;
        }

        .pagination {
            display: flex;
            padding-left: 0;
            list-style: none;
            border-radius: 4px;
            justify-content: center;
            margin-top: 20px;
        }

        .page-item.active .page-link {
            z-index: 1;
            color: white;
            background-color: var(--burgundy);
            border-color: var(--burgundy);
        }

        .page-link {
            position: relative;
            display: block;
            padding: 6px 12px;
            margin-left: -1px;
            line-height: 1.5;
            color: var(--burgundy);
            background-color: white;
            border: 1px solid #dee2e6;
            text-decoration: none;
        }

        .page-link:hover {
            color: #5a000f;
            background-color: #e9ecef;
            border-color: #dee2e6;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.6s ease forwards;
        }

        @media (max-width: 992px) {
            .header-title {
                font-size: 1.7rem;
            }
            
            .form-container, .stats-container {
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
            
            .form-container, .stats-container {
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
        }

        @media (max-width: 576px) {
            .header-title {
                font-size: 1.4rem;
            }
            
            .form-container, .stats-container {
                padding: 16px;
            }
            
            .table th,
            .table td {
                padding: 8px;
                font-size: 0.85rem;
            }
        }

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
    </style>
</head>
<body>
    <!-- Header Bar -->
    <div class="header-bar">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="header-title">USAS E-Learning</h1>
                <div>
                    <span class="me-3"><?= htmlspecialchars($_SESSION['username']) ?></span>
                    <a href="logout.php" class="btn btn-sm btn-danger">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= strpos($message, 'Error') === false ? 'success' : 'danger' ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Student Performance Overview Section - Compact Version -->
        <div class="stats-container" style="margin-bottom: 15px;">
            <div class="stats-header" style="margin-bottom: 10px; padding-bottom: 8px;">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 style="margin-bottom: 0;">Student Performance</h4>
                    
                    <!-- Improved Filter by Matrix Number -->
                    <form method="GET" class="ms-2">
                        <div class="input-group input-group-sm" style="width: 220px;">
                            <input type="text" class="form-control form-control-sm" 
                                   name="matrix_filter" 
                                   placeholder="Student Matrix No" 
                                   value="<?= htmlspecialchars($matrix_filter) ?>"
                                   style="font-size: 0.8rem;">
                            
                            <button class="btn btn-primary btn-sm" type="submit" 
                                    style="padding: 0.25rem 0.5rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 8.692V13.5a.5.5 0 0 1-.342.474l-3 1A.5.5 0 0 1 6 14.5V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5v-2z"/>
                                </svg>
                            </button>
                            
                            <?php if (!empty($matrix_filter)): ?>
                                <a href="lecturer_homepage.php" class="btn btn-outline-secondary btn-sm" 
                                   style="padding: 0.25rem 0.5rem;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                                    </svg>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                        
                <!-- Compact Overall Stats -->
                <div class="d-flex mt-2" style="gap: 8px;">
                    <span class="badge bg-primary" style="font-size: 0.75rem;">Avg: <?= $overallPercentage ?>%</span>
                    <span class="badge bg-success" style="font-size: 0.75rem;"><?= $overallCorrect ?>/<?= $overallQuestions ?></span>
                    <span class="badge bg-info" style="font-size: 0.75rem;"><?= $overallAttempts ?> attempts</span>
                </div>
            </div>
            
            <?php if (!empty($subject_stats)): ?>
                <div class="subject-stats" style="margin-top: 10px;">
                    <?php foreach ($subject_stats as $stat): ?>
                        <?php 
                        $subject_percentage = $stat['total_questions'] > 0 ? 
                            round(($stat['total_correct'] / $stat['total_questions']) * 100, 2) : 0;
                        ?>
                        <div class="subject-stat-item" style="padding: 8px 10px; margin-bottom: 8px;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div style="max-width: 60%;">
                                    <strong style="font-size: 0.9rem;">
                                        <?= htmlspecialchars($stat['subject_code']) ?>: 
                                        <?= htmlspecialchars(mb_strimwidth($stat['subject_name'], 0, 20, '...')) ?>
                                    </strong>
                                    <div class="stat-label" style="font-size: 0.7rem;"><?= $stat['total_attempts'] ?> attempts</div>
                                </div>
                                <div class="text-end">
                                    <span class="stat-value" style="font-size: 0.9rem;"><?= $subject_percentage ?>%</span>
                                    <div class="stat-label" style="font-size: 0.7rem;"><?= $stat['total_correct'] ?>/<?= $stat['total_questions'] ?></div>
                                </div>
                            </div>
                            <div class="progress" style="height: 5px; margin-top: 5px;">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?= $subject_percentage ?>%" 
                                     aria-valuenow="<?= $subject_percentage ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Compact Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Performance pagination" class="mt-2">
                            <ul class="pagination pagination-sm justify-content-center" style="margin-bottom: 0;">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page-1 ?>&matrix_filter=<?= urlencode($matrix_filter) ?>">&laquo;</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php 
                                // Show limited page numbers
                                $start = max(1, $page - 2);
                                $end = min($total_pages, $page + 2);
                                
                                if ($start > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                
                                for ($i = $start; $i <= $end; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&matrix_filter=<?= urlencode($matrix_filter) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor;
                                
                                if ($end < $total_pages) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page+1 ?>&matrix_filter=<?= urlencode($matrix_filter) ?>">&raquo;</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info py-2" style="font-size: 0.85rem; margin-top: 10px;">
                    <?php if (!empty($matrix_filter)): ?>
                        No attempts for this student
                    <?php else: ?>
                        No attempts recorded
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Main Content Tabs -->
        <ul class="nav nav-tabs" id="lecturerTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="add-subject-tab" data-bs-toggle="tab" data-bs-target="#add-subject" type="button" role="tab">Add Subject</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="add-question-tab" data-bs-toggle="tab" data-bs-target="#add-question" type="button" role="tab">Add Questions</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload" type="button" role="tab">Upload Questions</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="view-tab" data-bs-toggle="tab" data-bs-target="#view" type="button" role="tab">View Subjects</button>
            </li>
        </ul>

        <div class="tab-content form-container" id="lecturerTabsContent">
            <!-- Add Subject Tab -->
            <div class="tab-pane fade show active" id="add-subject" role="tabpanel">
                <h3 class="mb-4">Add New Subject</h3>
                <form method="POST">
                    <div class="mb-3">
                        <label for="subject_code" class="form-label">Subject Code</label>
                        <input type="text" class="form-control" id="subject_code" name="subject_code" required>
                    </div>
                    <div class="mb-3">
                        <label for="subject_name" class="form-label">Subject Name</label>
                        <input type="text" class="form-control" id="subject_name" name="subject_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="year" class="form-label">Year</label>
                        <select class="form-control" id="year" name="year" required>
                            <option value="">Select Year</option>
                            <?php 
                            $current_year = date("Y");
                            for ($i = $current_year; $i >= $current_year - 4; $i--) {
                                echo "<option value='$i'>$i</option>";
                            }
                            ?>
                        </select>
                    <div class="mb-3">
                        <label for="semester" class="form-label">Semester</label>
                        <input type="text" class="form-control" id="semester" name="semester" required placeholder="e.g., Sem III 2024/2025">
                    </div></div>
                    <button type="submit" name="add_subject" class="btn btn-primary">Add Subject</button>
                </form>
            </div>

            <!-- Add Questions Tab with Bilingual Support -->
            <div class="tab-pane fade" id="add-question" role="tabpanel">
                <h3 class="mb-4">Add Questions to Subject</h3>
                <?php if (empty($subjects)): ?>
                    <div class="alert alert-info">You need to create a subject first before adding questions.</div>
                <?php else: ?>
                    <form id="questionForm" method="POST">
                        <div class="mb-3">
                            <label for="subject_id" class="form-label">Select Subject</label>
                            <select class="form-control" id="subject_id" name="subject_id" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?= $subject['id'] ?>">
                                        <?= htmlspecialchars($subject['subject_code']) ?> - 
                                        <?= htmlspecialchars($subject['subject_name']) ?> (<?= $subject['year'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="questionsContainer">
                            <div class="question-group mb-4 p-3 border rounded">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5>Question 1</h5>
                                    <button type="button" class="btn btn-sm btn-danger remove-question" style="display:none;">Remove</button>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Question Text (English/Malay)</label>
                                    <textarea class="form-control question-textarea" name="questions[0][question_text]" rows="2" required placeholder="English question / Soalan dalam Bahasa Melayu"></textarea>
                                    <div class="form-text">Use format: English question / Soalan BM</div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Option A (English/Malay)</label>
                                        <input type="text" class="form-control option-input" name="questions[0][option_a]" required placeholder="English option / Pilihan BM">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Option B (English/Malay)</label>
                                        <input type="text" class="form-control option-input" name="questions[0][option_b]" required placeholder="English option / Pilihan BM">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Option C (English/Malay)</label>
                                        <input type="text" class="form-control option-input" name="questions[0][option_c]" required placeholder="English option / Pilihan BM">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Option D (English/Malay)</label>
                                        <input type="text" class="form-control option-input" name="questions[0][option_d]" required placeholder="English option / Pilihan BM">
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
                            <button type="button" id="addQuestionBtn" class="btn btn-primary">Add Another Question</button>
                            <button type="submit" name="add_questions" class="btn btn-primary">Submit All Questions</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Upload Questions Tab with Bilingual Support -->
            <div class="tab-pane fade" id="upload" role="tabpanel">
                <h3 class="mb-4">Upload Questions from File</h3>
                <?php if (empty($subjects)): ?>
                    <div class="alert alert-info">You need to create a subject first before uploading questions.</div>
                <?php else: ?>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="file_subject_id" class="form-label">Select Subject</label>
                            <select class="form-control" id="file_subject_id" name="file_subject_id" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?= $subject['id'] ?>">
                                        <?= htmlspecialchars($subject['subject_code']) ?> - 
                                        <?= htmlspecialchars($subject['subject_name']) ?> (<?= $subject['year'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="question_file" class="form-label">Question File (.docx or .pdf)</label>
                            <input type="file" class="form-control" id="question_file" name="question_file" accept=".docx,.pdf" required>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="card format-card">
                                        <div class="card-header bg-primary text-white">
                                            <strong>Bilingual Format</strong>
                                        </div>
                                        <div class="card-body">
                                            <code style="font-size: 0.8rem; white-space: pre-wrap;">
Q2. Which of the following is a popular social networking site?
Antara berikut, yang manakah merupakan laman rangkaian sosial yang popular?
A) Facebook / Facebook
B) Chrome / Chrome
C) Safari / Safari
D) Internet Explorer / Internet Explorer
Correct answer: A / Jawapan: A

Q3. What is the capital of France?
Apakah ibu negara Perancis?
A) London / London
B) Paris / Paris
C) Berlin / Berlin
D) Madrid / Madrid
Correct answer: B / Jawapan: B
                                            </code>
                                            <div class="form-text mt-2">
                                                1. Use paragraph breaks (Enter) between English and Malay versions<br>
                                                2. For answers, use: Correct answer: X / Jawapan: X<br>
                                                3. DOCX or PDF format only
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card format-card">
                                        <div class="card-header bg-primary text-white">
                                            <strong>Single Language Format</strong>
                                        </div>
                                        <div class="card-body">
                                            <code style="font-size: 0.8rem; white-space: pre-wrap;">
Q2. Which of the following is a popular social networking site?
A) Facebook
B) Chrome
C) Safari
D) Internet Explorer
Correct answer: A

Q3. What is the capital of France?
A) London
B) Paris
C) Berlin
D) Madrid
Correct answer: B
                                            </code>
                                            <div class="form-text mt-2">
                                                1. Standard single language format<br>
                                                2. DOCX or PDF format only
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Upload File</button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- View Subjects Tab -->
            <div class="tab-pane fade" id="view" role="tabpanel">
                <h3 class="mb-4">Your Subjects</h3>
                <?php if (empty($subjects)): ?>
                    <div class="alert alert-info">You haven't created any subjects yet.</div>
                <?php else: ?>
                    <div class="list-group question-list">
                        <?php foreach ($subjects as $subject): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5><?= htmlspecialchars($subject['subject_code']) ?> - <?= htmlspecialchars($subject['subject_name']) ?></h5>
                                        <p class="mb-1">Year: <?= $subject['year'] ?> | Semester: <?= htmlspecialchars($subject['semester']) ?></p>
                                        <small class="text-muted">Created: <?= date('d M Y', strtotime($subject['created_at'])) ?></small>
                                    </div>
                                    <div class="d-flex">
                                        <a href="lecturer_subject_question.php?subject_id=<?= $subject['id'] ?>" class="btn btn-sm btn-primary me-2">View Questions</a>
                                        <form method="POST" action="delete_subject.php" onsubmit="return confirm('Are you sure you want to delete this subject and all its questions? This cannot be undone.');">
                                            <input type="hidden" name="subject_id" value="<?= $subject['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </div>
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
                    <label class="form-label">Question Text (English/Malay)</label>
                    <textarea class="form-control question-textarea" name="questions[${questionCount-1}][question_text]" rows="2" required placeholder="English question / Soalan dalam Bahasa Melayu"></textarea>
                    <div class="form-text">Use format: English question / Soalan BM</div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Option A (English/Malay)</label>
                        <input type="text" class="form-control option-input" name="questions[${questionCount-1}][option_a]" required placeholder="English option / Pilihan BM">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Option B (English/Malay)</label>
                        <input type="text" class="form-control option-input" name="questions[${questionCount-1}][option_b]" required placeholder="English option / Pilihan BM">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Option C (English/Malay)</label>
                        <input type="text" class="form-control option-input" name="questions[${questionCount-1}][option_c]" required placeholder="English option / Pilihan BM">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Option D (English/Malay)</label>
                        <input type="text" class="form-control option-input" name="questions[${questionCount-1}][option_d]" required placeholder="English option / Pilihan BM">
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