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

// Check for matrix_numbers in GET or POST
if (isset($_GET['matrix_numbers'])) {
    $matrix_numbers = explode(',', $_GET['matrix_numbers']);
} elseif (isset($_GET['matrix_number'])) {
    $matrix_numbers = [$_GET['matrix_number']];
} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['matrix_numbers'])) {
    $matrix_numbers = is_array($_POST['matrix_numbers']) ? $_POST['matrix_numbers'] : [$_POST['matrix_numbers']];
}

// If still empty, redirect back
if (empty($matrix_numbers)) {
    $_SESSION['message'] = "No users selected for deletion.";
    header("Location: admin_homepage.php?active_tab=view-users");
    exit();
}

// Process deletion if confirmed
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_delete'])) {
    $placeholders = implode(',', array_fill(0, count($matrix_numbers), '?'));
    $types = str_repeat('s', count($matrix_numbers));
    
    $sql = "DELETE FROM user WHERE matrix_number IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$matrix_numbers);

    if ($stmt->execute()) {
        $_SESSION['message'] = count($matrix_numbers) > 1 ? 
            "Successfully deleted " . count($matrix_numbers) . " users." : 
            "User deleted successfully.";
    } else {
        $_SESSION['message'] = "Error deleting users: " . $conn->error;
    }
    
    header("Location: admin_homepage.php?active_tab=view-users");
    exit();
}

// Fetch users to display in confirmation
$users_to_delete = [];
if (!empty($matrix_numbers)) {
    $placeholders = implode(',', array_fill(0, count($matrix_numbers), '?'));
    $types = str_repeat('s', count($matrix_numbers));
    
    $sql = "SELECT matrix_number, username, role FROM user WHERE matrix_number IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$matrix_numbers);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $users_to_delete[] = $row;
    }
}

if (empty($users_to_delete)) {
    $_SESSION['message'] = "No valid users selected for deletion.";
    header("Location: admin_homepage.php?active_tab=view-users");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= count($users_to_delete) > 1 ? 'Delete Users' : 'Delete User' ?> - USAS E-Learning</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
            margin-top: 50px;
        }
        .card-header {
            font-weight: bold;
        }
        .user-list {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2><?= count($users_to_delete) > 1 ? 'Delete Users' : 'Delete User' ?></h2>
        
        <div class="card mt-4">
            <div class="card-header bg-danger text-white">
                <h4 class="mb-0">Confirm Deletion</h4>
            </div>
            <div class="card-body">
                <p>Are you sure you want to delete the following user(s)? This action cannot be undone.</p>
                
                <div class="user-list mb-4">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Matrix Number</th>
                                <th>Username</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users_to_delete as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['matrix_number']) ?></td>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= htmlspecialchars($user['role']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <form method="POST">
                    <?php foreach ($users_to_delete as $user): ?>
                        <input type="hidden" name="matrix_numbers[]" value="<?= htmlspecialchars($user['matrix_number']) ?>">
                    <?php endforeach; ?>
                    
                    <div class="d-flex justify-content-between">
                        <button type="submit" name="confirm_delete" class="btn btn-danger">Confirm Delete</button>
                        <a href="admin_homepage.php?active_tab=view-users" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>