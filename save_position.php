<?php
require_once 'db.php';
require_once 'add_notification.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$flight_id = (int)($data['flight_id'] ?? 0);
$lat = (float)($data['lat'] ?? 0);
$lon = (float)($data['lon'] ?? 0);
$altitude = (int)($data['altitude'] ?? 0);
$speed = (int)($data['speed'] ?? 0);
$heading = (int)($data['heading'] ?? 90);

if ($flight_id <= 0) {
    echo json_encode(["status" => "error", "message" => "flight_id invalid"]);
    exit;
}

$check = $conn->prepare("
    SELECT id
    FROM flights
    WHERE id = ?
      AND status = 'Active'
      AND actual_departure IS NOT NULL
      AND estimated_arrival IS NOT NULL
      AND NOW() BETWEEN actual_departure AND estimated_arrival
");

$check->bind_param("i", $flight_id);
$check->execute();
$validFlight = $check->get_result()->fetch_assoc();
$check->close();

if (!$validFlight) {
    echo json_encode([
        "status" => "ignored",
        "message" => "Zborul nu este in desfasurare."
    ]);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO positions (
        flight_id,
        recorded_at,
        lat,
        lon,
        altitude,
        speed,
        heading
    )
    VALUES (?, NOW(), ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "iddiii",
    $flight_id,
    $lat,
    $lon,
    $altitude,
    $speed,
    $heading
);

if ($stmt->execute()) {
    addNotification(
        $conn,
        'positions',
        'Inserare',
        'A fost salvata o pozitie noua pentru zborul cu ID #' . $flight_id .
        ' (lat: ' . round($lat, 4) .
        ', lon: ' . round($lon, 4) .
        ', altitudine: ' . $altitude .
        ' m, viteza: ' . $speed . ' km/h).'
    );

    echo json_encode(["status" => "ok"]);
} else {
    echo json_encode(["status" => "error", "message" => $stmt->error]);
}

$stmt->close();