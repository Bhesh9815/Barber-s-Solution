<?php
include 'db_connection.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php"); // Redirect if not logged in
    exit();
}

// Fetch user details
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM user_tbl WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$user_name = $user['name'];
$user_email = $user['email'];
$user_contact = $user['contact'];
$user_address = $user['address'];

// Success message
$success_message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $contact = $_POST['contact'];
    $address = $_POST['address'];

    $update_sql = "UPDATE user_tbl SET name = ?, email = ?, contact = ?, address = ? WHERE id = ?";
    $stmt_update = $conn->prepare($update_sql);
    $stmt_update->bind_param('ssssi', $name, $email, $contact, $address, $user_id);
    $stmt_update->execute();

    $success_message = "Profile updated successfully!";
    
    // Fetch updated user details
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }
        .profile-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            margin: 0 auto;
        }
        .profile-container h2 {
            margin-bottom: 20px;
            font-size: 24px;
            color: #333;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
        /* Adjusted contact field */
        .form-group input#contact {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            height: 40px;
        }
        .form-actions {
            margin-top: 20px;
            text-align: center;
        }
        .form-actions button {
            padding: 10px 20px;
            background-color: #28a745;
            border: none;
            color: #fff;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: 0.3s;
        }
        .form-actions button:hover {
            background-color: #218838;
        }
        .success-message {
            background-color: #28a745;
            color: #fff;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 20px;
            display: none;
        }
    </style>
</head>
<body>

    <div class="profile-container">
        <h2><i class="fas fa-user-edit"></i> Edit Your Profile</h2>

        <!-- Success Message -->
        <?php if (!empty($success_message)): ?>
            <div class="success-message" id="success-message"><?php echo $success_message; ?></div>
            <script>
                // Show success message and redirect after 2 seconds
                document.getElementById('success-message').style.display = 'block';
                setTimeout(function() {
                    window.location.href = 'user_dashboard.php';
                }, 100);
            </script>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="name"><i class="fas fa-user"></i> Name:</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user_name); ?>" required>
            </div>
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>" required>
            </div>
            <div class="form-group">
                <label for="contact"><i class="fas fa-phone"></i> Contact:</label>
                <input type="text" id="contact" name="contact" value="<?php echo htmlspecialchars($user_contact); ?>" required>
            </div>
            <div class="form-group">
                <label for="address"><i class="fas fa-map-marker-alt"></i> Address:</label>
                <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($user_address); ?>" required>
            </div>
            <div class="form-actions">
                <button type="submit"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const form = document.querySelector("form");
        
        form.addEventListener("submit", function(event) {
            event.preventDefault(); // Prevent immediate submission
            
            let name = document.getElementById("name").value.trim();
            let email = document.getElementById("email").value.trim();
            let contact = document.getElementById("contact").value.trim();
            let address = document.getElementById("address").value.trim();

            if (name === "" || email === "" || contact === "" || address === "") {
                alert("All fields are required!");
                return;
            }

            // Simulate form submission
            form.submit();
        });
    });
</script>

</body>
</html>
