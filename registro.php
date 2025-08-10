<?php
header('Content-Type: application/json');
require 'conexion.php';

$nombre = trim($_POST['nombre'] ?? '');
$email  = trim($_POST['correo'] ?? '');
$pass   = $_POST['contrasena'] ?? '';
$rol    = 'cliente'; // solo se crean clientes desde la web

if (!$nombre || !$email || !$pass) {
  echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
  exit;
}

try {
  $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE correo = :correo OR nombre = :nombre");
  $stmt->execute(['correo' => $email, 'nombre' => $nombre]);
  if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'El usuario ya existe']);
    exit;
  }

  $hash = password_hash($pass, PASSWORD_DEFAULT);
  $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, correo, contrasena, rol) VALUES (:n, :c, :h, :r)");
  $stmt->execute(['n'=>$nombre, 'c'=>$email, 'h'=>$hash, 'r'=>$rol]);

  echo json_encode(['success' => true, 'message' => 'Usuario registrado con éxito']);
} catch (PDOException $e) {
  echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}
