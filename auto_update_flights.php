<?php
require_once 'db.php';
require_once 'add_notification.php';

$conn->query("SET time_zone = '+03:00'");

$conn->query("
    UPDATE flights
    SET status = 'Active'
    WHERE actual_departure IS NOT NULL
      AND estimated_arrival IS NOT NULL
      AND NOW() BETWEEN actual_departure AND estimated_arrival
      AND status NOT IN ('Active', 'Cancelled')
");

$conn->query("
    UPDATE flights
    SET status = 'Completed'
    WHERE estimated_arrival IS NOT NULL
      AND NOW() > estimated_arrival
      AND status NOT IN ('Completed', 'Cancelled')
");

$completedChanged = $conn->affected_rows;

if ($completedChanged > 0) {
    addNotification(
        $conn,
        'flights',
        'Automatizare',
        $completedChanged . ' zboruri au fost trecute automat in stare Finalizat.'
    );
}