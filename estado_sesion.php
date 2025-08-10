<?php
/**
 * Archivo: estado_sesion.php
 * Propósito:
 *   - Exponer en JSON el estado de la sesión actual para el frontend.
 *     Útil para mostrar/ocultar elementos (p. ej., menú admin) sin recargar la página.
 * Salida (JSON):
 *   {
 *     "logged": bool,          // true si hay sesión iniciada
 *     "rol": "admin|cliente",  // rol actual (null si no hay sesión)
 *     "nombre": string|null    // nombre del usuario logueado
 *   }
 * Uso:
 *   - Llamo con fetch desde JS: fetch('estado_sesion.php').then(r => r.json())
 *   - En base a "logged" y "rol" decido qué UI mostrar.
 * Notas:
 *   - No modifica sesión; solo lee variables de $_SESSION.
 *   - El header JSON se envía antes de cualquier salida (evita problemas de encoding).
 */

header('Content-Type: application/json'); // Respuesta en formato JSON
session_start();                           // Inicio/continuo la sesión

// ¿Hay usuario logueado?
$logged = isset($_SESSION['usuario_id']);

// Si está logueado, leo rol y nombre; si no, envío nulls
$rol = $logged ? ($_SESSION['usuario_rol'] ?? 'cliente') : null;
$nombre = $logged ? ($_SESSION['usuario_nombre'] ?? null) : null;

// Devuelvo el estado en un objeto JSON simple
echo json_encode([
  'logged' => $logged,
  'rol' => $rol,
  'nombre' => $nombre
]);
