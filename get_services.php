<?php
// Include the database connection file
include 'db_connection.php';

// Check if barber_id is provided in the query string
if (!isset($_GET['barber_id'])) {
    // If barber_id is missing, return an empty JSON response
    echo json_encode([]);
    exit();
}

// Get the barber_id from the query string
$barber_id = intval($_GET['barber_id']);

// Fetch services offered by the selected barber
$sql = "SELECT id, service_name, price FROM services WHERE barber_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $barber_id);
$stmt->execute();
$result = $stmt->get_result();

// Store the services in an array
$services = [];
while ($row = $result->fetch_assoc()) {
    $services[] = $row;
}

// Return the services as JSON
header('Content-Type: application/json');
echo json_encode($services);

// Close the database connection
$stmt->close();
$conn->close();
?>