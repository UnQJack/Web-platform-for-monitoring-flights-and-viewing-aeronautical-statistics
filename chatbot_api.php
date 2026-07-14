<?php
header('Content-Type: application/json; charset=UTF-8');

$data = json_decode(file_get_contents("php://input"), true);
$message = trim($data['message'] ?? '');

if ($message === '') {
    echo json_encode([
        "status" => "error",
        "reply" => "Te rog scrie o intrebare."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$ch = curl_init("http://127.0.0.1:5055/ask");

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode([
        "message" => $message
    ]),
    CURLOPT_TIMEOUT => 90
]);

$response = curl_exec($ch);

if ($response === false) {
    echo json_encode([
        "status" => "error",
        "reply" => "Nu ma pot conecta la serviciul AI local."
    ], JSON_UNESCAPED_UNICODE);
    curl_close($ch);
    exit;
}

curl_close($ch);

$result = json_decode($response, true);

echo json_encode([
    "status" => $result['status'] ?? "ok",
    "reply" => $result['answer'] ?? "Nu am primit raspuns de la asistentul AI."
], JSON_UNESCAPED_UNICODE);
?>