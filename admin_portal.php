<?php
// Start session
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

// Initialize message variable
$message = '';
$message_type = ''; // success or error

// Handle Add Movie
if (isset($_POST['add_movie'])) {
    $title = trim($_POST['title']);
    $release_year = (int)$_POST['release_year'];
    $genre = trim($_POST['genre']);
    $description = trim($_POST['description']);
    $image_url = trim($_POST['image_url']);
    $imdb_rating = (float)$_POST['imdb_rating'];
    $full_movie_url = trim($_POST['full_movie_url']);

    // Get the next MovieID
    $result = $conn->query("SELECT MAX(MovieID) as max_id FROM tblmovies");
    $row = $result->fetch_assoc();
    $movie_id = ($row['max_id'] ?? 0) + 1;

    $stmt = $conn->prepare("INSERT INTO tblmovies (MovieID, Title, ReleaseYear, Genre, Description, ImageURL, IMDbRating, FullMovieURL) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isisssds", $movie_id, $title, $release_year, $genre, $description, $image_url, $imdb_rating, $full_movie_url);
    
    if ($stmt->execute()) {
        $message = "Movie added successfully!";
        $message_type = 'success';
        // Clear form by redirecting to the same page
        header('Location: admin_portal.php?message=' . urlencode($message) . '&type=success');
        exit();
    } else {
        $message = "Error adding movie: " . $conn->error;
        $message_type = 'error';
    }
    $stmt->close();
}

// Handle Edit Movie
if (isset($_POST['edit_movie'])) {
    $movie_id = (int)$_POST['movie_id'];
    $title = trim($_POST['title']);
    $release_year = (int)$_POST['release_year'];
    $genre = trim($_POST['genre']);
    $description = trim($_POST['description']);
    $image_url = trim($_POST['image_url']);
    $imdb_rating = (float)$_POST['imdb_rating'];
    $full_movie_url = trim($_POST['full_movie_url']);

    $stmt = $conn->prepare("UPDATE tblmovies SET Title = ?, ReleaseYear = ?, Genre = ?, Description = ?, ImageURL = ?, IMDbRating = ?, FullMovieURL = ? WHERE MovieID = ?");
    $stmt->bind_param("sisssdsi", $title, $release_year, $genre, $description, $image_url, $imdb_rating, $full_movie_url, $movie_id);
    
    if ($stmt->execute()) {
        $message = "Movie updated successfully!";
        $message_type = 'success';
        // Redirect to clear edit form
        header('Location: admin_portal.php?message=' . urlencode($message) . '&type=success');
        exit();
    } else {
        $message = "Error updating movie: " . $conn->error;
        $message_type = 'error';
    }
    $stmt->close();
}

// Handle Delete Movie
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    
    // Delete related records from tblmovielistdisplay
    $stmt = $conn->prepare("DELETE FROM tblmovielistdisplay WHERE MovieID = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    
    // Delete related records from tblwatchliststatus
    $stmt = $conn->prepare("DELETE FROM tblwatchliststatus WHERE MovieID = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    
    // Delete from tblmovies
    $stmt = $conn->prepare("DELETE FROM tblmovies WHERE MovieID = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message = "Movie deleted successfully!";
        $message_type = 'success';
        header('Location: admin_portal.php?message=' . urlencode($message) . '&type=success');
        exit();
    } else {
        $message = "Error deleting movie: " . $conn->error;
        $message_type = 'error';
    }
    $stmt->close();
}

// Fetch movie to edit
$edit_movie = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $stmt = $conn->prepare("SELECT * FROM tblmovies WHERE MovieID = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_movie = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Handle Search
$search_query = '';
if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
    $search_query = $conn->real_escape_string($search_query);
    $sql = "SELECT * FROM tblmovies WHERE Title LIKE '%$search_query%' OR Genre LIKE '%$search_query%' OR ReleaseYear LIKE '%$search_query%'";
} else {
    $sql = "SELECT * FROM tblmovies";
}
$result = $conn->query($sql);

// Get message from URL if redirected
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    $message_type = $_GET['type'] ?? 'success';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RELAX MOVIE - Admin Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }

        body {
            background: #0f0f0f;
            color: #fff;
            overflow-x: hidden;
            position: relative;
            min-height: 100vh;
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

        header {
            width: 100%;
            background: transparent;
            padding: 20px;
            text-align: center;
            position: relative;
        }

        .logo {
            font-size: 48px;
            font-weight: bold;
            color: #e50914;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .logo span {
            display: inline-block;
            animation: bounce 1.5s ease-in-out infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .logo span:nth-child(n) { animation-delay: calc(0.1s * var(--i)); }

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

        .btn-logout {
            background: #1f8dd6;
            color: #fff;
            position: absolute;
            top: 20px;
            right: 20px;
        }

        .btn-logout:hover {
            background: #2a9df4;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(31, 141, 214, 0.5);
        }

        main {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        h2, h3 {
            text-align: center;
            font-size: 28px;
            margin: 40px 0 20px;
            color: #e50914;
            text-shadow: 1px 1px 4px rgba(0, 0, 0, 0.7);
        }

        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
            font-size: 16px;
            opacity: 1;
            transition: opacity 2s ease-out;
        }

        .message.fade-out {
            opacity: 0;
        }

        .success {
            background: #28a745;
            color: #fff;
        }

        .error {
            background: #dc3545;
            color: #fff;
        }

        .form-container {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 40px;
        }

        .form-container label {
            display: block;
            font-size: 16px;
            margin-bottom: 8px;
            color: #f0f0f0;
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

        .form-container input[type="submit"],
        .form-container input[type="reset"] {
            background: #e50914;
            color: #fff;
            cursor: pointer;
            width: auto;
            margin-right: 10px;
        }

        .form-container input[type="submit"]:hover,
        .form-container input[type="reset"]:hover {
            background: #f40612;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(229, 9, 20, 0.5);
        }

        .search-container {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .search-container input[type="text"] {
            padding: 10px;
            width: 300px;
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            border: none;
        }

        .search-container input[type="submit"] {
            background: #e50914;
            color: #fff;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }

        .search-container input[type="submit"]:hover {
            background: #f40612;
        }

        .table-container {
            overflow-x: auto;
            margin-bottom: 40px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            overflow: hidden;
        }

        th, td {
            padding: 12px;
            text-align: left;
            font-size: 14px;
            color: #f0f0f0;
        }

        th {
            background: rgba(229, 9, 20, 0.5);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        th:last-child, td:last-child {
            min-width: 150px;
            white-space: nowrap;
        }

        tr:nth-child(even) {
            background: rgba(255, 255, 255, 0.05);
        }

        td {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .action-btn {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            margin-right: 10px;
            text-decoration: none;
            color: #fff;
            display: inline-block;
        }

        .edit-btn {
            background: #1f8dd6;
        }

        .edit-btn:hover {
            background: #2a9df4;
        }

        .delete-btn {
            background: #ff4d4d;
        }

        .delete-btn:hover {
            background: #ff6666;
        }

        @media (max-width: 600px) {
            h2, h3 {
                font-size: 24px;
            }

            th, td {
                font-size: 12px;
                padding: 8px;
            }

            td:last-child {
                display: flex;
                flex-direction: column;
                gap: 8px;
                align-items: flex-start;
            }

            .action-btn {
                padding: 6px 12px;
                font-size: 12px;
                margin-right: 0;
                width: 100%;
                text-align: center;
            }

            .search-container input[type="text"] {
                width: 200px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <span style="--i:1">R</span><span style="--i:2">E</span><span style="--i:3">L</span><span style="--i:4">A</span><span style="--i:5">X</span>
            <span style="--i:6">M</span><span style="--i:7">O</span><span style="--i:8">V</span><span style="--i:9">I</span><span style="--i:10">E</span>
        </div>
        <a href="logout.php">
            <button class="btn btn-logout">Logout</button>
        </a>
    </header>

    <main>
        <h2>Admin Portal - Manage Movies</h2>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>" id="message">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <script>
                // Fade out message after 3 seconds
                setTimeout(() => {
                    const message = document.getElementById('message');
                    if (message) {
                        message.classList.add('fade-out');
                        setTimeout(() => message.remove(), 2000);
                    }
                }, 3000);
            </script>
        <?php endif; ?>

        <!-- Search Movies -->
        <div class="search-container">
            <form method="GET">
                <input type="text" name="search" placeholder="Search by title, genre, or year" value="<?php echo htmlspecialchars($search_query); ?>">
                <input type="submit" value="Search">
            </form>
        </div>

        <!-- Add Movie Form -->
        <h3>Add New Movie</h3>
        <div class="form-container">
            <form method="POST" id="addMovieForm">
                <label>Title:</label><input type="text" name="title" required>
                <label>Release Year:</label><input type="number" name="release_year" min="1888" max="<?php echo date('Y'); ?>" required>
                <label>Genre:</label><input type="text" name="genre" required>
                <label>Description:</label><textarea name="description" required></textarea>
                <label>Image URL:</label><input type="url" name="image_url" required>
                <label>IMDb Rating:</label><input type="number" step="0.1" min="0" max="10" name="imdb_rating" required>
                <label>Full Movie URL:</label><input type="url" name="full_movie_url" required>
                <input type="submit" name="add_movie" value="Add Movie">
                <input type="reset" value="Clear Form">
            </form>
        </div>

        <!-- Edit Movie Form -->
        <?php if ($edit_movie): ?>
            <h3>Edit Movie</h3>
            <div class="form-container">
                <form method="POST">
                    <input type="hidden" name="movie_id" value="<?php echo $edit_movie['MovieID']; ?>">
                    <label>Title:</label><input type="text" name="title" value="<?php echo htmlspecialchars($edit_movie['Title']); ?>" required>
                    <label>Release Year:</label><input type="number" name="release_year" min="1888" max="<?php echo date('Y'); ?>" value="<?php echo $edit_movie['ReleaseYear']; ?>" required>
                    <label>Genre:</label><input type="text" name="genre" value="<?php echo htmlspecialchars($edit_movie['Genre']); ?>" required>
                    <label>Description:</label><textarea name="description" required><?php echo htmlspecialchars($edit_movie['Description']); ?></textarea>
                    <label>Image URL:</label><input type="url" name="image_url" value="<?php echo htmlspecialchars($edit_movie['ImageURL']); ?>" required>
                    <label>IMDb Rating:</label><input type="number" step="0.1" min="0" max="10" name="imdb_rating" value="<?php echo $edit_movie['IMDbRating']; ?>" required>
                    <label>Full Movie URL:</label><input type="url" name="full_movie_url" value="<?php echo htmlspecialchars($edit_movie['FullMovieURL']); ?>" required>
                    <input type="submit" name="edit_movie" value="Update Movie">
                </form>
            </div>
        <?php endif; ?>

        <!-- Display Movies -->
        <h3>Movie List</h3>
        <div class="table-container">
            <table>
                <tr>
                    <th>Movie ID</th>
                    <th>Title</th>
                    <th>Release Year</th>
                    <th>Genre</th>
                    <th>Description</th>
                    <th>Image URL</th>
                    <th>IMDb Rating</th>
                    <th>Full Movie URL</th>
                    <th>Actions</th>
                </tr>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['MovieID']; ?></td>
                            <td><?php echo htmlspecialchars($row['Title']); ?></td>
                            <td><?php echo $row['ReleaseYear']; ?></td>
                            <td><?php echo htmlspecialchars($row['Genre']); ?></td>
                            <td><?php echo htmlspecialchars($row['Description']); ?></td>
                            <td><a href="<?php echo htmlspecialchars($row['ImageURL']); ?>" target="_blank">View Image</a></td>
                            <td><?php echo $row['IMDbRating']; ?></td>
                            <td><a href="<?php echo htmlspecialchars($row['FullMovieURL']); ?>" target="_blank">Watch</a></td>
                            <td>
                                <a href="admin_portal.php?edit_id=<?php echo $row['MovieID']; ?>" class="action-btn edit-btn">Edit</a>
                                <a href="admin_portal.php?delete_id=<?php echo $row['MovieID']; ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this movie? This will also remove it from all user watchlists.')">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center;">No movies found.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </main>
</body>
</html>
<?php $conn->close(); ?>