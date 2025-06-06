<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Database Connection
$host = 'localhost:3307';
$user = 'root';
$password = '';
$database = 'moviedb';

$conn = new mysqli($host, $user, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("❌ Failed to connect to database: " . $conn->connect_error);
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

// Fetch movies for the logged-in user
$sql = "SELECT MovieID, Title, Genre, ReleaseYear, WatchedStatus, Description, ImageURL, IMDbRating, FullMovieURL FROM tblmovielistdisplay WHERE UserID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

// Prepare movies array for JavaScript
$movies = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $movies[] = [
            'movieID' => $row['MovieID'],
            'title' => htmlspecialchars($row['Title']),
            'genre' => htmlspecialchars($row['Genre']),
            'year' => $row['ReleaseYear'],
            'watched' => $row['WatchedStatus'] === 'Watched' ? true : false,
            'poster' => htmlspecialchars($row['ImageURL']),
            'description' => htmlspecialchars($row['Description']),
            'imdbRating' => $row['IMDbRating'] ? number_format($row['IMDbRating'], 1) : 'N/A',
            'hasMovie' => !empty($row['FullMovieURL']) // Indicate if movie exists
        ];
    }
}
$stmt->close();

// Fetch unique genres for filter
$genre_sql = "SELECT DISTINCT Genre FROM tblmovielistdisplay";
$genre_result = $conn->query($genre_sql);
$genres = ['All'];
if ($genre_result && $genre_result->num_rows > 0) {
    while ($row = $genre_result->fetch_assoc()) {
        $genres[] = htmlspecialchars($row['Genre']);
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>RELAX MOVIE</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Outfit', sans-serif;
      position: relative;
      overflow-x: hidden;
      background: #0f0f0f;
      color: #fff;
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

    .glass {
      background: rgba(31, 41, 55, 0.7);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .movie-card {
      background: rgba(255, 255, 255, 0.1);
      border-radius: 10px;
      padding: 10px;
      width: 150px;
      cursor: pointer;
      transition: transform 0.3s, box-shadow 0.3s;
      position: relative;
      overflow: hidden;
    }

    .movie-card:hover {
      transform: scale(1.05);
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.5);
    }

    .movie-card:focus {
      outline: 3px solid #e50914;
      outline-offset: 2px;
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
      color: #f0f0f0;
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

    .movie-overlay span {
      color: #fff;
      font-size: 14px;
      font-weight: bold;
      text-align: center;
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

    .status-icon {
      position: absolute;
      bottom: 30px;
      right: 10px;
      font-size: 1.25rem;
    }

    #movieGrid {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 20px;
      max-width: 1200px;
      margin: 0 auto;
    }

    /* Navbar and General Mobile Responsiveness */
    @media (max-width: 640px) {
      nav {
        flex-direction: column;
        align-items: center;
        padding: 10px;
      }

      nav h1 {
        font-size: 1.5rem;
        margin-bottom: 10px;
      }

      nav .flex {
        flex-direction: column;
        gap: 10px;
        width: 100%;
        align-items: center;
      }

      nav .flex a {
        width: 100%;
        text-align: center;
        padding: 10px;
        font-size: 0.9rem;
      }

      main {
        padding: 1rem;
      }

      h2 {
        font-size: 1.75rem;
        margin-bottom: 1.5rem;
      }

      #genreFilter {
        width: 100%;
        padding: 0.5rem;
        font-size: 0.9rem;
      }

      .movie-card {
        width: 130px;
      }

      .movie-card img {
        height: 180px;
      }

      .movie-card p {
        font-size: 12px;
      }

      .movie-overlay span {
        font-size: 12px;
      }

      .movie-badge {
        font-size: 0.6rem;
        padding: 2px 5px;
      }

      .status-icon {
        font-size: 1rem;
        bottom: 25px;
        right: 8px;
      }
    }
  </style>
</head>
<body class="text-white min-h-screen flex flex-col">

  <!-- Navbar -->
  <nav class="glass sticky top-0 z-50 text-white p-4 shadow-md flex justify-between items-center backdrop-blur sm:flex-row flex-col">
    <h1 class="text-2xl font-bold tracking-wide">🎬 RELAX MOVIE</h1>
    <div class="flex items-center space-x-3 text-sm sm:mt-0 mt-3">
      <a href="index.php" class="hover:scale-105 transition bg-gray-700 px-4 py-2 rounded-lg hover:bg-gray-600">Home</a>
      <a href="watchlist.php" class="hover:scale-105 transition bg-gray-700 px-4 py-2 rounded-lg hover:bg-gray-600">Watchlist</a>
      <a href="search.php" class="hover:scale-105 transition bg-gray-700 px-4 py-2 rounded-lg hover:bg-gray-600">Search</a>
      <a href="profile.php" class="hover:scale-105 transition bg-gray-700 px-4 py-2 rounded-lg hover:bg-gray-600">Profile</a>
      <a href="logout.php" class="hover:scale-105 transition bg-white text-black px-4 py-2 rounded-lg hover:bg-gray-200">Logout</a>
    </div>
  </nav>

  <!-- Main Content -->
  <main class="p-6 sm:p-8 max-w-6xl mx-auto w-full flex-grow">
    <h2 class="text-4xl font-bold mb-8 text-center">🎥 Featured Movies</h2>

    <!-- Genre Filter -->
    <div class="mb-6 flex justify-end">
      <label for="genreFilter" class="sr-only">Filter by Genre</label>
      <select id="genreFilter" class="bg-gray-800 text-white px-4 py-2 rounded border border-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-400">
        <?php foreach ($genres as $genre): ?>
          <option value="<?= $genre ?>"><?= $genre ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Movie Grid -->
    <div id="movieGrid"></div>
  </main>

  <!-- Footer -->
  <footer class="text-center py-6 text-gray-200 text-sm border-t border-gray-700">
    © 2025 RELAX MOVIE. All rights reserved.
  </footer>

  <!-- JavaScript for Dynamic Rendering -->
  <script>
    const movies = <?php echo json_encode($movies); ?>;

    const movieGrid = document.getElementById("movieGrid");
    const genreFilter = document.getElementById("genreFilter");

    function renderMovies(filter = "All") {
      movieGrid.innerHTML = "";

      const filtered = movies.filter(movie => filter === "All" || movie.genre === filter);

      if (filtered.length === 0) {
        movieGrid.innerHTML = '<p class="text-center text-gray-300">No movies found in this genre.</p>';
        return;
      }

      filtered.forEach(movie => {
        const card = document.createElement("div");
        card.className = "movie-card";
        card.tabIndex = 0;
        card.innerHTML = `
          <a href="watch.php?movieid=${movie.movieID}">
            <img src="${movie.poster}" alt="Poster of ${movie.title}">
            <span class="movie-badge">${movie.watched ? '⏱ Continue Watching' : '▶ Watch Now'}</span>
            <div class="movie-overlay"><span>IMDb: ${movie.imdbRating}</span></div>
            <p>${movie.title}</p>
          </a>
        `;

        card.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            card.querySelector('a').click();
          }
        });

        movieGrid.appendChild(card);
      });
    }

    // Initial Load
    renderMovies();

    // Filter Listener
    genreFilter.addEventListener("change", (e) => {
      renderMovies(e.target.value);
    });
  </script>
</body>
</html>