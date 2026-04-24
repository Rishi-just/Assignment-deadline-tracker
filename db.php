<?php
session_start();

$server = "localhost";
$username = "root";
$password = "";
$database = "assignment_beta";
$port = 3307;

$conn = mysqli_connect($server, $username, $password, $database, $port);


?>
