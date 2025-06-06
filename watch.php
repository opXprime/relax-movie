<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Database connection
$host = 'localhost:3307';
$username = 'root';
$password = '';
$dbname = 'moviedb';
$port = 3307;

$conn = new mysqli($host, $username, $password, $dbname, $port);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get movie ID and user ID
$movieID = isset($_GET['movieid']) ? intval($_GET['movieid']) : 0;
$userID = $_SESSION['user_id'];
if ($movieID <= 0) {
    echo "Invalid movie ID.";
    exit;
}

// Update WatchedStatus to 'Watched' on page load
$update_sql = "INSERT INTO tblwatchliststatus (MovieID, UserID, WatchedStatus) 
               VALUES (?, ?, 'Watched') 
               ON DUPLICATE KEY UPDATE WatchedStatus = 'Watched'";
$stmt_update = $conn->prepare($update_sql);
$stmt_update->bind_param("ii", $movieID, $userID);
$stmt_update->execute();
$stmt_update->close();

// Update tblmovielistdisplay
$update_display_sql = "INSERT INTO tblmovielistdisplay 
                       (MovieID, UserID, WatchedStatus, Title, Genre, ReleaseYear, ImageURL, Description, IMDbRating, FullMovieURL)
                       SELECT ?, ?, 'Watched', Title, Genre, ReleaseYear, ImageURL, Description, IMDbRating, FullMovieURL
                       FROM tblmovies WHERE MovieID = ?
                       ON DUPLICATE KEY UPDATE WatchedStatus = 'Watched'";
$stmt_display = $conn->prepare($update_display_sql);
$stmt_display->bind_param("iii", $movieID, $userID, $movieID);
$stmt_display->execute();
$stmt_display->close();

// Fetch movie details
$sql = "SELECT MovieID, Title, FullMovieURL, Description, IMDbRating, ImageURL, Genre, ReleaseYear 
        FROM tblmovies WHERE MovieID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $movieID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo "Movie not found.";
    exit;
}
$movie = $result->fetch_assoc();

// Convert YouTube URL to embed format
$videoURL = $movie['FullMovieURL'];
if (strpos($videoURL, 'watch?v=') !== false) {
    $videoURL = str_replace('watch?v=', 'embed/', $videoURL);
}

// Fetch related movies
$genre = $movie['Genre'];
$related_sql = "SELECT MovieID, Title, ImageURL FROM tblmovies 
                WHERE Genre = ? AND MovieID != ? LIMIT 4";
$related_stmt = $conn->prepare($related_sql);
$related_stmt->bind_param("si", $genre, $movieID);
$related_stmt->execute();
$related_result = $related_stmt->get_result();
$related_movies = [];
while ($row = $related_result->fetch_assoc()) {
    $related_movies[] = $row;
}

// Fetch average IMDb rating for the genre
$avg_rating_sql = "SELECT AVG(IMDbRating) as avgRating FROM tblmovies WHERE Genre = ? AND IMDbRating IS NOT NULL";
$avg_rating_stmt = $conn->prepare($avg_rating_sql);
$avg_rating_stmt->bind_param("s", $genre);
$avg_rating_stmt->execute();
$avg_rating_result = $avg_rating_stmt->get_result();
$avgRating = $avg_rating_result->num_rows > 0 ? $avg_rating_result->fetch_assoc()['avgRating'] : 0;
$avg_rating_stmt->close();

// Fetch a recommended movie in the same genre (excluding the current movie)
$recommend_sql = "SELECT MovieID, Title FROM tblmovies 
                 WHERE Genre = ? AND MovieID != ? AND IMDbRating IS NOT NULL 
                 ORDER BY IMDbRating DESC LIMIT 1";
$recommend_stmt = $conn->prepare($recommend_sql);
$recommend_stmt->bind_param("si", $genre, $movieID);
$recommend_stmt->execute();
$recommend_result = $recommend_stmt->get_result();
$recommended_movie = $recommend_result->num_rows > 0 ? $recommend_result->fetch_assoc() : null;
$recommend_stmt->close();

$related_stmt->close();
$stmt->close();
$conn->close();

// Determine the referring page for the back button
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
$backLink = 'index.php'; // Default to index.php
$backText = 'Back to Home'; // Default text

if (strpos($referer, 'watchlist.php') !== false) {
    $backLink = 'watchlist.php';
    $backText = 'Back to Watchlist';
} elseif (strpos($referer, 'search.php') !== false) {
    $backLink = 'search.php';
    $backText = 'Back to Search';
} elseif (strpos($referer, 'index.php') !== false) {
    $backLink = 'index.php';
    $backText = 'Back to Home';
}

// Define genre-based fun facts (hardcoded for genres, not individual movies)
$genreFacts = [
    'Action' => 'Action movies often feature high-energy stunts, with some of the most iconic ones performed by stars like Jackie Chan, who does his own stunts!',
    'Drama' => 'Drama films often explore deep emotional themes and have been the genre of choice for many Oscar-winning movies!',
    'Thriller' => 'Thrillers are known for keeping audiences on the edge of their seats, often using suspenseful music and unexpected plot twists!',
    'Comedy' => 'Did you know? The highest-grossing comedy films often rely on physical humor and witty dialogue to make audiences laugh!',
    'Sci-Fi' => 'Sci-Fi movies often predict future technologies—some concepts from classic sci-fi films have inspired real-world innovations!',
    'Biography/Drama' => 'Biographical dramas bring real-life stories to the screen, often earning critical acclaim for their authenticity!',
    'Comedy/Fantasy' => 'Comedy/Fantasy blends humor with magical elements, creating whimsical and entertaining stories!',
    'Sci-Fi/Adventure' => 'Sci-Fi/Adventure films take viewers on thrilling journeys through futuristic worlds and beyond!',
    'Action/Thriller' => 'Action/Thriller movies combine high-stakes action with suspense, keeping audiences on edge!',
    'Thriller/Drama' => 'Thriller/Drama films mix intense suspense with deep emotional storytelling!',
    'Crime/Drama' => 'Crime/Drama explores the darker side of humanity, often with morally complex characters!',
    'Animation/Action' => 'Animation/Action brings dynamic fight scenes to life with stunning visuals!',
    'Animation/Fantasy' => 'Animation/Fantasy creates magical worlds with limitless imagination for all ages!',
    'Drama/History' => 'Historical dramas recreate the past, shedding light on significant events and figures!',
    'Drama/Romance' => 'Drama/Romance captures the highs and lows of love with emotional depth!',
    'Fantasy/Adventure' => 'Fantasy/Adventure takes viewers on epic quests in magical realms!',
    'Action/Sci-Fi' => 'Action/Sci-Fi combines futuristic technology with explosive action sequences!',
    'Action/Drama' => 'Action/Drama mixes intense action with deep character-driven stories!',
    'Animation/Adventure' => 'Animation/Adventure offers family-friendly journeys with heartwarming lessons!',
    'Sci-Fi/Thriller' => 'Sci-Fi/Thriller explores futuristic concepts with nail-biting suspense!',
    'Comedy/Drama' => 'Comedy/Drama balances humor with heartfelt moments for a unique storytelling experience!',
    'Drama/Western' => 'Western dramas bring the Wild West to life with tales of honor and revenge!',
    'Drama/Music' => 'Music dramas showcase the transformative power of music in emotional stories!',
    'Thriller/Horror' => 'Thriller/Horror keeps audiences terrified with suspense and scares!',
    'Mystery/Comedy' => 'Mystery/Comedy blends whodunits with humor for a fun, engaging watch!',
    'Action/Crime' => 'Action/Crime delivers high-stakes investigations with thrilling action!',
    'Sci-Fi/Comedy' => 'Sci-Fi/Comedy mixes futuristic ideas with laugh-out-loud moments!',
    'War/Drama' => 'War dramas depict the harsh realities of conflict with powerful storytelling!'
];

// Default fact if genre not found
$defaultFact = 'This movie belongs to the ' . htmlspecialchars($genre) . ' genre, known for its unique storytelling!';

// Get the genre fact
$genreFact = isset($genreFacts[$movie['Genre']]) ? $genreFacts[$movie['Genre']] : $defaultFact;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Watch <?php echo htmlspecialchars($movie['Title']); ?> - RelaxMovie</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
  <script src="https://www.youtube.com/iframe_api"></script>
  <style>
    body {
      font-family: 'Outfit', sans-serif;
      background: #0f0f0f;
      min-height: 100vh;
      margin: 0;
      color: #fff;
      position: relative;
      overflow-x: hidden;
      display: flex;
      flex-direction: column;
    }
    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: url('<?php echo htmlspecialchars($movie['ImageURL']); ?>') no-repeat center/cover;
      filter: blur(20px);
      opacity: 0.3;
      z-index: -2;
    }
    body::after {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      box-shadow: inset 0 0 100px rgba(0, 255, 255, 0.2), inset 0 0 100px rgba(255, 0, 255, 0.2);
      animation: neonGlow 8s ease-in-out infinite;
      pointer-events: none;
      z-index: -1;
    }
    @keyframes neonGlow {
      0%, 100% { box-shadow: inset 0 0 100px rgba(0, 255, 255, 0.2), inset 0 0 100px rgba(255, 0, 255, 0.2); }
      50% { box-shadow: inset 0 0 150px rgba(0, 255, 255, 0.3), inset 0 0 150px rgba(255, 0, 255, 0.3); }
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes slideInLeft {
      from { transform: translateX(-100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideInRight {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
    .container {
      display: flex;
      justify-content: center;
      width: 100%;
      max-width: 1200px;
      gap: 20px;
      align-items: flex-start;
      flex-grow: 1;
    }
    .player-container {
      flex: 0 1 640px;
      animation: fadeIn 0.5s ease-out;
    }
    .player-box {
      background: rgba(31, 41, 55, 0.7);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      padding: 1.5rem;
      border-radius: 10px;
      text-align: center;
      transition: box-shadow 0.3s ease;
    }
    .player-box:hover {
      box-shadow: 0 0 20px rgba(0, 255, 255, 0.3), 0 0 20px rgba(255, 0, 255, 0.3);
    }
    h2 {
      font-size: 1.8rem;
      font-weight: 600;
      color: #f0f0f0;
      margin-bottom: 1rem;
      opacity: 1;
      transition: opacity 0.3s ease;
    }
    h2.hidden {
      opacity: 0;
    }
    .video-wrapper {
      position: relative;
      padding-bottom: 56.25%;
      height: 0;
      overflow: hidden;
      border-radius: 8px;
      background: #000;
      transition: box-shadow 0.3s ease;
    }
    .video-wrapper.playing {
      box-shadow: 0 0 15px rgba(0, 255, 255, 0.5), 0 0 15px rgba(255, 0, 255, 0.5);
    }
    #player {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
    }
    .controls {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin: 1rem 0;
      gap: 10px;
      flex-wrap: wrap;
    }
    .control-button {
      background: #ff0000;
      color: #fff;
      padding: 0.5rem 1rem;
      border-radius: 6px;
      font-size: 0.8rem;
      font-weight: 500;
      cursor: pointer;
      transition: background 0.3s ease, transform 0.2s ease;
    }
    .control-button:hover {
      background: #cc0000;
      transform: scale(1.05);
    }
    select {
      background: #1f2937;
      color: #fff;
      padding: 0.5rem;
      border-radius: 6px;
      border: 1px solid #4b5563;
      font-size: 0.8rem;
      cursor: pointer;
    }
    .description {
      font-size: 0.9rem;
      color: #d0d0d0;
      margin: 1rem 0;
      line-height: 1.4;
      text-align: left;
      animation: fadeIn 0.7s ease-out;
    }
    .imdb-rating {
      font-size: 0.8rem;
      color: #f0c14b;
      margin-bottom: 1rem;
      text-align: left;
    }
    .back-button {
      display: inline-block;
      padding: 0.75rem 1.5rem;
      background: #ff0000;
      color: #fff;
      text-decoration: none;
      border-radius: 6px;
      font-size: 0.9rem;
      font-weight: 500;
      transition: background 0.3s ease, transform 0.2s ease;
    }
    .back-button:hover {
      background: #cc0000;
      transform: scale(1.05);
    }
    .side-panel {
      width: 150px;
      background: rgba(31, 41, 55, 0.7);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 10px;
      padding: 1rem;
      opacity: 0;
      transition: opacity 0.3s ease;
      animation: none;
    }
    .left-panel:hover, .right-panel:hover {
      opacity: 1;
    }
    .left-panel {
      animation: slideInLeft 0.5s ease-out forwards;
    }
    .right-panel {
      animation: slideInRight 0.5s ease-out forwards;
    }
    .side-panel h4 {
      font-size: 1rem;
      font-weight: 600;
      color: #f0f0f0;
      margin-bottom: 0.5rem;
    }
    .side-panel p {
      font-size: 0.8rem;
      color: #d0d0d0;
      margin: 0.5rem 0;
    }
    .side-panel .action-button {
      display: block;
      background: #ff0000;
      color: #fff;
      padding: 0.5rem;
      border-radius: 6px;
      text-align: center;
      font-size: 0.8rem;
      margin-top: 0.5rem;
      transition: background 0.3s ease, transform 0.2s ease;
      border: none;
      width: 100%;
    }
    .side-panel .action-button:hover {
      background: #cc0000;
      transform: scale(1.05);
    }
    .related-movies {
      margin-top: 2rem;
      animation: fadeIn 0.9s ease-out;
    }
    .related-movies h3 {
      font-size: 1.2rem;
      font-weight: 600;
      color: #f0f0f0;
      margin-bottom: 1rem;
    }
    .related-grid {
      display: flex;
      gap: 10px;
      overflow-x: auto;
      padding-bottom: 10px;
    }
    .related-card {
      flex: 0 0 120px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 8px;
      overflow: hidden;
      transition: transform 0.3s ease;
    }
    .related-card:hover {
      transform: scale(1.05);
    }
    .related-card img {
      width: 100%;
      height: 150px;
      object-fit: cover;
    }
    .related-card p {
      font-size: 0.7rem;
      color: #f0f0f0;
      padding: 5px;
      text-align: center;
    }
    .loading {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 40px;
      height: 40px;
      border: 4px solid #ff0000;
      border-top-color: transparent;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      z-index: 10;
      display: none;
    }
    .error-message {
      font-size: 0.9rem;
      color: #ff4d4d;
      background: rgba(255, 75, 75, 0.2);
      padding: 0.75rem;
      border-radius: 6px;
      margin-bottom: 1rem;
      box-shadow: 0 0 10px rgba(255, 75, 75, 0.5);
      display: none;
    }
    .fullscreen-message {
      position: fixed;
      top: 20px;
      left: 50%;
      transform: translateX(-50%);
      background: rgba(0, 0, 0, 0.8);
      color: #fff;
      padding: 0.5rem 1rem;
      border-radius: 6px;
      font-size: 0.8rem;
      opacity: 0;
      transition: opacity 0.3s ease;
    }
    .fullscreen-message.show {
      opacity: 1;
    }
    /* Modal Styles for Insights */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.7);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }
    .modal-content {
      background: #1f2937;
      padding: 1.5rem;
      border-radius: 10px;
      max-width: 500px;
      width: 90%;
      text-align: center;
      position: relative;
      color: #fff;
      box-shadow: 0 0 20px rgba(0, 255, 255, 0.3), 0 0 20px rgba(255, 0, 255, 0.3);
    }
    .modal-content h3 {
      font-size: 1.5rem;
      margin-bottom: 1rem;
      color: #f0f0f0;
    }
    .modal-content p {
      font-size: 1rem;
      color: #d0d0d0;
      margin-bottom: 1rem;
    }
    .modal-content a {
      color: #f0c14b;
      text-decoration: underline;
      cursor: pointer;
    }
    .modal-content .modal-button {
      background: #ff0000;
      color: #fff;
      padding: 0.5rem 1rem;
      border-radius: 6px;
      font-size: 0.9rem;
      cursor: pointer;
      transition: background 0.3s ease, transform 0.2s ease;
      margin: 0.5rem;
    }
    .modal-content .modal-button:hover {
      background: #cc0000;
      transform: scale(1.05);
    }
    @keyframes spin {
      to { transform: translate(-50%, -50%) rotate(360deg); }
    }
    @media (max-width: 768px) {
      .container {
        flex-direction: column;
        align-items: center;
      }
      .side-panel {
        display: none;
      }
      .player-container {
        padding: 15px;
      }
      .player-box {
        padding: 1rem;
      }
      h2 {
        font-size: 1.5rem;
      }
      .video-wrapper {
        padding-bottom: 75%;
      }
      .description, .imdb-rating {
        font-size: 0.8rem;
      }
      .controls {
        gap: 8px;
      }
      .control-button, select {
        font-size: 0.7rem;
        padding: 0.4rem 0.8rem;
      }
      .back-button {
        padding: 0.5rem 1rem;
        font-size: 0.8rem;
      }
      .related-card {
        flex: 0 0 100px;
      }
      .related-card img {
        height: 130px;
      }
      .related-card p {
        font-size: 0.65rem;
      }
      .modal-content {
        width: 95%;
        padding: 1rem;
      }
      .modal-content h3 {
        font-size: 1.3rem;
      }
      .modal-content p {
        font-size: 0.9rem;
      }
      .modal-content .modal-button {
        font-size: 0.8rem;
        padding: 0.4rem 0.8rem;
      }
    }
    @media (max-width: 480px) {
      h2 {
        font-size: 1.3rem;
      }
      .video-wrapper {
        padding-bottom: 100%;
      }
      .description, .imdb-rating {
        font-size: 0.75rem;
      }
      .control-button, select {
        font-size: 0.65rem;
        padding: 0.3rem 0.6rem;
      }
      .back-button {
        padding: 0.5rem 0.75rem;
        font-size: 0.7rem;
      }
      .related-card {
        flex: 0 0 80px;
      }
      .related-card img {
        height: 100px;
      }
      .related-card p {
        font-size: 0.6rem;
      }
      .modal-content h3 {
        font-size: 1.1rem;
      }
      .modal-content p {
        font-size: 0.8rem;
      }
      .modal-content .modal-button {
        font-size: 0.7rem;
        padding: 0.3rem 0.6rem;
      }
    }
  </style>
</head>
<body>
  <nav class="p-4 shadow-md flex justify-between items-center backdrop-blur sm:flex-row flex-col bg-gray-900">
    <h1 class="text-2xl font-bold">🎬 RELAX MOVIE</h1>
    <div class="flex items-center space-x-3 text-sm mt-3 sm:mt-0">
      <a href="index.php" class="bg-gray-700 px-4 py-2 rounded hover:bg-gray-600">Home</a>
      <a href="watchlist.php" class="bg-blue-600 px-4 py-2 rounded hover:bg-blue-500">Watchlist</a>
      <a href="logout.php" class="bg-white text-black px-4 py-2 rounded hover:bg-gray-200">Logout</a>
    </div>
  </nav>

  <div class="container">
    <div class="side-panel left-panel">
      <h4>Details</h4>
      <p>Genre: <?php echo htmlspecialchars($movie['Genre']); ?></p>
      <p>Year: <?php echo htmlspecialchars($movie['ReleaseYear']); ?></p>
    </div>
    <div class="player-container">
      <div class="player-box">
        <h2 id="title"><?php echo htmlspecialchars($movie['Title']); ?></h2>
        <div class="video-wrapper">
          <div id="player"></div>
          <div class="loading" id="loading"></div>
          <div class="error-message" id="errorMessage">Video unavailable. Please try another movie.</div>
        </div>
        <div class="controls">
          <button class="control-button" id="playPause">Pause</button>
          <button class="control-button" id="toggle4K">Enable 4K</button>
          <button class="control-button" id="fullScreen">Full Screen</button>
          <select id="playbackSpeed">
            <option value="0.5">0.5x</option>
            <option value="1" selected>1x</option>
            <option value="1.5">1.5x</option>
            <option value="2">2x</option>
          </select>
        </div>
        <div class="imdb-rating">IMDb: <?php echo $movie['IMDbRating'] ? number_format($movie['IMDbRating'], 1) : 'N/A'; ?></div>
        <p class="description"><?php echo htmlspecialchars($movie['Description']); ?></p>
        <a href="<?php echo htmlspecialchars($backLink); ?>" class="back-button"><?php echo htmlspecialchars($backText); ?></a>
        <?php if (!empty($related_movies)): ?>
          <div class="related-movies">
            <h3>Related Movies</h3>
            <div class="related-grid">
              <?php foreach ($related_movies as $related): ?>
                <a href="watch.php?movieid=<?php echo $related['MovieID']; ?>" class="related-card">
                  <img src="<?php echo htmlspecialchars($related['ImageURL']); ?>" alt="<?php echo htmlspecialchars($related['Title']); ?>">
                  <p><?php echo htmlspecialchars($related['Title']); ?></p>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="side-panel right-panel">
      <h4>Actions</h4>
      <button class="action-button" onclick="showInsights()">Movie Insights</button>
    </div>
  </div>
  <div class="fullscreen-message" id="fullscreenMessage">Full Screen Enabled</div>

  <!-- Insights Modal -->
  <div class="modal" id="insightsModal">
    <div class="modal-content">
      <h3>Movie Insights</h3>
      <p><strong>Genre Fact:</strong> <?php echo htmlspecialchars($genreFact); ?></p>
      <p><strong>Rating Comparison:</strong> This movie has an IMDb rating of <?php echo $movie['IMDbRating'] ? number_format($movie['IMDbRating'], 1) : 'N/A'; ?>. The average rating for <?php echo htmlspecialchars($movie['Genre']); ?> movies is <?php echo $avgRating ? number_format($avgRating, 1) : 'N/A'; ?>.</p>
      <?php if ($recommended_movie): ?>
        <p><strong>Recommendation:</strong> Check out <a href="watch.php?movieid=<?php echo $recommended_movie['MovieID']; ?>"><?php echo htmlspecialchars($recommended_movie['Title']); ?></a>, another great <?php echo htmlspecialchars($movie['Genre']); ?> movie!</p>
      <?php else: ?>
        <p><strong>Recommendation:</strong> Explore more <?php echo htmlspecialchars($movie['Genre']); ?> movies in your watchlist!</p>
      <?php endif; ?>
      <button class="modal-button" onclick="closeInsights()">Close</button>
    </div>
  </div>

  <script>
    let player;
    function onYouTubeIframeAPIReady() {
      player = new YT.Player('player', {
        height: '100%',
        width: '100%',
        videoId: '<?php echo htmlspecialchars(basename(parse_url($videoURL, PHP_URL_PATH))); ?>',
        playerVars: {
          'autoplay': 1,
          'controls': 1,
          'rel': 0,
          'showinfo': 0
        },
        events: {
          'onReady': onPlayerReady,
          'onStateChange': onPlayerStateChange,
          'onError': onPlayerError
        }
      });
    }

    function onPlayerReady(event) {
      document.getElementById('loading').style.display = 'none';
      setTimeout(() => {
        document.getElementById('title').classList.add('hidden');
      }, 3000);
    }

    function onPlayerStateChange(event) {
      if (event.data === YT.PlayerState.PLAYING) {
        document.querySelector('.video-wrapper').classList.add('playing');
        document.getElementById('playPause').textContent = 'Pause';
      } else {
        document.querySelector('.video-wrapper').classList.remove('playing');
        document.getElementById('playPause').textContent = 'Play';
      }
    }

    function onPlayerError(event) {
      document.getElementById('loading').style.display = 'none';
      document.getElementById('errorMessage').style.display = 'block';
    }

    document.getElementById('playPause').addEventListener('click', () => {
      if (player.getPlayerState() === YT.PlayerState.PLAYING) {
        player.pauseVideo();
      } else {
        player.playVideo();
      }
    });

    document.getElementById('toggle4K').addEventListener('click', () => {
      const qualities = player.getAvailableQualityLevels();
      if (qualities.includes('hd2160')) {
        player.setPlaybackQuality('hd2160');
        alert('4K enabled!');
      } else {
        alert('4K not available for this video.');
      }
    });

    document.getElementById('playbackSpeed').addEventListener('change', (e) => {
      player.setPlaybackRate(parseFloat(e.target.value));
    });

    document.getElementById('fullScreen').addEventListener('click', () => {
      const videoWrapper = document.querySelector('.video-wrapper');
      if (document.fullscreenElement) {
        document.exitFullscreen();
        document.getElementById('fullscreenMessage').textContent = 'Full Screen Exited';
      } else {
        videoWrapper.requestFullscreen();
        document.getElementById('fullscreenMessage').textContent = 'Full Screen Enabled';
      }
      const message = document.getElementById('fullscreenMessage');
      message.classList.add('show');
      setTimeout(() => message.classList.remove('show'), 2000);
    });

    document.querySelector('.back-button').addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        window.location.href = '<?php echo htmlspecialchars($backLink); ?>';
      }
    });

    document.querySelector('.player-container').addEventListener('mouseenter', () => {
      document.querySelector('.left-panel').style.opacity = '1';
      document.querySelector('.right-panel').style.opacity = '1';
    });
    document.querySelector('.player-container').addEventListener('mouseleave', () => {
      document.querySelector('.left-panel').style.opacity = '0';
      document.querySelector('.right-panel').style.opacity = '0';
    });

    function showInsights() {
      document.getElementById('insightsModal').style.display = 'flex';
    }

    function closeInsights() {
      document.getElementById('insightsModal').style.display = 'none';
    }
  </script>
</body>
</html>