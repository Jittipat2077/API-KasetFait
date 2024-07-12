<?php
// $servername = "localhost";
// $username = "root";
// $password = "";
// $dbname = "project";

// // Create connection
// $conn = new mysqli($servername, $username, $password, $dbname);

// // Check connection
// if ($conn->connect_error) {
//     die("Connection failed: " . $conn->connect_error);
// }
// echo "Connected successfully";
//  <---------sever------------>
$servername = "jittipat.bowlab.net";
$username = "u583789277_jittipat";
$password = "KasetFair2567";
$dbname = "u583789277_jittipat";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

