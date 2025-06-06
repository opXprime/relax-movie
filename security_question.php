<?php
session_start();

if (!isset($_SESSION['pending_username']) || !isset($_SESSION['security_question']) || !isset($_SESSION['security_answer_hash'])) {
    header("Location: login.php");
    exit;
}

$question = $_SESSION['security_question'];
$username = $_SESSION['pending_username'];
$answerHash = $_SESSION['security_answer_hash'];
$authOrigin = $_SESSION['auth_origin'] ?? 'login'; // default to login

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $userAnswer = trim($_POST['security_answer']);

    if (password_verify($userAnswer, $answerHash)) {
        // Clear session variables for security
        unset($_SESSION['security_question']);
        unset($_SESSION['security_answer_hash']);
        unset($_SESSION['auth_origin']);

        // Set logged in user
        $_SESSION['username'] = $username;

        // Redirect based on where they came from
        if ($authOrigin === 'forgot') {
            header("Location: update_profile.php");
        } else {
            header("Location: index.html");
        }
        exit;
    } else {
        $error = "Incorrect answer. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Answer Security Question</title>
  <link rel="stylesheet" href="css/login.css?v=2">
  <style>
    .error {
      color: red;
      font-weight: bold;
      margin-bottom: 15px;
      text-align: center;
    }
  </style>
</head>
<body class="dark-theme">
  <div class="login-container">
    <form class="login-box" method="POST" action="security_question.php">
      <h2>Security Question</h2>

      <?php if (!empty($error)): ?>
        <div class="error"><?php echo $error; ?></div>
      <?php endif; ?>

      <p><strong><?php echo htmlspecialchars($question); ?></strong></p>
      <label for="security_answer">Your Answer:</label>
      <input type="text" name="security_answer" id="security_answer" required />

      <button type="submit">Verify</button>

      <div class="back-button-container">
        <a href="login.php" class="back-button">Back to Login</a>
      </div>
    </form>
  </div>
</body>
</html>
