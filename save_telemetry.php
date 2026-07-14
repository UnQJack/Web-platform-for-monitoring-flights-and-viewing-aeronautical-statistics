<?php
session_start();
require_once 'db.php';
require_once 'add_notification.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$flight_id = (int)($data['flight_id'] ?? 0);

if ($flight_id <= 0) {
    echo json_encode(["status" => "error", "message" => "flight_id invalid"]);
    exit;
}

$signal_strength = rand(-95, -45);
$latency_ms = rand(40, 250);
$packet_loss = rand(0, 500) / 100;

$bands = ['VHF', 'ADS-B', 'SATCOM', 'LTE Ground Station'];
$frequency_band = $bands[array_rand($bands)];

if ($signal_strength > -65 && $packet_loss < 1.5 && $latency_ms < 120) {
    $connection_status = 'Stabil';
} elseif ($signal_strength > -85 && $packet_loss < 3.5) {
    $connection_status = 'Instabil';
} else {
    $connection_status = 'Deconectat';
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
    INSERT INTO telemetry (
        flight_id,
        recorded_at,
        signal_strength,
        latency_ms,
        packet_loss,
        frequency_band,
        connection_status
    )
    VALUES (?, NOW(), ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "iiidss",
    $flight_id,
    $signal_strength,
    $latency_ms,
    $packet_loss,
    $frequency_band,
    $connection_status
);

$statusRo = $connectionStatus;

if ($connectionStatus === 'Stable') {
    $statusRo = 'Stabil';
} elseif ($connectionStatus === 'Weak') {
    $statusRo = 'Instabil';
} elseif ($connectionStatus === 'Lost') {
    $statusRo = 'Deconectat';
}

if ($stmt->execute()) {
    addNotification(
        $conn,
        'telemetry',
        'Actualizare',
        'Au fost actualizati parametrii telecom pentru zborul cu ID #' . $flight_id .
        ': semnal ' . $signal_strength . ' dBm, latenta ' . $latency_ms .
        ' ms, pierdere pachete ' . $packet_loss .
        '%, banda ' . $frequency_band .
        ', stare conexiune ' . $statusRo . '.'
    );

    echo json_encode([
        "status" => "ok",
        "signal_strength" => $signal_strength,
        "latency_ms" => $latency_ms,
        "packet_loss" => $packet_loss,
        "frequency_band" => $frequency_band,
        "connection_status" => $connection_status
    ]);
} else {
    echo json_encode(["status" => "error", "message" => $stmt->error]);
}

$stmt->close();
?>