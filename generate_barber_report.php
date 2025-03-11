<?php
// Disable error reporting temporarily for debugging
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering at the very top of your script
ob_start();
session_start();
include 'db_connection.php';
require('tcpdf/tcpdf.php'); // Using TCPDF for PDF generation

// Redirect if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: Barbers_Solution.html#login"); // Redirect to login page
    exit();
}

// Check if the logged-in user is a barber (Admin)
if ($_SESSION['user_role'] !== 'Admin') {
    echo "<script>alert('Access denied! Only barbers can view this report.'); window.location.href='user_dashboard.php';</script>";
    exit();
}

$barber_id = $_SESSION['user_id']; // Use the logged-in barber's ID from session
$start_date = date('Y-m-d', strtotime('-1 month')); // Last 1 month only
$end_date = date('Y-m-d');

// Updated SQL query to get all activities for the logged-in barber
   $sql = "SELECT 
            u.name AS username, 
            a.appointment_date, 
            a.appointment_time, 
            s.service_name, 
            s.price 
        FROM appointment_services aps
        JOIN appointments a ON aps.appointment_id = a.id
        JOIN services s ON aps.service_id = s.id
        JOIN user_tbl u ON a.user_id = u.id
        WHERE s.barber_id = ? 
        AND a.appointment_date BETWEEN ? AND ? 
        ORDER BY a.appointment_date DESC, u.name";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $barber_id, $start_date, $end_date); // Bind barber_id
$stmt->execute();
$result = $stmt->get_result();
$activities = $result->fetch_all(MYSQLI_ASSOC);

// PDF Download Logic
if (isset($_GET['download']) && $_GET['download'] == 'pdf') {
    ob_end_clean(); // Clear any previous output buffer

    // Set headers for PDF download with the correct file name
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Barber_activity_report.pdf"');

    // Create a new TCPDF instance
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->AddPage();

    // Set font to one that supports Unicode characters
    $pdf->SetFont('dejavusans', '', 12);  // Use DejaVu Sans

    // Title
    $pdf->Cell(0, 10, 'Barber Activity Report', 0, 1, 'C');
    $pdf->Ln(10);

    // Table Header
    $pdf->SetFont('dejavusans', 'B', 12);  // Make header bold
    $pdf->Cell(15, 10, 'S.N.', 1, 0, 'C');
    $pdf->Cell(40, 10, 'Username', 1, 0, 'C');
    $pdf->Cell(50, 10, 'Appointment Date', 1, 0, 'C');
    $pdf->Cell(50, 10, 'Appointment Time', 1, 0, 'C');
    $pdf->Cell(50, 10, 'Service Name', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Price', 1, 1, 'C');

    // Table Data
    $pdf->SetFont('dejavusans', '', 12);  // Use regular font for data rows
    $sn = 1;
    foreach ($activities as $activity) {
        $pdf->Cell(15, 10, $sn++, 1, 0, 'C');
        $pdf->Cell(40, 10, utf8_decode($activity['username']), 1);
        $pdf->Cell(50, 10, $activity['appointment_date'], 1);
        $pdf->Cell(50, 10, $activity['appointment_time'], 1);
        $pdf->Cell(50, 10, utf8_decode($activity['service_name']), 1);
        $pdf->Cell(30, 10, number_format($activity['price'], 2), 1);
        $pdf->Ln();
    }

    // Output PDF
    $pdf->Output('Barber_activity_report.pdf', 'D');
    exit();
}

// CSV Download Logic
if (isset($_GET['download']) && $_GET['download'] == 'csv') {
    ob_end_clean(); // Clear any previous output buffer

    // Set headers for CSV download with the correct file name
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="Barber_activity_report.csv"');

    // Open output stream
    $output = fopen('php://output', 'w');

    // CSV Header
    fputcsv($output, [
        'S.N.', 'Username', 'Appointment Date', 'Appointment Time', 
        'Service Name', 'Price'
    ]);

    // CSV Data
    $sn = 1;
    foreach ($activities as $activity) {
        fputcsv($output, [
            $sn++,
            $activity['username'],
            $activity['appointment_date'],
            $activity['appointment_time'],
            $activity['service_name'],
            number_format($activity['price'], 2)
        ]);
    }

    fclose($output);
    exit();
}

// Show Data in HTML Table
echo "<a href='generate_barber_report.php?download=pdf'><i class='fas fa-download'></i> Download PDF</a> | ";
echo "<a href='generate_barber_report.php?download=csv'><i class='fas fa-download'></i> Download CSV</a> | ";
echo "<a href='Barber_dashboard.php'><i class='fas fa-arrow-left'></i> Back</a><br><br>";
// Add CSS to control table width
echo "<style>
    table {
        width: 100%;
        table-layout: auto;
        border-collapse: collapse;
    }
    th, td {
        border: 1px solid #ddd;
        text-align: left;
        padding: 8px;
    }
    th {
        background-color: #f2f2f2;
    }
    td {
        word-wrap: break-word;
        overflow: hidden;
    }
</style>";

echo "<table>";
echo "<tr><th>S.N.</th><th>Username</th><th>Appointment Date</th><th>Appointment Time</th><th>Service Name</th><th>Price</th></tr>";
$sn = 1;
foreach ($activities as $activity) {
    echo "<tr><td>" . $sn++ . "</td><td>" . htmlspecialchars($activity['username']) . "</td><td>" . htmlspecialchars($activity['appointment_date']) . "</td><td>" . htmlspecialchars($activity['appointment_time']) . "</td><td>" . htmlspecialchars($activity['service_name']) . "</td><td>" . number_format($activity['price'], 2) . "</td></tr>";
}
echo "</table>";

// End output buffering
ob_end_flush();
?>