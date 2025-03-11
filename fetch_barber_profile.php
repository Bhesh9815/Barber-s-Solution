<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
include 'db_connection.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: user_register.html");
    exit();
}

// Fetch user details from the database
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM user_tbl WHERE id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// If user not found, terminate the session and redirect
if (!$user) {
    // Destroy the session to prevent further access
    session_unset();
    session_destroy();

    // Redirect to login page with an error message
    $_SESSION['error'] = "Your account is no longer available. Please contact support.";
    header("Location: user_register.html");
    exit();
}

// Assign user details to variables
$user_name = $user['name'];
$user_email = $user['email'];
$user_role = $user['role'];

// Get search criteria from the form (if any)
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch barber profiles based on search criteria
$barber_sql = "SELECT id, name, speciality, experience, email, contact, barbershop_photo, address FROM barber_tbl WHERE 1=1";

if (!empty($search_query)) {
    $barber_sql .= " AND (name LIKE ? OR address LIKE ?)";
}

$stmt = $conn->prepare($barber_sql);

if (!empty($search_query)) {
    $search_param = "%$search_query%";
    $stmt->bind_param("ss", $search_param, $search_param);
}

$stmt->execute();
$barber_result = $stmt->get_result();

$barbers = [];
if ($barber_result->num_rows > 0) {
    while ($row = $barber_result->fetch_assoc()) {
        $barbers[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barber Profiles</title>
    <style>
        /* Ensure the body and html take up the full height of the screen */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: 'Arial', sans-serif;
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
            background-color: #2C3E50;
            color: #fff;
            text-align: center;
            padding: 10px 0;
            z-index: 1000;
        }

        /* Navbar Styles */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #2C3E50;
            padding: 10px 20px;
            height: 60px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .logo {
            display: flex;
            align-items: center;
        }

        .logo img {
            height: 40px;
            margin-right: 10px;
        }

        .logo span {
            font-size: 20px;
            font-weight: bold;
            color: #fff;
            font-family: 'Arial', sans-serif;
        }

        nav {
            display: flex;
            align-items: center;
        }

        nav a {
            color: #fff;
            text-decoration: none;
            margin: 0 10px;
            padding: 8px 16px;
            transition: background-color 0.3s ease;
            font-size: 16px;
            font-family: 'Arial', sans-serif;
            border-radius: 4px;
        }

        nav a:hover {
            background-color: #34495E;
        }

        nav a.active {
            font-weight: bold; /* Only bold for active tab, no background color */
        }

        /* Navbar Search Form */
        .navbar-search {
            display: flex;
            align-items: center;
            margin-left: 20px;
        }

        .navbar-search input[type="text"] {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-right: 10px;
            font-size: 14px;
            width: 200px; /* Fixed width for search bar */
        }

        .navbar-search button {
            padding: 8px 16px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .navbar-search button:hover {
            background-color: #0056b3;
        }

        /* User Menu Styles */
        .user-menu {
            margin-left: 20px;
        }

        .user-button {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #333;
            color: #fff;
            border: none;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease;
            font-family: 'Arial', sans-serif;
        }

        .user-button:hover {
            background-color: #555;
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
            font-size: 14px;
            font-family: 'Arial', sans-serif;
        }

        .dropdown-content a:hover {
            background-color: #f1f1f1;
        }

        .user-menu.active .dropdown-content {
            display: block;
        }

        /* Grid Styles for Barber Profiles */
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            padding: 20px;
        }

        .barber-card {
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            background-color: #f9f9f9;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .barber-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }

        .barber-card h3 {
            margin: 10px 0;
            font-size: 1.2em;
            font-family: 'Arial', sans-serif;
        }

        .barber-card p {
            margin: 5px 0;
            color: #555;
            font-family: 'Arial', sans-serif;
        }

        .barber-card button {
            margin-top: 10px;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Arial', sans-serif;
        }

        .barber-card button:hover {
            background-color: #0056b3;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 50%;
            max-width: 600px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

        #serviceList {
            margin: 20px 0;
        }

        #serviceList label {
            display: block;
            margin: 10px 0;
        }

        #confirmBooking {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }

        #confirmBooking:hover {
            background-color: #218838;
        }
    </style>
</head>
<body>
    <!-- Header with Search Bar in Navbar -->
    <header>
        <div class="logo">
            <img src="logo.png" alt="Logo">
            <span>Barber's Solution</span>
        </div>
        <nav aria-label="Main navigation">
            <a href="user_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'user_dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
            <a href="fetch_barber_profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'fetch_barber_profile.php' ? 'active' : ''; ?>">Appointments</a>
            
            <!-- Search Form in Navbar -->
            <form action="fetch_barber_profile.php" method="GET" class="navbar-search">
                <input type="text" name="search" placeholder="Search by name or location..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit">Search</button>
            </form>

            <div class="user-menu">
                <button class="user-button"><?php echo htmlspecialchars(substr($user_name, 0, 1)); ?></button>
                <div class="dropdown-content">
                    <a href="user_profile_update.php">Edit Profile</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Barber Profiles Grid -->
    <div class="grid-container">
        <?php if (!empty($barbers)): ?>
            <?php foreach ($barbers as $barber): ?>
                <div class="barber-card">
                    <?php if (!empty($barber['barbershop_photo'])): ?>
                        <img src="<?php echo htmlspecialchars($barber['barbershop_photo']); ?>" alt="<?php echo htmlspecialchars($barber['name']); ?>">
                    <?php else: ?>
                        <img src="path/to/placeholder.jpg" alt="Placeholder Image">
                    <?php endif; ?>
                    <h3><?php echo htmlspecialchars($barber['name']); ?></h3>
                    <p><strong>Speciality:</strong> <?php echo htmlspecialchars($barber['speciality']); ?></p>
                    <p><strong>Experience:</strong> <?php echo htmlspecialchars($barber['experience']); ?> years</p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($barber['email']); ?></p>
                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($barber['contact']); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($barber['address']); ?></p>

                    <!-- Updated "Book Appointment" Button -->
                    <button onclick="openServiceModal(<?php echo $barber['id']; ?>)">Book Appointment</button>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No barbers found matching your search criteria.</p>
        <?php endif; ?>
    </div>

    <!-- Modal for Service Selection -->
    <div id="serviceModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Select Services</h2>
            <div id="serviceList"></div>
            <p><strong>Total Price: रू<span id="totalPrice">0</span></strong></p>
            <button id="confirmBooking">Confirm Booking</button>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <p>&copy; 2025 Barber's Solution. All Rights Reserved.</p>
    </footer>

    <script>
        // JavaScript for Modal and Service Selection
        const modal = document.getElementById("serviceModal");
        const closeBtn = document.querySelector(".close");
        const serviceList = document.getElementById("serviceList");
        const totalPrice = document.getElementById("totalPrice");
        const confirmBookingBtn = document.getElementById("confirmBooking");

        let selectedBarberId = null;
        let selectedServices = [];

        // Function to open the modal and fetch services
        function openServiceModal(barberId) {
            selectedBarberId = barberId;
            fetch(`get_services.php?barber_id=${barberId}`)
                .then(response => response.json())
                .then(data => {
                    serviceList.innerHTML = ""; // Clear previous content
                    data.forEach(service => {
                        const serviceItem = document.createElement("label");
                        serviceItem.innerHTML = `
                            <input type="checkbox" name="service" value="${service.id}" data-price="${service.price}">
                            ${service.service_name} - रू${service.price}
                        `;
                        serviceList.appendChild(serviceItem);
                    });
                    modal.style.display = "block";
                });
        }

        // Function to calculate total price
        function calculateTotalPrice() {
            const selectedCheckboxes = document.querySelectorAll("input[name='service']:checked");
            let total = 0;
            selectedCheckboxes.forEach(checkbox => {
                total += parseFloat(checkbox.dataset.price);
            });
            totalPrice.textContent = total.toFixed(2);
        }

        // Event listeners
        closeBtn.addEventListener("click", () => {
            modal.style.display = "none";
        });

        window.addEventListener("click", (event) => {
            if (event.target === modal) {
                modal.style.display = "none";
            }
        });

        serviceList.addEventListener("change", calculateTotalPrice);

        confirmBookingBtn.addEventListener("click", () => {
            const selectedCheckboxes = document.querySelectorAll("input[name='service']:checked");
            selectedServices = Array.from(selectedCheckboxes).map(checkbox => checkbox.value);
            if (selectedServices.length > 0) {
                window.location.href = `appointments.php?barber_id=${selectedBarberId}&services=${selectedServices.join(",")}`;
            } else {
                alert("Please select at least one service.");
            }
        });

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