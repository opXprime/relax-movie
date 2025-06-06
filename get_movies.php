<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "moviedb";
$port = 3307;

// Connect to database
$conn = new mysqli($host, $user, $password, $database, $port);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

// Fetch movie data
$sql = "SELECT title, genre, year, watched, poster FROM movies"; // adjust table/column names if different
$result = $conn->query($sql);

$movies = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $movies[] = $row;
    }
}

$conn->close();

header('Content-Type: application/json');
echo json_encode($movies);
?>
