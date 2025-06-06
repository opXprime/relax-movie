<?php
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

// Get filter parameters
$genre = isset($_GET['genre']) ? $conn->real_escape_string($_GET['genre']) : '';
$status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$sort = isset($_GET['sort']) ? $conn->real_escape_string($_GET['sort']) : '';

// Build SQL query
$sql = "SELECT MovieID, Title, Genre, ReleaseYear, WatchedStatus, Description, ImageURL, IMDbRating, FullMovieURL 
        FROM tblmovielistdisplay 
        WHERE UserID = 1";

if ($genre !== '') {
    $sql .= " AND Genre = '$genre'";
}
if ($status !== '') {
    $sql .= " AND WatchedStatus = '" . ($status == '1' ? 'Watched' : 'Not Watched') . "'";
}
if ($sort === 'asc') {
    $sql .= " ORDER BY ReleaseYear ASC";
} elseif ($sort === 'desc') {
    $sql .= " ORDER BY ReleaseYear DESC";
}

$result = $conn->query($sql);

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
            'hasMovie' => !empty($row['FullMovieURL'])
        ];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filtered Movies - RELAX MOVIE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            position: relative;
            overflow-x: hidden;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #000;
        }
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.9)),
                        url('https://images.unsplash.com/photo-1489599849927-2ee91cede3f5?crop=entropy&cs=tinysrgb&fit=max&q=80&w=1080') no-repeat center center / cover;
            z-index: -1;
        }
        .glass {
            background: rgba(31, 41, 55, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .movie-card {
            cursor: pointer;
        }
        .movie-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #1e90ff;
            color: #fff;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 0.7rem;
            font-weight: normal;
        }
        .status-icon {
            font-size: 1.5rem;
        }
        footer {
            margin-top: auto;
        }
    </style>
</head>
<body class="text-white">
    <!-- Navbar (matches watch.php) -->
    <nav class="glass sticky top-0 z-50 text-white p-4 shadow-md flex justify-between items-center backdrop-blur w-full">
        <h1 class="text-2xl font-bold tracking-wide">🎬 RELAX MOVIE</h1>
        <div class="flex items-center space-x-3 text-sm">
            <a href="index.php" class="hover:scale-105 transition bg-gray-700 px-4 py-2 rounded-lg hover:bg-gray-600">Home</a>
            <a href="watchlist.php" class="hover:scale-105 transition bg-blue-600 px-4 py-2 rounded-lg hover:bg-blue-500">Watchlist</a>
            <a href="search.php" class="hover:scale-105 transition bg-gray-700 px-4 py-2 rounded-lg hover:bg-gray-600">Search</a>
            <a href="profile.php" class="hover:scale-105 transition bg-gray-700 px-4 py-2 rounded-lg hover:bg-gray-600">Profile</a>
            <a href="logout.php" class="hover:scale-105 transition bg-white text-black px-4 py-2 rounded-lg hover:bg-gray-200">Logout</a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="p-6 sm:p-8 max-w-6xl mx-auto w-full flex-grow">
        <h2 class="text-4xl font-bold mb-8 text-center">📋 Filtered Movies</h2>

        <div class="glass p-6 rounded-2xl shadow-lg mb-6">
            <a href="watchlist.php" class="inline-block bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-500 transition">🔙 Back to Filters</a>
        </div>

        <?php if (empty($movies)): ?>
            <div class="text-center text-gray-300">
                <p>No movies found matching your filters.</p>
                <a href="search.php" class="inline-block mt-4 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-500">Try Different Filters</a>
            </div>
        <?php else: ?>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($movies as $movie): ?>
                    <div class="movie-card glass p-5 rounded-2xl shadow-lg hover:scale-105 transition-transform duration-300">
                        <a href="watch.php?movieid=<?php echo $movie['movieID']; ?>">
                            <div class="relative">
                                <img src="<?php echo $movie['poster']; ?>" alt="Poster of <?php echo $movie['title']; ?>" class="rounded-xl mb-4 w-full h-72 object-cover">
                                <?php if ($movie['hasMovie']): ?>
                                    <span class="movie-badge">Watch Now</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 class="text-2xl font-semibold"><?php echo $movie['title']; ?></h3>
                                    <p class="text-sm text-gray-300"><?php echo $movie['year']; ?> • IMDb: <?php echo $movie['imdbRating']; ?></p>
                                </div>
                                <div class="<?php echo $movie['watched'] ? 'text-green-400' : 'text-yellow-400'; ?> status-icon"
                                     title="<?php echo $movie['watched'] ? 'Watched' : 'Not Watched'; ?>"
                                     aria-label="<?php echo $movie['watched'] ? 'Watched' : 'Not Watched'; ?>">
                                    <?php echo $movie['watched'] ? '✔️' : '🕒'; ?>
                                </div>
                            </div>
                            <div class="mt-2">
                                <span class="inline-block bg-blue-600 text-xs font-medium px-3 py-1 rounded-full"><?php echo $movie['genre']; ?></span>
                            </div>
                            <p class="text-sm text-gray-300 mt-2"><?php echo $movie['description']; ?></p>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="text-center py-6 text-gray-200 text-sm border-t border-gray-700">
        © 2025 RELAX MOVIE. All rights reserved.
    </footer>
</body>
</html>