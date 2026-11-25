/**
 * js/script.js
 * - Captura el evento submit del formulario con addEventListener.
 * - Valida en el cliente, arma un FormData y lo envía con fetch a registrar.php.
 * - Usa switch para decidir qué hacer según la acción (Guardar / Modificar / Buscar / Eliminar).
 * - Muestra cada resultado con SweetAlert2 y recarga la tabla con ListarProductos().
 */

// ---------- Referencias al DOM ----------
const frm          = document.getElementById('frm');
const campoAccion  = document.getElementById('Accion');
const campoIdp     = document.getElementById('idp');
const btnRegistrar = document.getElementById('Registrar');
const btnCancelar  = document.getElementById('btnCancelar');
const alertaForm   = document.getElementById('alertaForm');
const tituloForm   = document.getElementById('subtituloForm');
const tarjetaForm  = document.getElementById('tarjetaForm');
const resultado    = document.getElementById('resultado');   // <tbody>
const txtBuscar    = document.getElementById('txtBuscar');

// ---------- Utilidades ----------

// Evita inyectar HTML cuando mostramos textos que vienen del servidor
function escapar(texto) {
    return String(texto ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}

// Lista de errores dentro del Alert de Bootstrap
function mostrarAlerta(errores) {
    alertaForm.innerHTML = '<ul class="mb-0">' +
        errores.map(e => `<li>${escapar(e)}</li>`).join('') + '</ul>';
    alertaForm.classList.remove('d-none');
}

function ocultarAlerta() {
    alertaForm.classList.add('d-none');
    alertaForm.innerHTML = '';
}

// Vuelve el formulario al modo "Registrar"
function limpiarFormulario() {
    frm.reset();                                   // limpia los inputs
    campoAccion.value = 'Guardar';
    campoIdp.value    = '';
    btnRegistrar.value = 'Registrar';
    btnRegistrar.classList.replace('btn-outline-warning', 'btn-outline-success');
    btnCancelar.classList.add('d-none');
    tituloForm.textContent = 'Formulario para Agregar Productos';
    tarjetaForm.classList.remove('modo-edicion');
    ocultarAlerta();
}

// ---------- Validación del lado del cliente (con switch) ----------
function validarCliente(accion) {
    const errores  = [];
    const codigo   = frm.Codigo.value.trim();
    const producto = frm.Producto.value.trim();
    const precio   = parseFloat(frm.Precio.value);
    const cantidad = frm.Cantidad.value.trim();
    let minCantidad;

    switch (accion) {
        case 'Guardar':
            minCantidad = 1;                       // producto nuevo: al menos 1 unidad
            break;
        case 'Modificar':
            minCantidad = 0;                       // puede quedar agotado
            if (!campoIdp.value) errores.push('Selecciona un producto de la tabla para modificar.');
            break;
        default:
            minCantidad = 0;
            errores.push('Acción no válida.');
    }

    if (codigo === '')      errores.push('El código es obligatorio.');
    if (codigo.length > 20) errores.push('El código no puede superar 20 caracteres.');
    if (producto === '')    errores.push('El producto es obligatorio.');
    if (isNaN(precio) || precio <= 0) errores.push('El precio debe ser mayor que 0.');
    if (cantidad === '' || !Number.isInteger(Number(cantidad)) || Number(cantidad) < minCantidad) {
        errores.push(`La cantidad debe ser un entero mayor o igual a ${minCantidad}.`);
    }

    return errores;
}

// ---------- Envío a registrar.php (lo usan Guardar, Modificar, Buscar y Eliminar) ----------
// Siempre devuelve un objeto con success / message / accion / errors, incluso si algo falla.
function enviarARegistrar(datos) {
    return fetch('registrar.php', {
        method: 'POST',
        body: datos                                // el navegador pone el Content-Type correcto
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la respuesta de red del servidor.');
        }
        return response.json();                    // convierte la respuesta del controlador a objeto JS
    })
    .catch(error => {
        console.error('Error:', error);
        const esJson = error instanceof SyntaxError;
        return {
            success: false,
            message: esJson
                ? 'El servidor no devolvió un JSON válido. Revisa que registrar.php solo haga echo json_encode(...).'
                : error.message,
            accion: datos.get('Accion'),
            errors: {}
        };
    });
}

// ---------- Tabla: pide las filas a listar.php ----------
function ListarProductos() {
    const datos = new FormData();
    datos.append('producto', txtBuscar.value.trim());

    fetch('listar.php', { method: 'POST', body: datos })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta de red del servidor.');
            }
            return response.text();                // listar.php devuelve filas <tr> en HTML
        })
        .then(html => {
            resultado.innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            resultado.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">No se pudo cargar la lista de productos.</td></tr>';
        });
}

// ---------- Guardar / Modificar: evento submit ----------
frm.addEventListener('submit', (e) => {
    e.preventDefault();                            // evita que la página se recargue
    ocultarAlerta();

    const accion  = campoAccion.value;
    const errores = validarCliente(accion);

    if (errores.length > 0) {
        mostrarAlerta(errores);
        Swal.fire({ icon: 'warning', title: 'Revisa el formulario', text: errores[0] });
        return;
    }

    const datosFormulario = new FormData(frm);     // toma todos los inputs que tengan name

    enviarARegistrar(datosFormulario).then(response => {
        console.log('Respuesta del servidor:', response);
        const erroresServidor = Object.values(response.errors || {});

        if (response.success) {
            switch (response.accion) {
                case 'Guardar':
                case 'Modificar': {
                    const titulo = response.accion === 'Guardar' ? 'Guardado' : 'Actualizado';
                    Swal.fire({
                        icon: 'success',
                        title: titulo,
                        text: response.message,
                        timer: 1800,
                        showConfirmButton: false
                    });
                    limpiarFormulario();
                    ListarProductos();
                    break;
                }
                default:
                    Swal.fire({ icon: 'info', title: 'Listo', text: response.message });
            }
        } else {
            // El servidor respondió, pero con errores de validación: es una advertencia, no un fallo del sistema
            if (erroresServidor.length > 0) {
                console.warn('Errores de validación:', erroresServidor);
                mostrarAlerta(erroresServidor);
            }
            Swal.fire({
                icon: 'error',
                title: 'No se pudo completar',
                html: `<p>${escapar(response.message)}</p>` +
                      (erroresServidor.length
                        ? '<ul class="text-start">' + erroresServidor.map(m => `<li>${escapar(m)}</li>`).join('') + '</ul>'
                        : '')
            });
        }
    });
});

// ---------- Editar: busca el producto por id y llena el formulario ----------
function Editar(id) {
    const datosBusqueda = new FormData();
    datosBusqueda.append('Accion', 'Buscar');
    datosBusqueda.append('id', id);

    enviarARegistrar(datosBusqueda).then(response => {
        console.log('Datos del producto recuperados:', response);

        if (response.success) {
            // Cambiamos el formulario a modo edición
            btnRegistrar.value = 'Actualizar';
            btnRegistrar.classList.replace('btn-outline-success', 'btn-outline-warning');
            campoAccion.value  = 'Modificar';
            btnCancelar.classList.remove('d-none');
            tituloForm.textContent = `Editando producto #${response.id}`;
            tarjetaForm.classList.add('modo-edicion');

            // Llenamos los inputs (las columnas de la BD vienen en minúsculas)
            campoIdp.value        = response.id;
            frm.Codigo.value      = response.codigo;
            frm.Producto.value    = response.producto;
            frm.Precio.value      = response.precio;
            frm.Cantidad.value    = response.cantidad;

            ocultarAlerta();
            frm.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            Swal.fire({ icon: 'error', title: 'No se pudo cargar el producto', text: response.message });
            ListarProductos();
        }
    });
}

// ---------- Eliminar (reto extra): pide confirmación ----------
function Eliminar(id) {
    Swal.fire({
        icon: 'warning',
        title: '¿Eliminar producto?',
        text: 'Esta acción no se puede deshacer.',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    }).then(confirmacion => {
        if (!confirmacion.isConfirmed) return;

        const datos = new FormData();
        datos.append('Accion', 'Eliminar');
        datos.append('id', id);

        enviarARegistrar(datos).then(response => {
            if (response.success) {
                Swal.fire({ icon: 'success', title: 'Eliminado', text: response.message, timer: 1500, showConfirmButton: false });
                if (campoIdp.value === String(id)) limpiarFormulario();   // si lo estaba editando
                ListarProductos();
            } else {
                Swal.fire({ icon: 'error', title: 'No se pudo eliminar', text: response.message });
            }
        });
    });
}

// ---------- Eventos ----------

// Un solo escuchador en el <tbody>: el clic "sube" (burbujea) desde el botón hasta el contenedor
resultado.addEventListener('click', (e) => {
    switch (e.target.name) {                       // name del botón: Editar o Eliminar
        case 'Editar':
            Editar(e.target.dataset.id);
            break;
        case 'Eliminar':
            Eliminar(e.target.dataset.id);
            break;
    }
});

btnCancelar.addEventListener('click', limpiarFormulario);

// Búsqueda mientras se escribe (espera 300 ms para no consultar en cada tecla)
let temporizador;
txtBuscar.addEventListener('input', () => {
    clearTimeout(temporizador);
    temporizador = setTimeout(ListarProductos, 300);
});

// Al cargar la página, mostrar la tabla
document.addEventListener('DOMContentLoaded', ListarProductos);
