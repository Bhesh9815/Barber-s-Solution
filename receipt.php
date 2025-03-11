<?php
session_start();
include 'db_connection.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get the appointment ID from the URL
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
if ($appointment_id <= 0) {
    echo "Invalid appointment ID.";
    exit;
}

// Fetch appointment and payment details
$sql = "SELECT a.*, p.transaction_id, p.amount, p.payment_date 
        FROM appointments a
        LEFT JOIN payments p ON a.id = p.appointment_id
        WHERE a.id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error preparing query: " . $conn->error);
}
$stmt->bind_param("i", $appointment_id);
if (!$stmt->execute()) {
    die("Error executing query: " . $stmt->error);
}
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();
$stmt->close();

if (!$appointment) {
    echo "Appointment not found.";
    exit;
}

// Check if payment details exist
if (empty($appointment['transaction_id']) || empty($appointment['amount']) || empty($appointment['payment_date'])) {
    echo "Payment details not found for this appointment.";
    exit;
}

// Generate receipt content
$receipt_content = "
    <h1>Payment Receipt</h1>
    <p><strong>Appointment ID:</strong> {$appointment['id']}</p>
    <p><strong>Transaction ID:</strong> {$appointment['transaction_id']}</p>
    <p><strong>Amount Paid:</strong> रू{$appointment['amount']}</p>
    <p><strong>Payment Date:</strong> {$appointment['payment_date']}</p>
    <p><strong>Appointment Date:</strong> {$appointment['appointment_date']}</p>
    <p><strong>Appointment Time:</strong> {$appointment['appointment_time']}</p>
";

// Include TCPDF library
require('tcpdf/tcpdf.php');

// Create a new PDF instance
$pdf = new TCPDF();

// Add a page
$pdf->AddPage();

// Write receipt content to the PDF
$pdf->writeHTML($receipt_content);

// Set headers to force download the PDF
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="receipt_' . $appointment_id . '.pdf"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Expires: 0');

// Output the PDF to the browser
$pdf->Output('receipt_' . $appointment_id . '.pdf', 'D');
exit;
?>