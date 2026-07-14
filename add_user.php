<?php
session_start();
require_once 'db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];

    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $error = "Exista deja un cont cu acest email.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO users (name, email, password_hash, role)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("ssss", $name, $email, $password, $role);

        if ($stmt->execute()) {
            $message = "Cont creat cu succes!";
        } else {
            $error = "Eroare la creare cont.";
        }

        $stmt->close();
    }

    $check->close();
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
</style>

</head>
<body>

<div class="card">

    <div class="title">Adauga Utilizator Nou</div>
    <div class="subtitle">Creeaza un nou cont de utilizator</div>

    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="success"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST">

        <div class="form-group">
            <label>Nume</label>
            <input type="text" name="name" required>
        </div>

        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required>
        </div>

        <div class="form-group">
            <label>Parola</label>
            <input type="password" name="password" required>
        </div>

        <div class="form-group">
            <label>Rol</label>
            <select name="role">
                <option value="user">Utilizator</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Adauga Utilizator Nou</button>
        <a href="add_booking.php" class="btn btn-secondary">Inapoi</a>
    </form>

</div>

</body>
</html>