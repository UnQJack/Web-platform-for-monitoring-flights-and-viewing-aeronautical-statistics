<?php
$servername = "localhost:8889";
$username = "root";  
$password = "root";      
$database = "licenta";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Conexiune esuata: " . $conn->connect_error);
}

?>

