<?php
function addNotification($conn, $tableName, $actionType, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    $stmt = $conn->prepare("
        INSERT INTO notifications (
            user_id,
            table_name,
            action_type,
            message,
            created_at
        )
        VALUES (?, ?, ?, ?, NOW())
    ");

    $stmt->bind_param("isss", $userId, $tableName, $actionType, $message);
    $stmt->execute();
    $stmt->close();
}