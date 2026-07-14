<?php
session_start();
require_once 'db.php';
require_once 'add_notification.php';
require_once 'send_email_notification.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: bookings.php");
    exit;
}

$successMessage = '';
$errorMessage = '';

$usersSql = "SELECT id, name, email FROM users ORDER BY name ASC";
$usersStmt = $conn->query($usersSql);
$users = [];

while ($row = $usersStmt->fetch_assoc()) {
    $users[] = $row;
}

$flightsSql = "
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
    WHERE f.status = 'Active'
    ORDER BY f.flight_number ASC
";
$flightsStmt = $conn->query($flightsSql);
$flights = [];

while ($row = $flightsStmt->fetch_assoc()) {
    $flights[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $flight_id = isset($_POST['flight_id']) ? (int)$_POST['flight_id'] : 0;
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';

    if ($flight_id > 0 && $user_id > 0 && $status !== '') {
        $capacitySql = "
            SELECT
                ac.seat_capacity,
                COUNT(b.id) AS total_bookings
            FROM flights f
            JOIN aircraft ac ON f.aircraft_id = ac.id
            LEFT JOIN bookings b ON f.id = b.flight_id
            WHERE f.id = ?
            GROUP BY ac.seat_capacity
        ";

        $capacityStmt = $conn->prepare($capacitySql);
        $capacityStmt->bind_param("i", $flight_id);
        $capacityStmt->execute();

        $capacityData = $capacityStmt->get_result()->fetch_assoc();
        $capacityStmt->close();

        if (!$capacityData) {
            $errorMessage = "Nu exista aeronava asociata zborului.";
        }
        else {
            $seatCapacity = (int)$capacityData['seat_capacity'];
            $totalBookings = (int)$capacityData['total_bookings'];

            if ($totalBookings >= $seatCapacity) {
                $errorMessage = "Nu mai exista locuri disponibile pentru acest zbor.";
            }
            else {
                $priceStmt = $conn->prepare("SELECT base_price FROM flights WHERE id = ?");
                $priceStmt->bind_param("i", $flight_id);
                $priceStmt->execute();
                $priceResult = $priceStmt->get_result();
                $flightData = $priceResult->fetch_assoc();
                $priceStmt->close();

                if ($flightData && (float)$flightData['base_price'] > 0) {

                    $price = (float)$flightData['base_price'];

                    $stmt = $conn->prepare("
                        INSERT INTO bookings (
                            flight_id, 
                            user_id, 
                            price, 
                            status, 
                            created_at
                        )
                        VALUES (?, ?, ?, ?, NOW())
                    ");

                    $stmt->bind_param("iids", $flight_id, $user_id, $price, $status);

                    if ($stmt->execute()) {

                        addNotification(
                            $conn,
                            'bookings',
                            'Inserare',
                            'A fost adaugata o rezervare pentru zborul cu ID #' . $flight_id . '.'
                        );

                        $successMessage = "Rezervarea a fost adaugata cu succes.";

                        $userEmailStmt = $conn->prepare("
                            SELECT email, name
                            FROM users
                            WHERE id = ?
                        ");

                        $userEmailStmt->bind_param("i", $user_id);
                        $userEmailStmt->execute();

                        $userData = $userEmailStmt->get_result()->fetch_assoc();

                        $userEmailStmt->close();

                        $statusRo = $status;

                        if ($status === 'Paid') {
                            $statusRo = 'Platita';
                        }
                        elseif ($status === 'Pending') {
                            $statusRo = 'In asteptare';
                        }
                        elseif ($status === 'Cancelled') {
                            $statusRo = 'Anulata';
                        }

                        if ($userData) {

                            $subject = "SkyTix - Rezervare adaugata";

                            $emailMessage =
                                "Buna, " . $userData['name'] . "!\n\n" .
                                "Rezervarea ta a fost adaugata cu succes.\n" .
                                "ID zbor: #" . $flight_id . ".\n" .
                                "Pret: " . number_format($price, 2) . " RON.\n" .
                                "Stare: " . $statusRo . ".\n" .
                                "Multumim ca folosesti SkyTix.";

                                sendEmailNotification(
                                    $conn,
                                    $userData['email'],
                                    $subject,
                                    $emailMessage
                            );
                        }

                    }
                    else {
                        $errorMessage = "Eroare la inserare: " . $conn->error;
                    }
                    $stmt->close();
                }
                else {
                    $errorMessage = "Zborul selectat nu are pret definit in baza de date.";
                }
            }
        }
    }
    else {
        $errorMessage = "Completeaza toate campurile corect.";
    }
}

?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adauga Rezervare</title>
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

        .page-wrapper {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .form-card {
            background: white;
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.05);
        }

        .form-title {
            font-size: 30px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .form-subtitle {
            color: #8a8a8a;
            margin-bottom: 24px;
        }

        .message {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 18px;
            font-weight: 600;
        }

        .success {
            background: #eef6df;
            color: #3f5f1b;
        }

        .error {
            background: #fbe7e7;
            color: #8f2f2f;
        }

        .form-grid {
            display: grid;
            gap: 18px;
        }

        .form-group label {
            display: block;
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 16px;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 15px;
            outline: none;
        }

        .helper-row {
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .helper-text {
            color: #8a8a8a;
            font-size: 14px;
        }

        .helper-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #d8b75b;
            color: #1f1f1f;
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            transition: 0.2s ease;
        }

        .helper-link:hover {
            background: #cfaf52;
        }

        .price-preview {
            margin-top: 10px;
            padding: 12px 14px;
            border-radius: 12px;
            background: #f6f1df;
            color: #7a5a1a;
            font-size: 14px;
            font-weight: 700;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
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
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="form-card">
            <div class="form-title">Adauga Rezervare</div>
            <div class="form-subtitle">Introdu o rezervare noua in baza de date</div>

            <?php if ($successMessage): ?>
                <div class="message success"><?= htmlspecialchars($successMessage) ?></div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
                <div class="message error"><?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="user_id">Utilizator</label>
                        <select name="user_id" id="user_id" required>
                            <option value="">Selecteaza utilizator</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= (int)$user['id'] ?>">
                                    <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="helper-row">
                            <span class="helper-text">Nu apare utilizatorul in lista?</span>
                            <a href="add_user.php?redirect=add_booking.php" class="helper-link">Adauga utilizator nou</a>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="flight_id">Zbor</label>
                        <select name="flight_id" id="flight_id" required>
                            <option value="">Selecteaza zbor</option>
                            <?php foreach ($flights as $flight): ?>
                                <option 
                                    value="<?= (int)$flight['id'] ?>"
                                    data-price="<?= htmlspecialchars($flight['base_price']) ?>"
                                >
                                    <?= htmlspecialchars($flight['flight_number']) ?> -
                                    <?= htmlspecialchars($flight['airline_name']) ?> -
                                    <?= htmlspecialchars($flight['origin_code']) ?> → <?= htmlspecialchars($flight['destination_code']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div id="pricePreview" class="price-preview" style="display:none;">
                            Pret: <span id="priceValue"></span> RON
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="status">Stare</label>
                        <select name="status" id="status" required>
                            <option value="">Selecteaza stare</option>
                            <option value="Paid">Platita</option>
                            <option value="Pending">In asteptare</option>
                            <option value="Cancelled">Anulata</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Salveaza rezervarea</button>
                    <a href="bookings.php" class="btn btn-secondary">Inapoi</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const flightSelect = document.getElementById('flight_id');
        const pricePreview = document.getElementById('pricePreview');
        const priceValue = document.getElementById('priceValue');

        function updatePricePreview() {
            const selectedOption = flightSelect.options[flightSelect.selectedIndex];
            const price = selectedOption.getAttribute('data-price');

            if (price && flightSelect.value !== '') {
                priceValue.textContent = parseFloat(price).toFixed(2);
                pricePreview.style.display = 'block';
            } else {
                pricePreview.style.display = 'none';
            }
        }

        flightSelect.addEventListener('change', updatePricePreview);
        updatePricePreview();
    </script>
</body>
</html>