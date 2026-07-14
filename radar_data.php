<?php
require_once 'db.php';
require_once 'add_notification.php';

header('Content-Type: application/json; charset=UTF-8');

$conn->query("SET time_zone = '+03:00'");

function distanceKm($lat1, $lon1, $lat2, $lon2) {
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) ** 2 +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) ** 2;

    return $R * (2 * atan2(sqrt($a), sqrt(1 - $a)));
}

function azimuthDeg($lat1, $lon1, $lat2, $lon2) {
    $lat1 = deg2rad($lat1);
    $lat2 = deg2rad($lat2);
    $dLon = deg2rad($lon2 - $lon1);

    $y = sin($dLon) * cos($lat2);
    $x = cos($lat1) * sin($lat2) -
         sin($lat1) * cos($lat2) * cos($dLon);

    return fmod((rad2deg(atan2($y, $x)) + 360), 360);
}

function getDetectionStatus($distanceKm, $rangeKm, $altitude, $speed) {
    if ($altitude <= 0 && $speed <= 0) {
        return 'La sol';
    }

    if ($distanceKm > $rangeKm) {
        return 'In afara razei';
    }

    if ($altitude < 1000 && $speed < 250) {
        return 'Aterizare';
    }

    if ($altitude < 1500 && $speed >= 250) {
        return 'Decolare';
    }

    if ($speed < 80) {
        return 'Viteza redusa';
    }

    if ($distanceKm < 20) {
        return 'Foarte aproape de radar';
    }

    if ($distanceKm < 120) {
        return 'Urmarire activa';
    }

    if ($distanceKm >= $rangeKm * 0.8) {
        return 'Iese din raza radarului';
    }

    return 'Detectat';
}

$stationsResult = $conn->query("
    SELECT
        rs.id,
        rs.radar_name,
        rs.lat,
        rs.lon,
        rs.range_km,
        rs.frequency,
        a.iata_code,
        a.city
    FROM radar_stations rs
    JOIN airports a ON rs.airport_id = a.id
    ORDER BY rs.radar_name ASC
");

$radarStations = [];

while ($row = $stationsResult->fetch_assoc()) {
    $radarStations[] = $row;
}

$flightsSql = "
    SELECT
        f.id,
        f.flight_number,
        f.status,
        f.actual_departure,
        f.estimated_arrival,
        a.name AS airline_name,
        ao.iata_code AS origin_code,
        ad.iata_code AS destination_code,
        p.lat AS current_lat,
        p.lon AS current_lon,
        p.altitude,
        p.speed,
        p.recorded_at AS position_time
    FROM flights f
    JOIN airlines a ON f.airline_id = a.id
    JOIN airports ao ON f.origin_airport_id = ao.id
    JOIN airports ad ON f.destination_airport_id = ad.id
    JOIN (
        SELECT p1.*
        FROM positions p1
        JOIN (
            SELECT flight_id, MAX(id) AS max_id
            FROM positions
            GROUP BY flight_id
        ) p2 ON p1.flight_id = p2.flight_id
            AND p1.id = p2.max_id
    ) p ON f.id = p.flight_id
    WHERE f.status = 'Active'
      AND f.actual_departure IS NOT NULL
      AND f.estimated_arrival IS NOT NULL
      AND NOW() BETWEEN f.actual_departure AND f.estimated_arrival
    ORDER BY f.flight_number ASC
";

$result = $conn->query($flightsSql);
$detections = [];

while ($flight = $result->fetch_assoc()) {
    $currentLat = (float)$flight['current_lat'];
    $currentLon = (float)$flight['current_lon'];

    $nearestStation = null;
    $bestDistance = null;
    $bestAzimuth = null;

    foreach ($radarStations as $station) {
        $distance = distanceKm(
            (float)$station['lat'],
            (float)$station['lon'],
            $currentLat,
            $currentLon
        );

        $azimuth = azimuthDeg(
            (float)$station['lat'],
            (float)$station['lon'],
            $currentLat,
            $currentLon
        );

        if ($distance <= (float)$station['range_km']) {
            if ($bestDistance === null || $distance < $bestDistance) {
                $nearestStation = $station;
                $bestDistance = $distance;
                $bestAzimuth = $azimuth;
            }
        }
    }

    if ($nearestStation) {
        $distanceRounded = round($bestDistance, 2);
        $azimuthRounded = round($bestAzimuth, 2);

        $detectionStatus = getDetectionStatus(
            $distanceRounded,
            (float)$nearestStation['range_km'],
            (int)$flight['altitude'],
            (int)$flight['speed']
        );

        $checkStmt = $conn->prepare("
            SELECT id
            FROM radar
            WHERE flight_id = ?
                AND radar_name = ?
                AND detected_at = ?
            LIMIT 1    
        ");

        $checkStmt->bind_param(
            "iss",
            $flight['id'],
            $nearestStation['radar_name'],
            $flight['position_time']
        );

        $checkStmt->execute();
        $alreadyLogged = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($alreadyLogged) {
            $updateStmt = $conn->prepare("
                UPDATE radar
                SET 
                    distance_km = ?,
                    azimuth_deg = ?,
                    detection_status = ?
                WHERE id = ?
            ");

            $updateStmt->bind_param(
                "ddsi",
                $distanceRounded,
                $azimuthRounded,
                $detectionStatus,
                $alreadyLogged['id']
            );

            $updateStmt->execute();
            $updateStmt->close();
        }

        if (!$alreadyLogged) {
            $insertStmt = $conn->prepare("
                INSERT INTO radar (
                    flight_id,
                    radar_name,
                    distance_km,
                    azimuth_deg,
                    detection_status,
                    detected_at
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $insertStmt->bind_param(
                "isddss",
                $flight['id'],
                $nearestStation['radar_name'],
                $distanceRounded,
                $azimuthRounded,
                $detectionStatus,
                $flight['position_time']
            );

            $insertStmt->execute();
            addNotification(
                $conn,
                'radar',
                'Detectie radar',
                'Zborul ' . $flight['flight_number'] .
                ' a fost detectat de ' . $nearestStation['radar_name'] .
                '. Distanta: ' . $distanceRounded .
                ' km, azimut: ' . $azimuthRounded . '°.'
            );
            $insertStmt->close();
        }
        $detections[] = [
            'flight_id' => (int)$flight['id'],
            'flight_number' => $flight['flight_number'],
            'airline_name' => $flight['airline_name'],
            'origin_code' => $flight['origin_code'],
            'destination_code' => $flight['destination_code'],
            'current_lat' => $currentLat,
            'current_lon' => $currentLon,
            'altitude' => (int)$flight['altitude'],
            'speed' => (int)$flight['speed'],
            'position_time' => $flight['position_time'],
            'radar_name' => $nearestStation['radar_name'],
            'radar_lat' => (float)$nearestStation['lat'],
            'radar_lon' => (float)$nearestStation['lon'],
            'range_km' => (float)$nearestStation['range_km'],
            'frequency' => $nearestStation['frequency'],
            'distance_km' => $distanceRounded,
            'azimuth_deg' => $azimuthRounded,
            'detection_status' => $detectionStatus
        ];
    }
}

echo json_encode([
    'stations' => $radarStations,
    'detections' => $detections
], JSON_UNESCAPED_UNICODE);