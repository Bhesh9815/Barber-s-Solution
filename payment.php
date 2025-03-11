<?php
session_start();
include 'db_connection.php';

// Get the appointment ID from the URL
$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($appointment_id <= 0) {
    echo "Invalid appointment ID.";
    exit;
}

// Debugging: Print the appointment ID
echo "Appointment ID: " . $appointment_id . "<br>";

// Khalti credentials
$khalti_public_key = '29773d3c537f424f999dd6fb542e1edc';
$khalti_secret_key = '4b7bec03eefb4d56b01e551e0d745b9f';
$return_url = 'http://localhost/BarberSystem/receipt.php?appointment_id=' . $appointment_id;
$callback_url = 'http://localhost/BarberSystem/khalti_callback.php'; // Khalti callback URL

// Debugging: Print the return URL
echo "Return URL: " . $return_url . "<br>";

// Fetch appointment details from the database
$sql = "SELECT a.id, a.user_id, a.barber_id, a.appointment_date, a.appointment_time, a.status, b.price
        FROM appointments a
        JOIN appointment_services as ap_s ON a.id = ap_s.appointment_id
        JOIN services b ON ap_s.service_id = b.id
        WHERE a.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$result = $stmt->get_result();

// Fetch all services and calculate the total price
$total_price = 0;
while ($row = $result->fetch_assoc()) {
    $total_price += $row['price'];
}
$stmt->close();

// If no services are found, stop the process
if ($total_price <= 0) {
    echo "No services found for this appointment.";
    exit;
}

// Convert the total price to paisa (Khalti requires amount in paisa)
$amount = $total_price * 100; // Amount in paisa (150.00 NPR = 15000 paisa)
$product_name = 'Barber Appointment';

// Initiate Khalti Transaction
$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://a.khalti.com/api/v2/epayment/initiate/',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode([
        'return_url' => $return_url,
        'website_url' => 'http://localhost/BarberSystem/user_dashboard.php',
        'amount' => $amount,
        'purchase_order_id' => $appointment_id,
        'purchase_order_name' => $product_name,
        'product_identity' => $appointment_id,
        'product_name' => $product_name,
        'callback_url' => $callback_url,
    ]),
    CURLOPT_HTTPHEADER => array(
        'Authorization: Key ' . $khalti_secret_key,
        'Content-Type: application/json'
    ),
));

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    echo "cURL Error #:" . $err;
} else {
    $response_data = json_decode($response, true);
    if (isset($response_data['payment_url'])) {
        // Redirect the user to Khalti's payment URL
        header('Location: ' . $response_data['payment_url']);
        exit;
    } else {
        // Handle error from Khalti API
        echo "Khalti API Error: " . json_encode($response_data);
    }
}
?>