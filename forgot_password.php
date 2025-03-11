<?php
session_start();
include 'db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = trim($_POST['role']);

    // Validate email, phone, and role
    if (empty($email) || empty($phone) || empty($role)) {
        echo "<script>alert('Please fill in all fields.'); window.location.href='forgot_password.php';</script>";
        exit();
    }

    // Check if email and phone exist in the appropriate table
    if ($role == "User") {
        $table = "user_tbl";
    } elseif ($role == "Admin") {
        $table = "barber_tbl";
    } else {
        echo "<script>alert('Invalid role!'); window.location.href='forgot_password.php';</script>";
        exit();
    }

    // Query to check if email and phone match in the database
    $sql = "SELECT id, email, contact FROM $table WHERE email = ? AND contact = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare Error: " . $conn->error);
        echo "<script>alert('Database error. Please try again later.'); window.location.href='forgot_password.php';</script>";
        exit();
    }

    $stmt->bind_param("ss", $email, $phone);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Email and phone match, redirect to password reset page
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_role'] = $role;
        header("Location: reset_password.php");
        exit();
    } else {
        echo "<script>alert('No account found with this email and contact number!'); window.location.href='forgot_password.php';</script>";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
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
        .form-container input, .form-container select {
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
        .error {
            color: red;
            font-size: 12px;
            display: none;
        }
        .error.show {
            display: block;
        }
    </style>
    <script type="text/javascript">
        function validatePhone() {
            const phoneInput = document.getElementById('phone');
            const phoneError = document.getElementById('phoneError');

            // Allow only numbers, +, -, and spaces
            phoneInput.value = phoneInput.value.replace(/[^0-9+-\s]/g, '');
            // Remove leading spaces
            phoneInput.value = phoneInput.value.replace(/^[ ]+/g, '');

            // Limit to 10 digits (excluding +977 or country code)
            let digitsOnly = phoneInput.value.replace(/[^0-9]/g, '');
            if (digitsOnly.length > 10) {
                phoneInput.value = phoneInput.value.slice(0, phoneInput.value.length - (digitsOnly.length - 10));
            }

            // Validate Nepali phone number format
            if (!phoneInput.value.trim()) {
                phoneError.textContent = "Contact is required.";
                phoneError.classList.add('show');
            } else if (!/^(\+977-?|0)?9[78]\d{8}$/.test(phoneInput.value)) {
                phoneError.textContent = "Invalid Nepali phone number format.";
                phoneError.classList.add('show');
            } else {
                phoneError.textContent = "";
                phoneError.classList.remove('show');
            }
        }

        // Attach the validation function to the phone input
        document.getElementById('phone').addEventListener('input', validatePhone);
    </script>
</head>
<body>
    <div class="form-container">
        <h2>Forgot Password</h2>
        <form action="forgot_password.php" method="POST" onsubmit="return validateForm()">
            <input type="email" name="email" placeholder="Enter your email" required> <br> <br>
            <input type="tel" id="phone" name="phone" placeholder="Contact" required>
            <span id="phoneError" class="error"></span><br>
            <select name="role" required>
                <option value="" disabled selected>Select Role</option>
                <option value="User">User</option>
                <option value="Admin">Admin</option>
            </select>
            <button type="submit">Reset Password</button>
        </form>
    </div>

    <script>
        function validateForm() {
            const phoneInput = document.getElementById('phone');
            const phoneError = document.getElementById('phoneError');

            // Re-validate phone number before form submission
            validatePhone();

            // Check if there are any validation errors
            if (phoneError.classList.contains('show')) {
                return false; // Prevent form submission
            }
            return true; // Allow form submission
        }
    </script>
</body>
</html>