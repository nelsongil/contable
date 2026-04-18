<?php
$pageTitle = 'Ayuda';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="topbar">
  <h1><i class="bi bi-question-circle me-2"></i>Ayuda — Manual de uso</h1>
</div>

<p class="text-muted mb-4" style="max-width:720px;font-size:.88rem">
  Guía práctica para autónomos. Sin tecnicismos. Todo lo que necesitas para llevar tu contabilidad trimestral.
</p>

<div class="row g-4" style="max-width:860px">
  <div class="col-12">
    <div class="accordion" id="ayudaAccordion">

      <!-- ═══════════════════════════════════════════════════════
           SECCIÓN 1 — Facturas emitidas
      ══════════════════════════════════════════════════════════ -->
      <div class="accordion-item border mb-2 rounded overflow-hidden">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed fw-semibold" type="button"
                  data-bs-toggle="collapse" data-bs-target="#s1" aria-expanded="false">
            <i class="bi bi-receipt me-2 text-primary"></i>
            Sección 1 — Cómo crear y gestionar facturas emitidas
          </button>
        </h2>
        <div id="s1" class="accordion-collapse collapse" data-bs-parent="#ayudaAccordion">
          <div class="accordion-body" style="font-size:.88rem">

            <h6 class="fw-bold mb-2">Crear una factura nueva</h6>
            <ol class="mb-3">
              <li>Ve a <strong>Facturación → Nueva factura</strong>.</li>
              <li>Selecciona el cliente (o escribe su nombre directamente si aún no está en la agenda).</li>
              <li>Añade las líneas de concepto con cantidad, descripción y precio unitario.</li>
              <li>Elige el tipo de IVA (21 %, 10 % o 4 %) y, si aplica, el porcentaje de retención IRPF.</li>
              <li>Guarda como <strong>Emitida</strong> para que compute en el resumen trimestral,
                  o como <strong>Borrador</strong> si aún no está lista para enviar.</li>
            </ol>

            <h6 class="fw-bold mb-2">Numeración automática</h6>
            <p class="mb-2">
              El sistema asigna el número correlativo según el formato configurado en
              <strong>Configuración → Empresa y nº</strong> (prefijo + año + dígitos, ej. <code>F20260001</code>).
              No puedes repetir el mismo número dentro de un año: si necesitas empezar desde otro número,
              ajusta el campo "Próximo nº de factura" en los ajustes.
            </p>

            <h6 class="fw-bold mb-2">Marcar una factura como pagada</h6>
            <p class="mb-2">
              Abre la factura y cambia su estado a <strong>Pagada</strong>. Esto es solo informativo:
              no afecta al cálculo fiscal, que siempre se basa en la fecha de emisión (criterio de caja
              simplificado no está implementado).
            </p>

            <h6 class="fw-bold mb-2">Cancelar una factura</h6>
            <p class="mb-2">
              Cambia el estado a <strong>Cancelada</strong>. Las facturas canceladas <em>no</em> computan
              en los totales ni en los modelos fiscales. No se eliminan para mantener el historial.
            </p>

            <div class="alert alert-warning py-2 mt-3" style="font-size:.82rem">
              <i class="bi bi-exclamation-triangle me-1"></i>
              <strong>Importante:</strong> La numeración debe ser correlativa y sin saltos. Si cancelas una factura,
              el número queda "consumido" aunque no compute fiscalmente.
            </div>

          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════
           SECCIÓN 2 — Gastos / facturas recibidas
      ══════════════════════════════════════════════════════════ -->
      <div class="accordion-item border mb-2 rounded overflow-hidden">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed fw-semibold" type="button"
                  data-bs-toggle="collapse" data-bs-target="#s2" aria-expanded="false">
            <i class="bi bi-bag me-2 text-success"></i>
            Sección 2 — Cómo registrar y gestionar gastos
          </button>
        </h2>
        <div id="s2" class="accordion-collapse collapse" data-bs-parent="#ayudaAccordion">
          <div class="accordion-body" style="font-size:.88rem">

            <h6 class="fw-bold mb-2">Registrar un gasto</h6>
            <ol class="mb-3">
              <li>Ve a <strong>Facturación → Nueva compra</strong>.</li>
              <li>Introduce el proveedor, fecha, número de factura del proveedor, base imponible e IVA.</li>
              <li>Selecciona la <strong>categoría de gasto</strong>: es lo más importante porque define
                  qué porcentaje del IVA puedes deducirte y qué parte del gasto reduce tu IRPF.</li>
              <li>Guarda. El gasto aparecerá en el resumen trimestral del periodo correspondiente.</li>
            </ol>

            <h6 class="fw-bold mb-2">Categorías de gasto y deducibilidad</h6>
            <p class="mb-2">
              Cada categoría tiene dos porcentajes clave:
            </p>
            <ul class="mb-3">
              <li><strong>% IVA deducible:</strong> qué parte del IVA pagado te puedes deducir.
                  Ejemplo: un ordenador para el trabajo = 100 %. Un coche de uso mixto = 50 %.</li>
              <li><strong>% IRPF deducible:</strong> qué parte de la base reduce tu rendimiento neto.
                  Un seguro de hogar donde trabajas en parte = 30 %. Un gasto íntegramente profesional = 100 %.</li>
            </ul>
            <p class="mb-3">
              Gestiona las categorías en <strong>Configuración → Categorías de gasto</strong>.
              Si el gasto no encaja en ninguna, usa la categoría <em>Otros gastos deducibles</em>.
            </p>

            <h6 class="fw-bold mb-2">Ejemplo práctico</h6>
            <div class="p-3 rounded mb-3" style="background:var(--surface-2);border:1px solid var(--border)">
              Compras un ordenador por <strong>1.000 € + 210 € IVA</strong>.<br>
              Categoría: <em>Hardware y equipos</em> (100 % IVA deducible, 100 % IRPF deducible).<br>
              → Deduces los 210 € de IVA en el Modelo 303.<br>
              → Los 1.000 € reducen tu base del Modelo 130 (ahorras 200 € de IRPF si tu base es positiva).
            </div>

            <div class="alert alert-info py-2" style="font-size:.82rem">
              <i class="bi bi-info-circle me-1"></i>
              Guarda siempre la factura original del proveedor. En caso de inspección, es el documento
              justificativo imprescindible.
            </div>

          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════
           SECCIÓN 3 — Declaraciones trimestrales
      ══════════════════════════════════════════════════════════ -->
      <div class="accordion-item border mb-2 rounded overflow-hidden">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed fw-semibold" type="button"
                  data-bs-toggle="collapse" data-bs-target="#s3" aria-expanded="false">
            <i class="bi bi-calendar3 me-2 text-warning"></i>
            Sección 3 — Cada trimestre: qué declarar en Hacienda
          </button>
        </h2>
        <div id="s3" class="accordion-collapse collapse" data-bs-parent="#ayudaAccordion">
          <div class="accordion-body" style="font-size:.88rem">

            <p class="mb-3">
              Como autónomo en estimación directa simplificada presentas modelos trimestrales entre el
              <strong>1 y el 20 del mes siguiente</strong> al trimestre (T1: abril, T2: julio, T3: octubre, T4: enero).
            </p>

            <!-- 3.1 Modelo 303 -->
            <h6 class="fw-bold mb-1">3.1 Modelo 303 — IVA (próximamente automático)</h6>
            <p class="mb-3">
              El Modelo 303 es la declaración trimestral del IVA. Consulta los datos en
              <strong>Fiscal → Resumen trimestral</strong>, sección IVA.
              Los importes que necesitas son: IVA devengado en ventas, IVA deducible en compras
              y el resultado (positivo = ingresas, negativo = compensas).
            </p>

            <!-- 3.2 Modelo 130 -->
            <h6 class="fw-bold mb-1">3.2 Modelo 130 — Pago fraccionado IRPF</h6>
            <p class="mb-1">
              Ve a <strong>Fiscal → Modelo 130</strong>. La app calcula automáticamente lo que debes ingresar.
            </p>
            <div class="p-3 rounded mb-3" style="background:var(--surface-2);border:1px solid var(--border)">
              <strong>Ejemplo T2:</strong> Acumulas 20.000 € de ingresos y 8.000 € de gastos desde enero.
              Base acumulada = 12.000 €. El 20% son 2.400 €. Si te retuvieron 500 € en facturas
              y ya pagaste 700 € en T1, ingresas <strong>1.200 €</strong> en T2.
            </div>

            <!-- 3.3 Modelo 115 -->
            <h6 class="fw-bold mb-1">3.3 Modelo 115 — Retenciones arrendamientos</h6>
            <p class="mb-3">
              Solo si alquilas un local u oficina para tu actividad. Ve a <strong>Fiscal → Modelo 115</strong>.
              Registra las facturas de alquiler en Compras con la categoría <em>Arrendamiento</em>
              e indica el porcentaje de retención IRPF (habitualmente 19 %).
            </p>

            <!-- 3.4 Modelo 347 -->
            <h6 class="fw-bold mb-1">3.4 Modelo 347 — Operaciones con terceros (anual)</h6>
            <p class="mb-3">
              Se presenta <strong>en febrero</strong> del año siguiente. Incluye a todos los clientes
              y proveedores con los que hayas facturado más de 3.005,06 € en el año.
              Ve a <strong>Fiscal → Modelo 347</strong> para ver el listado.
            </p>

            <!-- 3.5 Modelo 111 -->
            <h6 class="fw-bold mb-1">3.5 Modelo 111 — Retenciones empleados (si tienes empleados)</h6>
            <p class="mb-3">
              Si tienes empleados y el módulo está activado, ve a <strong>Laboral → Modelo 111</strong>.
              Registra las retenciones mensuales en <strong>Laboral → Retenciones mensuales</strong>
              y la app consolida los datos por trimestre.
            </p>

            <!-- 3.6 Exportar libros AEAT -->
            <h6 class="fw-bold mb-1">3.6 Cómo exportar los libros oficiales para Hacienda</h6>
            <p class="mb-1">
              Si Hacienda te pide los Libros Registro de Facturas, descárgalos desde
              <strong>Libros → Exportación AEAT</strong>. Ya tienen el formato correcto.
            </p>
            <p class="mb-1">
              El formato cumple la <em>Orden HAC/773/2019</em>: archivo CSV con codificación UTF-8,
              separador punto y coma, decimales con coma y fechas DD/MM/AAAA.
              Excel lo abre directamente sin recodificación.
            </p>
            <div class="p-3 rounded mb-3" style="background:var(--surface-2);border:1px solid var(--border)">
              <strong>Pasos:</strong>
              <ol class="mb-0 ps-3">
                <li>Ve a <strong>Libros → Exportación AEAT</strong>.</li>
                <li>Selecciona el año y (opcionalmente) el trimestre.</li>
                <li>Pulsa <em>Descargar Libro Expedidas</em> y/o <em>Descargar Libro Recibidas</em>.</li>
                <li>El archivo descargado es el que entregas a Hacienda o a tu asesor.</li>
              </ol>
            </div>
            <p class="mb-3" style="font-size:.82rem;color:var(--text-3)">
              Para que el código AEAT de cada gasto sea correcto, asegúrate de tener configuradas
              las categorías de gasto con su código G correspondiente en
              <strong>Configuración → Categorías de gasto</strong>.
            </p>

          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════
           SECCIÓN 4 — Configuración y ajustes
      ══════════════════════════════════════════════════════════ -->
      <div class="accordion-item border mb-2 rounded overflow-hidden">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed fw-semibold" type="button"
                  data-bs-toggle="collapse" data-bs-target="#s4" aria-expanded="false">
            <i class="bi bi-gear me-2 text-secondary"></i>
            Sección 4 — Configuración y ajustes
          </button>
        </h2>
        <div id="s4" class="accordion-collapse collapse" data-bs-parent="#ayudaAccordion">
          <div class="accordion-body" style="font-size:.88rem">

            <h6 class="fw-bold mb-2">Datos de empresa y numeración</h6>
            <p class="mb-3">
              En <strong>Configuración → Empresa y nº</strong> configuras el nombre, CIF, dirección,
              el porcentaje de IRPF que aplicas por defecto en las facturas (retención que te practican
              tus clientes) y el formato de numeración de facturas.
            </p>

            <h6 class="fw-bold mb-2">Plantilla de factura</h6>
            <p class="mb-3">
              En <strong>Configuración → Plantilla factura</strong> configuras los colores,
              el logo y los textos legales que aparecerán en el PDF de tus facturas.
            </p>

            <h6 class="fw-bold mb-2">Tema de la interfaz</h6>
            <p class="mb-3">
              En <strong>Configuración → Tema interfaz</strong> puedes personalizar los colores
              de la aplicación. También puedes alternar entre modo claro y oscuro con el botón
              de luna/sol en la parte inferior del menú lateral.
            </p>

            <h6 class="fw-bold mb-2">Copias de seguridad</h6>
            <p class="mb-3">
              En <strong>Configuración → Copias de seguridad</strong> puedes descargar un volcado
              completo de tu base de datos en formato SQL. Hazlo antes de cualquier actualización
              importante o al final de cada trimestre.
            </p>

            <h6 class="fw-bold mb-2">Actualizaciones</h6>
            <p class="mb-3">
              La app comprueba automáticamente si hay nuevas versiones disponibles.
              Cuando aparezca la barra de aviso en la parte superior, ve a
              <strong>Configuración → Actualizaciones</strong> para aplicarla.
              El proceso realiza una copia de seguridad automática antes de actualizar.
            </p>

            <div class="alert alert-info py-2" style="font-size:.82rem">
              <i class="bi bi-info-circle me-1"></i>
              Esta aplicación es una herramienta de <strong>control contable</strong>, no un sustituto
              de un asesor fiscal. Los cálculos son orientativos — consulta siempre con un profesional
              antes de presentar tus declaraciones.
            </div>

          </div>
        </div>
      </div>

    </div><!-- /accordion -->
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
