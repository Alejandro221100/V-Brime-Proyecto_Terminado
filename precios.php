<?php
/**
 * Módulo: Listado de precios (dinámico desde BD)
 * Propósito:
 * - Consulto 'productos' activos 
 */

require 'conexion.php'; // Conexión PDO ($pdo)

try {
  // Consulta: productos activos ordenados del más reciente al más antiguo
  $stmt = $pdo->query("SELECT id, nombre, precio, imagen FROM productos WHERE activo=1 ORDER BY creado_en DESC");
  $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  // Si hay error de BD, continúo con arreglo vacío para no romper la vista
  $productos = [];
}
?>

<section class="content price">
  <article class="contain">
    <h2 class="title">Consultar Precios</h2>
    <p>Explora nuestra lista de productos disponibles.</p>

    <!-- Contenedor de tarjetas -->
    <div style="display:flex;justify-content:center;gap:20px;flex-wrap:wrap;margin-top:40px;">

      <?php if (!$productos): ?>
        <!-- Estado vacío: no hay productos activos -->
        <p style="color:#f8f8d9;">No hay productos activos por ahora.</p>

      <?php else: foreach ($productos as $p):
        // Preparar datos seguros para la vista
        $img = $p['imagen'] ?: 'productos_venta/placeholder.png';
        $nombre = htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8');
        $precio = number_format((float)$p['precio'], 0, '.', ',');
      ?>

      <!-- Tarjeta de producto (enlace a #modulo-venta y preselección por data-producto) -->
      <a href="#modulo-venta" class="product-card" data-producto="<?= $nombre ?>" onclick="seleccionarProducto(this)"
         style="text-decoration:none;color:inherit;width:220px;background:#7d2181;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.2);text-align:center;">

        <div style="width:100%;height:200px;background:#fff;">
          <img src="<?= $img ?>" alt="<?= $nombre ?>" style="width:100%;height:100%;object-fit:contain;">
        </div>

        <div style="padding:15px;">
          <h4 style="color:#000;"><?= $nombre ?></h4>
          <strong style="color:#f8f8d9;">$<?= $precio ?> MXN</strong>
        </div>
      </a>

      <?php endforeach; endif; ?>
    </div>
  </article>
</section>
