<?php
session_start();
require_once 'db.php';

$flightsSql = "
    SELECT
        f.id,
        f.flight_number,
        f.callsign,
        f.status,
        f.scheduled_departure,
        f.scheduled_arrival,
        f.estimated_departure,
        f.estimated_arrival,
        f.actual_departure,
        f.actual_arrival,
        a.name AS airline_name,
        ac.model AS aircraft_model,
        ac.registration,
        ao.iata_code AS origin_code,
        ao.city AS origin_city,
        ao.lat AS origin_lat,
        ao.lon AS origin_lon,
        ad.iata_code AS destination_code,
        ad.city AS destination_city,
        ad.lat AS destination_lat,
        ad.lon AS destination_lon
    FROM flights f
    JOIN airlines a ON f.airline_id = a.id
    LEFT JOIN aircraft ac ON f.aircraft_id = ac.id
    JOIN airports ao ON f.origin_airport_id = ao.id
    JOIN airports ad ON f.destination_airport_id = ad.id
    WHERE f.status = 'Active'
    ORDER BY f.actual_departure ASC
";

$result = $conn->query($flightsSql);
$flights = [];

while ($row = $result->fetch_assoc()) {
    $flights[] = $row;
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>SkyTix - Urmarire Zboruri</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">

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
            max-width: 1450px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .dashboard-shell {
            background: #efeded;
            border-radius: 30px;
            display: grid;
            grid-template-columns: 240px 1fr;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0,0,0,0.05);
        }

        .sidebar {
            background: #f7f7f7;
            padding: 28px 20px;
            min-height: 850px;
        }

        .sidebar-logo {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 30px;
        }

        .menu {
            list-style: none;
        }

        .menu li {
            margin-bottom: 12px;
        }

        .menu li a {
            display: block;
            text-decoration: none;
            color: #5a5a5a;
            font-weight: 500;
            padding: 14px 16px;
            border-radius: 14px;
        }

        .menu li a:hover {
            background: #e8e8e8;
        }

        .menu li.active a {
            background: #e4d09c;
            color: #1f1f1f;
            font-weight: 700;
        }

        .main {
            padding: 26px;
        }

        .topbar {
            margin-bottom: 24px;
        }

        .topbar h2 {
            font-size: 26px;
        }

        .tracking-grid {
            display: grid;
            grid-template-columns: 330px 1fr 330px;
            gap: 20px;
            min-height: 720px;
        }

        .panel,
        .map-card,
        .details-card {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.04);
        }

        .panel {
            padding: 20px;
        }

        .panel-title {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 16px;
        }

        .flight-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
            max-height: 660px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .flight-item {
            background: #f7f7f7;
            border: 2px solid transparent;
            border-radius: 18px;
            padding: 16px;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .flight-item:hover,
        .flight-item.active {
            border-color: #d8b75b;
            background: #fff8df;
        }

        .flight-top {
            display: flex;
            justify-content: space-between;
            margin-bottom: 14px;
            font-weight: 700;
        }

        .route-big {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 28px;
            font-weight: 900;
        }

        .plane-line {
            flex: 1;
            height: 2px;
            background: #d8b75b;
            margin: 0 12px;
            position: relative;
        }

        .plane-line::after {
            content: "✈";
            position: absolute;
            left: 50%;
            top: -12px;
            transform: translateX(-50%);
            color: #1f1f1f;
            font-size: 18px;
        }

        .city-small {
            display: flex;
            justify-content: space-between;
            color: #8a8a8a;
            font-size: 13px;
            margin-top: 6px;
        }

        .map-card {
            padding: 14px;
        }

        #map {
            height: 692px;
            width: 100%;
            border-radius: 20px;
            overflow: hidden;
        }

        .details-card {
            overflow: hidden;
        }

        .details-header {
            background: #1f1f1f;
            color: #ffffff;
            padding: 18px;
        }

        .details-header h3 {
            color: #d8b75b;
            font-size: 24px;
            margin-bottom: 6px;
        }

        .details-body {
            padding: 20px;
        }

        .route-details {
            display: grid;
            grid-template-columns: 1fr 40px 1fr;
            align-items: center;
            text-align: center;
            margin-bottom: 20px;
        }

        .airport-code {
            font-size: 36px;
            font-weight: 900;
        }

        .airport-city {
            color: #8a8a8a;
            font-size: 14px;
        }

        .plane-circle {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #d8b75b;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: auto;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 13px 0;
            border-bottom: 1px solid #ececec;
            font-size: 15px;
        }

        .info-row span:first-child {
            color: #8a8a8a;
        }

        .info-row strong {
            text-align: right;
        }

        .time-grid-card {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-top: 1px solid #ececec;
            border-bottom: 1px solid #ececec;
            margin-top: 12px;
        }

        .time-grid-card div {
            padding: 13px 10px;
            border-bottom: 1px solid #ececec;
        }

        .time-grid-card div:nth-child(odd) {
            border-right: 1px solid #ececec;
        }

        .time-grid-card span {
            display: block;
            color: #8a8a8a;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .time-grid-card strong {
            display: block;
            font-size: 16px;
            font-weight: 800;
        }

        .empty-card {
            color: #8a8a8a;
            font-size: 15px;
            padding: 20px;
        }

        .map-plane-icon {
            font-size: 28px;
            filter: drop-shadow(0 2px 3px rgba(0,0,0,0.3));
        }

        @media (max-width: 1200px) {
            .tracking-grid {
                grid-template-columns: 1fr;
            }

            #map {
                height: 520px;
            }
        }

        @media (max-width: 850px) {
            .dashboard-shell {
                grid-template-columns: 1fr;
            }

            .sidebar {
                min-height: auto;
            }
        }
    </style>
</head>

<body>
<div class="page-wrapper">
    <div class="dashboard-shell">
        <aside class="sidebar">
            <div class="sidebar-logo">✈ SkyTix</div>
            <ul class="menu">
                <li><a href="home.php">Pagina Principala</a></li>
                <li><a href="bookings.php">Rezervari</a></li>
                <li><a href="flights.php">Zboruri</a></li>
                <li><a href="payments.php">Plati</a></li>
                <li><a href="messages.php">Mesaje</a></li>
                <li class="active"><a href="tracking.php">Urmarire Zboruri</a></li>
                <li><a href="telecom.php">Telecom</a></li>
                <li><a href="radar.php">Radar</a></li>
                <li><a href="ai_predictions.php">Predictii AI</a></li>
                <li><a href="deals.php">Oferte</a></li>
                <li><a href="anomaly_detection.php">Detectare Anomalii</a></li>
                <li><a href="alerts.php">Alerte</a></li>
            </ul>
        </aside>

        <main class="main">
            <div class="topbar">
                <h2>Urmarire Zboruri</h2>
            </div>

            <div class="tracking-grid">
                <div class="panel">
                    <div class="panel-title">Zboruri Active</div>

                    <?php if (!empty($flights)): ?>
                        <div class="flight-list">
                            <?php foreach ($flights as $index => $flight): ?>
                                <div class="flight-item <?= $index === 0 ? 'active' : '' ?>" data-index="<?= $index ?>">
                                    <div class="flight-top">
                                        <span><?= htmlspecialchars($flight['flight_number']) ?></span>
                                        <span><?= htmlspecialchars($flight['registration'] ?? 'N/A') ?></span>
                                    </div>

                                    <div class="route-big">
                                        <span><?= htmlspecialchars($flight['origin_code']) ?></span>
                                        <span class="plane-line"></span>
                                        <span><?= htmlspecialchars($flight['destination_code']) ?></span>
                                    </div>

                                    <div class="city-small">
                                        <span><?= htmlspecialchars($flight['origin_city']) ?></span>
                                        <span><?= htmlspecialchars($flight['destination_city']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-card">Nu exista zboruri active pentru urmarire.</div>
                    <?php endif; ?>
                </div>

                <div class="map-card">
                    <div id="map"></div>
                </div>

                <div class="details-card">
                    <div class="details-header">
                        <h3 id="detailFlightNumber">Zbor</h3>
                        <div id="detailAirline">Companie aeriana</div>
                    </div>

                    <div class="details-body">
                        <div class="route-details">
                            <div>
                                <div class="airport-code" id="detailOrigin">---</div>
                                <div class="airport-city" id="detailOriginCity">---</div>
                            </div>

                            <div class="plane-circle">✈</div>

                            <div>
                                <div class="airport-code" id="detailDestination">---</div>
                                <div class="airport-city" id="detailDestinationCity">---</div>
                            </div>
                        </div>

                        <div class="info-row">
                            <span>Stare</span>
                            <strong id="detailStatus">---</strong>
                        </div>

                        <div class="info-row">
                            <span>Aeronava</span>
                            <strong id="detailAircraft">---</strong>
                        </div>

                        <div class="info-row">
                            <span>Inmatriculare</span>
                            <strong id="detailRegistration">---</strong>
                        </div>

                        <div class="info-row">
                            <span>Viteza</span>
                            <strong id="detailSpeed">0 km/h</strong>
                        </div>

                        <div class="info-row">
                            <span>Altitudine</span>
                            <strong id="detailAltitude">0 m</strong>
                        </div>

                        <div class="info-row">
                            <span>Distanta totala</span>
                            <strong id="detailDistance">---</strong>
                        </div>

                        <div class="info-row">
                            <span>Distanta ramasa</span>
                            <strong id="detailRemainingDistance">---</strong>
                        </div>

                        <div class="time-grid-card">
                            <div>
                                <span>Plecare programata</span>
                                <strong id="detailScheduledDeparture">---</strong>
                            </div>

                            <div>
                                <span>Plecare actuala</span>
                                <strong id="detailActualDeparture">---</strong>
                            </div>

                            <div>
                                <span>Sosire programata</span>
                                <strong id="detailScheduledArrival">---</strong>
                            </div>

                            <div>
                                <span>Sosire estimata</span>
                                <strong id="detailEstimatedArrival">---</strong>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>
const flights = <?= json_encode($flights) ?>;

const map = L.map('map').setView([44.5, 26.0], 5);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap'
}).addTo(map);

let routeLayer = null;
let travelledLayer = null;
let planeMarker = null;
let originMarker = null;
let destinationMarker = null;

let selectedFlightIndex = 0;
let selectedFlight = flights.length > 0 ? flights[0] : null;

const planeIcon = L.divIcon({
    html: '<div class="map-plane-icon">✈</div>',
    className: '',
    iconSize: [30, 30],
    iconAnchor: [15, 15]
});

function formatDateTime(value) {
    if (!value) return 'N/A';

    const date = new Date(value.replace(' ', 'T'));

    return date.toLocaleString('ro-RO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function toDate(value) {
    if (!value) return null;
    return new Date(value.replace(' ', 'T'));
}

function distanceKm(lat1, lon1, lat2, lon2) {
    const R = 6371;

    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;

    const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(lat1 * Math.PI / 180) *
        Math.cos(lat2 * Math.PI / 180) *
        Math.sin(dLon / 2) * Math.sin(dLon / 2);

    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return R * c;
}

function calculateLiveData(flight) {
    const start = toDate(flight.actual_departure);
    const end = toDate(flight.estimated_arrival);
    const now = new Date();

    let progress = 0;
    let speed = 0;
    let altitude = 0;

    if (start && end && end > start) {
        if (now < start) {
            progress = 0;
            speed = 0;
            altitude = 0;
        } else if (now >= end) {
            progress = 1;
            speed = 0;
            altitude = 0;
        } else {
            progress = (now - start) / (end - start);
            progress = Math.max(0, Math.min(1, progress));

            if (progress < 0.15) {
                altitude = Math.round(11000 * (progress / 0.15));
                speed = Math.round(250 + 600 * (progress / 0.15));
            } else if (progress < 0.80) {
                altitude = 11000;
                speed = 850;
            } else {
                const descentProgress = (progress - 0.80) / 0.20;
                altitude = Math.round(11000 * (1 - descentProgress));
                speed = Math.round(850 - 550 * descentProgress);
            }
        }
    }

    const originLat = parseFloat(flight.origin_lat);
    const originLon = parseFloat(flight.origin_lon);
    const destLat = parseFloat(flight.destination_lat);
    const destLon = parseFloat(flight.destination_lon);

    const currentLat = originLat + (destLat - originLat) * progress;
    const currentLon = originLon + (destLon - originLon) * progress;

    const totalDistance = distanceKm(originLat, originLon, destLat, destLon);
    const travelledDistance = totalDistance * progress;
    const remainingDistance = totalDistance - travelledDistance;                    

    return {
        totalDistance,
        travelledDistance,
        remainingDistance,
        progress,
        speed,
        altitude,
        currentLat,
        currentLon
    };
}

function clearMap() {
    if (routeLayer) map.removeLayer(routeLayer);
    if (travelledLayer) map.removeLayer(travelledLayer);
    if (planeMarker) map.removeLayer(planeMarker);
    if (originMarker) map.removeLayer(originMarker);
    if (destinationMarker) map.removeLayer(destinationMarker);

    routeLayer = null;
    travelledLayer = null;
    planeMarker = null;
    originMarker = null;
    destinationMarker = null;
}

function drawFlight(flight, fit = false) {
    if (!flight) return;

    const live = calculateLiveData(flight);

    const origin = [
        parseFloat(flight.origin_lat),
        parseFloat(flight.origin_lon)
    ];

    const destination = [
        parseFloat(flight.destination_lat),
        parseFloat(flight.destination_lon)
    ];

    const current = [
        live.currentLat,
        live.currentLon
    ];

    if (!routeLayer) {
        routeLayer = L.polyline([origin, destination], {
            color: '#d8b75b',
            weight: 4,
            opacity: 0.55
        }).addTo(map);

        travelledLayer = L.polyline([origin, current], {
            color: '#1f1f1f',
            weight: 4,
            opacity: 0.85
        }).addTo(map);

        originMarker = L.circleMarker(origin, {
            radius: 7,
            color: '#1f1f1f',
            fillColor: '#d8b75b',
            fillOpacity: 1
        }).addTo(map).bindPopup('Plecare: ' + flight.origin_code);

        destinationMarker = L.circleMarker(destination, {
            radius: 7,
            color: '#1f1f1f',
            fillColor: '#ffffff',
            fillOpacity: 1
        }).addTo(map).bindPopup('Destinatie: ' + flight.destination_code);

        planeMarker = L.marker(current, {
            icon: planeIcon
        }).addTo(map);
    } else {
        travelledLayer.setLatLngs([origin, current]);
        animatePlane(current);
    }

    planeMarker.bindPopup(`
        <strong>${flight.flight_number}</strong><br>
        ${flight.origin_code} → ${flight.destination_code}<br>
        Viteza: ${live.speed} km/h<br>
        Altitudine: ${live.altitude} m
    `);

    updateDetails(flight, live);

    if (fit) {
        map.fitBounds(routeLayer.getBounds(), {
            padding: [50, 50]
        });
    }
}

function animatePlane(newLatLng) {
    if (!planeMarker) return;

    const start = planeMarker.getLatLng();
    const end = L.latLng(newLatLng[0], newLatLng[1]);
    const duration = 900;
    const startTime = performance.now();

    function animate(time) {
        const t = Math.min((time - startTime) / duration, 1);

        const lat = start.lat + (end.lat - start.lat) * t;
        const lng = start.lng + (end.lng - start.lng) * t;

        planeMarker.setLatLng([lat, lng]);

        if (t < 1) {
            requestAnimationFrame(animate);
        }
    }

    requestAnimationFrame(animate);
}

function updateDetails(flight, live) {
    document.getElementById('detailFlightNumber').textContent = flight.flight_number || 'Zbor';
    document.getElementById('detailAirline').textContent = flight.airline_name || 'Companie aeriana';

    document.getElementById('detailOrigin').textContent = flight.origin_code || '---';
    document.getElementById('detailDestination').textContent = flight.destination_code || '---';

    document.getElementById('detailOriginCity').textContent = flight.origin_city || '---';
    document.getElementById('detailDestinationCity').textContent = flight.destination_city || '---';

    let statusRo = flight.status;

    if (flight.status === 'Active') {
        statusRo = 'Activ';
    }
    else if (flight.status === 'Completed') {
        statusRo = 'Finalizat';
    }
    else if (flight.status === 'Cancelled') {
        statusRo = 'Anulat';
    }
    
    document.getElementById('detailStatus').textContent = statusRo || '---';
    document.getElementById('detailAircraft').textContent = flight.aircraft_model || 'N/A';
    document.getElementById('detailRegistration').textContent = flight.registration || 'N/A';

    document.getElementById('detailSpeed').textContent = live.speed + ' km/h';
    document.getElementById('detailAltitude').textContent = live.altitude + ' m';

    document.getElementById('detailScheduledDeparture').textContent = formatDateTime(flight.scheduled_departure);
    document.getElementById('detailActualDeparture').textContent = formatDateTime(flight.actual_departure);
    document.getElementById('detailScheduledArrival').textContent = formatDateTime(flight.scheduled_arrival);
    document.getElementById('detailEstimatedArrival').textContent = formatDateTime(flight.estimated_arrival);

    document.getElementById('detailDistance').textContent =
        Math.round(live.totalDistance) + ' km';

    document.getElementById('detailRemainingDistance').textContent =
        Math.round(live.remainingDistance) + ' km';                    
}

function selectFlight(index) {
    selectedFlightIndex = index;
    selectedFlight = flights[index];

    document.querySelectorAll('.flight-item').forEach(item => {
        item.classList.remove('active');
    });

    const selectedItem = document.querySelector(`.flight-item[data-index="${index}"]`);
    if (selectedItem) {
        selectedItem.classList.add('active');
    }

    clearMap();
    drawFlight(selectedFlight, true);
}

function calculateHeading(lat1, lon1, lat2, lon2) {
    const phi1 = lat1 * Math.PI / 180;
    const phi2 = lat2 * Math.PI / 180;
    const deltaLon = (lon2 - lon1) * Math.PI / 180;

    const y = Math.sin(deltaLon) * Math.cos(phi2);
    const x =
        Math.cos(phi1) * Math.sin(phi2) -
        Math.sin(phi1) * Math.cos(phi2) * Math.cos(deltaLon);

    return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
}

function savePositionToDatabase(flight, live) {
    const heading = calculateHeading(
        live.currentLat,
        live.currentLon,
        parseFloat(flight.destination_lat),
        parseFloat(flight.destination_lon)
    );
    fetch('save_position.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            flight_id: flight.id,
            lat: live.currentLat,
            lon: live.currentLon,
            altitude: live.altitude,
            speed: live.speed,
            heading: Math.round(heading),
            vertical_speed: null
        })
    });
}

const flightList = document.querySelector('.flight-list');

if (flightList) {
    flightList.addEventListener('click', function(e) {
        const item = e.target.closest('.flight-item');
        if (!item) return;

        const index = parseInt(item.dataset.index, 10);
        selectFlight(index);
    });
}

if (flights.length > 0) {
    selectFlight(0);
}

setInterval(() => {

    flights.forEach(flight => {

        const live = calculateLiveData(flight);

        if (selectedFlight && selectedFlight.id === flight.id) {
            drawFlight(selectedFlight, false);
        }

        savePositionToDatabase(flight, live);

        fetch('save_telemetry.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                flight_id: flight.id
            })
        });

    });

}, 3000);



</script>

</body>
</html>