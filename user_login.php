<?php
ob_start(); // Output buffering to prevent header issues
session_start();
include 'db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role = $_POST['role'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Select correct table based on role
    if ($role == "User") {
        $sql = "SELECT id, name, email, password FROM user_tbl WHERE email = ?";
    } elseif ($role == "Admin") {
        $sql = "SELECT id, name, email, password FROM barber_tbl WHERE email = ?";
    } else {
        echo "<script>alert('Invalid role!'); window.location.href='Barbers Solution.html#login';</script>";
        exit();
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare Error: " . $conn->error);
        echo "Database error. Please try again later.";
        exit();
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->errno) {
        error_log("Execute Error: " . $stmt->error);
        echo "Database error. Please try again later.";
        exit();
    }

    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $name, $email, $stored_password);
        $stmt->fetch();

        // Check password (if hashed, use password_verify)
        if ($password == $stored_password) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = $role;
            $_SESSION['user_email'] = $email;

            // Redirect based on role
            if ($role == "User") {
                header("Location: user_dashboard.php");
            } elseif ($role == "Admin") {
                header("Location: barber_dashboard.php");
            }

            ob_end_flush(); // Flush output buffer before redirect
            exit();
        } else {
            echo "<script>alert('Invalid password!'); window.location.href='Barbers Solution.html#login';</script>";
        }
    } else {
        echo "<script>alert('No account found with this email!'); window.location.href='Barbers Solution.html#login';</script>";
    }

    $stmt->close();
}

$conn->close();
ob_end_flush(); // Flush output buffer at the end
?>
