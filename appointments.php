<?php
session_start();
include 'db_connection.php';

// Redirect to registration page if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: user_register.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get the barber_id and service_ids from the URL
$barber_id = isset($_GET['barber_id']) ? $_GET['barber_id'] : null;
$service_ids = isset($_GET['services']) ? explode(",", $_GET['services']) : [];

// Fetch the selected barber's details
$selected_barber = null;
if ($barber_id) {
    $sql = "SELECT id, name FROM barber_tbl WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $barber_id);
    $stmt->execute();
    $selected_barber = $stmt->get_result()->fetch_assoc();
}

// Fetch the selected services' details
$selected_services = [];
if (!empty($service_ids)) {
    $placeholders = implode(",", array_fill(0, count($service_ids), "?"));
    $sql = "SELECT id, service_name, price FROM services WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat("i", count($service_ids)), ...$service_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $selected_services[] = $row;
    }
}

// Fetch available time slots for the selected barber
$available_slots = [];
if ($barber_id) {
    $sql = "SELECT slot_date, slot_time 
            FROM barber_time_slots 
            WHERE barber_id = ? 
            AND is_available = TRUE 
            AND slot_date >= CURDATE() 
            ORDER BY slot_date, slot_time";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $barber_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $available_slots[] = $row;
    }
}

// Process the form when submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $barber_id = $_POST['barber_id'];
    $service_ids = isset($_POST['service_ids']) ? explode(",", $_POST['service_ids']) : [];

    // Check if the selected time is available
    $check_availability_sql = "SELECT * FROM appointments WHERE appointment_date = ? AND appointment_time = ? AND barber_id = ?";
    $stmt = $conn->prepare($check_availability_sql);
    $stmt->bind_param("ssi", $appointment_date, $appointment_time, $barber_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo "<script>alert('The selected time slot is already booked. Please choose another time.');</script>";
    } else {
        // Save the appointment in the database
        $sql = "INSERT INTO appointments (user_id, barber_id, appointment_date, appointment_time, status) 
                VALUES (?, ?, ?, ?, 'Pending')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiss", $user_id, $barber_id, $appointment_date, $appointment_time);
        
        if ($stmt->execute()) {
            // Get the appointment_id of the newly created appointment
            $appointment_id = $stmt->insert_id;

            // Save the selected services for the appointment
            if (!empty($service_ids)) {
                $sql_insert = "INSERT INTO appointment_services (appointment_id, service_id) VALUES (?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);

                foreach ($service_ids as $service_id) {
                    $stmt_insert->bind_param('ii', $appointment_id, $service_id);
                    $stmt_insert->execute();
                }
                $stmt_insert->close();
            }

            // Log the activity in user_activities table
            $activity_description = "Booked an appointment with Barber: " . ($selected_barber['name'] ?? "Unknown") . " on " . $appointment_date . " at " . $appointment_time;
            $activity_date = date("Y-m-d H:i:s"); // Current timestamp
            
            $insert_activity_sql = "INSERT INTO user_activities (user_id, activity_description, activity_date) VALUES (?, ?, ?)";
            $activity_stmt = $conn->prepare($insert_activity_sql);
            $activity_stmt->bind_param("iss", $user_id, $activity_description, $activity_date);
            $activity_stmt->execute();
            $activity_stmt->close();

            // Add notification for the barber
            $notification_message = "New appointment booked by " . ($_SESSION['user_name'] ?? "User") . " on " . $appointment_date . " at " . $appointment_time;
            $notification_sql = "INSERT INTO notifications (barber_id, message) VALUES (?, ?)";
            $notification_stmt = $conn->prepare($notification_sql);
            $notification_stmt->bind_param("is", $barber_id, $notification_message);
            $notification_stmt->execute();
            $notification_stmt->close();

            // Redirect to payment page with the appointment_id
            header("Location: payment.php?id=$appointment_id");
            exit();
        } else {
            echo "<script>alert('Failed to book appointment. Please try again later.');</script>";
        }
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.2.0/fullcalendar.min.css" />
    <style>
        /* Adjust the body content to avoid overlap with fixed navbar */
        body {
            margin-top: 60px; /* Adjust this value based on your navbar height */
        }

        /* Make sure the navbar has a fixed position and proper width */
        header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background-color: #2C3E50; /* Same color as the button */
            z-index: 1000; /* Keep navbar above other content */
            padding: 10px 20px;
        }

        /* Ensure the content area starts below the navbar */
        .container {
            padding-top: 20px; /* Adjust as necessary to avoid overlap */
            display: flex; /* Use Flexbox */
            justify-content: space-between;
            align-items: flex-start;
        }

        .calendar {
            width: 48%; /* Take up half the width */
            text-align: right; /* Align the calendar to the right */
            margin-left: 20px; /* Add margin to the left of the calendar */
            border: 2px solid #2C3E50; /* Add border with color #2C3E50 */
            padding: 10px;
        }

        .form-section {
            width: 48%; /* Take up the other half of the width */
            padding-left: 20px; /* Add some space between the calendar and form */
            margin-right: 20px; /* Add margin to the right of the form */
            border: 2px solid #2C3E50; /* Add border with color #2C3E50 */
            padding: 10px;
        }

        /* Adjust other elements for consistency */
        header .logo {
            float: left;
        }

        header nav {
            float: right;
            margin-top: 10px;
        }

        /* Adjust the form inputs for a clean look */
        form {
            display: flex;
            flex-direction: column;
        }

        form label, form select, form input, form button {
            margin-bottom: 15px; /* Added more space between fields */
            font-size: 1.2em; /* Increased font size for better readability */
            padding: 12px; /* Increased padding */
        }

        form input, form select {
            width: 90%;
            box-sizing: border-box;
        }

       form button {
            width: 160px;
            background-color: #2C3E50;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            font-size: 1em; /* Reduced font size */
            padding: 12px;
        }

        form button:hover {
            background-color: #555;
        }

        /* Styles for services list */
        .services-list {
            margin: 20px 0;
        }

        .services-list label {
            display: block;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <img src="logo.png" alt="Logo">
            <span>Barber's Solution</span>
        </div>
        <nav>
            <a href="user_dashboard.php">Dashboard</a>
            <a href="appointments.php" class="active">Appointments</a>
        </nav>
    </header>

    <div class="container">
        <!-- Calendar Section -->
        <div class="calendar">
            <h2>Calendar</h2>
            <div id="calendar"></div>
        </div>

        <!-- Form Section -->
        <div class="form-section">
            <h2>Book an Appointment</h2>

            <form action="appointments.php" method="POST">
                <label for="appointment_date">Selected Date:</label>
                <input type="text" id="appointment_date" name="appointment_date" readonly required><br>

                <label for="appointment_time">Select Time Slot:</label>
                <select name="appointment_time" required>
                    <option value="">Select Time</option>
                    <?php foreach ($available_slots as $slot): ?>
                        <option value="<?php echo htmlspecialchars($slot['slot_time']); ?>">
                            <?php echo htmlspecialchars($slot['slot_time']); ?>
                        </option>
                    <?php endforeach; ?>
                </select><br>

                <!-- Hidden Barber ID Field -->
                <input type="hidden" name="barber_id" value="<?php echo $barber_id; ?>">

                <!-- Hidden Field to Store Selected Service IDs -->
                <input type="hidden" name="service_ids" value="<?php echo implode(",", $service_ids); ?>">

                <!-- Display Selected Barber -->
                <label for="barber_name">Selected Barber:</label>
                <input type="text" id="barber_name" value="<?php echo htmlspecialchars($selected_barber['name']); ?>" readonly><br>

                <!-- Display Selected Services -->
                <div class="services-list">
                    <h3>Selected Services:</h3>
                    <?php if (!empty($selected_services)): ?>
                        <ul>
                            <?php foreach ($selected_services as $service): ?>
                                <li><?php echo htmlspecialchars($service['service_name']); ?> - रू<?php echo htmlspecialchars($service['price']); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No services selected.</p>
                    <?php endif; ?>
                </div>

                <button type="submit">Proceed For Payment</button>
            </form>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 Barber's Solution. All Rights Reserved.</p>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.2.0/fullcalendar.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#calendar').fullCalendar({
                header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'month,agendaWeek,agendaDay'
                },
                selectable: true,
                select: function(startDate, endDate) {
                    var date = startDate.format();
                    $('#appointment_date').val(date); // Fill in selected date into the input field
                    $('#appointment_time').prop('disabled', false); // Enable time selection
                }
            });
        });
    </script>
</body>
</html>