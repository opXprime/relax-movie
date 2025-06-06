<?php
// Hardcoded admin credentials
$admin_username = 'admin';
$admin_password = 'SecurePass123!';

// HTTP Basic Authentication
if (!isset($_SERVER['PHP_AUTH_USER']) || 
    $_SERVER['PHP_AUTH_USER'] !== $admin_username || 
    $_SERVER['PHP_AUTH_PW'] !== $admin_password) {
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    die('Access denied. Please enter valid credentials.');
}

// Database connection
$host = 'localhost:3307';
$db_user = 'moviedb_user'; // Secure user from test log
$db_pass = 'StrongP@ssw0rd';
$db_name = 'moviedb';

$conn = new mysqli($host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    // Fallback to port 3306
    $host = 'localhost:3306';
    $conn = new mysqli($host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        die("Connection failed on both ports (3307 and 3306): " . $conn->connect_error);
    } else {
        echo "<p style='color: yellow; text-align: center;'>Connected on port 3306 (default).</p>";
    }
} else {
    echo "<p style='color: green; text-align: center;'>Connected on port 3307.</p>";
}

// Ensure MovieID has AUTO_INCREMENT
$check = $conn->query("SHOW COLUMNS FROM `tblmovies` WHERE `Field` = 'MovieID'");
$row = $check->fetch_assoc();
if (strpos($row['Extra'], 'auto_increment') === false) {
    $result = $conn->query("ALTER TABLE `tblmovies` MODIFY `MovieID` INT(11) NOT NULL AUTO_INCREMENT");
    if (!$result) {
        die("Error adding AUTO_INCREMENT to MovieID: " . $conn->error);
    } else {
        echo "<p style='color: green; text-align: center;'>AUTO_INCREMENT added to MovieID.</p>";
    }
}

// Handle form submissions
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["add_movie"])) {
        $title = $conn->real_escape_string(trim($_POST["title"]));
        $releaseYear = (int)$_POST["release_year"];
        $genre = $conn->real_escape_string(trim($_POST["genre"]));
        $description = $conn->real_escape_string(trim($_POST["description"]));
        $imageUrl = trim($_POST["image_url"]);
        $imdbRating = (float)$_POST["imdb_rating"];
        $fullMovieUrl = $conn->real_escape_string(trim($_POST["full_movie_url"]));

        // Handle file upload
        $uploadImagePath = '';
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == UPLOAD_ERR_OK) {
            $uploadDir = 'images/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = uniqid() . '_' . basename($_FILES['image_file']['name']);
            $targetFile = $uploadDir . $fileName;
            $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($imageFileType, $allowedTypes)) {
                if (move_uploaded_file($_FILES['image_file']['tmp_name'], $targetFile)) {
                    $uploadImagePath = $targetFile;
                } else {
                    $message = "Error uploading file.";
                }
            } else {
                $message = "Only JPG, JPEG, PNG, and GIF files are allowed.";
            }
        }

        // Use image_url if provided, else use uploaded file
        $imageUrl = $imageUrl ?: $uploadImagePath;

        // Validate inputs
        if (empty($title) || empty($genre) || empty($description) || empty($fullMovieUrl)) {
            $message = "Error: All required fields must be filled.";
        } elseif (strlen($title) > 100) {
            $message = "Error: Title must be 100 characters or less.";
        } elseif (strlen($genre) > 50) {
            $message = "Error: Genre must be 50 characters or less.";
        } elseif (strlen($imageUrl) > 255 || strlen($fullMovieUrl) > 255) {
            $message = "Error: URLs/paths must be 255 characters or less.";
        } elseif (!empty($imageUrl) && !preg_match('/^https?:\/\//', $imageUrl) && !file_exists($imageUrl)) {
            $message = "Error: Invalid Image URL or file path.";
        } else {
            $stmt = $conn->prepare("INSERT INTO `tblmovies` (`UserID`, `Title`, `ReleaseYear`, `WatchedStatus`, `Genre`, `Description`, `ImageURL`, `IMDbRating`, `FullMovieURL`) VALUES (NULL, ?, ?, NULL, ?, ?, ?, ?, ?)");
            if ($stmt === false) {
                die("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("sissdss", $title, $releaseYear, $genre, $description, $imageUrl, $imdbRating, $fullMovieUrl);
            if ($stmt->execute()) {
                $message = "Movie added successfully! ImageURL: " . htmlspecialchars($imageUrl);
            } else {
                $message = "Error executing statement: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    if (isset($_POST["remove_movie"])) {
        $movieId = (int)$_POST["movie_id"];
        $stmt = $conn->prepare("DELETE FROM `tblmovies` WHERE `MovieID` = ?");
        $stmt->bind_param("i", $movieId);
        if ($stmt->execute()) {
            $message = "Movie removed successfully!";
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch movies
$result = $conn->query("SELECT * FROM `tblmovies`");
$movies = $result->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RelexMovie - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.8)), url('https://images.unsplash.com/photo-1536440136628-849c177e76a1?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .card {
            backdrop-filter: blur(12px);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 0 1rem rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 90%;
            margin: 1rem;
        }
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        table {
            width: 100%;
            overflow-x: auto;
            display: block;
        }
        th, td {
            padding: 0.75rem;
            white-space: nowrap;
        }
        @media (max-width: 768px) {
            .card { max-width: 100%; padding: 1rem; }
            table { font-size: 0.875rem; }
            th, td { padding: 0.5rem; }
        }
        @media (max-width: 480px) {
            .card { margin: 0.5rem; padding: 0.75rem; }
            h1 { font-size: 1.5rem; }
            h2 { font-size: 1.25rem; }
            input, textarea, button { font-size: 0.875rem; padding: 0.5rem; }
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center">
    <div class="card animate-fade-in">
        <div class="flex justify-center mb-6">
            <img src="https://via.placeholder.com/150x50?text=RelexMovie" alt="RelexMovie Logo" class="h-12 filter drop-shadow-lg">
        </div>
        <h1 class="text-3xl font-bold text-center mb-4">Admin Panel</h1>
        <?php if (!empty($message)): ?>
            <p class="text-center text-green-400 mb-4"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <!-- Add Movie Form -->
        <h2 class="text-xl font-semibold mb-4">Add New Movie</h2>
        <form method="POST" enctype="multipart/form-data" class="mb-8">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-200 mb-1">Title</label>
                <input type="text" name="title" class="w-full p-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none" required>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-200 mb-1">Release Year</label>
                <input type="number" name="release_year" class="w-full p-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none" required>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-200 mb-1">Genre</label>
                <input type="text" name="genre" class="w-full p-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none" required placeholder="e.g., Biography/Drama">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-200 mb-1">Description</label>
                <textarea name="description" class="w-full p-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none" required></textarea>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-200 mb-1">Upload Image</label>
                <input type="file" name="image_file" class="w-full p-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none" accept="image/jpeg,image/png,image/gif">
                <p class="text-xs text-gray-400 mt-1">OR</p>
                <label class="block text-sm font-medium text-gray-200 mb-1">Image URL</label>
                <input type="text" name="image_url" class="w-full p-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none" placeholder="e.g., https://via.placeholder.com/300x450">
                <p class="text-xs text-gray-400 mt-1">Provide a URL or upload an image (one is required).</p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-200 mb-1">IMDb Rating</label>
                <input type="number" step="0.1" name="imdb_rating" class="w-full p-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none" required>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-200 mb-1">Full Movie URL</label>
                <input type="text" name="full_movie_url" class="w-full p-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none" required>
            </div>
            <button type="submit" name="add_movie" class="w-full bg-blue-600 text-white p-3 rounded-lg hover:bg-blue-700 transition duration-300">Add Movie</button>
        </form>

        <!-- Movie List and Remove -->
        <h2 class="text-xl font-semibold mb-4">Manage Movies</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-200">
                <thead class="bg-gray-800">
                    <tr>
                        <th class="p-3">S.No.</th>
                        <th class="p-3">Title</th>
                        <th class="p-3">Release Year</th>
                        <th class="p-3">Genre</th>
                        <th class="p-3">Description</th>
                        <th class="p-3">Image URL</th>
                        <th class="p-3">IMDb Rating</th>
                        <th class="p-3">Full Movie URL</th>
                        <th class="p-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $index = 1; foreach ($movies as $movie): ?>
                        <tr class="bg-gray-900">
                            <td class="p-3"><?php echo $index++; ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($movie['Title']); ?></td>
                            <td class="p-3"><? Daisuke Niwa Daisuke Niwa is a supporting character in the anime and manga series D.N.Angel. He is a high school student with a unique genetic condition that causes him to transform into the legendary phantom thief Dark Mousy whenever he experiences strong emotions, particularly love or passion. Here is a detailed overview of Daisuke Niwa based on the series:

### Background
- **Name**: Daisuke Niwa
- **Age**: 14 (at the start of the series)
- **Occupation**: High school student, phantom thief (as Dark Mousy)
- **Family**: Emiko Niwa (mother), Kosuke Niwa (father), Daiki Niwa (grandfather)
- **Affiliation**: Niwa family, associated with the Hikari family (rivals)

### Personality
Daisuke is kind-hearted, shy, and somewhat clumsy, often struggling with his confidence and the pressure of his dual identity. Despite his reluctance to embrace his role as Dark, he is brave, loyal, and deeply cares for his friends and family. His earnest and sincere nature makes him likable, though he often finds himself caught in awkward or comedic situations due to his transformations.

### Abilities and Transformation
- **Transformation into Dark Mousy**: Daisuke transforms into Dark, a charismatic and confident phantom thief, whenever he feels strong romantic or passionate emotions. This is a hereditary curse affecting the Niwa family, triggered by love. The transformation is temporary and reverts when Daisuke calms down.
- **Art Expertise**: As part of his training to become a phantom thief, Daisuke is skilled in art and can identify valuable artifacts, which aids in his heists.
- **Physical Skills**: He is agile and trained in stealth, lock-picking, and other thief-related skills, though he’s less experienced than Dark.
- ** Wiz**: Daisuke has a magical pet named Wiz (or With in some translations), a small, rabbit-like creature that can transform into Dark’s wings or even a duplicate of Daisuke, assisting in missions.

### Role in the Story
Daisuke’s journey in *D.N.Angel* revolves around balancing his normal life as a teenager with his secret life as Dark. He is often tasked with stealing cursed artworks created by the Hikari family, which have dangerous magical properties. His transformations are triggered by his feelings for Risa Harada, and later Riku Harada, creating a romantic subplot that drives much of the plot. Daisuke’s rivalry with Satoshi Hiwatari, a Hikari family member and classmate, adds tension, as Satoshi seeks to capture Dark.

### Key Relationships
- **Risa Harada**: Daisuke’s initial love interest, a popular girl who is charmed by Dark’s confidence but initially overlooks Daisuke.
- **Riku Harada**: Risa’s twin sister, who develops a closer bond with Daisuke due to their shared experiences and mutual respect.
- **Dark Mousy**: The alter-ego of Daisuke, who has his own personality and often teases Daisuke but also guides him.
- **Satoshi Hiwatari**: Daisuke’s rival, who transforms into Krad, Dark’s angelic counterpart, creating a complex dynamic.
- **Takeshi Saehara**: Daisuke’s best friend, a loud and loyal classmate who often gets involved in Daisuke’s adventures.

### Development
Throughout the series, Daisuke grows from a timid boy into a more confident individual, learning to embrace his role as Dark while staying true to his values. His relationships with Riku and others deepen, and he uncovers secrets about the Niwa-Hikari feud, the origins of Dark and Krad, and his family’s legacy.

### Notable Moments
- **First Transformation**: On his 14th birthday, Daisuke transforms into Dark after confessing his feelings to Risa, setting the stage for the series.
- **Heists**: Daisuke (as Dark) performs daring thefts of magical artworks, often under pressure from his family or to protect others.
- **Romantic Struggles**: His feelings for Risa and later Riku trigger transformations, leading to humorous and heartfelt moments.
- **Confrontations with Krad/Satoshi**: Battles between Dark and Krad, with Daisuke and Satoshi caught in the crossfire, highlight the stakes of their family rivalry.

### Appearance
Daisuke has red hair and large, expressive eyes, typical of shoujo anime protagonists. As Dark, he gains a taller, more mature appearance with darker hair and a confident demeanor, contrasting with Daisuke’s youthful look.

### Significance
Daisuke Niwa serves as the emotional core of *D.N.Angel*, embodying themes of identity, love, and duty. His dual nature as both a normal teenager and a legendary thief creates a compelling narrative, blending romance, comedy, action, and supernatural elements.

If you have specific questions about Daisuke Niwa or want details on a particular aspect (e.g., his relationship with Riku, a specific heist, or his role in the manga vs. anime), let me know!