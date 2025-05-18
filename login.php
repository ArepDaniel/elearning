<?php
session_start();
include('config.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $matrix_number = $_POST['matrix_number'];
    $ic_number = $_POST['ic_number'];

    $sql = "SELECT * FROM user WHERE matrix_number = ? AND ic_number = ? LIMIT 1";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ss", $matrix_number, $ic_number);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            $_SESSION['matrix_number'] = $user['matrix_number'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Role-based redirection
            if ($user['role'] == 'lecturer') {
                header("Location: lecturer_homepage.php");
            } elseif ($user['role'] == 'admin') {
                header("Location: admin_homepage.php");
            } else {
                header("Location: homepage.php");
            }
            exit();
        } else {
            $message = "Invalid login credentials!";
        }
    } else {
        $message = "Database query failed!";
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - USAS E-Learning</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --deep-navy: #0A1F3D;
            --burgundy: #800020;
            --gold: #D4AF37;
            --white-sand: #F5F5F0;
        }

        body {
            background: linear-gradient(rgba(255, 255, 255, 0.7), rgba(255, 255, 255, 0.7)), url('usas.jpg') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            color: #333;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 8px 32px rgba(10, 31, 61, 0.1);
            border: 1px solid rgba(212, 175, 55, 0.3);
            animation: floatIn 0.6s ease-out;
        }

        @keyframes floatIn {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        h2 {
            text-align: center;
            color: var(--deep-navy);
            margin-bottom: 30px;
            font-weight: bold;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 12px 15px;
            margin-bottom: 20px;
            color: #333;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--gold);
            box-shadow: 0 0 8px rgba(212, 175, 55, 0.4);
            background-color: white;
        }

        .form-control::placeholder {
            color: #999;
        }

        .btn-login {
            background-color: var(--burgundy);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: bold;
            width: 100%;
            transition: 0.3s ease;
            box-shadow: 0 4px 14px rgba(128, 0, 32, 0.3);
        }

        .btn-login:hover {
            background-color: #600018;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(128, 0, 32, 0.4);
        }

        .error-message {
            color: #b30000;
            text-align: center;
            margin-top: 15px;
            font-weight: 500;
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            right: 15px;
            top: 13px;
            color: #aaa;
        }

        .input-icon input {
            padding-right: 40px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>USAS E-LEARNING PORTAL</h2>
        <form method="POST">
            <div class="mb-3 input-icon">
                <input type="text" class="form-control" id="matrix_number" name="matrix_number" placeholder="Matrix Number" required>
                <i class="fas fa-user"></i>
            </div>
            <div class="mb-3 input-icon">
                <input type="password" class="form-control" id="ic_number" name="ic_number" placeholder="IC Number" required>
                <i class="fas fa-lock"></i>
            </div>
            <button type="submit" class="btn btn-login">LOGIN</button>
        </form>

        <?php if (isset($message)) { echo "<p class='error-message'>$message</p>"; } ?>
    </div>

    <!-- Font Awesome for icons -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>