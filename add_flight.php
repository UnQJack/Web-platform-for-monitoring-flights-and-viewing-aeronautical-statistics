<?php
session_start();
require_once 'db.php';
require_once 'add_notification.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: flights.php");
    exit;
}

$successMessage = '';
$errorMessage = '';

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
    $status = trim($_POST['status'] ?? '');

    $scheduled_dep = $_POST['scheduled_departure'] ?? '';
    $scheduled_arr = $_POST['scheduled_arrival'] ?? '';
    $estimated_dep = $_POST['estimated_departure'] ?? '';
    $estimated_arr = $_POST['estimated_arrival'] ?? '';
    $actual_dep = $_POST['actual_departure'] ?? '';
    $actual_arr = $_POST['actual_arrival'] ?? '';
    $base_price = (float)$_POST['base_price'];
    if (
        $flight_number &&
        $callsign &&
        $airline_id > 0 &&
        $origin_id > 0 &&
        $destination_id > 0 &&
        $origin_id !== $destination_id &&
        $status &&
        $scheduled_dep &&
        $scheduled_arr &&
        $estimated_dep &&
        $estimated_arr &&
        $actual_dep &&
        $actual_arr
    ) {
        $stmt = $conn->prepare("
            INSERT INTO flights (
                flight_number,
                callsign,
                airline_id,
                origin_airport_id,
                destination_airport_id,
                aircraft_id,
                status,
                scheduled_departure,
                scheduled_arrival,
                estimated_departure,
                estimated_arrival,
                actual_departure,
                actual_arrival,
                base_price
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssiiiisssssssd",
            $flight_number,
            $callsign,
            $airline_id,
            $origin_id,
            $destination_id,
            $aircraft_id,
            $status,
            $scheduled_dep,
            $scheduled_arr,
            $estimated_dep,
            $estimated_arr,
            $actual_dep,
            $actual_arr,
            $base_price
        );

        if ($stmt->execute()) {
            addNotification(
                $conn,
                'flights',
                'Inserare',
                'A fost adaugat zborul ' . $flight_number . '.'
            );
            $successMessage = "Zborul a fost adaugat cu succes!";
        } else {
            $errorMessage = "Eroare la inserare: " . $conn->error;
        }

        $stmt->close();
    } else {
        $errorMessage = "Completeaza toate campurile corect. Aeroportul de plecare trebuie sa fie diferit de cel de sosire.";
    }
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Adauga Zbor</title>
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
        }

        .wrapper {
            max-width: 920px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .card {
            background: #ffffff;
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.05);
        }

        .title {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .subtitle {
            color: #8a8a8a;
            margin-bottom: 22px;
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 14px;
        }

        label {
            font-weight: 700;
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
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

        .input-small {
            height: 34px;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 13px;
        }

        .input-date {
            height: 36px;
            padding: 6px 8px;
            border-radius: 8px;
            font-size: 13px;
            max-width: 100%;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 22px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            border-radius: 12px;
            padding: 12px 18px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            color: #1f1f1f;
        }

        .primary {
            background: #d8b75b;
        }

        .primary:hover {
            background: #cfaf52;
        }

        .secondary {
            background: #eeeeee;
            color: #1f1f1f;
            text-decoration: none;
        }

        .secondary:visited,
        .secondary:hover,
        .secondary:active {
            color: #1f1f1f;
            text-decoration: none;
        }

        .msg {
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-weight: 700;
        }

        .error {
            background: #fbe7e7;
            color: #8f2f2f;
        }

        .success {
            background: #e6f4ea;
            color: #2e6b3e;
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
<div class="wrapper">
    <div class="card">
        <div class="title">Adauga Zbor</div>
        <div class="subtitle">Introdu un zbor nou in sistem</div>

        <?php if ($errorMessage): ?>
            <div class="msg error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="msg success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Numarul Zborului</label>
                <input name="flight_number" class="input-small" required>
            </div>

            <div class="form-group">
                <label>Indicativ</label>
                <input name="callsign" class="input-small" required>
            </div>

            <div class="form-group">
                <label>Companie Aeriana</label>
                <select name="airline_id" required>
                    <option value="">Selecteaza</option>
                    <?php while ($a = $airlines->fetch_assoc()): ?>
                        <option value="<?= (int)$a['id'] ?>">
                            <?= htmlspecialchars($a['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="row">
                <div class="form-group">
                    <label>Plecare</label>
                    <select name="origin_airport_id" required>
                        <option value="">Selecteaza</option>
                        <?php $airports->data_seek(0); while ($a = $airports->fetch_assoc()): ?>
                            <option value="<?= (int)$a['id'] ?>">
                                <?= htmlspecialchars($a['iata_code']) ?> - <?= htmlspecialchars($a['city']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Sosire</label>
                    <select name="destination_airport_id" required>
                        <option value="">Selecteaza</option>
                        <?php $airports->data_seek(0); while ($a = $airports->fetch_assoc()): ?>
                            <option value="<?= (int)$a['id'] ?>">
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
                    <?php while ($a = $aircraft->fetch_assoc()): ?>
                        <option value="<?= (int)$a['id'] ?>">
                            <?= htmlspecialchars($a['model']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Pret de baza (RON)</label>
                <input type="number" name="base_price" step="0.01" min="0" required placeholder="ex: 299.99">
            </div>

            <div class="row">
                <div class="form-group">
                    <label>Plecare programata</label>
                    <input type="datetime-local" name="scheduled_departure" class="input-date" required>
                </div>

                <div class="form-group">
                    <label>Sosire programata</label>
                    <input type="datetime-local" name="scheduled_arrival" class="input-date" required>
                </div>
            </div>

            <div class="row">
                <div class="form-group">
                    <label>Plecare estimata</label>
                    <input type="datetime-local" name="estimated_departure" class="input-date" required>
                </div>

                <div class="form-group">
                    <label>Sosire estimata</label>
                    <input type="datetime-local" name="estimated_arrival" class="input-date" required>
                </div>
            </div>

            <div class="row">
                <div class="form-group">
                    <label>Plecare actuala</label>
                    <input type="datetime-local" name="actual_departure" class="input-date" required>
                </div>

                <div class="form-group">
                    <label>Sosire actuala</label>
                    <input type="datetime-local" name="actual_arrival" class="input-date" required>
                </div>
            </div>

            <div class="form-group">
                <label>Stare</label>
                <select name="status" required>
                    <option value="Active">Activ</option>
                    <option value="Completed">Complet</option>
                    <option value="Cancelled">Anulat</option>
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="btn primary">Salveaza</button>
                <a href="flights.php" class="btn secondary">Inapoi</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>