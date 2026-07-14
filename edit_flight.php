<?php
session_start();
require_once 'db.php';
require_once 'send_email_notification.php';
require_once 'add_notification.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: flights.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: flights.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM flights WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$flight = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$flight) {
    die("Zborul nu exista.");
}

$airlines = $conn->query("SELECT id, name FROM airlines ORDER BY name ASC");
$airports = $conn->query("SELECT id, iata_code, city FROM airports ORDER BY city ASC");
$aircraft = $conn->query("SELECT id, model FROM aircraft ORDER BY model ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $flight_number = trim($_POST['flight_number'] ?? '');
    $callsign = trim($_POST['callsign'] ?? '');
    $airline_id = (int)($_POST['airline_id'] ?? 0);
    $origin_id = (int)($_POST['origin_airport_id'] ?? 0);
    $destination_id = (int)($_POST['destination_airport_id'] ?? 0);
    $aircraft_id = !empty($_POST['aircraft_id']) ? (int)$_POST['aircraft_id'] : null;
    $base_price = (float)($_POST['base_price'] ?? 0);
    $scheduled_departure = $_POST['scheduled_departure'] ?? '';
    $scheduled_arrival = $_POST['scheduled_arrival'] ?? '';
    $estimated_departure = $_POST['estimated_departure'] ?? '';
    $estimated_arrival = $_POST['estimated_arrival'] ?? '';
    $actual_departure = $_POST['actual_departure'] ?? '';
    $actual_arrival = $_POST['actual_arrival'] ?? '';

    if (
        $flight_number &&
        $callsign &&
        $airline_id > 0 &&
        $origin_id > 0 &&
        $destination_id > 0 &&
        $origin_id !== $destination_id 
    ) {
        $stmt = $conn->prepare("
            UPDATE flights
            SET
                flight_number = ?,
                callsign = ?,
                airline_id = ?,
                origin_airport_id = ?,
                destination_airport_id = ?,
                aircraft_id = ?,
                scheduled_departure = ?,
                scheduled_arrival = ?,
                estimated_departure = ?,
                estimated_arrival = ?,
                actual_departure = ?,
                actual_arrival = ?,
                base_price = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "ssiiiissssssdi",
            $flight_number,
            $callsign,
            $airline_id,
            $origin_id,
            $destination_id,
            $aircraft_id,
            $scheduled_departure,
            $scheduled_arrival,
            $estimated_departure,
            $estimated_arrival,
            $actual_departure,
            $actual_arrival,
            $base_price,
            $id
        );

        if ($stmt->execute()) {
            addNotification(
                $conn,
                'flights',
                'Actualizare',
                'Zborul ' . $flight_number . ' a fost modificat.'
            );

            $emailSql = "
                SELECT
                    u.email,
                    u.name,
                    f.flight_number,
                    f.scheduled_departure,
                    f.scheduled_arrival,
                    f.estimated_departure,
                    f.estimated_arrival,
                    f.actual_departure,
                    f.actual_arrival,
                    a.name AS airline_name,
                    ao.iata_code AS origin_code,
                    ad.iata_code AS destination_code
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                JOIN flights f ON b.flight_id = f.id
                JOIN airlines a ON f.airline_id = a.id
                JOIN airports ao ON f.origin_airport_id = ao.id
                JOIN airports ad ON f.destination_airport_id = ad.id
                WHERE f.id = ?
            ";

            $emailStmt = $conn->prepare($emailSql);
            $emailStmt->bind_param("i", $id);
            $emailStmt->execute();
            $emailResult = $emailStmt->get_result();

            while ($emailData = $emailResult->fetch_assoc()) {
                $subject = "SkyTix - Zbor modificat";

                $message = "
                    <h2>Zborul tau a fost modificat</h2>
                    <p>Buna, " . htmlspecialchars($emailData['name']) . "!</p>
                    <p>Zborul asociat rezervarii tale a fost actualizat.</p>
                    <p><strong>Zbor:</strong> " . htmlspecialchars($emailData['flight_number']) . "</p>
                    <p><strong>Companie:</strong> " . htmlspecialchars($emailData['airline_name']) . "</p>
                    <p><strong>Ruta:</strong> " . htmlspecialchars($emailData['origin_code']) . " → " . htmlspecialchars($emailData['destination_code']) . "</p>
                    <p><strong>Plecare programata:</strong> " . htmlspecialchars($emailData['scheduled_departure']) . "</p>
                    <p><strong>Sosire programata:</strong> " . htmlspecialchars($emailData['scheduled_arrival']) . "</p>
                    <p><strong>Plecare estimata:</strong> " . htmlspecialchars($emailData['estimated_departure']) . "</p>
                    <p><strong>Sosire estimata:</strong> " . htmlspecialchars($emailData['estimated_arrival']) . "</p>
                    <p><strong>Plecare actuala:</strong> " . htmlspecialchars($emailData['actual_departure']) . "</p>
                    <p><strong>Sosire actuala:</strong> " . htmlspecialchars($emailData['actual_arrival']) . "</p>
                    <p>Multumim ca folosesti SkyTix!</p>
                ";

                sendEmailNotification(
                    $conn,
                    $emailData['email'],
                    $subject,
                    $message
                );
            }

            $emailStmt->close();

            header("Location: flights.php");
            exit;
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<title>Editare zbor</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f3efe4;
    padding: 40px;
}

.form-card {
    background: white;
    max-width: 980px;
    margin: auto;
    padding: 38px;
    border-radius: 28px;
}

h2 {
    font-size: 34px;
    margin-bottom: 28px;
}

.form-group {
    margin-bottom: 14px;
}

.row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

label {
    display: block;
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 6px;
}

input,
select {
    width: 100%;
    height: 40px;
    padding: 8px 12px;
    border-radius: 10px;
    border: 1px solid #dddddd;
    font-size: 14px;
    outline: none;
    transition: 0.2s ease;
    background: #ffffff;
}

input:focus,
select:focus {
    border-color: #d8b75b;
    box-shadow: 0 0 0 2px rgba(216, 183, 91, 0.22);
}

.input-date {
    height: 36px;
    padding: 6px 8px;
    border-radius: 8px;
    font-size: 13px;
    max-width: 100%;
    width: 476px;
}

.input-small {
    height: 34px;
    padding: 6px 10px;
    border-radius: 8px;
    font-size: 13px;
    width: 473px;
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

@media (max-width: 750px) {
    .row {
        grid-template-columns: 1fr;
        gap: 0;
    }
}
</style>
</head>

<body>

<div class="form-card">
    <h2>Editare zbor #<?= (int)$flight['id'] ?></h2>

    <form method="POST">
        <div class="row">
            <div class="form-group">
                <label>Numarul zborului</label>
                <input name="flight_number" class="input-small" value="<?= htmlspecialchars($flight['flight_number']) ?>" required>
            </div>

            <div class="form-group">
                <label>Indicativ</label>
                <input name="callsign" class="input-small" value="<?= htmlspecialchars($flight['callsign']) ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Companie aeriana</label>
            <select name="airline_id" required>
                <?php while ($a = $airlines->fetch_assoc()): ?>
                    <option value="<?= (int)$a['id'] ?>" <?= (int)$flight['airline_id'] === (int)$a['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a['name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="row">
            <div class="form-group">
                <label>Plecare</label>
                <select name="origin_airport_id" required>
                    <?php $airports->data_seek(0); while ($a = $airports->fetch_assoc()): ?>
                        <option value="<?= (int)$a['id'] ?>" <?= (int)$flight['origin_airport_id'] === (int)$a['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['iata_code']) ?> - <?= htmlspecialchars($a['city']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Sosire</label>
                <select name="destination_airport_id" required>
                    <?php $airports->data_seek(0); while ($a = $airports->fetch_assoc()): ?>
                        <option value="<?= (int)$a['id'] ?>" <?= (int)$flight['destination_airport_id'] === (int)$a['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['iata_code']) ?> - <?= htmlspecialchars($a['city']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Aeronava</label>
            <select name="aircraft_id">
                <option value="">—</option>
                <?php while ($ac = $aircraft->fetch_assoc()): ?>
                    <option value="<?= (int)$ac['id'] ?>" <?= (int)$flight['aircraft_id'] === (int)$ac['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ac['model']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Pret de baza (RON)</label>
            <input type="number" step="0.01" min="0" name="base_price" value="<?= htmlspecialchars($flight['base_price']) ?>" required>
        </div>

        <div class="row">
            <div class="form-group">
                <label>Plecare programata</label>
                <input type="datetime-local" name="scheduled_departure" class="input-date" value="<?= date('Y-m-d\TH:i', strtotime($flight['scheduled_departure'])) ?>">
            </div>

            <div class="form-group">
                <label>Sosire programata</label>
                <input type="datetime-local" name="scheduled_arrival" class="input-date" value="<?= date('Y-m-d\TH:i', strtotime($flight['scheduled_arrival'])) ?>">
            </div>
        </div>

        <div class="row">
            <div class="form-group">
                <label>Plecare estimata</label>
                <input type="datetime-local" name="estimated_departure" class="input-date" value="<?= date('Y-m-d\TH:i', strtotime($flight['estimated_departure'])) ?>">
            </div>

            <div class="form-group">
                <label>Sosire estimata</label>
                <input type="datetime-local" name="estimated_arrival" class="input-date" value="<?= date('Y-m-d\TH:i', strtotime($flight['estimated_arrival'])) ?>">
            </div>
        </div>

        <div class="row">
            <div class="form-group">
                <label>Plecare actuala</label>
                <input type="datetime-local" name="actual_departure" class="input-date" value="<?= date('Y-m-d\TH:i', strtotime($flight['actual_departure'])) ?>">
            </div>

            <div class="form-group">
                <label>Sosire actuala</label>
                <input type="datetime-local" name="actual_arrival" class="input-date" value="<?= date('Y-m-d\TH:i', strtotime($flight['actual_arrival'])) ?>">
            </div>
        </div>

        <button type="submit">Salveaza modificarile</button>
        <a href="flights.php" class="back-btn">Inapoi</a>
    </form>
</div>

</body>
</html>