
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$host = 'localhost:3307';
$username = 'root';
$password = '';
$dbname = 'moviedb';
$port = 3307;

$conn = new mysqli($host, $username, $password, $dbname, $port);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$movieID = isset($_POST['movieID']) ? intval($_POST['movieID']) : 0;
$userID = $_SESSION['user_id'];

if ($movieID > 0) {
    // Check current watched status
    $check_sql = "SELECT WatchedStatus FROM tblwatchliststatus WHERE MovieID = ? AND UserID = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $movieID, $userID);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $isWatched = $result->num_rows > 0 && $result->fetch_assoc()['WatchedStatus'] === 'Watched';
    $check_stmt->close();

    // Toggle status
    $newStatus = $isWatched ? 'Not Watched' : 'Watched';

    // Update tblwatchliststatus
    $sql = "INSERT INTO tblwatchliststatus (MovieID, UserID, WatchedStatus) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE WatchedStatus = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $movieID, $userID, $newStatus, $newStatus);
    $stmt->execute();
    $stmt->close();

    // Check if the movie exists in tblmovielistdisplay
    $check_display_sql = "SELECT COUNT(*) FROM tblmovielistdisplay WHERE MovieID = ? AND UserID = ?";
    $check_display_stmt = $conn->prepare($check_display_sql);
    $check_display_stmt->bind_param("ii", $movieID, $userID);
    $check_display_stmt->execute();
    $check_result = $check_display_stmt->get_result();
    $row_exists = $check_result->fetch_row()[0] > 0;
    $check_display_stmt->close();

    if ($row_exists) {
        // Update tblmovielistdisplay
        $update_display_sql = "UPDATE tblmovielistdisplay 
                              SET WatchedStatus = ? 
                              WHERE MovieID = ? AND UserID = ?";
        $stmt_display = $conn->prepare($update_display_sql);
        $stmt_display->bind_param("sii", $newStatus, $movieID, $userID);
        $stmt_display->execute();
        $stmt_display->close();
    } else {
        // Fallback: Insert if the row doesn't exist
        $insert_display_sql = "INSERT INTO tblmovielistdisplay 
                              (MovieID, UserID, WatchedStatus, Title, Genre, ReleaseYear, ImageURL, Description, IMDbRating, FullMovieURL)
                              SELECT ?, ?, ?, Title, Genre, ReleaseYear, ImageURL, Description, IMDbRating, FullMovieURL
                              FROM tblmovies WHERE MovieID = ?";
        $stmt_insert = $conn->prepare($insert_display_sql);
        $stmt_insert->bind_param("iisi", $movieID, $userID, $newStatus, $movieID);
        $stmt_insert->execute();
        $stmt_insert->close();
    }
}

$conn->close();
header("Location: watch.php?movieid=$movieID");
exit;
?>