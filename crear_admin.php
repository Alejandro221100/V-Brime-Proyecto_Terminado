<?php
/**
 * Archivo: crear_admin.php
 * Propósito:
 *   Script de una sola ejecución para crear mi usuario Administrador inicial.
 * Uso:
 *   1) Subo este archivo al servidor junto con conexion.php.
 *   2) Lo abro en el navegador: https://v-brime.infy.uk/crear_admin.php
 *   3) Si el admin se crea correctamente, BORRO este archivo de inmediato.
 * Seguridad:
 *   - Este archivo contiene credenciales en claro para el alta inicial; no debe quedar en producción.
 *   - Verifico que no exista ya un usuario con el mismo correo o nombre antes de insertarlo.
 * Notas:
 *   - Puedo cambiar $nombre, $correo y $pass aquí mismo antes de ejecutar.
 *   - La contraseña se guarda con password_hash().
 */

require 'conexion.php'; // Importo la conexión PDO ($pdo)

// Datos del admin que quiero crear (puedo personalizarlos antes de ejecutar)
$nombre='Test'; $correo='test@gmail.com'; $pass='@ISC1234'; $rol='admin';

try{
  // 1) Verifico si ya existe usuario con ese correo o nombre (idempotencia)
  $ex = $pdo->prepare("SELECT id FROM usuarios WHERE correo=:c OR nombre=:n");
  $ex->execute(['c'=>$correo,'n'=>$nombre]);
  if($ex->fetch()){ exit('El usuario ya existe.'); }

  // 2) Genero hash seguro de la contraseña y creo el usuario admin
  $hash=password_hash($pass,PASSWORD_DEFAULT);
  $ins=$pdo->prepare("INSERT INTO usuarios(nombre,correo,contrasena,rol) VALUES (?,?,?,?)");
  $ins->execute([$nombre,$correo,$hash,$rol]);

  // 3) Mensaje de éxito y recordatorio de borrar este archivo
  echo 'Administrador creado. BORRA este archivo (crear_admin.php).';

}catch(PDOException $e){
  // Error de BD (muestro mensaje escapado para evitar problemas de salida)
  http_response_code(500);
  echo 'Error: '.htmlspecialchars($e->getMessage());
}
