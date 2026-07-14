<?php
session_start();
require_once 'db.php';
require_once 'send_email_notification.php';
require_once 'add_notification.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: bookings.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: bookings.php");
    exit;
}

$bookingResult = $conn->query("SELECT * FROM bookings WHERE id = $id");

if ($bookingResult->num_rows === 0) {
    die("Rezervarea nu exista.");
}

$booking = $bookingResult->fetch_assoc();

$users = $conn->query("
    SELECT id, name, email
    FROM users
    ORDER BY name ASC
");

$flights = $conn->query("
    SELECT
        f.id,
        f.flight_number,
        f.base_price,
        a.name AS airline_name,
        ao.iata_code AS origin_code,
        ad.iata_code AS destination_code
    FROM flights f
    JOIN airlines a ON f.airline_id = a.id
    JOIN airports ao ON f.origin_airport_id = ao.id
    JOIN airports ad ON f.destination_airport_id = ad.id
    where f.status IN ('Scheduled', 'Active')
    ORDER BY f.flight_number ASC
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $flight_id = (int)($_POST['flight_id'] ?? 0);
    if ($flight_id <= 0) {
        die("Zbor invalid.");
    }

    $stmt = $conn->prepare("
        UPDATE bookings
        SET flight_id = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ii", $flight_id, $id);

    if ($stmt->execute()) {
        addNotification(
            $conn,
            'bookings',
            'Actualizare',
            'Rezervarea cu ID #' . $id . ' a fost modificata.'
        );

        $emailSql = "
            SELECT
                u.email,
                u.name,
                f.flight_number,
                a.name AS airline_name,
                ao.iata_code AS origin_code,
                ad.iata_code AS destination_code
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN flights f ON b.flight_id = f.id
            JOIN airlines a ON f.airline_id = a.id
            JOIN airports ao ON f.origin_airport_id = ao.id
            JOIN airports ad ON f.destination_airport_id = ad.id
            WHERE b.id = ?
        ";

        $emailStmt = $conn->prepare($emailSql);
        $emailStmt->bind_param("i", $id);
        $emailStmt->execute();
        $emailData = $emailStmt->get_result()->fetch_assoc();
        $emailStmt->close();

        if ($emailData) {
            $subject = "SkyTix - Rezervare modificata";
            $message = "
                <h2>Rezervarea ta a fost modificata</h2>
                <p>Buna, " . htmlspecialchars($emailData['name']) . "!</p>
                <p>Zborul asociat rezervarii tale a fost actualizat.</p>
                <p><strong>Zbor nou:</strong> " . htmlspecialchars($emailData['flight_number']) . "</p>
                <p><strong>Companie:</strong> " . htmlspecialchars($emailData['airline_name']) . "</p>
                <p><strong>Ruta:</strong> " . htmlspecialchars($emailData['origin_code']) . " → " . htmlspecialchars($emailData['destination_code']) . "</p>
                <p>Daca nu ai facut tu aceasta modificare, te rugam sa ne contactezi imediat.</p>
                <p>Multumim ca ai ales SkyTix!</p>";
            sendEmailNotification(
                $conn,
                $emailData['email'],
                $subject,
                $message
            );
        }
    }
    header("Location: bookings.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<title>Editare rezervare</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f3efe4;
    padding: 40px;
}

.form-card {
    background: white;
    max-width: 760px;
    margin: auto;
    padding: 38px;
    border-radius: 28px;
}

h2 {
    font-size: 34px;
    margin-bottom: 28px;
}

.form-group {
    margin-bottom: 22px;
}

label {
    display: block;
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 10px;
}

input,
select {
    width: 100%;
    padding: 16px;
    border-radius: 8px;
    border: 1px solid #aaa;
    font-size: 17px;
}

.info-box {
    background: #f7f7f7;
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 22px;
}

.info-box div {
    margin-bottom: 8px;
    font-size: 16px;
}

button,
.back-btn {
    background: #d8b75b;
    border: none;
    padding: 16px 24px;
    border-radius: 14px;
    font-weight: 800;
    cursor: pointer;
    text-decoration: none;
    color: #1f1f1f;
    display: inline-block;
    margin-right: 10px;
}

.back-btn {
    background: #eeeeee;
}
</style>
</head>

<body>

<div class="form-card">
    <h2>Editare rezervare #<?= (int)$booking['id'] ?></h2>

    <form method="POST">
        <div class="form-group">
            <label>Zbor / Companie / Ruta</label>
            <select name="flight_id" id="flightSelect" required>
                <?php while ($f = $flights->fetch_assoc()): ?>
                    <option
                        value="<?= (int)$f['id'] ?>"
                        data-price="<?= htmlspecialchars($f['base_price']) ?>"
                        data-company="<?= htmlspecialchars($f['airline_name']) ?>"
                        data-route="<?= htmlspecialchars($f['origin_code'] . ' → ' . $f['destination_code']) ?>"
                        <?= (int)$booking['flight_id'] === (int)$f['id'] ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($f['flight_number']) ?>
                        -
                        <?= htmlspecialchars($f['airline_name']) ?>
                        -
                        <?= htmlspecialchars($f['origin_code']) ?> → <?= htmlspecialchars($f['destination_code']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <button type="submit">Salveaza modificarile</button>
        <a href="bookings.php" class="back-btn">Inapoi</a>
    </form>
</div>
</body>
</html>