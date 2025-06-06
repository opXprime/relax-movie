<?php
ob_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');

session_start();

$host = 'localhost:3307';
$user = 'root';
$password = '';
$database = 'moviedb';
$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log("POST data: " . print_r($_POST, true));
    $username = trim($_POST['username']);
    $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
    $security_question = trim($_POST['security_question']);
    $security_answer = password_hash(trim($_POST['security_answer']), PASSWORD_DEFAULT);
    $special_code = trim($_POST['special_code']);

    if ($special_code !== "11111111") {
        $error = "Invalid security code. Contact the respective department.";
    } else {
        $stmt = $conn->prepare("SELECT username FROM admin WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "Username already exists. Please choose a different username.";
            $stmt->close();
        } else {
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO admin (username, password, security_question, security_answer) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $password, $security_question, $security_answer);
            if ($stmt->execute()) {
                $_SESSION['admin_username'] = $username;
                $_SESSION['admin_logged_in'] = true;
                error_log("Signup successful, redirecting to admin_portal.php");
                header("Location: admin_portal.php");
                exit();
            } else {
                $error = "Signup failed: " . $conn->error;
                error_log("Signup error: " . $conn->error);
            }
            $stmt->close();
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Signup</title>
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
            text-shadow: 1px 1px 4px rgba(0, 0, 0, 0.7);
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

        .info {
            font-size: 14px;
            color: #f0f0f0;
            margin: 10px 0;
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
        
    </header>
    <form method="POST">
        <h2>Admin Signup</h2>
        <?php if ($error) echo "<p class='error'>" . htmlspecialchars($error) . "</p>"; ?>
        <p class="info">Contact the respective department to get the security code.</p>
        <input type="text" name="username" placeholder="Username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="text" name="security_question" placeholder="Security Question" value="<?php echo isset($_POST['security_question']) ? htmlspecialchars($_POST['security_question']) : ''; ?>" required>
        <input type="text" name="security_answer" placeholder="Security Answer" required>
        <input type="text" name="special_code" placeholder="Security Code" required>
        <input type="submit" value="Signup">
        <a href="admin.php">Already have an account? Login</a>
        <p class="info">If you are not the admin, kindly go to the <a href="login.html">user login page</a>.</p>
    </form>
</body>
</html>
<?php ob_end_flush(); ?>