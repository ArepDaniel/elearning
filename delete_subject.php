<?php
session_start();

// Redirect if not lecturer
if (!isset($_SESSION['matrix_number']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: login.php");
    exit();
}

include('config.php');

// Check if subject ID is provided
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['subject_id'])) {
    $subject_id = intval($_POST['subject_id']);
    $lecturer_matrix = $_SESSION['matrix_number'];

    // Verify subject ownership before deletion
    $verify_sql = "SELECT id FROM subjects WHERE id = ? AND matrix_number = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("is", $subject_id, $lecturer_matrix);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        $_SESSION['message'] = "Error: You can only delete your own subjects.";
        header("Location: lecturer_homepage.php");
        exit();
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // First delete all questions related to this subject
        $delete_questions_sql = "DELETE FROM questions WHERE subject_id = ?";
        $stmt = $conn->prepare($delete_questions_sql);
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();

        // Then delete the subject
        $delete_subject_sql = "DELETE FROM subjects WHERE id = ?";
        $stmt = $conn->prepare($delete_subject_sql);
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();

        // Commit transaction
        $conn->commit();

        $_SESSION['message'] = "Subject and all its questions deleted successfully.";
    } catch (Exception $e) {
        // Rollback transaction if there's an error
        $conn->rollback();
        $_SESSION['message'] = "Error deleting subject: " . $e->getMessage();
    }

    header("Location: lecturer_homepage.php");
    exit();
} else {
    header("Location: lecturer_homepage.php");
    exit();
}
?>