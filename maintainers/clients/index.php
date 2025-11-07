<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

try {
  $method = $_SERVER['REQUEST_METHOD'];
  $conn = ConfigDB::getConnection();

  switch ($method) {
    case 'GET':
      $stmt = $conn->prepare("SELECT id_client, clientname, email, phone, birthday
      FROM clients
      ORDER BY id_client DESC");
      $stmt->execute();
      $result = $stmt->get_result();

      $services = [];
      while ($row = $result->fetch_assoc()) {
        $services[] = $row;
      }

      echo json_encode(["success" => true, "data" => $services]);
      $stmt->close();
      break;

    case 'POST':
      $input = json_decode(file_get_contents('php://input'), true);
      $service = $input['service'] ?? null;
      $price = $input['price'] ?? null;
      $roles = $input['roles'] ?? null;
      $description = $input['description'] ?? null;

      if ($service === null || $roles === null || $description === null || $price === null || $service === '' || $description === '') {
        http_response_code(400);
        echo json_encode(["error" => "Faltan campos para agregar"]);
        exit;
      }

      // Verificar duplicidad antes de insertar
      $duplicado = getServiceDuplicated($conn, $service, $roles);
      if ($duplicado !== null) {
        http_response_code(409);
        echo json_encode(["error" => "No se puede duplicar un nombre existente en un cargo."]);
        exit;
      }

      $stmt = $conn->prepare("INSERT INTO services (service, price, id_role, description) VALUES (?, ?, ?, ?)");
      $stmt->bind_param("siis", $service, $price, $roles, $description);
      $stmt->execute();

      $lastId = $conn->insert_id;
      $stmt->close();

      $newRow = getServiceById($conn, $lastId);

      echo json_encode([
          "success" => true,
          "newRow" => $newRow
      ]);
      break;

    case 'PUT':
      $input = json_decode(file_get_contents('php://input'), true);
      $idService = $input['idService'] ?? null;
      $service = $input['service'] ?? null;
      $price = $input['price'] ?? null;
      $roles = $input['roles'] ?? null;
      $description = $input['description'] ?? null;

      if ($idService === null || $service === null || $roles === null || $description === null || $price === null || $service === '' || $description === '') {
        http_response_code(400);
        echo json_encode(["error" => "Faltan campos para editar"]);
        exit;
      }

      $duplicado = getServiceDuplicated($conn, $service, $roles, $idService);

      if ($duplicado !== null) {
        http_response_code(409);
        echo json_encode(["error" => "No se puede duplicar un nombre existente en un cargo."]);
        exit;
      } 
      
      $stmt = $conn->prepare("UPDATE services SET service = ?, price = ?, id_role = ?, description = ? WHERE id_service = ?");
      $stmt->bind_param("siisi", $service, $price, $roles, $description, $idService);
      $stmt->execute();
      $stmt->close();

      $updatedRow = getServiceById($conn, $idService);

      echo json_encode([
        "success" => true,
        "updatedRow" => $updatedRow
      ]);
      break;

    case 'DELETE':
      $input = json_decode(file_get_contents('php://input'), true);
      $idService = $input['idService'] ?? null;

      if ($idService == null) {
        http_response_code(400);
        echo json_encode(["error" => "Faltan id o id vacío"]);
        exit;
      }

      $ServicioExistente = getServiceById($conn, $idService);
      if($ServicioExistente === null){
        echo json_encode(["error" => "Servicio no encontrado."]);
        exit;
      }

      $stmt = $conn->prepare("DELETE FROM services WHERE id_service = ?");
      $stmt->bind_param("i", $idService);
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
function getServiceById($conn, $id) {
  $stmt = $conn->prepare("SELECT s.id_service, s.service, s.description, s.price, s.id_role, r.role
      FROM services s
      INNER JOIN roles r ON r.id_role = s.id_role 
      WHERE s.id_service = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();

  $result = $stmt->get_result();
  $box = $result->fetch_assoc();

  $stmt->close();
  return $box;
}

// Arreglar función getServiceDuplicated para uso en agregar y editar
function getServiceDuplicated($conn, $service, $roles, $excludeId = null) {
  if ($excludeId === null) {
    // Para agregar: sólo verificar si existe el nombre sin importar id
    $stmt = $conn->prepare("SELECT id_service FROM services WHERE service = ? AND id_role = ?");
    $stmt->bind_param("si", $service, $roles);
  } else {
    // Para editar: verificar si existe otro registro con mismo nombre distinto al id dado
    $stmt = $conn->prepare("SELECT id_service FROM services WHERE service = ? AND id_role = ? AND id_service <> ?");
    $stmt->bind_param("sii", $service, $roles, $excludeId);
  }
  $stmt->execute();
  $result = $stmt->get_result();
  $service = $result->fetch_assoc();
  $stmt->close();
  return $service;
}