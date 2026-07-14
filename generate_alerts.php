<?php
session_start();
require_once 'db.php';

function calculateDelayMinutes($scheduledArrival, $estimatedArrival) {
    if (!$scheduledArrival || !$estimatedArrival) {
        return 0;
    }

    $scheduled = strtotime($scheduledArrival);
    $estimated = strtotime($estimatedArrival);

    return max(0, round(($estimated - $scheduled) / 60));
}

function insertAlert($conn, $flightId, $priority, $score, $message) {
    $stmt = $conn->prepare("
        INSERT INTO alerts (flight_id, priority, score, message, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");

    if (!$stmt) {
        die("Eroare prepare INSERT: " . $conn->error);
    }

    $stmt->bind_param("isis", $flightId, $priority, $score, $message);
    $stmt->execute();
    $stmt->close();
}

function callPredictionApi($payload) {
    $ch = curl_init("http://127.0.0.1:5050/predict_delay");

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        curl_close($ch);
        return null;
    }

    curl_close($ch);

    return json_decode($response, true);
}

function callAnomalyApi($payload) {
    $ch = curl_init("http://127.0.0.1:5050/detect_anomaly");

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        curl_close($ch);
        return null;
    }

    curl_close($ch);

    return json_decode($response, true);
}

$conn->query("DELETE FROM alerts");

$sql = "
    SELECT
        f.id,
        f.flight_number,
        f.status,
        f.scheduled_departure,
        f.scheduled_arrival,
        f.estimated_arrival,
        f.base_price,

        a.name AS airline_name,

        ao.iata_code AS origin_code,
        ad.iata_code AS destination_code,

        COALESCE(r.distance_km, 0) AS distance_km,

        COALESCE(bc.bookings_count, 0) AS bookings_count,

        COALESCE(t.signal_strength, -70) AS signal_strength,
        COALESCE(t.latency_ms, 100) AS latency_ms,
        COALESCE(t.packet_loss, 1.0) AS packet_loss,

        COALESCE(p.altitude, 0) AS altitude,
        COALESCE(p.speed, 0) AS speed

    FROM flights f

    JOIN airlines a 
        ON f.airline_id = a.id

    JOIN airports ao 
        ON f.origin_airport_id = ao.id

    JOIN airports ad 
        ON f.destination_airport_id = ad.id

    LEFT JOIN routes r 
        ON r.origin_airport_id = f.origin_airport_id
        AND r.destination_airport_id = f.destination_airport_id

    LEFT JOIN (
        SELECT 
            flight_id,
            COUNT(*) AS bookings_count
        FROM bookings
        GROUP BY flight_id
    ) bc 
        ON bc.flight_id = f.id

    LEFT JOIN (
        SELECT t1.*
        FROM telemetry t1
        INNER JOIN (
            SELECT flight_id, MAX(id) AS max_id
            FROM telemetry
            GROUP BY flight_id
        ) t2
            ON t1.flight_id = t2.flight_id
            AND t1.id = t2.max_id
    ) t 
        ON f.id = t.flight_id

    LEFT JOIN (
        SELECT p1.*
        FROM positions p1
        INNER JOIN (
            SELECT flight_id, MAX(id) AS max_id
            FROM positions
            GROUP BY flight_id
        ) p2
            ON p1.flight_id = p2.flight_id
            AND p1.id = p2.max_id
    ) p 
        ON f.id = p.flight_id

    WHERE f.status IN ('Active', 'Cancelled')
";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

$generated = 0;

while ($flight = $result->fetch_assoc()) {
    $flightId = (int)$flight["id"];
    $flightNumber = $flight["flight_number"];

    $realDelay = calculateDelayMinutes(
        $flight["scheduled_arrival"],
        $flight["estimated_arrival"]
    );

    if ($flight["status"] === "Cancelled") {
        insertAlert(
            $conn,
            $flightId,
            "CRITICAL",
            100,
            "Zbor {$flightNumber}: Zborul a fost anulat"
        );
        $generated++;
        continue;
    }

    if ($realDelay >= 90) {
        insertAlert(
            $conn,
            $flightId,
            "HIGH",
            80,
            "Zbor {$flightNumber}: Zborul are o intarziere majora de {$realDelay} minute"
        );
        $generated++;
    } elseif ($realDelay >= 30) {
        insertAlert(
            $conn,
            $flightId,
            "MEDIUM",
            50,
            "Zbor {$flightNumber}: Zborul are o intarziere moderata de {$realDelay} minute"
        );
        $generated++;
    }

    $payload = [
        "airline" => $flight["airline_name"],
        "origin" => $flight["origin_code"],
        "destination" => $flight["destination_code"],
        "departure_hour" => (int)date("H", strtotime($flight["scheduled_departure"])),
        "weekday" => (int)date("N", strtotime($flight["scheduled_departure"])),
        "distance_km" => (float)$flight["distance_km"],
        "base_price" => (float)$flight["base_price"],
        "bookings_count" => (int)$flight["bookings_count"],
        "signal_strength" => (float)$flight["signal_strength"],
        "latency_ms" => (float)$flight["latency_ms"],
        "packet_loss" => (float)$flight["packet_loss"],
        "actual_delay_minutes" => $realDelay
    ];

    $savedPrediction = $_SESSION['ai_predictions'][$flightId] ?? null;

    if ($savedPrediction) {
        $predictedDelay = (float)$savedPrediction['delay_minutes'];
        $probability = (float)$savedPrediction['delay_probability'];
        $riskLevel = trim((string)$savedPrediction['risk_level']);
    } else {
        $prediction = callPredictionApi($payload);

        if ($prediction && isset($prediction["status"]) && $prediction["status"] === "ok") {
            $predictedDelay = round((float)($prediction["delay_minutes"] ?? 0), 1);
            $probability = (float)($prediction["delay_probability"] ?? 0);
            $riskLevel = trim((string)($prediction["risk_level"] ?? ''));

            if ($probability <= 1) {
                $probability *= 100;
            }

            $probability = round($probability, 1);

            $_SESSION['ai_predictions'][$flightId] = [
                'delay_probability' => $probability,
                'delay_minutes' => $predictedDelay,
                'risk_level' => $riskLevel,
                'created_at' => date('Y-m-d H:i:s')
            ];
        } else {
            $predictedDelay = 0;
            $probability = 0;
            $riskLevel = '';
        }
    }

    $predictionError = abs($realDelay - $predictedDelay);

    if (
        strtolower($riskLevel) === "ridicat" ||
        $probability >= 65 ||
        $predictedDelay >= 15 ||
        $predictionError >= 30
    ) {
        insertAlert(
            $conn,
            $flightId,
            "HIGH",
            90,
            "Predictie AI pentru zbor {$flightNumber}: modelul Random Forest estimeaza risc ridicat de intarziere. Probabilitate: " .
            round($probability, 1) . "%, intarziere estimata: " .
            round($predictedDelay, 1) . " minute. Intarzierea calculata este de {$realDelay} minute. Diferenta: " .
            round($predictionError, 1) . " minute."
        );

        $generated++;
    }

    $anomalyPayload = [
        "signal_strength" => (float)$flight["signal_strength"],
        "latency_ms" => (float)$flight["latency_ms"],
        "packet_loss" => (float)$flight["packet_loss"],
        "altitude" => (float)$flight["altitude"],
        "speed" => (float)$flight["speed"]
        ];

    $anomaly = callAnomalyApi($anomalyPayload);

    if ($anomaly && isset($anomaly["status"]) && $anomaly["status"] === "ok") {
        if ($anomaly["is_anomaly"] === true) {
            $error = $anomaly["reconstruction_error"];
            $threshold = $anomaly["threshold"];

            insertAlert(
                $conn,
                $flightId,
                "HIGH",
                95,
                "Anomalie AI pentru zbor {$flightNumber}: reteaua neuronala Autoencoder a detectat valori tehnice neobisnuite. Eroare reconstructie: {$error}, prag: {$threshold}."
            );

            $generated++;
        }
    }
}

$stmt->close();

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    echo json_encode([
        "status" => "ok",
        "generated" => $generated
    ]);
    exit;
}

header("Location: alerts.php");
exit;
?>