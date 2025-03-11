<?php
session_start();
include 'db_connection.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Khalti credentials
$khalti_secret_key = '4b7bec03eefb4d56b01e551e0d745b9f'; // Replace with your actual Khalti secret key

// Handle Khalti Callback (Payment Success/Failure)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Read the raw JSON payload from Khalti
    $payload = file_get_contents('php://input');
    $data = json_decode($payload, true);

    // Log the payload for debugging
    file_put_contents('khalti_callback.log', "Payload: " . $payload . "\n", FILE_APPEND);

    // Validate the payload
    if (!$data || !isset($data['idx']) || !isset($data['amount']) || !isset($data['purchase_order_id'])) {
        die("Invalid or incomplete payload.");
    }

    // Extract data from Khalti's payload
    $transaction_id = $data['idx']; // Unique transaction ID from Khalti
    $amount_in_paisa = $data['amount']; // Amount in paisa
    $amount_in_npr = (float)($amount_in_paisa / 100); // Convert paisa to NPR
    $appointment_id = $data['purchase_order_id']; // Get appointment_id from Khalti's response

    // Debugging: Print received data
    echo "Transaction ID: $transaction_id<br>";
    echo "Amount in Paisa: $amount_in_paisa<br>";
    echo "Amount in NPR: $amount_in_npr<br>";
    echo "Appointment ID: $appointment_id<br>";

    // Verify the payment with Khalti's API
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://a.khalti.com/api/v2/epayment/lookup/',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode([
            'idx' => $transaction_id,
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
        // Handle cURL error
        echo "cURL Error #:" . $err;
    } else {
        // Decode Khalti's response
        $response_data = json_decode($response, true);

        // Check if payment is completed
        if (isset($response_data['state']['name']) && $response_data['state']['name'] == 'Completed') {
            // Payment is successful

            // Save payment details in the database
            $sql = "INSERT INTO payments (appointment_id, transaction_id, amount, payment_status, payment_date) 
                    VALUES (?, ?, ?, 'Completed', NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                die("Error preparing query: " . $conn->error);
            }
            $stmt->bind_param("isd", $appointment_id, $transaction_id, $amount_in_npr);
            if (!$stmt->execute()) {
                die("Error executing query: " . $stmt->error);
            }
            $stmt->close();

            // Update appointment status to 'Confirmed'
            $sql = "UPDATE appointments SET status = 'Confirmed' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                die("Error preparing query: " . $conn->error);
            }
            $stmt->bind_param("i", $appointment_id);
            if (!$stmt->execute()) {
                die("Error updating appointment status: " . $stmt->error);
            }
            $stmt->close();

            // Redirect to receipt page
            header("Location: receipt.php?appointment_id=$appointment_id");
            exit;
        } else {
            // Payment failed or is not completed
            echo "Payment failed. Please try again.";
        }
    }
} else {
    // Invalid request method
    echo "Invalid request method.";
}
?>