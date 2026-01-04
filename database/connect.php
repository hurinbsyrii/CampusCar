<?php
$servername = "localhost";
$username = "root";
$password = ""; // no space if no password
$port = 3301;

// Database names
$campuscar = "campuscar";
$utemcampuscar = "utemcampuscar";

// Connect to campuscar
$connCampuscar = new mysqli($servername, $username, $password, $campuscar, $port);
if ($connCampuscar->connect_error) {
    die("Connection to campuscar failed: " . $connCampuscar->connect_error);
}

// Connect to utemcampuscar
$connUtem = new mysqli($servername, $username, $password, $utemcampuscar, $port);
if ($connUtem->connect_error) {
    die("Connection to utemcampuscar failed: " . $connUtem->connect_error);
}

// Default connection
$conn = $connCampuscar;

echo "Connected successfully to both databases";
