<?php
/** Módulo: Pedido (dinámico desde BD)
 *  Tarea: cargar productos activos y pintar formulario de venta.
 *  Salida: HTML del form; total lo calcula JS en el cliente.
 */
require 'conexion.php';

try {
  // Productos activos (nombre/precio) ordenados A–Z
  $stmt = $pdo->query("SELECT nombre, precio FROM productos WHERE activo=1 ORDER BY nombre ASC");
  $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $productos = []; }
?>

<!-- Formulario de pedido -->
<section class="content sale" id="form-venta">
  <h2 class="title">Pedido</h2>

  <form id="formularioVenta" style="max-width:600px;margin:auto;">
    <!-- Datos del cliente -->
    <label>Nombre completo:</label>
    <input type="text" id="nombre" name="nombre" required><br><br>

    <label>Dirección:</label>
    <input type="text" id="direccion" name="direccion" required><br><br>

    <label>Correo electrónico:</label>
    <input type="email" id="correo" name="correo" required><br><br>

    <label>Número telefónico:</label>
    <input type="tel" id="telefono" name="telefono" required><br><br>

    <!-- Producto: opciones generadas desde BD -->
    <label>Producto a comprar:</label>
    <select id="producto" name="producto" required>
      <option value="" data-precio="0">Seleccione una opción</option>
      <?php foreach ($productos as $p):
        $n = htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8');
        $pr = (float)$p['precio'];
      ?>
        <option value="<?= $n ?>" data-precio="<?= $pr ?>"><?= $n ?></option>
      <?php endforeach; ?>
    </select><br><br>

    <!-- Cantidad -->
    <label>Cantidad:</label>
    <input type="number" id="cantidad" name="cantidad" min="1" value="1" required><br><br>

    <!-- Total mostrado (JS lo actualiza) -->
    <label>Total (MXN):</label>
    <input type="text" id="total" name="total_visible" readonly value="$0"><br><br>

    <!-- Pago -->
    <label>Forma de pago:</label>
    <select id="pago" name="pago" required>
      <option value="">Seleccione método de pago</option>
      <option value="tarjeta">Tarjeta de crédito/débito</option>
      <option value="paypal">PayPal</option>
    </select><br><br>

    <button type="submit" class="btn">Enviar pedido</button>
  </form>
</section>

<!-- Pantalla de agradecimiento (se muestra tras enviar) -->
<section class="content thank-you" id="gracias" style="display:none;">
  <h2 class="title">¡Gracias por tu compra!</h2>
  <p>Hemos recibido tu pedido correctamente.</p>
  <a href="#" class="btn" onclick="volverAlInicio()">Volver al inicio</a>
</section>
