<?php
header('Content-Type: application/json');
session_start();
require 'conexion.php'; // usa el mismo PDO que el resto del sitio

// Captura y sanea
$nombre    = trim($_POST['nombre']   ?? '');
$direccion = trim($_POST['direccion']?? '');
$telefono  = trim($_POST['telefono'] ?? '');
$correo    = trim($_POST['correo']   ?? '');
$pago      = trim($_POST['pago']     ?? '');
$producto  = trim($_POST['producto'] ?? '');
$cantidad  = (int)($_POST['cantidad'] ?? 1);

// total: preferimos el oculto 'total' (numérico). Si no viene, intentamos parsear 'total_visible'
if (isset($_POST['total'])) {
  $total = (float)($_POST['total']);
} else {
  // "$9,280 MXN" -> 9280
  $tv = preg_replace('/[^0-9\.]/', '', $_POST['total_visible'] ?? '0');
  $total = (float)$tv;
}

// VALIDACIONES BÁSICAS
if (!$nombre || !$direccion || !$telefono || !$correo || !$pago || !$producto || $cantidad < 1) {
  echo json_encode(["success" => false, "message" => "Campos incompletos"]);
  exit;
}

try {
  // Asegura existencia de la tabla (por si hace falta en un deployment nuevo)
  $pdo->exec("CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    direccion VARCHAR(255) NOT NULL,
    telefono VARCHAR(50) NOT NULL,
    correo VARCHAR(120) NOT NULL,
    forma_pago VARCHAR(50) NOT NULL,
    producto VARCHAR(200) NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // Insert
  $stmt = $pdo->prepare("INSERT INTO pedidos
    (nombre, direccion, telefono, correo, forma_pago, producto, cantidad, total)
    VALUES (:nombre, :direccion, :telefono, :correo, :pago, :producto, :cantidad, :total)");

  $ok = $stmt->execute([
    ':nombre'    => $nombre,
    ':direccion' => $direccion,
    ':telefono'  => $telefono,
    ':correo'    => $correo,
    ':pago'      => $pago,
    ':producto'  => $producto,
    ':cantidad'  => $cantidad,
    ':total'     => $total,
  ]);

  if ($ok) {
    echo json_encode(["success" => true]);
  } else {
    echo json_encode(["success" => false, "message" => "No se pudo guardar el pedido (execute)"]);
  }
} catch (PDOException $e) {
  // Puedes loguearlo a un archivo si prefieres no exponerlo
  echo json_encode(["success" => false, "message" => "Error BD: " . $e->getMessage()]);
}
