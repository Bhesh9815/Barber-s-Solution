<?php

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

// Include database connection
include 'db_connection.php';

// Check for back button navigation and clear session
if (isset($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] === 'no-cache, must-revalidate, post-check=0, pre-check=0') {
    session_unset();
    session_destroy();
    header("Location: user_register.html");
    exit();
}


// Redirect if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: user_register.html");
    exit();
}

// Fetch barber's details from the barber_tbl
$barber_id = $_SESSION['user_id'];
$sql = "SELECT * FROM barber_tbl WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}
$stmt->bind_param('i', $barber_id);
$stmt->execute();
$result = $stmt->get_result();
$barber = $result->fetch_assoc();

// If barber not found, set a default value
if (!$barber) {
    $barber = [
        'name' => 'Barber Not Found',
        'email' => '',
        'contact' => '',
        'address' => '',
        'speciality' => '',
        'experience' => '',
        'barbershop_photo' => ''
    ];
}

// Process form submission for updating barber's profile
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    // Get form data
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['contact'];
    $address = $_POST['address'];
    $speciality = $_POST['speciality'];
    $experience = $_POST['experience'];
    $photo = $_FILES['photo'];

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
        header("Location: barber_dashboard.php");
        exit();
    }

    // Update barber's profile in the database
    $sql = "UPDATE barber_tbl SET 
            name = ?, 
            email = ?, 
            contact = ?, 
            address = ?, 
            speciality = ?, 
            experience = ? 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssssssi', $name, $email, $phone, $address, $speciality, $experience, $barber_id);
    $stmt->execute();

    // Handle file upload (profile photo)
    if ($photo['error'] == 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true); // Create the directory if it doesn't exist
        }
        $target_file = $target_dir . basename($photo['name']);

        if (move_uploaded_file($photo['tmp_name'], $target_file)) {
            // Update photo path in the database
            $sql = "UPDATE barber_tbl SET barbershop_photo = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $target_file, $barber_id);
            $stmt->execute();
        } else {
            $_SESSION['error'] = "Failed to upload file.";
        }
    }

    // Refresh the page to show updated profile
    header("Location: barber_dashboard.php");
    exit();
}

// Fetch today's appointments with status "Pending" for the logged-in barber
$date_today = date('Y-m-d');
$sql = "SELECT u.name AS client_name, a.appointment_time, a.appointment_date, a.status, a.id AS appointment_id 
        FROM appointments a
        JOIN user_tbl u ON a.user_id = u.id
        WHERE a.barber_id = ? AND a.appointment_date = ? AND a.status = 'Pending'
        ORDER BY a.appointment_time ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('is', $_SESSION['user_id'], $date_today);
$stmt->execute();
$result = $stmt->get_result();
$appointments = $result->fetch_all(MYSQLI_ASSOC);

// Fetch services for the logged-in barber
$sql = "SELECT * FROM services WHERE barber_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $barber_id);
$stmt->execute();
$result = $stmt->get_result();
$services = $result->fetch_all(MYSQLI_ASSOC);

// Process service management actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['service_action'])) {
    $service_action = $_POST['service_action'];
    $service_id = isset($_POST['service_id']) ? $_POST['service_id'] : null;

    if ($service_action == 'add') {
        // Add a new service
        $servicename = $_POST['service_name'];
        $description = $_POST['description'];
        $price = $_POST['price'];

        $sql = "INSERT INTO services (service_name, description, price, barber_id) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssdi', $servicename, $description, $price, $barber_id);
        $stmt->execute();
    } elseif ($service_action == 'edit' && $service_id) {
        // Edit an existing service
        $servicename = $_POST['service_name'];
        $description = $_POST['description'];
        $price = $_POST['price'];

        $sql = "UPDATE services SET service_name = ?, description = ?, price = ? WHERE id = ? AND barber_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssdii', $servicename, $description, $price, $service_id, $barber_id);
        $stmt->execute();
    } elseif ($service_action == 'delete' && $service_id) {
        // Delete a service
        $sql = "DELETE FROM services WHERE id = ? AND barber_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $service_id, $barber_id);
        $stmt->execute();
    }

    // Refresh the page to show updated services
    header("Location: barber_dashboard.php");
    exit();
}

// Fetch notifications for the logged-in barber
$sql = "SELECT * FROM notifications WHERE barber_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $barber_id);
$stmt->execute();
$result = $stmt->get_result();
$notifications = $result->fetch_all(MYSQLI_ASSOC);

// Process marking notifications as read
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_as_read'])) {
    $notification_id = $_POST['notification_id'];
    $sql = "UPDATE notifications SET is_read = 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $notification_id);
    $stmt->execute();

    // Refresh the page to show updated notifications
    header("Location: barber_dashboard.php");
    exit();
}

// Handle slot adjustment form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_slot'])) {
    $slot_date = $_POST['slot_date'];
    $slot_time = $_POST['slot_time'];

    // Check if the slot already exists
    $check_sql = "SELECT * FROM barber_time_slots WHERE barber_id = ? AND slot_date = ? AND slot_time = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("iss", $barber_id, $slot_date, $slot_time);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        $_SESSION['error'] = "This time slot already exists.";
    } else {
        // Insert the new time slot
        $insert_sql = "INSERT INTO barber_time_slots (barber_id, slot_date, slot_time) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iss", $barber_id, $slot_date, $slot_time);
        if ($insert_stmt->execute()) {
            $_SESSION['success'] = "Time slot added successfully.";
        } else {
            $_SESSION['error'] = "Failed to add time slot.";
        }
    }

    // Refresh the page
    header("Location: barber_dashboard.php");
    exit();
}

// Handle slot deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_slot'])) {
    $slot_id = $_POST['slot_id'];

    // Delete the time slot from the database
    $delete_sql = "DELETE FROM barber_time_slots WHERE id = ? AND barber_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("ii", $slot_id, $barber_id);

    if ($delete_stmt->execute()) {
        $_SESSION['success'] = "Time slot deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete time slot.";
    }

    // Refresh the page
    header("Location: barber_dashboard.php");
    exit();
}

// Fetch available time slots for the logged-in barber
$sql = "SELECT * FROM barber_time_slots WHERE barber_id = ? ORDER BY slot_date, slot_time";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $barber_id);
$stmt->execute();
$result = $stmt->get_result();
$time_slots = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barber's Dashboard</title>
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        /* Global Styles */
        html {
            scroll-behavior: smooth;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            scroll-behavior: smooth;
        }
        body {
            font-family: 'Roboto', sans-serif;
            color: #2C3E50;
            background-color: #ECF0F1;
            min-height: 100vh;
            display: flex;
            flex-direction: row;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background-color: #2C3E50;
            padding: 20px;
            position: fixed;
            height: 100%;
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            transition: width 0.3s ease;
        }
        .sidebar:hover {
            width: 280px;
        }
        .sidebar .logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 30px;
        }
        .sidebar .logo img {
            height: 60px;
            margin-bottom: 10px;
        }
        .sidebar .logo h3 {
            color: #FFFFFF;
            text-align: center;
            margin-bottom: 10px;
        }
        .sidebar nav {
            margin-top: 30px;
            width: 100%;
        }
        .sidebar nav a {
            display: block;
            color: #FFFFFF;
            padding: 15px 20px;
            font-size: 16px;
            margin-bottom: 10px;
            text-decoration: none;
            transition: background 0.3s, color 0.3s;
            border-radius: 5px;
        }
        .sidebar nav a:hover {
            background-color: #F1C40F;
            color: #2C3E50;
        }
        .sidebar nav a i {
            margin-right: 10px;
            font-size: 18px;
        }

        /* Edit Profile Button */
        .edit-profile-button {
            display: flex;
            justify-content: center;
            margin-top: 10px;
        }
        .edit-profile-button button {
            background-color: #28a745;
            color: #fff;
            font-size: 14px;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .edit-profile-button button:hover {
            background-color: #218838;
        }

        /* Main Content */
        .main-content {
            margin-left: 270px;
            padding: 40px;
            width: calc(100% - 270px);
            transition: margin-left 0.3s ease;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 10px;
            }
            .sidebar nav a {
                font-size: 14px;
            }
        }

        /* Section Styling */
        .dashboard-section {
            background-color: #3498db;
            color: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .dashboard-card {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        h1, h2 {
            margin-bottom: 20px;
            font-size: 24px;
            color: #2C3E50;
        }

        /* Appointments List */
        #appointments ul {
            list-style: none;
            padding: 0;
        }
        #appointments li {
            background-color: #ecf0f1;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        /* Button Styles */
        button {
            padding: 10px 15px;
            margin-right: 10px;
            background-color: #F1C40F;
            border: none;
            color: #2C3E50;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background-color: #F39C12;
        }

        /* Edit Profile Form */
        .edit-profile-form {
            display: none; /* Hidden by default */
            margin-top: 20px;
        }
        .edit-profile-form.active {
            display: block; /* Show when active */
        }
        .edit-profile-form input[type="text"],
        .edit-profile-form input[type="email"],
        .edit-profile-form input[type="file"],
        .edit-profile-form input[type="number"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .edit-profile-form button {
            background-color: #28a745;
            color: #fff;
        }
        .edit-profile-form button:hover {
            background-color: #218838;
        }

        /* Service Management Styles */
        .service-management {
            margin-top: 20px;
        }
        .service-management h2 {
            margin-bottom: 15px;
        }
        .service-management table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .service-management th, .service-management td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .service-management th {
            background-color: #f1c40f;
            color: #2c3e50;
        }
        .service-management tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .service-management tr:hover {
            background-color: #f1f1f1;
        }
        .service-management .actions {
            display: flex;
            gap: 5px;
        }
        .service-management .actions button {
            padding: 5px 10px;
            font-size: 12px;
        }
        .service-management .add-service-form {
            margin-top: 20px;
        }
        .service-management .add-service-form input[type="text"],
        .service-management .add-service-form input[type="number"] {
            padding: 8px;
            margin-right: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .service-management .add-service-form button {
            padding: 8px 16px;
            background-color: #28a745;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .service-management .add-service-form button:hover {
            background-color: #218838;
        }
        #notifications ul {
            list-style: none;
            padding: 0;
        }
        #notifications li {
            background-color: #f9f9f9;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        #notifications li.unread {
            background-color: #e3f2fd; /* Light blue for unread notifications */
            border-left: 4px solid #2196f3; /* Blue accent */
        }
        #notifications li.read {
            background-color: #f5f5f5; /* Light gray for read notifications */
        }
        #notifications small {
            display: block;
            color: #666;
            margin-top: 5px;
        }
        #notifications button {
            background-color: #28a745;
            color: #fff;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
        }
        #notifications button:hover {
            background-color: #218838;
        }

        /* Slot Adjustment Section */
        .slot-adjustment {
            margin-top: 20px;
        }
        .slot-adjustment h2 {
            margin-bottom: 15px;
        }
        .slot-adjustment table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .slot-adjustment th, .slot-adjustment td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .slot-adjustment th {
            background-color: #f1c40f;
            color: #2c3e50;
        }
        .slot-adjustment tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .slot-adjustment tr:hover {
            background-color: #f1f1f1;
        }
        .slot-adjustment .add-slot-form {
            margin-top: 20px;
        }
        .slot-adjustment .add-slot-form input[type="date"],
        .slot-adjustment .add-slot-form input[type="time"] {
            padding: 8px;
            margin-right: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .slot-adjustment .add-slot-form button {
            padding: 8px 16px;
            background-color: #28a745;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .slot-adjustment .add-slot-form button:hover {
            background-color: #218838;
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
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo">
            <img src="logo.png" alt="Project Logo">
            <h3><?php echo htmlspecialchars($barber['name']); ?></h3>
            <!-- Edit Profile Button -->
            <div class="edit-profile-button">
                <button onclick="toggleEditProfileForm()"><i class="fas fa-user-edit"></i> Edit Profile</button>
            </div>
        </div>
        <nav>
            <a href="#home"><i class="fas fa-home"></i> Home</a>
            <a href="manageappointments.php"><i class="fas fa-calendar-alt"></i> Manage Appointments</a>
            <a href="#services"><i class="fas fa-cogs"></i> Services</a>
            <a href="generate_barber_report.php"><i class="fa-solid fa-file"></i> Reports</a>
            <a href="#notifications"><i class="fas fa-bell"></i> Notifications</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Display error messages if any -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message" style="color: red; margin-bottom: 20px;">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Display success messages if any -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message" style="color: green; margin-bottom: 20px;">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <section id="home" class="dashboard-section">
            <p>Manage your appointments and notifications here.</p>
        </section>

        <!-- Edit Profile Form -->
        <section id="edit-profile" class="dashboard-card edit-profile-form">
            <h2>Edit Profile</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="update_profile" value="1">
                
                <!-- Name -->
                <label for="name">Name:</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($barber['name']); ?>" required><br><br>
                
                <!-- Email -->
                <label for="email">Email:</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($barber['email']); ?>" required><br><br>
                
                <!-- Phone -->
                <label for="phone">Phone:</label>
                <input type="text" name="contact" value="<?php echo htmlspecialchars($barber['contact']); ?>" required><br><br>
                
                <!-- Address -->
                <label for="address">Address:</label>
                <input type="text" name="address" value="<?php echo htmlspecialchars($barber['address']); ?>" required><br><br>
                
                <!-- Speciality -->
                <label for="speciality">Speciality:</label>
                <input type="text" name="speciality" value="<?php echo htmlspecialchars($barber['speciality']); ?>" required><br><br>
                
                <!-- Experience -->
                <label for="experience">Experience (years):</label>
                <input type="number" name="experience" value="<?php echo htmlspecialchars($barber['experience']); ?>" required><br><br>
                
                <!-- Profile Photo -->
                <label for="photo">Profile Photo:</label>
                <input type="file" name="photo"><br><br>
                
                <!-- Submit Button -->
                <button type="submit">Update Profile</button>
            </form>
        </section>

        <!-- Today's Appointments -->
        <section id="appointments" class="dashboard-card">
            <h2>Today's Appointments</h2>
            <ul>
                <?php foreach ($appointments as $appointment): ?>
                    <li>
                        <strong><?php echo htmlspecialchars($appointment['client_name']); ?></strong> - <?php echo htmlspecialchars($appointment['appointment_time']); ?> (<?php echo htmlspecialchars($appointment['status']); ?>)
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <!-- Notifications Section -->
        <section id="notifications" class="dashboard-card">
            <h2>Notifications</h2>
            <ul>
                <?php if (empty($notifications)): ?>
                    <li>No new notifications.</li>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <li class="<?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                            <small><?php echo htmlspecialchars($notification['created_at']); ?></small>
                            <?php if (!$notification['is_read']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                    <button type="submit" name="mark_as_read">Mark as Read</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </section>

        <!-- Service Management -->
        <section id="services" class="dashboard-card service-management">
            <h2>Manage Services</h2>
            <table>
                <thead>
                    <tr>
                        <th>Service Name</th>
                        <th>Description</th>
                        <th>Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                            <td><?php echo htmlspecialchars($service['description']); ?></td>
                            <td>रू<?php echo htmlspecialchars($service['price']); ?></td>
                            <td class="actions">
                                <button onclick="editService(<?php echo $service['id']; ?>, '<?php echo htmlspecialchars($service['service_name']); ?>', '<?php echo htmlspecialchars($service['description']); ?>', <?php echo htmlspecialchars($service['price']); ?>)">Edit</button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="service_action" value="delete">
                                    <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                    <button type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Add/Edit Service Form -->
            <div class="add-service-form">
                <h3><?php echo isset($_POST['edit_service']) ? 'Edit Service' : 'Add New Service'; ?></h3>
                <form method="POST">
                    <input type="hidden" name="service_action" value="<?php echo isset($_POST['edit_service']) ? 'edit' : 'add'; ?>">
                    <input type="hidden" name="service_id" value="<?php echo isset($_POST['edit_service']) ? $_POST['service_id'] : ''; ?>">
                    <input type="text" name="service_name" placeholder="Service Name" value="<?php echo isset($_POST['edit_service']) ? $_POST['service_name'] : ''; ?>" required>
                    <input type="text" name="description" placeholder="Description" value="<?php echo isset($_POST['edit_service']) ? $_POST['description'] : ''; ?>" required>
                    <input type="number" name="price" placeholder="Price" step="0.01" value="<?php echo isset($_POST['edit_service']) ? $_POST['price'] : ''; ?>" required>
                    <button type="submit"><?php echo isset($_POST['edit_service']) ? 'Update Service' : 'Add Service'; ?></button>
                </form>
            </div>
        </section>

  <!-- Slot Adjustment Section -->
<section id="slot-adjustment" class="dashboard-card slot-adjustment">
    <h2>Manage Time Slots</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Status</th>
                <th>Action</th> <!-- New column for delete button -->
            </tr>
        </thead>
        <tbody>
            <?php foreach ($time_slots as $slot): ?>
                <tr>
                    <td><?php echo htmlspecialchars($slot['slot_date']); ?></td>
                    <td><?php echo htmlspecialchars($slot['slot_time']); ?></td>
                    <td><?php echo $slot['is_available'] ? 'Available' : 'Booked'; ?></td>
                    <td>
                        <!-- Delete Button -->
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="delete_slot" value="1">
                            <input type="hidden" name="slot_id" value="<?php echo $slot['id']; ?>">
                            <button type="submit" style="background-color: #dc3545; color: white;">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Add Slot Form -->
    <div class="add-slot-form">
        <h3>Add New Time Slot</h3>
        <form method="POST">
            <input type="hidden" name="add_slot" value="1">
            <input type="date" name="slot_date" required>
            <input type="time" name="slot_time" required>
            <button type="submit">Add Slot</button>
        </form>
    </div>
</section>
    </div>
    <script>
        // Toggle Edit Profile Form
        function toggleEditProfileForm() {
            console.log("Toggle function called"); // Debugging
            const editProfileForm = document.getElementById('edit-profile');
            if (editProfileForm) {
                editProfileForm.classList.toggle('active');
                console.log("Edit profile form toggled"); // Debugging
            } else {
                console.error("Edit profile form not found!"); // Debugging
            }
        }

        // Edit Service Function
        function editService(id, service_name, description, price) {
            const form = document.querySelector('.add-service-form form');
            form.querySelector('input[name="service_action"]').value = 'edit';
            form.querySelector('input[name="service_id"]').value = id;
            form.querySelector('input[name="service_name"]').value = service_name;
            form.querySelector('input[name="description"]').value = description;
            form.querySelector('input[name="price"]').value = price;
            form.querySelector('button[type="submit"]').textContent = 'Update Service';
        }
    </script>
</body>
</html>