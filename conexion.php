<?php
/**
 * Archivo: conexion.php
 * Propósito:
 *   - Centralizar la conexión a MySQL usando PDO para todo el sitio V-Brime.
 * Uso:
 *   - Incluir con `require 'conexion.php';` y usar la variable $pdo.
 * Salida:
 *   - Expone $pdo (instancia PDO) listo para consultas.
 * Seguridad:
 *   - Este archivo contiene credenciales;.
 *   - En producción, usa variables de entorno o un archivo .env fuera del docroot.
 * Notas:
 *   - Codificación actual: utf8 (considera utf8mb4 para soporte completo de emojis).
 */

$host = 'sql206.infinityfree.com'; // Servidor MySQL
$user = 'if0_38917775';            // Usuario de la base de datos
$pass = 'DPtJJ4ovJmg6';            // Contraseña
$db   = 'if0_38917775_basededatos';// Nombre de la base de datos

try {
    // DSN: incluye host, base y charset; PDO manejará la conexión
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);

    // Modo de errores: lanzar excepciones para poder capturar fallos
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    
} catch (PDOException $e) {
    // Error de conexión:
    die("Error de conexión: " . $e->getMessage());
}
?>
