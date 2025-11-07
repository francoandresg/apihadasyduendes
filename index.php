<?php

// Headers CORS pa' que funcione cross-domain con cookies
header('Access-Control-Allow-Origin: https://app.hadasyduendes.cl');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');

$url = $_GET['url'] ?? '';

switch ($url) {
    case 'login':
        include __DIR__ . '/login/index.php';
        break;
    case 'logout':
        include __DIR__ . '/logout/index.php';
        break;


    default:
        http_response_code(404);
        echo json_encode(["mensaje" => "Endpoint no encontrado"]);
        break;
}