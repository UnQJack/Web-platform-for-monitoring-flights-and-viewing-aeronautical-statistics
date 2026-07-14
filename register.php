<?php
session_start();
require_once 'db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    if (empty($name) || empty($email) || empty($password)) {
        $error = "Toate campurile sunt obligatorii";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email invalid";
    } elseif (strlen($password) < 4) {
        $error = "Parola trebuie sa aiba cel putin 4 caractere";
    } elseif (!in_array($role, ['user', 'admin'])) {
        $error = "Rol invalid";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $error = "Exista deja un cont cu acest email";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO users (name, email, password_hash, role)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->bind_param("ssss", $name, $email, $password_hash, $role);

            if ($stmt->execute()) {
                $message = "Cont creat cu succes! Te poti autentifica";
            } else {
                $error = "Eroare la creare cont.";
            }

            $stmt->close();
        }

        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Register - SkyTix</title>

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

        .card {
            width: 100%;
            max-width: 460px;
            background: #ffffff;
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
        }

        .title {
            font-size: 34px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #8a8a8a;
            margin-bottom: 24px;
        }

        .error {
            background: #fbe7e7;
            color: #8f2f2f;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-weight: 600;
        }

        .success {
            background: #e6f4ea;
            color: #1e6b3a;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            font-weight: 700;
            margin-bottom: 6px;
            display: block;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            border: 1px solid #ddd;
            outline: none;
            font-size: 15px;
        }

        .form-group input:focus,
        .form-group select:focus {
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
            margin-top: 8px;
        }

        .btn-primary {
            background: #d8b75b;
            color: #1f1f1f;
        }

        .btn-secondary {
            background: #efefef;
            color: #1f1f1f;
            margin-left: 8px;
        }

        .btn-primary:hover {
            background: #cfaf52;
        }

        .btn-secondary:hover {
            background: #e6e6e6;
        }

        .back-login {
            margin-top: 15px;
            text-align: center;
            font-size: 14px;
        }

        .back-login a {
            color: #d8b75b;
            font-weight: 700;
            text-decoration: none;
        }

        .back-login a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

<div class="card">

    <div class="title">Creeaza cont</div>
    <div class="subtitle">Inregistreaza-te in SkyTix</div>

    <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
        <div class="success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST">

        <div class="form-group">
            <label for="name">Nume</label>
            <input 
                type="text" 
                name="name" 
                id="name" 
                required
            >
        </div>

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

        <div class="form-group">
            <label for="role">Rol</label>
            <select name="role" id="role">
                <option value="user">Utilizator</option>
                <option value="admin">Administrator</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">
            Creeaza cont
        </button>

        <a href="login.php" class="btn btn-secondary">
            Inapoi
        </a>
    </form>

    <div class="back-login">
        Ai deja cont? <a href="login.php">Autentificare</a>
    </div>

</div>

</body>
</html>