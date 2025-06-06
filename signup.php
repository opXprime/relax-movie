<?php
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
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirmPassword = $_POST["confirm_password"];
    $birthdate = $_POST["birthdate"];
    $securityQuestion = $_POST["security_question"];
    $securityAnswer = trim($_POST["security_answer"]);

    // Username length check
    if (strlen($username) < 5 || strlen($username) > 15) {
        header("Location: signup.html?error=username_length");
        exit;
    }

    // Age check
    $birthDateObj = new DateTime($birthdate);
    $today = new DateTime();
    $age = $today->diff($birthDateObj)->y;
    if ($age < 18) {
        header("Location: signup.html?error=underage");
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: signup.html?error=invalid_email");
        exit;
    }

    // Check if email already exists
    $checkEmail = $conn->prepare("SELECT UserID FROM users WHERE email = ?");
    if (!$checkEmail) {
        die("Prepare failed: " . $conn->error);
    }
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $emailResult = $checkEmail->get_result();
    if ($emailResult->num_rows > 0) {
        header("Location: signup.html?error=email_exists");
        exit;
    }

    // Check if username already exists
    $checkUsername = $conn->prepare("SELECT UserID FROM users WHERE username = ?");
    if (!$checkUsername) {
        die("Prepare failed: " . $conn->error);
    }
    $checkUsername->bind_param("s", $username);
    $checkUsername->execute();
    $usernameResult = $checkUsername->get_result();
    if ($usernameResult->num_rows > 0) {
        header("Location: signup.html?error=username_exists");
        exit;
    }

    // Password match check
    if ($password !== $confirmPassword) {
        header("Location: signup.html?error=password_mismatch");
        exit;
    }

    // Security question and answer check
    if (empty($securityQuestion) || empty($securityAnswer)) {
        header("Location: signup.html?error=security_required");
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $hashedSecurityAnswer = password_hash($securityAnswer, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (username, email, password, birthdate, security_question, security_answer) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ssssss", $username, $email, $hashedPassword, $birthdate, $securityQuestion, $hashedSecurityAnswer);

    if ($stmt->execute()) {
        header("Location: login.html?message=Signup+successful.+Please+login.");
        exit;
    } else {
        echo "Signup failed: " . $stmt->error;
    }

    $checkEmail->close();
    $checkUsername->close();
    $stmt->close();
} else {
    header("Location: signup.html");
    exit;
}

$conn->close();
?>