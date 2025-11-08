<?php

include 'headers.php';

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
