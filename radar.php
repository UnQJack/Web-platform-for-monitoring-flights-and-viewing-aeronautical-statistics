<?php
session_start();
require_once 'db.php';
?>
<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<title>SkyTix - Radar</title>
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

.menu li.active a {
    background: #e4d09c;
    color: #1f1f1f;
    font-weight: 700;
}

.main {
    padding: 26px;
}

.page-title {
    font-size: 30px;
    font-weight: 900;
    margin-bottom: 24px;
}

.radar-grid {
    display: grid;
    grid-template-columns: 1.35fr 0.9fr;
    gap: 22px;
}

.map-card,
.info-card,
.table-card {
    background: white;
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.04);
}

#radarMap {
    height: 660px;
    width: 100%;
    border-radius: 20px;
    overflow: hidden;
}

.info-card {
    margin-bottom: 22px;
}

.info-title {
    font-size: 26px;
    font-weight: 900;
    margin-bottom: 16px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #ececec;
    gap: 20px;
}

.info-row span {
    color: #8a8a8a;
}

.info-row strong {
    text-align: right;
}

.radar-table {
    width: 100%;
    border-collapse: collapse;
}

.radar-table th,
.radar-table td {
    text-align: left;
    padding: 14px 12px;
    border-bottom: 1px solid #ececec;
    font-size: 15px;
}

.radar-table th {
    color: #8a8a8a;
    background: #fafafa;
}

.badge {
    display: inline-block;
    padding: 9px 14px;
    border-radius: 999px;
    font-weight: 900;
    font-size: 13px;
}

.detected {
    background: #e6f2df;
    color: #2f6b24;
}

.empty {
    color: #8a8a8a;
    font-size: 16px;
    padding: 14px 0;
}

.radar-icon {
    font-size: 24px;
}

.plane-icon {
    font-size: 26px;
}

@media (max-width: 1200px) {
    .radar-grid {
        grid-template-columns: 1fr;
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
                <li><a href="tracking.php">Urmarire Zboruri</a></li>
                <li><a href="telecom.php">Telecom</a></li>
                <li class="active"><a href="radar.php">Radar</a></li>
                <li><a href="ai_predictions.php">Predictii AI</a></li>
                <li><a href="deals.php">Oferte</a></li>
                <li><a href="anomaly_detection.php">Detectare Anomalii</a></li>
                <li><a href="alerts.php">Alerte</a></li>
            </ul>
        </aside>

        <main class="main">
            <div class="page-title">Radar</div>

            <div class="radar-grid">
                <div class="map-card">
                    <div id="radarMap"></div>
                </div>

                <div>
                    <div class="info-card">
                        <div class="info-title">Statii Radar</div>

                        <div class="info-row">
                            <span>Total statii</span>
                            <strong id="totalStations">0</strong>
                        </div>

                        <div class="info-row">
                            <span>Zboruri detectate</span>
                            <strong id="totalDetections">0</strong>
                        </div>

                        <div class="info-row">
                            <span>Frecventa simulata</span>
                            <strong>1030 / 1090 MHz</strong>
                        </div>

                        <div class="info-row">
                            <span>Raza implicita</span>
                            <strong>370 - 450 km</strong>
                        </div>
                    </div>

                    <div class="table-card">
                        <div class="info-title">Detectii Radar</div>

                        <table class="radar-table">
                            <thead>
                                <tr>
                                    <th>Zbor</th>
                                    <th>Statie</th>
                                    <th>Distanta</th>
                                    <th>Azimut</th>
                                    <th>Stare</th>
                                </tr>
                            </thead>

                            <tbody id="radarTableBody">
                                <tr>
                                    <td colspan="5" class="empty">Se incarca datele radar...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>

    </div>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>
const map = L.map('radarMap').setView([45.8, 24.9], 5);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap'
}).addTo(map);

const radarIcon = L.divIcon({
    html: '<div class="radar-icon">📡</div>',
    className: '',
    iconSize: [30, 30],
    iconAnchor: [15, 15]
});

const planeIcon = L.divIcon({
    html: '<div class="plane-icon">✈</div>',
    className: '',
    iconSize: [30, 30],
    iconAnchor: [15, 15]
});

let radarLayers = [];
let planeLayers = [];

function clearRadarLayers() {
    planeLayers.forEach(layer => map.removeLayer(layer));
    planeLayers = [];
}

function drawStations(stations) {
    if (radarLayers.length > 0) return;

    stations.forEach(station => {
        const lat = parseFloat(station.lat);
        const lon = parseFloat(station.lon);
        const range = parseFloat(station.range_km);

        if (!lat || !lon) return;

        const marker = L.marker([lat, lon], { icon: radarIcon })
            .addTo(map)
            .bindPopup(`
                <strong>${station.radar_name}</strong><br>
                Aeroport: ${station.iata_code} - ${station.city}<br>
                Raza: ${range} km<br>
                Frecventa: ${station.frequency}
            `);

        const circle = L.circle([lat, lon], {
            radius: range * 1000,
            color: '#d8b75b',
            fillColor: '#d8b75b',
            fillOpacity: 0.04,
            weight: 2
        }).addTo(map);

        radarLayers.push(marker, circle);
    });
}

function drawDetections(detections) {
    clearRadarLayers();

    detections.forEach(d => {
        const planeLat = parseFloat(d.current_lat);
        const planeLon = parseFloat(d.current_lon);
        const radarLat = parseFloat(d.radar_lat);
        const radarLon = parseFloat(d.radar_lon);

        const planeMarker = L.marker([planeLat, planeLon], { icon: planeIcon })
            .addTo(map)
            .bindPopup(`
                <strong>${d.flight_number}</strong><br>
                ${d.origin_code} → ${d.destination_code}<br>
                Radar: ${d.radar_name}<br>
                Distanta: ${parseFloat(d.distance_km) < 1 ? 'La sol' : d.distance_km + ' km'}<br>
                Azimut: ${parseFloat(d.distance_km) < 1 ? '-' : d.azimuth_deg + '°'}<br>
                Viteza: ${d.speed} km/h<br>
                Altitudine: ${d.altitude} m
            `);

        const line = L.polyline([[radarLat, radarLon], [planeLat, planeLon]], {
            color: '#1f1f1f',
            weight: 2,
            opacity: 0.65,
            dashArray: '6, 6'
        }).addTo(map);

        planeLayers.push(planeMarker, line);
    });
}

function updateTable(detections) {
    const tbody = document.getElementById('radarTableBody');

    if (detections.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="empty">
                    Nu exista zboruri aflate in desfasurare detectate de radar.
                </td>
            </tr>
        `;
        return;
    }

    let html = '';

    detections.forEach(d => {
        const distanceText = parseFloat(d.distance_km) < 1 ? 'La sol' : d.distance_km + ' km';
        const azimuthText = parseFloat(d.distance_km) < 1 ? '—' : d.azimuth_deg + '°';

        html += `
            <tr>
                <td>
                    <strong>${d.flight_number}</strong><br>
                    ${d.origin_code} → ${d.destination_code}
                </td>

                <td>${d.radar_name.replace('Radar Station', 'Statie Radar')}</td>
                <td>${distanceText}</td>
                <td>${azimuthText}</td>

                <td>
                    <span class="badge detected">
                        ${d.detection_status}
                    </span>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

async function loadRadarData() {
    try {
        const response = await fetch('radar_data.php');
        const data = await response.json();

        const stations = data.stations || [];
        const detections = data.detections || [];

        document.getElementById('totalStations').textContent = stations.length;
        document.getElementById('totalDetections').textContent = detections.length;

        drawStations(stations);
        drawDetections(detections);
        updateTable(detections);

    } catch (error) {
        document.getElementById('radarTableBody').innerHTML = `
            <tr>
                <td colspan="5" class="empty">
                    Eroare la incarcarea datelor radar.
                </td>
            </tr>
        `;
    }
}

loadRadarData();

setInterval(loadRadarData, 3000);
</script>

</body>
</html>