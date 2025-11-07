<?php
// ------------------
// Configuración sesión compartida
// ------------------
ini_set('session.cookie_domain', '.hadasyduendes.cl');
ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');

session_set_cookie_params([
    'lifetime' => 0,                  // hasta cerrar navegador
    'path' => '/',
    'domain' => '.hadasyduendes.cl',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);

session_start();
session_regenerate_id(true); // fuerza nuevo Set-Cookie

require_once __DIR__ . '/../config/database.php';
header("Content-Type: application/json");

// ------------------
// Validación de método
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
        // Guardar info en sesión
        $_SESSION['loggedin']   = true;
        $_SESSION['id_user']    = $user['id_user'];
        $_SESSION['user']       = $user['user'];
        $_SESSION['id_role']    = $user['id_role'];
        $_SESSION['role']       = $user['role'];
        $_SESSION['id_profile'] = $user['id_profile'];
        $_SESSION['profile']    = $user['profile'];
        $_SESSION['email']      = $user['email'];
        $_SESSION['theme']      = $user['theme'];

        echo json_encode(["success" => true, "theme" => $user['theme']]);
    } else {
        http_response_code(401);
        echo json_encode(["error" => "Credenciales incorrectas o usuario no activo"]);
    }
} else {
    http_response_code(401);
    echo json_encode(["error" => "Credenciales incorrectas o usuario no activo"]);
}

$stmt->close();
$conn->close();