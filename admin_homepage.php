<?php
session_start();

// Redirect if not logged in or not admin
if (!isset($_SESSION['matrix_number']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include(__DIR__ . '/config.php');
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
unset($_SESSION['message']);

// Function to recursively delete directory
function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

// Handle manual user addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $matrix_number = $conn->real_escape_string($_POST['matrix_number']);
    $ic_number = $conn->real_escape_string($_POST['ic_number']);
    $username = $conn->real_escape_string($_POST['username']);
    $role = $conn->real_escape_string($_POST['role']);

    $sql = "INSERT INTO user (matrix_number, ic_number, username, role) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $matrix_number, $ic_number, $username, $role);
    
    if ($stmt->execute()) {
        $message = "User added successfully!";
    } else {
        $message = "Error: " . $conn->error;
    }
}

// Handle Excel file upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['user_file'])) {
    $upload_dir = __DIR__ . '/uploads/';
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $filename = basename($_FILES['user_file']['name']);
    $target_file = $upload_dir . uniqid() . '_' . $filename;
    $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    if ($fileType != "xlsx") {
        $message = "Only XLSX files are allowed.";
    } elseif (move_uploaded_file($_FILES['user_file']['tmp_name'], $target_file)) {
        try {
            $temp_dir = $upload_dir . 'temp_' . uniqid() . '/';
            mkdir($temp_dir, 0777, true);
            
            $zip = new ZipArchive;
            if ($zip->open($target_file)) {
                $zip->extractTo($temp_dir);
                $zip->close();
            } else {
                throw new Exception("Failed to open XLSX file");
            }
            
            $sharedStringsPath = $temp_dir . 'xl/sharedStrings.xml';
            $sheetPath = $temp_dir . 'xl/worksheets/sheet1.xml';
            
            if (!file_exists($sharedStringsPath) || !file_exists($sheetPath)) {
                throw new Exception("Invalid XLSX file structure");
            }
            
            $sharedStrings = [];
            $sharedStringsXml = simplexml_load_file($sharedStringsPath);
            foreach ($sharedStringsXml->si as $si) {
                $sharedStrings[] = (string)$si->t;
            }
            
            $sheetXml = simplexml_load_file($sheetPath);
            $rows = $sheetXml->sheetData->row;
            
            $successCount = 0;
            $errorCount = 0;
            $isFirstRow = true;
            
            foreach ($rows as $row) {
                $cells = $row->c;
                $rowData = [];
                
                foreach ($cells as $cell) {
                    $cellValue = '';
                    $cellRef = (string)$cell['r'];
                    $cellType = (string)$cell['t'];
                    
                    if ($cellType === 's') {
                        $index = (int)$cell->v;
                        $cellValue = $sharedStrings[$index] ?? '';
                    } else {
                        $cellValue = (string)$cell->v;
                    }
                    
                    $rowData[] = $cellValue;
                }
                
                if ($isFirstRow) {
                    $isFirstRow = false;
                    continue;
                }
                
                if (count($rowData) >= 4) {
                    $matrix_number = $conn->real_escape_string(trim($rowData[0]));
                    $ic_number = $conn->real_escape_string(trim($rowData[1]));
                    $username = $conn->real_escape_string(trim($rowData[2]));
                    $role = strtolower($conn->real_escape_string(trim($rowData[3])));
                    
                    if (!in_array($role, ['student', 'lecturer', 'admin'])) {
                        $errorCount++;
                        continue;
                    }
                    
                    $sql = "INSERT INTO user (matrix_number, ic_number, username, role) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssss", $matrix_number, $ic_number, $username, $role);
                    
                    if ($stmt->execute()) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                }
            }
            
            deleteDirectory($temp_dir);
            unlink($target_file);
            
            $message = "File processed. $successCount users added. $errorCount errors.";
            
        } catch (Exception $e) {
            $message = "Error processing file: " . $e->getMessage();
            if (isset($temp_dir) && file_exists($temp_dir)) {
                deleteDirectory($temp_dir);
            }
            if (file_exists($target_file)) {
                unlink($target_file);
            }
        }
    } else {
        $message = "Error uploading file.";
    }
}

// Handle subject search and filters
$subject_search = '';
$year_filter = '';
$subject_page = 1;
$subjects_per_page = 10;

if (isset($_GET['subject_search'])) {
    $subject_search = $conn->real_escape_string(trim($_GET['subject_search']));
}

if (isset($_GET['year_filter']) && is_numeric($_GET['year_filter'])) {
    $year_filter = intval($_GET['year_filter']);
}

if (isset($_GET['subject_page']) && is_numeric($_GET['subject_page'])) {
    $subject_page = max(1, intval($_GET['subject_page']));
}

// Handle subject deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_subject'])) {
    $subject_id = intval($_POST['subject_id']);
    
    // First delete related student results to maintain referential integrity
    $delete_results_sql = "DELETE FROM student_results WHERE subject_id = ?";
    $stmt = $conn->prepare($delete_results_sql);
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    
    // Then delete related questions
    $delete_questions_sql = "DELETE FROM questions WHERE subject_id = ?";
    $stmt = $conn->prepare($delete_questions_sql);
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    
    // Then delete any uploaded question documents
    $delete_docs_sql = "DELETE FROM question_documents WHERE subject_id = ?";
    $stmt = $conn->prepare($delete_docs_sql);
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    
    // Finally delete the subject itself
    $delete_sql = "DELETE FROM subjects WHERE id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $subject_id);
    
    if ($stmt->execute()) {
        $message = "Subject and all related data deleted successfully.";
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = 'success';
        header("Location: admin_homepage.php?active_tab=view-subjects");
        exit();
    } else {
        $message = "Error deleting subject: " . $conn->error;
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = 'danger';
        header("Location: admin_homepage.php?active_tab=view-subjects");
        exit();
    }
}

// Build SQL queries for subjects
$subjects_sql = "SELECT s.*, u.username as lecturer_name 
                FROM subjects s 
                JOIN user u ON s.matrix_number = u.matrix_number
                WHERE 1";

$subjects_count_sql = "SELECT COUNT(*) as total 
                      FROM subjects s 
                      JOIN user u ON s.matrix_number = u.matrix_number
                      WHERE 1";

// Calculate offset before the conditional
$subject_offset = ($subject_page - 1) * $subjects_per_page;

if (!empty($subject_search)) {
    $search_term = "%$subject_search%";
    $subjects_sql .= " AND (s.subject_code LIKE ? OR s.subject_name LIKE ? OR u.username LIKE ?)";
    $subjects_count_sql .= " AND (s.subject_code LIKE ? OR s.subject_name LIKE ? OR u.username LIKE ?)";
    
    // Prepare statements with parameters for security
    $stmt = $conn->prepare($subjects_sql . " ORDER BY s.subject_code LIMIT ?, ?");
    $stmt->bind_param("sssii", $search_term, $search_term, $search_term, $subject_offset, $subjects_per_page);
    $stmt->execute();
    $subjects_result = $stmt->get_result();
    
    $count_stmt = $conn->prepare($subjects_count_sql);
    $count_stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $count_stmt->execute();
    $subjects_count_result = $count_stmt->get_result();
} else {
    $subjects_sql .= " ORDER BY s.subject_code LIMIT ?, ?";
    $stmt = $conn->prepare($subjects_sql);
    $stmt->bind_param("ii", $subject_offset, $subjects_per_page);
    $stmt->execute();
    $subjects_result = $stmt->get_result();
    
    $subjects_count_result = $conn->query($subjects_count_sql);
}

$total_subjects = $subjects_count_result->fetch_assoc()['total'];
$total_subject_pages = ceil($total_subjects / $subjects_per_page);

$subjects = [];
while ($row = $subjects_result->fetch_assoc()) {
    $subjects[] = $row;
}

// Handle user search and filters
$search = '';
$role_filter = '';
$page = 1;
$users_per_page = 10;

if (isset($_GET['search'])) {
    $search = $conn->real_escape_string(trim($_GET['search']));
}

if (isset($_GET['role_filter']) && in_array($_GET['role_filter'], ['student', 'lecturer', 'admin'])) {
    $role_filter = $_GET['role_filter'];
}

if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $page = max(1, intval($_GET['page']));
}

$active_tab = isset($_GET['active_tab']) ? $_GET['active_tab'] : 'add-user';

// Build SQL queries for users
$sql = "SELECT * FROM user WHERE 1";
$count_sql = "SELECT COUNT(*) as total FROM user WHERE 1";

if (!empty($search)) {
    $sql .= " AND (matrix_number LIKE '%$search%' OR username LIKE '%$search%' OR ic_number LIKE '%$search%')";
    $count_sql .= " AND (matrix_number LIKE '%$search%' OR username LIKE '%$search%' OR ic_number LIKE '%$search%')";
}

if (!empty($role_filter)) {
    $sql .= " AND role = '$role_filter'";
    $count_sql .= " AND role = '$role_filter'";
}

$sql .= " ORDER BY role, username";
$offset = ($page - 1) * $users_per_page;
$sql .= " LIMIT $offset, $users_per_page";

$count_result = $conn->query($count_sql);
$total_users = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_users / $users_per_page);

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - USAS E-Learning</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

        .search-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
            border: 1px solid rgba(0, 0, 0, 0.05);
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

        .subject-item {
            padding: 20px;
            margin-bottom: 12px;
            background-color: white;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 8px;
            transition: var(--transition);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.03);
        }

        .subject-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }

        .subject-code {
            font-weight: bold;
            color: var(--deep-navy);
            font-size: 1.1rem;
        }

        .subject-name {
            color: #555;
            margin-bottom: 5px;
        }

        .subject-lecturer {
            font-size: 0.9rem;
            color: #666;
        }

        .subject-year {
            font-size: 0.9rem;
            color: #666;
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
            margin-bottom: 16px;
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

        .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -15px;
            margin-left: -15px;
        }

        .col-md-5,
        .col-md-3,
        .col-md-2,
        .col-md-6,
        .col-md-1 {
            position: relative;
            width: 100%;
            padding-right: 15px;
            padding-left: 15px;
        }

        @media (min-width: 768px) {
            .col-md-5 {
                flex: 0 0 41.666667%;
                max-width: 41.666667%;
            }
            .col-md-3 {
                flex: 0 0 25%;
                max-width: 25%;
            }
            .col-md-2 {
                flex: 0 0 16.666667%;
                max-width: 16.666667%;
            }
            .col-md-6 {
                flex: 0 0 50%;
                max-width: 50%;
            }
            .col-md-1 {
                flex: 0 0 8.333333%;
                max-width: 8.333333%;
            }
        }

        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin: 0;
            vertical-align: middle;
        }

        #user_file {
            padding: 10px;
            border: 1px dashed #ccc;
            border-radius: 8px;
            background-color: #f9f9f9;
        }

        #selectAll {
            margin-right: 5px;
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
            
            .form-container, .search-container {
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
            
            .form-container, .search-container {
                padding: 18px;
                border-radius: 10px;
            }
            
            .table th,
            .table td {
                padding: 10px;
                font-size: 0.9rem;
            }
            
            .subject-item {
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
            
            .form-container, .search-container {
                padding: 16px;
            }
            
            .table th,
            .table td {
                padding: 8px;
                font-size: 0.85rem;
            }
        }

        .mb-3 {
            margin-bottom: 1rem !important;
        }

        .mb-4 {
            margin-bottom: 1.5rem !important;
        }

        .mt-2 {
            margin-top: 0.5rem !important;
        }

        .mt-3 {
            margin-top: 1rem !important;
        }

        .mt-4 {
            margin-top: 1.5rem !important;
        }

        .ms-2 {
            margin-left: 0.5rem !important;
        }

        .me-3 {
            margin-right: 1rem !important;
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

        .w-100 {
            width: 100% !important;
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

        <ul class="nav nav-tabs" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $active_tab === 'add-user' ? 'active' : '' ?>" id="add-user-tab" data-bs-toggle="tab" data-bs-target="#add-user" type="button" role="tab">Add User</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $active_tab === 'upload-users' ? 'active' : '' ?>" id="upload-users-tab" data-bs-toggle="tab" data-bs-target="#upload-users" type="button" role="tab">Upload Users</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $active_tab === 'view-users' ? 'active' : '' ?>" id="view-users-tab" data-bs-toggle="tab" data-bs-target="#view-users" type="button" role="tab">View Users</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $active_tab === 'view-subjects' ? 'active' : '' ?>" id="view-subjects-tab" data-bs-toggle="tab" data-bs-target="#view-subjects" type="button" role="tab">View Subjects</button>
            </li>
        </ul>

        <div class="tab-content form-container" id="adminTabsContent">
            <!-- Add User Tab -->
            <div class="tab-pane fade <?= $active_tab === 'add-user' ? 'show active' : '' ?>" id="add-user" role="tabpanel">
                <h3 class="mb-4">Add New User</h3>
                <form method="POST">
                    <div class="mb-3">
                        <label for="matrix_number" class="form-label">Matrix Number</label>
                        <input type="text" class="form-control" id="matrix_number" name="matrix_number" required>
                    </div>
                    <div class="mb-3">
                        <label for="ic_number" class="form-label">IC Number</label>
                        <input type="text" class="form-control" id="ic_number" name="ic_number" required>
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-control" id="role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="student">Student</option>
                            <option value="lecturer">Lecturer</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                </form>
            </div>

            <!-- Upload Users Tab -->
            <div class="tab-pane fade <?= $active_tab === 'upload-users' ? 'show active' : '' ?>" id="upload-users" role="tabpanel">
                <h3 class="mb-4">Upload Users from Excel</h3>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="user_file" class="form-label">Excel File (.xlsx only)</label>
                        <input type="file" class="form-control" id="user_file" name="user_file" accept=".xlsx" required>
                        <div class="form-text">
                            Upload an Excel file with columns: Matrix Number, IC Number, Username, Role.<br>
                            First row should be headers. Supported roles: student, lecturer, admin.
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Upload File</button>
                </form>
            </div>

            <!-- View Users Tab -->
            <div class="tab-pane fade <?= $active_tab === 'view-users' ? 'show active' : '' ?>" id="view-users" role="tabpanel">
                <h3 class="mb-4">System Users</h3>
                
                <!-- Search and Filter Form -->
                <div class="search-container">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="active_tab" value="view-users">
                        <div class="col-md-5">
                            <input type="text" name="search" class="form-control" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="role_filter" class="form-select">
                                <option value="">All Roles</option>
                                <option value="student" <?= $role_filter === 'student' ? 'selected' : '' ?>>Student</option>
                                <option value="lecturer" <?= $role_filter === 'lecturer' ? 'selected' : '' ?>>Lecturer</option>
                                <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                        <?php if (!empty($search) || !empty($role_filter)): ?>
                            <div class="col-md-2">
                                <a href="admin_homepage.php?active_tab=view-users" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
                
                <?php if ($result->num_rows == 0): ?>
                    <div class="alert alert-info">No users found matching your criteria.</div>
                <?php else: ?>
                    <!-- Bulk Actions -->
                    <div class="mb-3">
                        <div class="d-flex">
                            <button type="button" class="btn btn-primary btn-sm me-2" onclick="submitBulkAction('edit')">Edit Selected</button>
                            <button type="button" class="btn btn-danger btn-sm" onclick="submitBulkAction('delete')">Delete Selected</button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th width="40px"><input type="checkbox" id="selectAll"></th>
                                    <th>Matrix Number</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($user = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><input type="checkbox" name="selected_users[]" value="<?= $user['matrix_number'] ?>"></td>
                                        <td><?= htmlspecialchars($user['matrix_number']) ?></td>
                                        <td><?= htmlspecialchars($user['username']) ?></td>
                                        <td><?= htmlspecialchars($user['role']) ?></td>
                                        <td>
                                            <a href="edit_user.php?matrix_number=<?= urlencode($user['matrix_number']) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <a href="delete_user.php?matrix_number=<?= urlencode($user['matrix_number']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?search=<?= urlencode($search) ?>&role_filter=<?= $role_filter ?>&page=<?= $page-1 ?>&active_tab=view-users" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?search=<?= urlencode($search) ?>&role_filter=<?= $role_filter ?>&page=<?= $i ?>&active_tab=view-users"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?search=<?= urlencode($search) ?>&role_filter=<?= $role_filter ?>&page=<?= $page+1 ?>&active_tab=view-users" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                    
                    <div class="text-muted text-center">
                        Showing <?= ($offset + 1) ?> to <?= min($offset + $users_per_page, $total_users) ?> of <?= $total_users ?> users
                    </div>
                <?php endif; ?>
            </div>

            <!-- View Subjects Tab -->
            <div class="tab-pane fade <?= $active_tab === 'view-subjects' ? 'show active' : '' ?>" id="view-subjects" role="tabpanel">
                <h3 class="mb-4">System Subjects</h3>
                
                <!-- Search and Filter Form -->
                <div class="search-container mb-4">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="active_tab" value="view-subjects">
                        <div class="col-md-6">
                            <input type="text" name="subject_search" class="form-control" placeholder="Search by code, name or lecturer..." value="<?= htmlspecialchars($subject_search) ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="year_filter" class="form-select">
                                <option value="">All Years</option>
                                <?php
                                $current_year = date("Y");
                                for ($i = $current_year; $i >= $current_year - 4; $i--) {
                                    echo '<option value="'.$i.'" '.($year_filter == $i ? 'selected' : '').'>'.$i.'</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                        <?php if (!empty($subject_search) || !empty($year_filter)): ?>
                            <div class="col-md-1">
                                <a href="admin_homepage.php?active_tab=view-subjects" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
                
                <?php if (empty($subjects)): ?>
                    <div class="alert alert-info">No subjects found matching your criteria.</div>
                <?php else: ?>
                    <div class="subject-list">
                        <?php foreach ($subjects as $subject): ?>
                            <div class="subject-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="subject-code"><?= htmlspecialchars($subject['subject_code']) ?></div>
                                        <div class="subject-name"><?= htmlspecialchars($subject['subject_name']) ?></div>
                                        <div class="subject-lecturer">Lecturer: <?= htmlspecialchars($subject['lecturer_name']) ?></div>
                                        <div class="subject-year">Year: <?= htmlspecialchars($subject['year']) ?></div>
                                    </div>
                                    <div>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="subject_id" value="<?= $subject['id'] ?>">
                                            <button type="submit" name="delete_subject" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this subject and all its questions?')">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_subject_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination">
                                <?php if ($subject_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?subject_search=<?= urlencode($subject_search) ?>&year_filter=<?= $year_filter ?>&subject_page=<?= $subject_page-1 ?>&active_tab=view-subjects" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_subject_pages; $i++): ?>
                                    <li class="page-item <?= $i == $subject_page ? 'active' : '' ?>">
                                        <a class="page-link" href="?subject_search=<?= urlencode($subject_search) ?>&year_filter=<?= $year_filter ?>&subject_page=<?= $i ?>&active_tab=view-subjects"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($subject_page < $total_subject_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?subject_search=<?= urlencode($subject_search) ?>&year_filter=<?= $year_filter ?>&subject_page=<?= $subject_page+1 ?>&active_tab=view-subjects" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                    
                    <div class="text-muted text-center mt-3">
                        Showing <?= ($subject_offset + 1) ?> to <?= min($subject_offset + $subjects_per_page, $total_subjects) ?> of <?= $total_subjects ?> subjects
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab handling
        const urlParams = new URLSearchParams(window.location.search);
        const activeTabParam = urlParams.get('active_tab');
        
        if (activeTabParam) {
            const tab = new bootstrap.Tab(document.getElementById(activeTabParam + '-tab'));
            tab.show();
        }
        
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tabEl => {
            tabEl.addEventListener('click', function() {
                const tabId = this.getAttribute('data-bs-target').substring(1);
                document.getElementById('activeTabInput').value = tabId;
            });
        });

        // Select all checkboxes
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="selected_users[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    });

    // Submit bulk actions
    function submitBulkAction(action) {
        const selectedCheckboxes = document.querySelectorAll('input[name="selected_users[]"]:checked');
        
        if (selectedCheckboxes.length === 0) {
            alert('Please select at least one user.');
            return false;
        }
        
        const selectedUsers = Array.from(selectedCheckboxes).map(checkbox => checkbox.value);
        
        if (action === 'edit') {
            window.location.href = `edit_user.php?matrix_numbers=${selectedUsers.join(',')}`;
        } else if (action === 'delete') {
            if (confirm('Are you sure you want to delete the selected users?')) {
                window.location.href = `delete_user.php?matrix_numbers=${selectedUsers.join(',')}`;
            }
        }
        return true;
    }
    </script>
</body>
</html>