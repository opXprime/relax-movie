<?php
session_start();

// Define testing mode constant
$testing_mode = defined('TESTING_MODE') && TESTING_MODE;

// Check if user is logged in
if (!$testing_mode && !isset($_SESSION['user_id'])) {
    if (!$testing_mode) {
        header("Location: login.php");
        exit;
    }
}

// Database Connection
$host = 'localhost:3307';
$user = 'root';
$password = '';
$database = 'moviedb';
$port = 3307;

$conn = new mysqli($host, $user, $password, $database, $port);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$userId = $_SESSION['user_id'];

// Populate tblwatchliststatus for the user
$populate_status_sql = "INSERT IGNORE INTO tblwatchliststatus (MovieID, UserID, WatchedStatus)
                        SELECT MovieID, ?, 'Not Watched' FROM tblmovies
                        WHERE MovieID NOT IN (
                            SELECT MovieID FROM tblwatchliststatus WHERE UserID = ?
                        )";
$populate_status_stmt = $conn->prepare($populate_status_sql);
$populate_status_stmt->bind_param("ii", $userId, $userId);
$populate_status_stmt->execute();
$populate_status_stmt->close();

// Populate tblmovielistdisplay for the user
$populate_display_sql = "INSERT IGNORE INTO tblmovielistdisplay 
                        (MovieID, UserID, WatchedStatus, Title, Genre, ReleaseYear, ImageURL, Description, IMDbRating, FullMovieURL)
                        SELECT m.MovieID, ?, COALESCE(ws.WatchedStatus, 'Not Watched'), m.Title, m.Genre, m.ReleaseYear, 
                               m.ImageURL, m.Description, m.IMDbRating, m.FullMovieURL
                        FROM tblmovies m
                        LEFT JOIN tblwatchliststatus ws ON m.MovieID = ws.MovieID AND ws.UserID = ?
                        WHERE m.MovieID NOT IN (
                            SELECT MovieID FROM tblmovielistdisplay WHERE UserID = ?
                        )";
$populate_display_stmt = $conn->prepare($populate_display_sql);
$populate_display_stmt->bind_param("iii", $userId, $userId, $userId);
$populate_display_stmt->execute();
$populate_display_stmt->close();

// Fetch unique genres
$genres = ['All'];
$genre_sql = "SELECT DISTINCT Genre FROM tblmovielistdisplay WHERE UserID = ?";
$genre_stmt = $conn->prepare($genre_sql);
$genre_stmt->bind_param("i", $userId);
$genre_stmt->execute();
$genre_result = $genre_stmt->get_result();
if ($genre_result && $genre_result->num_rows > 0) {
    while ($row = $genre_result->fetch_assoc()) {
        if (!empty($row['Genre'])) {
            $genres[] = htmlspecialchars($row['Genre']);
        }
    }
}
$genre_stmt->close();

// Handle filter input
$genre = isset($_GET['genre']) ? trim($_GET['genre']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$status = htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); // Sanitize $status
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : '';
$valid_status = ($status === '0' || $status === '1' || $status === '');

// Build query
$sql = "SELECT MovieID, Title, Genre, ReleaseYear, WatchedStatus, ImageURL, Description, IMDbRating, FullMovieURL 
        FROM tblmovielistdisplay 
        WHERE UserID = ?";
$params = [$userId];
$types = "i";

if (!empty($genre) && $genre !== 'All') {
    $sql .= " AND Genre = ?";
    $params[] = $genre;
    $types .= "s";
}

if ($status !== '' && $valid_status) {
    $sql .= " AND WatchedStatus = ?";
    $params[] = ($status === '1' ? 'Watched' : 'Not Watched');
    $types .= "s";
}

if ($sort === 'asc' || $sort === 'desc') {
    $sql .= " ORDER BY ReleaseYear " . ($sort === 'asc' ? 'ASC' : 'DESC');
}

$movies = [];
if ($valid_status) {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Query preparation failed: " . $conn->error);
    }
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        die("Query execution failed: " . $stmt->error);
    }
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $movies[] = $row;
        }
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Watchlist - RELAX MOVIE</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet"/>
  <style>
    body {
        font-family: 'Outfit', sans-serif;
        background: #0f0f0f;
        color: #fff;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }
    .movie-card {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        padding: 10px;
        width: 150px;
        position: relative;
        overflow: hidden;
        transition: transform 0.3s, box-shadow 0.3s;
        text-decoration: none;
        color: inherit;
    }
    .movie-card:hover {
        transform: scale(1.05);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.5);
    }
    .movie-card img {
        width: 100%;
        border-radius: 8px;
        height: 200px;
        object-fit: cover;
    }
    .movie-card p {
        margin-top: 8px;
        font-size: 14px;
        text-align: center;
    }
    .movie-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .movie-card:hover .movie-overlay {
        opacity: 1;
    }
    .movie-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: #ff0000;
        color: #fff;
        padding: 3px 6px;
        border-radius: 4px;
        font-size: 0.65rem;
        font-weight: bold;
    }
    #movieGrid {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 20px;
        max-width: 1200px;
        margin: 0 auto;
    }
    .glass {
        background: rgba(31, 41, 55, 0.6);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        border: 0;
    }
  </style>
</head>
<body class="text-white">
  <header>
    <nav class="p-4 shadow-md flex justify-between items-center backdrop-blur sm:flex-row flex-col bg-gray-900">
      <h1 class="text-2xl font-bold">🎬 RELAX MOVIE</h1>
      <div class="flex items-center space-x-3 text-sm mt-3 sm:mt-0">
        <a href="index.php" class="bg-gray-700 px-4 py-2 rounded hover:bg-gray-600">Home</a>
        <a href="watchlist.php" class="bg-blue-600 px-4 py-2 rounded hover:bg-blue-500" aria-current="page">Watchlist</a>
        <a href="logout.php" class="bg-white text-black px-4 py-2 rounded hover:bg-gray-200">Logout</a>
      </div>
    </nav>
  </header>

  <main class="p-6 sm:p-8 form-container flex-grow">
    <h2 class="text-4xl font-bold mb-8 text-center">🔍 Filter Your Watchlist</h2>

    <!-- Filter Form -->
    <section class="glass p-8 rounded-2xl shadow-lg mb-8" aria-labelledby="filter-heading">
      <h3 id="filter-heading" class="sr-only">Filter and Sort Options</h3>
      <form action="watchlist.php" method="GET" class="grid sm:grid-cols-3 gap-6">
        <div>
          <label for="genre" class="block text-sm font-semibold mb-2">Genre</label>
          <select name="genre" id="genre" class="w-full bg-gray-800 text-white px-4 py-3 rounded-lg border border-gray-600">
            <?php foreach ($genres as $g): ?>
              <option value="<?php echo $g === 'All' ? '' : $g; ?>" <?php echo $genre === $g ? 'selected' : ''; ?>>
                <?php echo $g; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="status" class="block text-sm font-semibold mb-2">Watch Status</label>
          <select name="status" id="status" class="w-full bg-gray-800 text-white px-4 py-3 rounded-lg border border-gray-600">
            <option value="" <?php echo $status === '' ? 'selected' : ''; ?>>All</option>
            <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>Watched</option>
            <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>Not Watched</option>
          </select>
        </div>
        <div>
          <label for="sort" class="block text-sm font-semibold mb-2">Sort By Year</label>
          <select name="sort" id="sort" class="w-full bg-gray-800 text-white px-4 py-3 rounded-lg border border-gray-600">
            <option value="" <?php echo $sort === '' ? 'selected' : ''; ?>>None</option>
            <option value="asc" <?php echo $sort === 'asc' ? 'selected' : ''; ?>>Ascending</option>
            <option value="desc" <?php echo $sort === 'desc' ? 'selected' : ''; ?>>Descending</option>
          </select>
        </div>
        <button type="submit" class="sm:col-span-3 bg-blue-600 text-white font-semibold px-6 py-3 rounded-lg hover:bg-blue-500 transition">
          Apply Filters
        </button>
      </form>
    </section>

    <!-- Movie Grid -->
    <section id="movieGrid" class="mt-10" role="region" aria-live="polite" aria-labelledby="movie-grid-heading">
      <h3 id="movie-grid-heading" class="sr-only">Watchlist Movies</h3>
      <?php if (count($movies) > 0): ?>
        <div class="flex flex-wrap justify-center gap-20 max-w-1200 mx-auto">
          <?php foreach ($movies as $movie): ?>
            <a href="watch.php?movieid=<?php echo $movie['MovieID']; ?>" class="movie-card" aria-label="<?php echo htmlspecialchars($movie['Title']); ?>, <?php echo htmlspecialchars($movie['Genre']); ?>, Released in <?php echo htmlspecialchars($movie['ReleaseYear']); ?>, Watch Status: <?php echo htmlspecialchars($movie['WatchedStatus']); ?>">
              <img src="<?php echo htmlspecialchars($movie['ImageURL']); ?>" alt="<?php echo htmlspecialchars($movie['Title']); ?> Poster">
              <p class="font-bold"><?php echo htmlspecialchars($movie['Title']); ?></p>
              <p class="text-sm text-gray-300"><?php echo htmlspecialchars($movie['Genre']); ?> • <?php echo htmlspecialchars($movie['ReleaseYear']); ?></p>
              <div class="movie-overlay">
                <span><?php echo htmlspecialchars($movie['Description']); ?></span>
              </div>
              <span class="movie-badge">
                <?php if ($movie['WatchedStatus'] === 'Watched'): ?>
                  ⏱ Continue Watching
                <?php else: ?>
                  ▶ Watch Now
                <?php endif; ?>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="text-center text-gray-400 text-lg font-medium">
          No movies found.
        </div>
      <?php endif; ?>
    </section>
  </main>

  <footer class="text-center py-6 text-gray-200 text-sm border-t border-gray-700">
    © 2025 RELAX MOVIE. All rights reserved.
  </footer>
</body>
</html>