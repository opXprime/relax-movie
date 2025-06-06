<?php
session_start();

// DB connection
$host = 'localhost';
$dbname = 'moviedb';
$username = 'root';
$password = '';
$port = 3307;

$conn = new mysqli($host, $username, $password, $dbname, $port);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user = trim($_POST["username"]);
    $pass = $_POST["password"];

    // Prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if user exists
    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();

        // Verify password
        if (password_verify($pass, $row['password'])) {
            // Successful login: set session and redirect back with success message
            $_SESSION['pending_username']     = $user;
            $_SESSION['security_question']    = $row['security_question'];
            $_SESSION['security_answer_hash'] = $row['security_answer_hash'];
            $_SESSION['auth_origin']          = 'login';

            // Redirect to self with a “login_successful” message
            header("Location: login.php?message=login_successful");
            exit;
        } else {
            // Incorrect password
            header("Location: login.php?error=incorrect_password");
            exit;
        }
    } else {
        // User not found
        header("Location: login.php?error=user_not_found");
        exit;
    }
}

// Close the DB connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login - Movielist</title>
  <link rel="stylesheet" href="css/login.css">
  <style>
    #infoMessage {
      display: none;
      margin-bottom: 10px;
      font-weight: bold;
      color: rgb(242, 248, 242);
    }
  </style>
</head>
<body class="dark-theme">
  <div class="login-container">
    <!-- This div will show error or success messages -->
    <div id="infoMessage"></div>

    <form class="login-box" action="login.php" method="POST">
      <h2>Login to RelaxMovie</h2>

      <label for="username">Username</label>
      <input type="text" id="username" name="username" placeholder="Enter your username" required />

      <label for="password">Password</label>
      <input type="password" id="password" name="password" placeholder="Enter your password" required />

      <div class="login-options">
        <a href="forgot_password.php">Forgot password?</a>
      </div>

      <button type="submit">Login</button>

      <p class="signup-link">
        Don't have an account? <a href="signup.html" class="signup-link">Sign Up</a>
      </p>
      <div class="back-button-container">
        <a href="mainpage.html" class="back-button"> Back to Main Page</a>
      </div>
    </form>
  </div>

  <script>
    // On page load, check for error= or message= in the URL
    const params  = new URLSearchParams(window.location.search);
    const error   = params.get("error");
    const message = params.get("message");
    const infoBox = document.getElementById("infoMessage");

    if (error) {
      // Show error message for 3 seconds, then reload the form without params
      let text = "";
      if (error === "incorrect_password") {
        text = "Incorrect password.";
      } else if (error === "user_not_found") {
        text = "User not found.";
      }
      infoBox.textContent = text;
      infoBox.style.display = "block";

      setTimeout(() => {
        window.location.href = "login.php";
      }, 3000);

    } else if (message) {
      // Show a success message for 3 seconds
      let text = "";
      if (message === "login_successful") {
        text = "Login successful. Redirecting...";
        infoBox.textContent = text;
        infoBox.style.display = "block";

        // After 3 seconds, go to security_question.php
        setTimeout(() => {
          window.location.href = "security_question.php";
        }, 3000);

      } else {
        // Handle other messages, e.g., from signup.php
        infoBox.textContent = message;
        infoBox.style.display = "block";

        // Hide after 3 seconds, stay on login page
        setTimeout(() => {
          infoBox.style.display = "none";
        }, 3000);
      }
    }
  </script>
</body>
</html>
