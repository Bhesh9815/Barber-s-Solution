<?php
session_start();
include 'db_connection.php';

if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_role'])) {
    // Redirect if session variables are not set
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION['reset_email'];
$role = $_SESSION['reset_role'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validate passwords
    if (empty($new_password) || empty($confirm_password)) {
        echo "<script>alert('Please fill in all fields.');</script>";
    } elseif ($new_password !== $confirm_password) {
        echo "<script>alert('Passwords do not match.');</script>";
    } else {
        // Hash the new password
        $hashed_password = $new_password;

        // Update the password in the database
        if ($role == "User") {
            $table = "user_tbl";
        } elseif ($role == "Admin") {
            $table = "barber_tbl";
        }

        $sql = "UPDATE $table SET password = ? WHERE email = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare Error: " . $conn->error);
            echo "<script>alert('Database error. Please try again later.');</script>";
        } else {
            $stmt->bind_param("ss", $hashed_password, $email);
            if ($stmt->execute()) {
                echo "<script>alert('Password updated successfully!');

                 window.location.href='Barbers solution.html#login';</script>";
                session_unset();
                session_destroy();
                exit();
            } else {
                error_log("Execute Error: " . $stmt->error);
                echo "<script>alert('Failed to update password. Please try again later.');</script>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .form-container {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 300px;
        }
        .form-container h2 {
            margin-bottom: 20px;
            text-align: center;
        }
        .form-container input {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .form-container button {
            width: 100%;
            padding: 10px;
            background-color: #28a745;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .form-container button:hover {
            background-color: #218838;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Reset Password</h2>
        <form action="reset_password.php" method="POST">
            <input type="password" name="new_password" placeholder="New Password" required>
            <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            <button type="submit">Update Password</button>
        </form>
    </div>
</body>
</html>