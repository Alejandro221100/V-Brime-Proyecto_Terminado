let productoPendiente = null;

/* Carga de módulos en el Home */
function cargarModulo(idElemento, ruta) {
  // cache-buster para evitar contenido en caché
  const url = `${ruta}${ruta.includes('?') ? '&' : '?'}_=${Date.now()}`;

  fetch(url, { cache: "no-cache" })
    .then(r => r.text())
    .then(html => {
      const host = document.getElementById(idElemento);
      if (!host) return;
      host.innerHTML = html;

      if (idElemento === "modulo-venta") activarFormularioVenta();
      if (idElemento === "modulo-login") activarLogin();
      if (idElemento === "modulo-registro") activarRegistro();

      // si el usuario hizo click en una tarjeta antes de que cargara venta.php
      if (productoPendiente && idElemento === "modulo-venta") {
        seleccionarProductoPorNombre(productoPendiente);
        productoPendiente = null;
      }
    })
    .catch(err => console.error("Error al cargar módulo:", ruta, err));
}

document.addEventListener("DOMContentLoaded", () => {
  cargarModulo("modulo-productos", "productos.html"); // estático (si quieres, luego lo hacemos dinámico)
  cargarModulo("modulo-nosotros", "nosotros.html");
  cargarModulo("modulo-precios", "precios.php"); // dinámico desde BD
  cargarModulo("modulo-venta", "venta.php");     // dinámico desde BD
  cargarModulo("modulo-contacto", "contacto.html");

  // Navbar dinámica
  actualizarNavbarSegunSesion();
});

/* Selección desde tarjetas de Precios */
function seleccionarProducto(elemento) {
  productoPendiente = elemento.getAttribute("data-producto");
  setTimeout(() => {
    const venta = document.getElementById("modulo-venta");
    if (venta) venta.scrollIntoView({ behavior: "smooth" });
  }, 300);
}

function seleccionarProductoPorNombre(nombreProducto) {
  const campoProducto = document.getElementById("producto");
  if (!campoProducto) return;
  const opcion = Array.from(campoProducto.options)
    .find(opt => (opt.text || "").includes(nombreProducto));
  if (opcion) campoProducto.value = opcion.value;
  // recalcular total
  campoProducto.dispatchEvent(new Event('change'));
}

/* Formulario de venta (pedido en venta.php) */
function activarFormularioVenta() {
  const formulario = document.getElementById("formularioVenta");
  if (!formulario) return;

  const seccionFormulario = document.getElementById("form-venta");
  const seccionGracias    = document.getElementById("gracias");

  const productoSelect = document.getElementById('producto');
  const cantidadInput  = document.getElementById('cantidad');
  const totalInput     = document.getElementById('total');

  // input oculto con el total numérico que recibe guardar_pedido.php
  let totalHidden = document.getElementById('total_num');
  if (!totalHidden) {
    totalHidden = document.createElement('input');
    totalHidden.type = 'hidden';
    totalHidden.name = 'total';
    totalHidden.id   = 'total_num';
    formulario.appendChild(totalHidden);
  }

  function calcularYMostrarTotal() {
    const opt = productoSelect?.selectedOptions?.[0];
    const precio   = parseFloat(opt?.dataset?.precio || '0');
    const cantidad = parseInt(cantidadInput?.value || '1', 10);
    const total    = (isNaN(precio) ? 0 : precio) * (isNaN(cantidad) ? 1 : cantidad);

    if (totalInput) totalInput.value = `$${total.toLocaleString('es-MX')}`;
    totalHidden.value = String(total);
  }

  productoSelect?.addEventListener('change', calcularYMostrarTotal);
  cantidadInput?.addEventListener('input', calcularYMostrarTotal);
  calcularYMostrarTotal();

  formulario.addEventListener("submit", function(e) {
    e.preventDefault();
    const datos = new FormData(formulario);
    fetch('guardar_pedido.php', { method: 'POST', body: datos })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          if (seccionFormulario) seccionFormulario.style.display = "none";
          if (seccionGracias)    seccionGracias.style.display    = "block";
          formulario.reset();
          calcularYMostrarTotal();
        } else {
          alert("Error: " + (data.message || 'No se pudo guardar el pedido'));
        }
      })
      .catch(() => alert("Hubo un problema al enviar tu pedido."));
  });
}

function volverAlInicio() {
  window.location.hash = "#";
  window.scrollTo({ top: 0, behavior: "smooth" });
}

/* Login (auth.html sección) */
function activarLogin() {
  const f = document.getElementById("formLogin");
  if (!f) return;

  const msg = document.getElementById("loginMensaje");
  f.addEventListener("submit", e => {
    e.preventDefault();
    fetch("login.php", { method: "POST", body: new FormData(f) })
      .then(r => r.json())
      .then(d => {
        msg.textContent = d.message;
        msg.style.color = d.success ? "#25D366" : "tomato";
        if (d.success) {
          // Si es admin => al panel
          if (d.rol === 'admin') { window.location.href = 'admin.php'; return; }
          // Si es cliente, puedes quedarte en la misma página o redirigir al home
          // window.location.href = 'index.html';
          f.reset();
          actualizarNavbarSegunSesion();
        }
      })
      .catch(() => { msg.textContent = "Error de conexión"; msg.style.color = "tomato"; });
  });
}

/* Registro (auth.html */
function activarRegistro() {
  const f = document.getElementById("formRegistro");
  if (!f) return;

  const msg = document.getElementById("registroMensaje");
  f.addEventListener("submit", e => {
    e.preventDefault();
    fetch("registro.php", { method: "POST", body: new FormData(f) })
      .then(r => r.json())
      .then(d => {
        msg.textContent = d.message;
        msg.style.color = d.success ? "#25D366" : "tomato";
        if (d.success) f.reset();
      })
      .catch(() => { msg.textContent = "Error de conexión"; msg.style.color = "tomato"; });
  });
}

/* Navbar responsive (móvil) */
function toggleMenu() {
  const nav = document.getElementById("navbar");
  if (nav) nav.classList.toggle("active");
}

/* Navbar según sesión (dinámica) */
function actualizarNavbarSegunSesion() {
  const nav = document.getElementById("navbar");
  if (!nav) return;

  fetch('estado_sesion.php', { cache: "no-cache" })
    .then(r => r.json())
    .then(st => {
      // Borra items previos generados dinámicamente
      nav.querySelectorAll('.nav-dyn').forEach(n => n.remove());

      if (st.logged) {
        if (st.rol === 'admin') {
          const liAdmin = document.createElement('a');
          liAdmin.href = 'admin.php';
          liAdmin.className = 'nav-dyn';
          liAdmin.textContent = 'Panel Admin';
          nav.appendChild(liAdmin);
        }

        const liLogout = document.createElement('a');
        liLogout.href = 'logout.php';
        liLogout.className = 'nav-dyn';
        liLogout.textContent = 'Salir' + (st.nombre ? ` (${st.nombre})` : '');
        nav.appendChild(liLogout);

        // Oculta el enlace "Login" si está presente
        const loginLink = Array.from(nav.querySelectorAll('a')).find(a => /login/i.test(a.textContent));
        if (loginLink) loginLink.style.display = 'none';
      } else {
        // No logueado: asegúrate de que "Login" se vea
        const loginLink = Array.from(nav.querySelectorAll('a')).find(a => /login/i.test(a.textContent));
        if (loginLink) loginLink.style.display = '';
      }
    })
    .catch(() => { /* silencioso */ });
}
