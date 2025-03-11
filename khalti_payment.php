<?php

// Configuration (Replace with your actual values)
$khalti_public_key = '29773d3c537f424f999dd6fb542e1edc';
$khalti_secret_key = '4b7bec03eefb4d56b01e551e0d745b9f';
$return_url = 'http://localhost/BarberSystem/user_dashboard.php'; // Where to redirect after payment
$callback_url = 'http://localhost/BarberSystem/khalti_callback.php'; // Khalti's notification URL

// Assuming you have the appointment details from your database$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$price = isset($_GET['price']) ? floatval($_GET['price']) : 0;

if ($appointment_id <= 0 || $price <= 0) {
    echo "Invalid appointment ID or price.";
    exit;
}

// Convert price to paisa (Khalti requires amount in paisa)
$amount = $price * 100; // Amount in paisa (150.00 NPR = 15000 paisa)
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