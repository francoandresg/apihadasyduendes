<?php
include '../headers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// =======================
// Cargar variables de entorno
// =======================
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

header("Content-Type: application/json");

// =======================
// Configuración JWT
// =======================
$secret_key = $_ENV['JWT_SECRET'] ?? '';
$issuer = $_ENV['JWT_ISSUER'] ?? 'http://localhost';
$audience = $_ENV['JWT_AUDIENCE'] ?? 'http://localhost:3000';
$issued_at = time();

// Expira a medianoche
$expiration_time = strtotime('23:59:59');

if (empty($secret_key)) {
    http_response_code(500);
    echo json_encode(["error" => "JWT_SECRET no configurado en el .env"]);
    exit();
}

// ------------------
// Validación método
// ------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"]);
    exit();
}

// ------------------
// Leer input JSON
// ------------------
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['username'], $input['password'])) {
    http_response_code(400);
    echo json_encode(["error" => "Faltan campos: usuario o clave"]);
    exit();
}

$username = trim($input['username']);
$password = trim($input['password']);

// ------------------
// Conectar a la DB
// ------------------
$conn = ConfigDB::getConnection();
$stmt = $conn->prepare("SELECT 
        u.id_user, 
        u.user, 
        u.username,
        u.email, 
        u.password,
        u.id_profile, 
        p.profile,
        u.id_role,
        r.role,
        u.theme
    FROM users u
    INNER JOIN profiles p ON u.id_profile = p.id_profile
    INNER JOIN roles r ON u.id_role = r.id_role
    WHERE u.username = ? 
    AND u.state = 1
");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

// ------------------
// Verificar usuario
// ------------------
if ($user = $result->fetch_assoc()) {
    if (password_verify($password, trim($user['password']))) {
        // ✅ Crear payload del token
        $payload = [
            "iss" => $issuer,
            "aud" => $audience,
            "iat" => $issued_at,
            "exp" => $expiration_time,
            "data" => [
                "id_user" => $user['id_user'],
                "username" => $user['username'],
                "email" => $user['email'],
                "role" => $user['role'],
                "profile" => $user['profile'],
                "nameuser" => $user['user']
            ]
        ];

        // ✅ Generar token
        $jwt = JWT::encode($payload, $secret_key, 'HS256');

        echo json_encode([
            "success" => true,
            "serviceToken" => $jwt,
            "user" => [
                "id_user" => $user['id_user'],
                "username" => $user['username'],
                "email" => $user['email'],
                "role" => $user['role'],
                "profile" => $user['profile'],
                "theme" => $user['theme'],
                "nameuser" => $user['user']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["error" => "Credenciales incorrectas"]);
    }
} else {
    http_response_code(401);
    echo json_encode(["error" => "Credenciales incorrectas"]);
}

$stmt->close();
$conn->close();
