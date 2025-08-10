<?php
/**
 * Archivo: admin.php
 * Módulo de administración V-Brime
 * Propósito:
 *   - Panel para rol "admin" con CRUD de Usuarios y Productos, y gestión de Pedidos.
 * Seguridad:
 *   - Requiere sesión iniciada y rol 'admin'.
 *   - Uso de PDO con consultas preparadas.
 *   - Salida escapada con htmlspecialchars (helper h()).
 * Notas:
 *   - Crea tablas 'productos' y 'pedidos' si no existen (idempotente).
 *   - (TODO) Agregar protección CSRF para formularios.
 */

require 'conexion.php';
session_start();

/* Protección de acceso (solo admin) */
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] ?? '') !== 'admin') {
  header('Location: auth.html#login-section');
  exit;
}

/*  Parámetros de vista/acción  */
$view = $_GET['view'] ?? 'usuarios';        // pestaña activa: usuarios | productos | pedidos
$accion = $_POST['accion'] ?? $_GET['accion'] ?? ''; // acción del formulario/enlace

/** Escapa salida HTML (previene XSS) */
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

try {
  /*    Bootstrap de BD (crear tablas si no existen)
   *  - Productos: catálogo administrable
   *  - Pedidos: registros generados desde la tienda*/
  $pdo->exec("CREATE TABLE IF NOT EXISTS productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    precio DECIMAL(12,2) NOT NULL DEFAULT 0,
    imagen VARCHAR(255) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $pdo->exec("CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    direccion VARCHAR(255) NOT NULL,
    telefono VARCHAR(50) NOT NULL,
    correo VARCHAR(120) NOT NULL,
    forma_pago VARCHAR(50) NOT NULL,
    producto VARCHAR(200) NOT NULL,  -- nota: texto (no FK); ver propuesta normalizada
    cantidad INT NOT NULL DEFAULT 1,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  /* USUARIOS: CRUD (solo admin)*/
  if ($view === 'usuarios') {
    // Crear usuario
    if ($accion === 'crear') {
      $n = trim($_POST['nombre'] ?? '');
      $c = trim($_POST['correo'] ?? '');
      $r = $_POST['rol'] ?? 'cliente';      // valores válidos: admin | cliente
      $p = $_POST['contrasena'] ?? '';
      if ($n && $c && $p && in_array($r, ['admin','cliente'], true)) {
        $h = password_hash($p, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO usuarios(nombre,correo,contrasena,rol) VALUES (?,?,?,?)")
            ->execute([$n,$c,$h,$r]);
      }
      header('Location: admin.php?view=usuarios'); exit;
    }

    // Actualizar usuario (con cambio opcional de contraseña)
    if ($accion === 'actualizar') {
      $id = (int)($_POST['id'] ?? 0);
      $n  = trim($_POST['nombre'] ?? '');
      $c  = trim($_POST['correo'] ?? '');
      $r  = $_POST['rol'] ?? 'cliente';
      $p  = $_POST['contrasena'] ?? '';
      if ($id && $n && $c && in_array($r, ['admin','cliente'], true)) {
        if ($p !== '') {
          $h = password_hash($p, PASSWORD_DEFAULT);
          $pdo->prepare("UPDATE usuarios SET nombre=?, correo=?, rol=?, contrasena=? WHERE id=?")
              ->execute([$n,$c,$r,$h,$id]);
        } else {
          $pdo->prepare("UPDATE usuarios SET nombre=?, correo=?, rol=? WHERE id=?")
              ->execute([$n,$c,$r,$id]);
        }
      }
      header('Location: admin.php?view=usuarios'); exit;
    }

    // Eliminar usuario (impide que el admin se borre a sí mismo)
    if ($accion === 'eliminar') {
      $id = (int)($_GET['id'] ?? 0);
      if ($id && $id != $_SESSION['usuario_id']) {
        $pdo->prepare("DELETE FROM usuarios WHERE id=?")->execute([$id]);
      }
      header('Location: admin.php?view=usuarios'); exit;
    }

    // Listado para la tabla
    $usuarios = $pdo->query("SELECT id,nombre,correo,rol,creado_en FROM usuarios ORDER BY id DESC")
                    ->fetchAll(PDO::FETCH_ASSOC);
  }

  /* PRODUCTOS: CRUD (con subida de imagen)*/
  if ($view === 'productos') {
    // Directorio para imágenes (creación segura e idempotente)
    if (!is_dir(__DIR__ . '/uploads')) { @mkdir(__DIR__ . '/uploads', 0775, true); }

    // Crear producto
    if ($accion === 'crear_prod') {
      $n = trim($_POST['nombre'] ?? '');
      $precio = (float)($_POST['precio'] ?? 0);
      $activo = isset($_POST['activo']) ? 1 : 0;
      $imgPath = null;

      // Validar y mover imagen si viene en el form
      if (!empty($_FILES['imagen']['name'])) {
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
          $fname = 'prod_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
          $dest = __DIR__ . '/uploads/' . $fname;
          if (move_uploaded_file($_FILES['imagen']['tmp_name'], $dest)) {
            $imgPath = 'uploads/' . $fname;
          }
        }
      }

      if ($n) {
        $pdo->prepare("INSERT INTO productos(nombre, precio, imagen, activo) VALUES (?,?,?,?)")
            ->execute([$n,$precio,$imgPath,$activo]);
      }
      header('Location: admin.php?view=productos'); exit;
    }

    // Actualizar producto (imagen opcional)
    if ($accion === 'actualizar_prod') {
      $id = (int)($_POST['id'] ?? 0);
      $n = trim($_POST['nombre'] ?? '');
      $precio = (float)($_POST['precio'] ?? 0);
      $activo = isset($_POST['activo']) ? 1 : 0;

      if ($id && $n) {
        $imgSql = "";
        $params = [$n, $precio, $activo, $id];

        // Si viene una nueva imagen, la guardamos y actualizamos campo
        if (!empty($_FILES['imagen']['name'])) {
          $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
          if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
            $fname = 'prod_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            $dest = __DIR__ . '/uploads/' . $fname;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $dest)) {
              $imgSql = ", imagen=?";
              $params = [$n, $precio, $activo, 'uploads/' . $fname, $id];
            }
          }
        }

        $pdo->prepare("UPDATE productos SET nombre=?, precio=?, activo=? $imgSql WHERE id=?")
            ->execute($params);
      }
      header('Location: admin.php?view=productos'); exit;
    }

    // Eliminar producto (borra archivo si existía)
    if ($accion === 'eliminar_prod') {
      $id = (int)($_GET['id'] ?? 0);
      if ($id) {
        $stmt = $pdo->prepare("SELECT imagen FROM productos WHERE id=?");
        $stmt->execute([$id]);
        $img = $stmt->fetchColumn();
        if ($img && file_exists(__DIR__ . '/' . $img)) { @unlink(__DIR__ . '/' . $img); }
        $pdo->prepare("DELETE FROM productos WHERE id=?")->execute([$id]);
      }
      header('Location: admin.php?view=productos'); exit;
    }

    // Listado para la tabla
    $productos = $pdo->query("SELECT id,nombre,precio,imagen,activo,creado_en FROM productos ORDER BY id DESC")
                     ->fetchAll(PDO::FETCH_ASSOC);
  }

  /* =======================================================
   *  PEDIDOS: listado y cambio de estado
   * ======================================================= */
  if ($view === 'pedidos') {
    // Asegurar columna 'estado' (solo la primera vez fallará si ya existe)
    try {
      $pdo->exec("ALTER TABLE pedidos ADD COLUMN estado ENUM('nuevo','procesando','enviado','cancelado') NOT NULL DEFAULT 'nuevo'");
    } catch (Exception $e) {
      /* columna ya existe: ignorar */
    }

    // Cambiar estado de un pedido (select autoguardado)
    if ($accion === 'set_estado') {
      $id = (int)($_POST['id'] ?? 0);
      $estado = $_POST['estado'] ?? 'nuevo';
      if ($id && in_array($estado, ['nuevo','procesando','enviado','cancelado'], true)) {
        $pdo->prepare("UPDATE pedidos SET estado=? WHERE id=?")->execute([$estado, $id]);
      }
      header('Location: admin.php?view=pedidos'); exit;
    }

    // Eliminar pedido
    if ($accion === 'eliminar_pedido') {
      $id = (int)($_GET['id'] ?? 0);
      if ($id) { $pdo->prepare("DELETE FROM pedidos WHERE id=?")->execute([$id]); }
      header('Location: admin.php?view=pedidos'); exit;
    }

    // Listado para la tabla
    $pedidos = $pdo->query("SELECT id,nombre,direccion,telefono,correo,forma_pago,producto,cantidad,total,estado,creado_en
                            FROM pedidos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (PDOException $e) {
  // Manejo genérico de error de BD (oculta detalle sensible)
  http_response_code(500);
  echo 'Error de base de datos';
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin | V-Brime</title>
  <link rel="stylesheet" href="style.css">
  <style>
    /* UI del panel admin (estilos mínimos locales) */
    .tabs { display:flex; gap:10px; margin-top:70px; justify-content:center; flex-wrap:wrap; }
    .tab { padding:10px 16px; border:2px solid #9c27b0; border-radius:999px; color:#fff; text-decoration:none; }
    .tab.active { background:#9c27b0; }
    .card { background:rgba(16,16,16,0.85); border-radius:16px; padding:20px; box-shadow:0 10px 30px rgba(0,0,0,0.35); }
    table.admin { width:100%; border-collapse:collapse; }
    table.admin th, table.admin td { padding:10px; text-align:left; border-bottom:1px solid #333; }
    .img-thumb { width:70px; height:70px; object-fit:cover; border-radius:8px; }
    .inline-form { display:inline; }
  </style>
</head>
<body>
  <!-- Barra superior con tabs -->
  <div class="head">
    <div class="logo" style="display:flex;align-items:center;">
      <img src="Logo_VBrime/VB.png" alt="Logo" style="height:55px;margin-right:20px;">
      <a href="index.html" style="color:#c026d0;text-decoration:none;text-shadow:1px 2px 5px #7a007c;">V-Brime</a>
    </div>
    <nav class="navbar" id="navbar">
      <a href="admin.php?view=usuarios" class="tab <?php echo $view==='usuarios'?'active':''; ?>">Usuarios</a>
      <a href="admin.php?view=productos" class="tab <?php echo $view==='productos'?'active':''; ?>">Productos</a>
      <a href="admin.php?view=pedidos" class="tab <?php echo $view==='pedidos'?'active':''; ?>">Pedidos</a>
      <a href="logout.php" class="tab">Salir (<?php echo h($_SESSION['usuario_nombre']); ?>)</a>
    </nav>
  </div>

  <section class="content">
    <?php if ($view === 'usuarios'): ?>
      <!-- ========== VISTA: USUARIOS ========== -->
      <h2 class="title">Usuarios</h2>

      <!-- Form: crear usuario -->
      <div class="card" style="max-width:900px;margin:0 auto 30px;">
        <h3>Crear usuario</h3>
        <form method="post" style="text-align:left;">
          <input type="hidden" name="accion" value="crear">
          <label>Nombre</label>
          <input name="nombre" required style="width:100%;padding:10px;border-radius:8px;margin-bottom:10px;">
          <label>Correo</label>
          <input type="email" name="correo" required style="width:100%;padding:10px;border-radius:8px;margin-bottom:10px;">
          <label>Rol</label>
          <select name="rol" style="width:100%;padding:10px;border-radius:8px;margin-bottom:10px;">
            <option value="cliente">Cliente</option>
            <option value="admin">Administrador</option>
          </select>
          <label>Contraseña</label>
          <input type="password" name="contrasena" required style="width:100%;padding:10px;border-radius:8px;margin-bottom:10px;">
          <button class="btn" type="submit">Crear</button>
        </form>
      </div>

      <!-- Tabla: lista y edición inline -->
      <div style="overflow-x:auto;max-width:1200px;margin:0 auto;" class="card">
        <table class="admin">
          <thead>
            <tr><th>ID</th><th>Nombre</th><th>Correo</th><th>Rol</th><th>Creado</th><th>Acciones</th></tr>
          </thead>
          <tbody>
            <?php foreach ($usuarios as $u): ?>
            <tr>
              <form method="post">
                <input type="hidden" name="accion" value="actualizar">
                <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                <td>#<?php echo (int)$u['id']; ?></td>
                <td><input name="nombre" value="<?php echo h($u['nombre']); ?>" style="width:100%;padding:6px;border-radius:6px;"></td>
                <td><input name="correo" value="<?php echo h($u['correo']); ?>" style="width:100%;padding:6px;border-radius:6px;"></td>
                <td>
                  <select name="rol" style="padding:6px;border-radius:6px;">
                    <option value="cliente" <?php echo $u['rol']==='cliente'?'selected':''; ?>>Cliente</option>
                    <option value="admin" <?php echo $u['rol']==='admin'?'selected':''; ?>>Administrador</option>
                  </select>
                </td>
                <td><?php echo h($u['creado_en']); ?></td>
                <td>
                  <input type="password" name="contrasena" placeholder="Nueva contraseña (opcional)" style="padding:6px;border-radius:6px;">
                  <button class="btn" type="submit" style="padding:6px 12px;margin-left:6px;">Guardar</button>
                  <a class="btn" href="admin.php?view=usuarios&accion=eliminar&id=<?php echo (int)$u['id']; ?>"
                     style="padding:6px 12px;margin-left:6px;background:#b00020;border-color:#b00020;"
                     onclick="return confirm('¿Eliminar usuario?');">Eliminar</a>
                </td>
              </form>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($view === 'productos'): ?>
      <!-- ========== VISTA: PRODUCTOS ========== -->
      <h2 class="title">Productos</h2>

      <!-- Form: crear producto -->
      <div class="card" style="max-width:900px;margin:0 auto 30px;">
        <h3>Crear producto</h3>
        <form method="post" enctype="multipart/form-data" style="text-align:left;">
          <input type="hidden" name="accion" value="crear_prod">
          <label>Nombre</label>
          <input name="nombre" required style="width:100%;padding:10px;border-radius:8px;margin-bottom:10px;">
          <label>Precio</label>
          <input type="number" step="0.01" name="precio" required style="width:100%;padding:10px;border-radius:8px;margin-bottom:10px;">
          <label>Imagen (jpg, png, webp, gif)</label>
          <input type="file" name="imagen" accept=".jpg,.jpeg,.png,.webp,.gif" style="width:100%;padding:10px;border-radius:8px;margin-bottom:10px;">
          <label><input type="checkbox" name="activo" checked> Activo</label><br><br>
          <button class="btn" type="submit">Crear</button>
        </form>
      </div>

      <!-- Tabla: lista y edición inline -->
      <div style="overflow-x:auto;max-width:1200px;margin:0 auto;" class="card">
        <table class="admin">
          <thead>
            <tr><th>ID</th><th>Imagen</th><th>Nombre</th><th>Precio</th><th>Activo</th><th>Creado</th><th>Acciones</th></tr>
          </thead>
          <tbody>
            <?php foreach ($productos as $p): ?>
            <tr>
              <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="actualizar_prod">
                <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                <td>#<?php echo (int)$p['id']; ?></td>
                <td><?php if ($p['imagen']): ?><img class="img-thumb" src="<?php echo h($p['imagen']); ?>" alt=""><?php endif; ?></td>
                <td><input name="nombre" value="<?php echo h($p['nombre']); ?>" style="width:100%;padding:6px;border-radius:6px;"></td>
                <td><input type="number" step="0.01" name="precio" value="<?php echo h($p['precio']); ?>" style="width:120px;padding:6px;border-radius:6px;"></td>
                <td><input type="checkbox" name="activo" <?php echo $p['activo']?'checked':''; ?>></td>
                <td><?php echo h($p['creado_en']); ?></td>
                <td>
                  <input type="file" name="imagen" accept=".jpg,.jpeg,.png,.webp,.gif" style="padding:6px;border-radius:6px;">
                  <button class="btn" type="submit" style="padding:6px 12px;margin-left:6px;">Guardar</button>
                  <a class="btn" href="admin.php?view=productos&accion=eliminar_prod&id=<?php echo (int)$p['id']; ?>"
                     style="padding:6px 12px;margin-left:6px;background:#b00020;border-color:#b00020;"
                     onclick="return confirm('¿Eliminar producto?');">Eliminar</a>
                </td>
              </form>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($view === 'pedidos'): ?>
      <!-- ========== VISTA: PEDIDOS ========== -->
      <h2 class="title">Pedidos</h2>

      <!-- Tabla de pedidos con cambio de estado inline -->
      <div style="overflow-x:auto;max-width:1200px;margin:0 auto;" class="card">
        <table class="admin">
          <thead>
            <tr><th>ID</th><th>Cliente</th><th>Contacto</th><th>Producto</th><th>Cant.</th><th>Total</th><th>Pago</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr>
          </thead>
          <tbody>
            <?php foreach ($pedidos as $pd): ?>
            <tr>
              <td>#<?php echo (int)$pd['id']; ?></td>
              <td><?php echo h($pd['nombre']); ?><br><small><?php echo h($pd['direccion']); ?></small></td>
              <td><?php echo h($pd['telefono']); ?><br><small><?php echo h($pd['correo']); ?></small></td>
              <td><?php echo h($pd['producto']); ?></td>
              <td><?php echo (int)$pd['cantidad']; ?></td>
              <td>$<?php echo number_format((float)$pd['total'], 2, '.', ','); ?></td>
              <td><?php echo h($pd['forma_pago']); ?></td>
              <td>
                <form method="post" class="inline-form">
                  <input type="hidden" name="accion" value="set_estado">
                  <input type="hidden" name="id" value="<?php echo (int)$pd['id']; ?>">
                  <select name="estado" onchange="this.form.submit()" style="padding:6px;border-radius:6px;">
                    <?php
                      $estados = ['nuevo'=>'Nuevo','procesando'=>'Procesando','enviado'=>'Enviado','cancelado'=>'Cancelado'];
                      foreach ($estados as $k=>$v) {
                        $sel = $pd['estado']===$k ? 'selected' : '';
                        echo '<option value="'.$k.'" '.$sel.'>'.$v.'</option>';
                      }
                    ?>
                  </select>
                </form>
              </td>
              <td><?php echo h($pd['creado_en']); ?></td>
              <td>
                <a class="btn" href="admin.php?view=pedidos&accion=eliminar_pedido&id=<?php echo (int)$pd['id']; ?>"
                   style="padding:6px 12px;background:#b00020;border-color:#b00020;"
                   onclick="return confirm('¿Eliminar pedido?');">Eliminar</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</body>
</html>
