<?php
ob_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');

$host = 'localhost:3307';
$user = 'root';
$password = '';
$database = 'moviedb';
$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

session_start();

$step = 1;
$security_question = '';
$username = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log("POST data: " . print_r($_POST, true));
    if ($_POST['step'] == 1) {
        $username = trim($_POST['username']);
        $stmt = $conn->prepare("SELECT security_question FROM admin WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($security_question);
            $stmt->fetch();
            $step = 2;
        } else {
            $error = "Admin not found.";
        }
        $stmt->close();
    } elseif ($_POST['step'] == 2) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $security_answer_input = trim($_POST['security_answer']);

        $stmt = $conn->prepare("SELECT password, security_answer FROM admin WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($hashed_password, $hashed_answer);
            $stmt->fetch();

            error_log("Password verify: " . (password_verify($password, $hashed_password) ? "Valid" : "Invalid"));
            error_log("Answer verify: " . (password_verify($security_answer_input, $hashed_answer) ? "Valid" : "Invalid"));

            if (password_verify($password, $hashed_password) && password_verify($security_answer_input, $hashed_answer)) {
                $_SESSION['admin_username'] = $username;
                $_SESSION['admin_logged_in'] = true;
                error_log("Redirecting to admin_portal.php");
                header("Location: admin_portal.php");
                exit();
            } else {
                $error = "Incorrect password or security answer.";
                $step = 1;
            }
        } else {
            $error = "User not found.";
            $step = 1;
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
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
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
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
            position: absolute;
            top: 0;
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

        .logo span:nth-child(n) {
            animation-delay: calc(0.1s * var(--i));
        }

        h2 {
            text-align: center;
            font-size: 28px;
            margin: 20px 0;
            color: #e50914;
            text-shadow: 1px 1px 4px rgba(0, 0, 0, 0.7);
        }

        .error {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
            font-size: 16px;
            background: #dc3545;
            color: #fff;
        }

        form {
            background: rgba(255, 255, 255, 0.1);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            width: 300px;
            text-align: center;
            z-index: 1;
        }

        label {
            display: block;
            font-size: 16px;
            margin-bottom: 8px;
            color: #f0f0f0;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: none;
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            font-size: 16px;
        }

        input[type="submit"] {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 5px;
            background: #e50914;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
        }

        input[type="submit"]:hover {
            background: #f40612;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(229, 9, 20, 0.5);
        }

        a {
            display: block;
            margin-top: 10px;
            color: #1f8dd6;
            text-decoration: none;
            font-size: 14px;
        }

        a:hover {
            color: #2a9df4;
        }

        .back-btn {
            display: block;
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            border: none;
            border-radius: 5px;
            background: #1f8dd6;
            color: #fff;
            font-size: 16px;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
        }

        .back-btn:hover {
            background: #2a9df4;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(31, 141, 214, 0.5);
        }

        @media (max-width: 600px) {
            .logo {
                font-size: 36px;
            }

            h2 {
                font-size: 24px;
            }

            form {
                width: 90%;
                padding: 20px;
            }

            header {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <span style="--i:1">A</span><span style="--i:2">d</span><span style="--i:3">m</span><span style="--i:4">i</span><span style="--i:5">n</span>
            <span style="--i:6"> </span><span style="--i:7">O</span><span style="--i:8">n</span><span style="--i:9">l</span><span style="--i:10">y</span>
        </div>
    </header>
    <form method="POST">
        <h2>Admin Login</h2>
        <?php if (isset($error)) echo "<p class='error'>" . htmlspecialchars($error) . "</p>"; ?>
        <?php if ($step == 1): ?>
            <input type="hidden" name="step" value="1">
            <input type="text" name="username" placeholder="Enter Username" value="<?php echo htmlspecialchars($username); ?>" required>
            <input type="submit" value="Next">
        <?php elseif ($step == 2): ?>
            <input type="hidden" name="step" value="2">
            <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
            <input type="password" name="password" placeholder="Enter Password" required>
            <label><strong>Security Question:</strong><br><?php echo htmlspecialchars($security_question); ?></label><br><br>
            <input type="text" name="security_answer" placeholder="Your Answer" required>
            <input type="submit" value="Login">
        <?php endif; ?>
        <a href="admin_signup.php">Don't have an account? Signup</a>
        <a href="mainpage.html" class="back-btn">Back to Main Page</a>
    </form>
</body>
</html>
<?php ob_end_flush(); ?>