<?php
session_start();

// Redirect if not logged in or not admin
if (!isset($_SESSION['matrix_number']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include('config.php');

// Initialize matrix_numbers array
$matrix_numbers = [];

// Check for matrix_numbers in GET
if (isset($_GET['matrix_numbers'])) {
    $matrix_numbers = explode(',', $_GET['matrix_numbers']);
} elseif (isset($_GET['matrix_number'])) {
    $matrix_numbers = [$_GET['matrix_number']];
}

// If empty, redirect back
if (empty($matrix_numbers)) {
    $_SESSION['message'] = "No users selected for editing.";
    header("Location: admin_homepage.php?active_tab=view-users");
    exit();
}

// Fetch users data
$users = [];
if (!empty($matrix_numbers)) {
    $placeholders = implode(',', array_fill(0, count($matrix_numbers), '?'));
    $types = str_repeat('s', count($matrix_numbers));
    
    $sql = "SELECT * FROM user WHERE matrix_number IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$matrix_numbers);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

if (empty($users)) {
    $_SESSION['message'] = "No valid users selected for editing.";
    header("Location: admin_homepage.php?active_tab=view-users");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_users'])) {
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($users as $index => $user) {
        $matrix_number = $user['matrix_number'];
        $ic_number = $conn->real_escape_string($_POST['ic_numbers'][$index]);
        $username = $conn->real_escape_string($_POST['usernames'][$index]);
        $role = $conn->real_escape_string($_POST['roles'][$index]);
        
        $update_sql = "UPDATE user SET ic_number = ?, username = ?, role = ? WHERE matrix_number = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssss", $ic_number, $username, $role, $matrix_number);
        
        $update_stmt->execute() ? $successCount++ : $errorCount++;
    }
    
    $_SESSION['message'] = $errorCount > 0 ? 
        "Updated $successCount users successfully, but failed to update $errorCount users." : 
        "All $successCount users updated successfully!";
    
    header("Location: admin_homepage.php?active_tab=view-users");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= count($users) > 1 ? 'Edit Users' : 'Edit User' ?> - USAS E-Learning</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1000px;
            margin-top: 50px;
        }
        .user-card {
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            background-color: white;
        }
        .user-header {
            background-color: #f8f9fa;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2><?= count($users) > 1 ? 'Edit Users' : 'Edit User' ?></h2>
        
        <form method="POST">
            <?php foreach ($users as $index => $user): ?>
                <div class="user-card">
                    <div class="user-header">
                        <h5>User: <?= htmlspecialchars($user['username']) ?></h5>
                        <input type="hidden" name="matrix_numbers[]" value="<?= htmlspecialchars($user['matrix_number']) ?>">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Matrix Number</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['matrix_number']) ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">IC Number</label>
                            <input type="text" class="form-control" name="ic_numbers[]" value="<?= htmlspecialchars($user['ic_number']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="usernames[]" value="<?= htmlspecialchars($user['username']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="roles[]" required>
                                <option value="student" <?= $user['role'] == 'student' ? 'selected' : '' ?>>Student</option>
                                <option value="lecturer" <?= $user['role'] == 'lecturer' ? 'selected' : '' ?>>Lecturer</option>
                                <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="d-flex justify-content-between mt-4">
                <button type="submit" name="update_users" class="btn btn-primary">Update Users</button>
                <a href="admin_homepage.php?active_tab=view-users" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>