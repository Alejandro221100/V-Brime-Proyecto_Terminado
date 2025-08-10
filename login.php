<?php
/**
 * Archivo: login.php
 * Propósito:
 *   - Autenticar al usuario (por correo o nombre) y abrir sesión.
 * Entradas (POST):
 *   - usuario: string (correo o nombre de usuario)
 *   - contrasena: string (texto plano)
 * Salida (JSON):
 *   - { success: bool, message: string, rol?: 'admin'|'cliente' }
 * Seguridad:
 *   - Comparo con password_verify() (hash almacenado en BD).
 *   - Uso PDO con consulta preparada (evita SQL Injection).
 * Notas:
 *   - Si el login es exitoso, guardo id/nombre/rol en $_SESSION.
 */

header('Content-Type: application/json'); // Responder siempre como JSON
require 'conexion.php';                   // Conexión PDO ($pdo)
session_start();                           // Iniciar/continuar sesión

$user = $_POST['usuario'] ?? '';
$pass = $_POST['contrasena'] ?? '';

// 2) Validación mínima de presencia
if (!$user || !$pass) {
  echo json_encode(['success' => false, 'message' => 'Usuario y contraseña requeridos']);
  exit;
}

try {
  // 3) Buscar por correo O nombre (un único parámetro :u)
  $stmt = $pdo->prepare("SELECT id, nombre, correo, contrasena, rol FROM usuarios WHERE correo = :u OR nombre = :u");
  $stmt->execute(['u' => $user]);
  $u = $stmt->fetch(PDO::FETCH_ASSOC);

  // 4) Verificar credenciales: password_verify contra el hash almacenado
  if ($u && password_verify($pass, $u['contrasena'])) {
    // 5) Guardar datos clave en la sesión
    $_SESSION['usuario_id'] = $u['id'];
    $_SESSION['usuario_nombre'] = $u['nombre'];
    $_SESSION['usuario_rol'] = $u['rol'];

    // 6) Responder éxito y el rol (para que el frontend ajuste la UI)
    echo json_encode(['success' => true, 'message' => 'Inicio de sesión exitoso', 'rol' => $u['rol']]);
  } else {
    // Credenciales inválidas (usuario no existe o contraseña no coincide)
    echo json_encode(['success' => false, 'message' => 'Credenciales inválidas']);
  }
} catch (PDOException $e) {
  // Error de servidor/BD (no expongo detalle por seguridad)
  echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}
