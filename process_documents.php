<?php
require 'config.php';

// Get all unprocessed documents
$sql = "SELECT * FROM question_documents WHERE processed = FALSE";
$result = $conn->query($sql);

while ($doc = $result->fetch_assoc()) {
    $filepath = $doc['filepath'];
    $subject_id = $doc['subject_id'];
    
    // Process Word document (you'll need to install phpword)
    // This is a simplified example - you'll need to implement actual parsing
    $questions = [];
    
    // Example parsing logic (pseudo-code)
    /*
    $phpWord = \PhpOffice\PhpWord\IOFactory::load($filepath);
    $sections = $phpWord->getSections();
    foreach ($sections as $section) {
        // Parse questions and options from document
        // Add to $questions array
    }
    */
    
    // For now, we'll just mark as processed
    $update_sql = "UPDATE question_documents SET processed = TRUE WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("i", $doc['id']);
    $stmt->execute();
    
    // In a real implementation, you would insert the parsed questions here
    /*
    foreach ($questions as $q) {
        $insert_sql = "INSERT INTO questions (...) VALUES (...)";
        // Execute insert
    }
    */
}

$conn->close();
?>