<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin.php');
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'moviedb', 3307);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = $conn->real_escape_string(trim($_POST['username']));
    $new_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $new_answer = password_hash($_POST['security_answer'], PASSWORD_DEFAULT);
    $admin_id = $_SESSION['admin_id'];
    $query = "UPDATE admin SET username = ?, password = ?, security_answer = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssi', $new_username, $new_password, $new_answer, $admin_id);
    $stmt->execute();
    $stmt->close();
    session_unset();
    session_destroy();
    header('Location: admin.php');
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Update Admin</title>
</head>
<body>
    <form method="POST">
        <label>Username: <input type="text" name="username" required></label><br>
        <label>Password: <input type="password" name="password" required></label><br>
        <label>What was the name of your first pet?: <input type="text" name="security_answer" required></label><br>
        <button type="submit">Update</button>
    </form>
</body>
</html>