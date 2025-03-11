<?php
include 'db_connection.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: user_register.html");
    exit();
}

$user_name = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Barber';

// Fetch barber's appointments
$sql = "SELECT u.name AS client_name, a.appointment_time, a.appointment_date, a.status, a.id AS appointment_id 
        FROM appointments a
        JOIN user_tbl u ON a.user_id = u.id
        WHERE a.barber_id = ? 
        ORDER BY a.appointment_date ASC, a.appointment_time ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$appointments = $result->fetch_all(MYSQLI_ASSOC);

// Handle appointment updates
if (isset($_POST['update_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    $new_time = $_POST['new_time'];
    $new_status = $_POST['new_status'];

    $update_sql = "UPDATE appointments SET appointment_time = ?, status = ? WHERE id = ?";
    $stmt_update = $conn->prepare($update_sql);
    $stmt_update->bind_param('ssi', $new_time, $new_status, $appointment_id);
    $stmt_update->execute();

    header("Location: manageappointments.php"); // Refresh the page to show updated time
    exit();
}

// Handle appointment deletion
if (isset($_POST['delete_appointment'])) {
    $appointment_id = $_POST['appointment_id'];

    $delete_sql = "DELETE FROM appointments WHERE id = ?";
    $stmt_delete = $conn->prepare($delete_sql);
    $stmt_delete->bind_param('i', $appointment_id);
    $stmt_delete->execute();

    header("Location: manageappointments.php"); // Refresh the page to reflect deletion
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments</title>
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        body { font-family: 'Roboto', sans-serif; margin: 0; display: flex; }
        .sidebar { width: 250px; background: #2c3e50; color: white; padding: 20px; height: 100vh; }
        .sidebar a { color: white; text-decoration: none; display: block; padding: 10px; transition: 0.3s; }
        .sidebar a:hover { background: #34495e; color: #f1c40f; }
        .logo img { max-width: 150px; height: auto; display: block; margin-bottom: 10px; }
        .main-content { flex-grow: 1; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2c3e50; color: white; }
        tr:hover { background: #f5f5f5; }
        .status-scheduled { color: #3498db; }
        .status-completed { color: #2ecc71; }
        .status-cancelled { color: #e74c3c; }
        .actions { display: flex; gap: 10px; }
        .actions button { padding: 5px 10px; border: none; border-radius: 5px; cursor: pointer; }
        .actions button.update { background: #3498db; color: white; }
        .actions button.delete { background: #e74c3c; color: white; }
        .modal { display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fff; margin: 10% auto; padding: 20px; width: 50%; border-radius: 10px; }
        .close { float: right; font-size: 24px; cursor: pointer; }
        .filter-bar { margin-bottom: 20px; display: flex; gap: 10px; }
        .filter-bar input, .filter-bar select { padding: 8px; border: 1px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo">
            <img src="logo.png" alt="Project Logo">
            <h3><?php echo $user_name; ?></h3>
        </div>
        <nav>
            <a href="#home"><i class="fas fa-home"></i> Home</a>
            <a href="barber_dashboard.php"><i class="fas fa-calendar-alt"></i> Dashboard</a>
            <a href="manageappointments.php"><i class="fas fa-calendar-check"></i> Manage Appointments</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <section class="dashboard-section">
            <h2>Manage Your Appointments</h2>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <input type="text" id="searchClient" placeholder="Search by client name...">
                <input type="date" id="filterDate">
                <select id="filterStatus">
                    <option value="">All Statuses</option>
                    <option value="Scheduled">Scheduled</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </div>

            <!-- Appointments Table -->
            <table>
                <thead>
                    <tr>
                        <th>Client Name</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($appointment['client_name']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['appointment_date']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['appointment_time']); ?></td>
                            <td class="status-<?php echo strtolower($appointment['status']); ?>">
                                <?php echo htmlspecialchars($appointment['status']); ?>
                            </td>
                            <td class="actions">
                                <?php if ($appointment['status'] != 'Completed' && $appointment['status'] != 'Cancelled'): ?>
                                    <button class="open-modal update" data-id="<?php echo $appointment['appointment_id']; ?>">
                                        <i class="fas fa-edit"></i> Update
                                    </button>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                    <button type="submit" name="delete_appointment" class="delete">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </div>

    <!-- Modal for updating appointment -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Update Appointment</h2>
            <form method="POST">
                <input type="hidden" name="appointment_id" id="appointment_id">
                <label for="new_time">New Time:</label>
                <input type="time" id="new_time" name="new_time" required>
                <label for="new_status">Status:</label>
                <select id="new_status" name="new_status">
                    <option value="Scheduled">Scheduled</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
                <button type="submit" name="update_appointment">Update</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const modal = document.getElementById("updateModal");
            const closeBtn = document.querySelector(".close");
            const openBtns = document.querySelectorAll(".open-modal");

            // Open modal for updating appointment
            openBtns.forEach(button => {
                button.addEventListener("click", function() {
                    document.getElementById("appointment_id").value = this.getAttribute("data-id");
                    modal.style.display = "block";
                });
            });

            // Close modal
            closeBtn.addEventListener("click", function() {
                modal.style.display = "none";
            });

            window.addEventListener("click", function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            });

            // Filter appointments
            const searchClient = document.getElementById("searchClient");
            const filterDate = document.getElementById("filterDate");
            const filterStatus = document.getElementById("filterStatus");
            const rows = document.querySelectorAll("tbody tr");

            function filterAppointments() {
                const clientName = searchClient.value.toLowerCase();
                const date = filterDate.value;
                const status = filterStatus.value.toLowerCase();

                rows.forEach(row => {
                    const rowClient = row.cells[0].textContent.toLowerCase();
                    const rowDate = row.cells[1].textContent;
                    const rowStatus = row.cells[3].textContent.toLowerCase();

                    const matchesClient = rowClient.includes(clientName);
                    const matchesDate = date ? rowDate === date : true;
                    const matchesStatus = status ? rowStatus === status : true;

                    if (matchesClient && matchesDate && matchesStatus) {
                        row.style.display = "";
                    } else {
                        row.style.display = "none";
                    }
                });
            }

            searchClient.addEventListener("input", filterAppointments);
            filterDate.addEventListener("change", filterAppointments);
            filterStatus.addEventListener("change", filterAppointments);
        });
    </script>
</body>
</html>