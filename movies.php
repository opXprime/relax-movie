<?php
header('Content-Type: application/json');

// DB Connection
$host = 'localhost';
$user = 'root';
$password = ''; // XAMPP default password is empty
$database = 'moviedb';
$port = 3307;

$conn = new mysqli($host, $user, $password, $database, $port);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Fetch movies
$sql = "SELECT title, release_year, genre, description, poster_url FROM tblmovies";
$result = $conn->query($sql);

$movies = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $movies[] = $row;
    }
}

echo json_encode($movies);
$conn->close();
?>

