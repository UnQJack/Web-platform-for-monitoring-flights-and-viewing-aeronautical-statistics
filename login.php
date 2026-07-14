<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'db.php';

$error = '';
$showRegister = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, name, email, password_hash, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {

        if (password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            header("Location: home.php");
            exit;
        } else {
            $error = "Parola gresita";
        }

    } else {
        $error = "Utilizatorul nu exista";
        $showRegister = true;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SkyTix</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f3efe4;
            color: #1f1f1f;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-card {
            width: 100%;
            max-width: 460px;
            background: #ffffff;
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
        }

        .login-title {
            font-size: 34px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .login-subtitle {
            color: #8a8a8a;
            margin-bottom: 24px;
        }

        .error-box {
            background: #fbe7e7;
            color: #8f2f2f;
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 18px;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #ddd;
            border-radius: 12px;
            outline: none;
            font-size: 15px;
        }

        .form-group input:focus {
            border-color: #d8b75b;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            width: 100%;
            margin-top: 8px;
        }

        .btn-primary {
            background: #d8b75b;
            color: #1f1f1f;
        }

        .btn-secondary {
            background: #efefef;
            color: #1f1f1f;
        }

        .btn-primary:hover {
            background: #cfaf52;
        }

        .btn-secondary:hover {
            background: #e6e6e6;
        }

        .register-box {
            margin-top: 10px;
            margin-bottom: 18px;
            font-size: 14px;
            color: #6b6b6b;
        }

        .register-link {
            font-weight: 700;
            color: #d8b75b;
            text-decoration: none;
            margin-left: 6px;
        }

        .register-link:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="login-title">Autentificare</div>
        <div class="login-subtitle">Intra in platforma SkyTix</div>

        <?php if (!empty($error)): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>

            <?php if ($showRegister): ?>
                <div class="register-box">
                    Nu ai cont?
                    <a href="register.php" class="register-link">Creeaza unul nou</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="email">Email</label>
                <input 
                    type="email" 
                    name="email" 
                    id="email" 
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Parola</label>
                <input 
                    type="password" 
                    name="password" 
                    id="password" 
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary">
                Autentificare
            </button>

            <a href="home.php" class="btn btn-secondary">
                Inapoi
            </a>
        </form>
    </div>
</body>
</html>