<?php
include '../../headers.php';
require_once __DIR__ . '/../../config/database.php';

header("Content-Type: application/json");

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $conn = ConfigDB::getConnection();

    switch ($method) {
        case 'GET':

            if (isset($_GET['selector'])) {
                $result = $conn->query("SELECT id_box, box FROM boxes ORDER BY box ASC");

                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        "id" => (int)$row["id_box"],
                        "name" => $row["box"]
                    ];
                }

                echo json_encode(["success" => true, "data" => $data]);
                break;
            }

            $stmt = $conn->prepare("SELECT id_box, box FROM boxes ORDER BY id_box DESC");
            $stmt->execute();
            $result = $stmt->get_result();
            $boxes = [];
            while ($row = $result->fetch_assoc()) {
                $boxes[] = $row;
            }
            echo json_encode(["success" => true, "data" => $boxes]);
            $stmt->close();
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $box = $input['box'] ?? null;
            if ($box == null) {
                http_response_code(400);
                echo json_encode(["error" => "Faltan campos para agregar"]);
                exit;
            }
            $duplicado = getBoxDuplicated($conn, $box);
            if ($duplicado !== null) {
                http_response_code(409);
                echo json_encode(["error" => "No se puede agregar un nombre que ya existe."]);
                exit;
            }
            $stmt = $conn->prepare("INSERT INTO boxes (box) VALUES (?)");
            $stmt->bind_param("s", $box);
            $stmt->execute();
            $lastId = $conn->insert_id;
            $stmt->close();
            $newRow = getBoxById($conn, $lastId);
            echo json_encode(["success" => true, "newRow" => $newRow]);
            break;

        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            $idBox = $input['idBox'] ?? null;
            $box = $input['box'] ?? null;
            if ($box == null || $idBox == null) {
                http_response_code(400);
                echo json_encode(["error" => "Faltan valor nombre o id vacío"]);
                exit;
            }
            $duplicado = getBoxDuplicated($conn, $box, $idBox);
            if ($duplicado !== null) {
                http_response_code(409);
                echo json_encode(["error" => "No se puede duplicar un nombre ya existente."]);
                exit;
            }
            $stmt = $conn->prepare("UPDATE boxes SET box = ? WHERE id_box = ?");
            $stmt->bind_param("si", $box, $idBox);
            $stmt->execute();
            $stmt->close();
            $updatedRow = getBoxById($conn, $idBox);
            echo json_encode(["success" => true, "updatedRow" => $updatedRow]);
            break;

        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true);
            $idBox = $input['idBox'] ?? null;
            if ($idBox == null) {
                http_response_code(400);
                echo json_encode(["error" => "Faltan id o id vacío"]);
                exit;
            }
            $boxExistente = getBoxById($conn, $idBox);
            if ($boxExistente === null) {
                echo json_encode(["error" => "Box no encontrado."]);
                exit;
            }
            $stmt = $conn->prepare("DELETE FROM boxes WHERE id_box = ?");
            $stmt->bind_param("i", $idBox);
            $stmt->execute();
            $stmt->close();
            echo json_encode(["success" => true]);
            break;

        default:
            http_response_code(405);
            echo json_encode(["error" => "Método no permitido"]);
            break;
    }
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error de base de datos", "message" => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error inesperado", "message" => $e->getMessage()]);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

// Funciones auxiliares
function getBoxById($conn, $id) {
    $stmt = $conn->prepare("SELECT id_box, box FROM boxes WHERE id_box = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $box = $result->fetch_assoc();
    $stmt->close();
    return $box;
}

function getBoxDuplicated($conn, $boxNombre, $excludeId = null) {
    if ($excludeId === null) {
        $stmt = $conn->prepare("SELECT id_box FROM boxes WHERE box = ?");
        $stmt->bind_param("s", $boxNombre);
    } else {
        $stmt = $conn->prepare("SELECT id_box FROM boxes WHERE box = ? AND id_box <> ?");
        $stmt->bind_param("si", $boxNombre, $excludeId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $box = $result->fetch_assoc();
    $stmt->close();
    return $box;
}
