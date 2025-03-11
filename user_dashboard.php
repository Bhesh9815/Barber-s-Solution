<?php
session_start();
// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

include 'db_connection.php';

// Check for back button navigation and clear session
if (isset($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] === 'no-cache, must-revalidate, post-check=0, pre-check=0') {
    session_unset();
    session_destroy();
    header("Location: user_register.html");
    exit();
}

// Redirect to registration page if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: user_register.html");
    exit();
}

// Fetch user details from the database
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM user_tbl WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$user_name = $user['name'];
$user_email = $user['email'];
$user_role = $user['role'];

// Fetch recent activities for the user from the activities table
$activity_sql = "SELECT activity_description, activity_date FROM user_activities WHERE user_id = ? ORDER BY activity_date DESC LIMIT 5";
$activity_stmt = $conn->prepare($activity_sql);
$activity_stmt->bind_param("i", $user_id);
$activity_stmt->execute();
$activity_result = $activity_stmt->get_result();
$activities = $activity_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Ensure the body and html take up the full height of the screen */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        /* Main container to push footer to the bottom */
        .container {
            min-height: calc(100vh - 120px); /* Adjust 120px to match the height of your header and footer */
            padding-bottom: 60px; /* Add padding to prevent content from overlapping with the footer */
        }

        /* Fixed footer */
        footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: #2C3E50; /* Add background color for better visibility */
            color: #fff; /* Text color */
            text-align: center;
            padding: 10px 0; /* Add padding for better spacing */
            z-index: 1000; /* Ensure the footer stays above other content */
        }

        /* Round button */
        .user-button {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #333;
            color: #fff;
            border: none;
            font-size: 25px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease;
        }

        .user-button:hover {
            background-color: #555;
        }

        /* Dropdown menu */
        .user-menu {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: #fff;
            min-width: 160px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            border-radius: 4px;
            z-index: 1;
        }

        .dropdown-content a {
            color: #333;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: background-color 0.3s ease;
        }

        .dropdown-content a:hover {
            background-color: #f1f1f1;
        }

        /* Show dropdown when clicked */
        .user-menu.active .dropdown-content {
            display: block;
        }

        /* Add margin to container to prevent overlap with navbar */
        .container {
            margin-top: 60px; /* Adjust this value to match the height of your navbar */
        }

        /* Style for Recent Activity container */
        #recent-activity {
            border: 3px dashed navy; /* Add a 3px dashed navy blue border */
            padding: 15px; /* Add padding for better spacing */
            margin-top: 20px; /* Add margin to separate it from the button */
            border-radius: 8px; /* Optional: Add rounded corners */
        }
    </style>
    <script type="text/javascript">
        function preventBack(){
            window.history.forward();
        }

        setTimeout(preventBack,0);
        window.onunload=function(){null};
        if(performance.navigation.type == 2){
            window.location.href='logout.php';
        }
    </script>
</head>
<body>
    <header>
        <div class="logo">
            <img src="logo.png" alt="Logo">
            <span>Barber's Solution</span>
        </div>
        <nav aria-label="Main navigation">
            <a href="user_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'user_dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
            <a href="fetch_barber_profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'fetch_barber_profile.php' ? 'active' : ''; ?>">Appointments</a>
            <div class="user-menu">
                <button class="user-button"><?php echo htmlspecialchars(substr($user_name, 0, 1)); ?></button>
                <div class="dropdown-content">
                    <a href="user_profile_update.php">Edit Profile</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container">
        <section>
            <h3>Your Profile</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($user_name); ?></p>
            <p><strong>Role:</strong> <?php echo htmlspecialchars($user_role); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user_email); ?></p>
        </section>

        <button onclick="toggleSection('recent-activity')">Recent Activity</button>
        <button onclick="generate_user_report()">Get Report</button>
        <section id="recent-activity">
            <h3>Recent Activity</h3>
            <?php if (!empty($activities)): ?>
                <ul>
                    <?php foreach ($activities as $activity): ?>
                        <li><?php echo htmlspecialchars($activity['activity_description']) . ' (' . htmlspecialchars($activity['activity_date']) . ')'; ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No recent activities found.</p>
            <?php endif; ?>
        </section>
    </div>

    <footer>
        <p>&copy; 2025 Barber's Solution. All Rights Reserved.</p>
    </footer>

    <script>
        // Toggle section visibility (Recent Activity)
        function toggleSection(sectionId) {
            const section = document.getElementById(sectionId);
            if (section.style.display === "none") {
                section.style.display = "block";
            } else {
                section.style.display = "none";
            }
        }

        // Generate PDF report
        function generate_user_report() {
            // Redirect to the PHP script that generates the PDF
            window.location.href = 'generate_user_report.php';
        }

        // Toggle the dropdown menu visibility
        const userMenuButton = document.querySelector('.user-button');
        const userMenu = document.querySelector('.user-menu');

        userMenuButton.addEventListener('click', function(event) {
            event.stopPropagation(); // Prevent click event from propagating to document
            userMenu.classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            if (!userMenu.contains(event.target)) {
                userMenu.classList.remove('active');
            }
        });
    </script>
</body>
</html>