<?php
$servername = "localhost"; 
$username = "root";        
$password = "";            
$dbname = "barber_solution";

$conn = new mysqli($servername, $username, $password, $dbname);


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
 ?>