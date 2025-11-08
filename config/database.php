
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Carga las variables del .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Mostrar errores solo si APP_DEBUG es true
if ($_ENV['APP_DEBUG'] === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

class ConfigDB {
    public static function getConnection(): mysqli {
        $conn = new mysqli(
            $_ENV['DB_HOST'],
            $_ENV['DB_USER'],
            $_ENV['DB_PASS'],
            $_ENV['DB_NAME']
        );

        if ($conn->connect_error) {
            die("Error de conexión: " . $conn->connect_error);
        }

        if (!$conn->set_charset("utf8mb4")) {
            printf("Error configurando el charset utf8mb4: %s\n", $conn->error);
            exit();
        }

        return $conn;
    }
}
