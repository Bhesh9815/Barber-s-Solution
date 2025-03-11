<?php
// Start output buffering
ob_start();
session_start();
include 'db_connection.php';
require('tcpdf/tcpdf.php'); // Using TCPDF for PDF generation

// Redirect if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: user_register.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$start_date = date('Y-m-d', strtotime('-1 month')); // Last 1 month only
$end_date = date('Y-m-d');

// Updated SQL query to avoid duplication
$sql = "SELECT 
            u.name AS username, 
            a.appointment_date, 
            a.appointment_time, 
            b.name AS barber_name, 
            s.service_name, 
            s.price 
        FROM appointment_services aps
        JOIN appointments a ON aps.appointment_id = a.id
        JOIN services s ON aps.service_id = s.id
        JOIN barber_tbl b ON s.barber_id = b.id
        JOIN user_tbl u ON a.user_id = u.id
        WHERE a.user_id = ? 
        AND a.appointment_date BETWEEN ? AND ? 
        ORDER BY a.appointment_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $user_id, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$activities = $result->fetch_all(MYSQLI_ASSOC);

// PDF Download Logic
if (isset($_GET['download'])) {
    ob_end_clean(); // Clear output buffer

    // Create a new TCPDF instance
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'User Activity Report', 0, 1, 'C');
    $pdf->Ln(10);

    // Table Header
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->Cell(15, 10, 'S.N.', 1, 0, 'C');
    $pdf->Cell(40, 10, 'Username', 1, 0, 'C');
    $pdf->Cell(40, 10, 'Appointment Date', 1, 0, 'C');
    $pdf->Cell(40, 10, 'Appointment Time', 1, 0, 'C');
    $pdf->Cell(50, 10, 'Barber Name', 1, 0, 'C');
    $pdf->Cell(50, 10, 'Service Name', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Price (रू)', 1, 1, 'C');

    // Table Data
    $pdf->SetFont('Helvetica', '', 12);
    $sn = 1;
    foreach ($activities as $activity) {
        $pdf->Cell(15, 10, $sn++, 1, 0, 'C');
        $pdf->Cell(40, 10, utf8_decode($activity['username']), 1);
        $pdf->Cell(40, 10, $activity['appointment_date'], 1);
        $pdf->Cell(40, 10, $activity['appointment_time'], 1);
        $pdf->Cell(50, 10, utf8_decode($activity['barber_name']), 1);
        $pdf->Cell(50, 10, utf8_decode($activity['service_name']), 1);
        $pdf->Cell(30, 10, 'रू' . number_format($activity['price'], 2), 1);
        $pdf->Ln();
    }

    // Output PDF
    $pdf->Output('user_activity_report.pdf', 'D');
    exit();
} else {
    // Show Data in HTML Table
    echo "<a href='generate_user_report.php?download=true'><i class='fas fa-download'></i> Download PDF</a> | ";
    echo "<a href='user_dashboard.php'><i class='fas fa-arrow-left'></i> Back</a><br><br>";
    echo "<table border='1' width='100%'>";
    echo "<tr><th>S.N.</th><th>Username</th><th>Appointment Date</th><th>Appointment Time</th><th>Barber Name</th><th>Service Name</th><th>Price (रू)</th></tr>";
    $sn = 1;
    foreach ($activities as $activity) {
        echo "<tr>
                <td>" . $sn++ . "</td>
                <td>" . htmlspecialchars($activity['username']) . "</td>
                <td>" . htmlspecialchars($activity['appointment_date']) . "</td>
                <td>" . htmlspecialchars($activity['appointment_time']) . "</td>
                <td>" . htmlspecialchars($activity['barber_name']) . "</td>
                <td>" . htmlspecialchars($activity['service_name']) . "</td>
                <td>रू" . number_format($activity['price'], 2) . "</td>
              </tr>";
    }
    echo "</table>";
}

// End output buffering
ob_end_flush();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Report</title>
</head>
<body>
    <i class="fa-solid fa-arrow-left"></i>
</body>
</html>