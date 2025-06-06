<!-- edit_movie.php -->
<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin.php');
    exit();
}

// Database connection
$conn = new mysqli('localhost:3307', 'root', '', 'moviedb');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch movie details
$movie_id = $_GET['id'];
$stmt = $conn->prepare("SELECT * FROM tblmovies WHERE MovieID = ?");
$stmt->bind_param("i", $movie_id);
$stmt->execute();
$result = $stmt->get_result();
$movie = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RELAX MOVIE - Edit Movie</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }

        body {
            background: #0f0f0f;
            background-attachment: fixed;
            color: #fff;
            overflow-x: hidden;
            position: relative;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            box-shadow: 
                inset 0 0 100px rgba(0, 255, 255, 0.2),
                inset 0 0 100px rgba(255, 0, 255, 0.2);
            animation: neonGlow 8s ease-in-out infinite;
            pointer-events: none;
            z-index: -1;
        }

        @keyframes neonGlow {
            0%, 100% { box-shadow: inset 0 0 100px rgba(0, 255, 255, 0.2), inset 0 0 100px rgba(255, 0, 255, 0.2); }
            50% { box-shadow: inset 0 0 150px rgba(0, 255, 255, 0.3), inset 0 0 150px rgba(255, 0, 255, 0.3); }
        }

        .form-container {
            background: rgba(255, 255, 255, 0.1);
            padding: 40px;
            border-radius: 10px;
            text-align: center;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.5);
        }

        .form-container h2 {
            font-size: 28px;
            margin-bottom: 20px;
            color: #e50914;
            text-shadow: 1px 1px 4px rgba(0, 0, 0, 0.7);
        }

        .form-container label {
            display: block;
            font-size: 16px;
            margin-bottom: 8px;
            color: #f0f0f0;
            text-align: left;
        }

        .form-container input,
        .form-container textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border: none;
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            font-size: 16px;
        }

        .form-container textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            text-transform: uppercase;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-update {
            background: #e50914;
            color: #fff;
            width: 100%;
        }

        .btn-update:hover {
            background: #f40612;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(229, 9, 20, 0.5);
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #1f8dd6;
            text-decoration: none;
            font-size: 16px;
        }

        .back-link:hover {
            color: #2a9df4;
        }

        @media (max-width: 600px) {
            .form-container {
                padding: 20px;
            }

            .form-container h2 {
                font-size: 24px;
            }

            .btn {
                padding: 10px 20px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Edit Movie</h2>
        <form method="POST" action="admin_portal.php">
            <input type="hidden" name="movie_id" value="<?php echo $movie['MovieID']; ?>">
            <label>Title:</label><input type="text" name="title" value="<?php echo $movie['Title']; ?>" required>
            <label>Release Year:</label><input type="number" name="release_year" value="<?php echo $movie['ReleaseYear']; ?>" required>
            <label>Genre:</label><input type="text" name="genre" value="<?php echo $movie['Genre']; ?>" required>
            <label>Description:</label><textarea name="description" required><?php echo $movie['Description']; ?></textarea>
            <label>Image URL:</label><input type="text" name="image_url" value="<?php echo $movie['ImageURL']; ?>" required>
            <label>IMDb Rating:</label><input type="number" step="0.1" name="imdb_rating" value="<?php echo $movie['IMDbRating']; ?>" required>
            <label>Full Movie URL:</label><input type="text" name="full_movie_url" value="<?php echo $movie['FullMovieURL']; ?>" required>
            <button type="submit" name="edit_movie" class="btn btn-update">Update Movie</button>
        </form>
        <a href="admin_portal.php" class="back-link">Back to Admin Portal</a>
    </div>
</body>
</html>
<?php $conn->close(); ?>