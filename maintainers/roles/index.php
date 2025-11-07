<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

try {
  $method = $_SERVER['REQUEST_METHOD'];
  $conn = ConfigDB::getConnection();

  switch ($method) {
    case 'GET':
      $stmt = $conn->prepare("SELECT id_role, role FROM roles ORDER BY id_role DESC");
      $stmt->execute();
      $result = $stmt->get_result();

      $roles = [];
      while ($row = $result->fetch_assoc()) {
        $roles[] = $row;
      }

      echo json_encode(["success" => true, "data" => $roles]);
      $stmt->close();
      break;

    case 'POST':
      $input = json_decode(file_get_contents('php://input'), true);
      $boxNombre = $input['box'] ?? null;

      if (!$boxNombre) {
        http_response_code(400);
        echo json_encode(["error" => "Falta el valor del box"]);
        exit;
      }

      // Verificar duplicidad antes de insertar
      $duplicado = getBoxDuplicated($conn, $boxNombre);
      if ($duplicado !== null) {
        http_response_code(409);
        echo json_encode(["error" => "No se puede agregar un nombre que ya existe."]);
        exit;
      }

      $stmt = $conn->prepare("INSERT INTO boxes (box) VALUES (?)");
      $stmt->bind_param("s", $boxNombre);
      $stmt->execute();

      $lastId = $conn->insert_id;
      $stmt->close();

      $newRow = getBoxById($conn, $lastId);

      echo json_encode([
          "success" => true,
          "newRow" => $newRow
      ]);
      break;

    case 'PUT':
      $input = json_decode(file_get_contents('php://input'), true);
      $boxId = $input['id_box'] ?? null;
      $boxNombre = $input['box'] ?? null;

      if (!$boxNombre || !$boxId) {
        http_response_code(400);
        echo json_encode(["error" => "Faltan valor nombre o id vacío"]);
        exit;
      }

      $duplicado = getBoxDuplicated($conn, $boxNombre, $boxId);

      if ($duplicado !== null) {
        http_response_code(409);
        echo json_encode(["error" => "No se puede duplicar un nombre ya existente."]);
        exit;
      } 
      
      $stmt = $conn->prepare("UPDATE boxes SET box = ? WHERE id_box = ?");
      $stmt->bind_param("si", $boxNombre, $boxId);
      $stmt->execute();
      $stmt->close();

      $updatedRow = getBoxById($conn, $boxId);

      echo json_encode([
        "success" => true,
        "updatedRow" => $updatedRow
      ]);
      break;

    case 'DELETE':
      $input = json_decode(file_get_contents('php://input'), true);
      $boxId = $input['id_box'] ?? null;

      if (!$boxId) {
        http_response_code(400);
        echo json_encode(["error" => "Faltan id o id vacío"]);
        exit;
      }

      $boxExistente = getBoxById($conn, $boxId);
      if($boxExistente === null){
        echo json_encode(["error" => "Box no encontrado."]);
        exit;
      }

      $stmt = $conn->prepare("DELETE FROM boxes WHERE id_box = ?");
      $stmt->bind_param("i", $boxId);
      $stmt->execute();

      $stmt->close();

      echo json_encode([
        "success" => true
      ]);
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

// Función optimizada para reutilizar conexión abierta
function getBoxById($conn, $id) {
  $stmt = $conn->prepare("SELECT id_box, box FROM boxes WHERE id_box = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();

  $result = $stmt->get_result();
  $box = $result->fetch_assoc();

  $stmt->close();
  return $box;
}

// Arreglar función getBoxDuplicated para uso en agregar y editar
function getBoxDuplicated($conn, $boxNombre, $excludeId = null) {
  if ($excludeId === null) {
    // Para agregar: sólo verificar si existe el nombre sin importar id
    $stmt = $conn->prepare("SELECT id_box FROM boxes WHERE box = ?");
    $stmt->bind_param("s", $boxNombre);
  } else {
    // Para editar: verificar si existe otro registro con mismo nombre distinto al id dado
    $stmt = $conn->prepare("SELECT id_box FROM boxes WHERE box = ? AND id_box <> ?");
    $stmt->bind_param("si", $boxNombre, $excludeId);
  }
  $stmt->execute();
  $result = $stmt->get_result();
  $box = $result->fetch_assoc();
  $stmt->close();
  return $box;
}