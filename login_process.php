<?php
session_start();

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "db_usas_elearning";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get and sanitize form input
$matrix_number = trim($_POST['matrix_number']);
$ic_number = trim($_POST['ic_number']);

// Prepared statement to prevent SQL injection
$stmt = $conn->prepare("SELECT * FROM user WHERE matrix_number = ? AND ic_number = ?");
$stmt->bind_param("ss", $matrix_number, $ic_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    // Store user data in session
    $_SESSION['username'] = $user['username'];
    $_SESSION['matrix_number'] = $user['matrix_number'];
    $_SESSION['role'] = $user['role'];

    header("Location: homepage.php");
    exit();
} else {
    header("Location: login.php?error=" . urlencode("Invalid Matrix Number or IC Number."));
    exit();
}

$stmt->close();
$conn->close();
?>