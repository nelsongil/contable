<?php
/**
 * fiscalInfoBox — caja de información fiscal colapsable (Nivel 2 del sistema de ayuda)
 *
 * Uso:
 *   require_once __DIR__ . '/../includes/fiscal_info.php';
 *   echo fiscalInfoBox([
 *       'title' => '¿Qué es el Modelo 130?',
 *       'items' => [
 *           ['label' => '¿Qué es?', 'text' => 'El pago fraccionado trimestral del IRPF...'],
 *           ['label' => 'Ejemplo',  'text' => 'Si acumulas 10.000 € de ingresos...'],
 *       ]
 *   ]);
 *
 * El bloque arranca colapsado; el usuario lo expande pulsando el enlace.
 * Los IDs son únicos por llamada dentro de la misma petición.
 */

function fiscalInfoBox(array $config): string
{
    static $counter = 0;
    $counter++;
    $id    = 'fib_' . $counter;
    $title = htmlspecialchars($config['title'] ?? 'Información fiscal', ENT_QUOTES, 'UTF-8');
    $items = $config['items'] ?? [];

    $rows = '';
    foreach ($items as $item) {
        $label = htmlspecialchars($item['label'] ?? '', ENT_QUOTES, 'UTF-8');
        $text  = htmlspecialchars($item['text']  ?? '', ENT_QUOTES, 'UTF-8');
        $rows .= '<dt class="col-sm-4 fw-semibold mb-1" style="font-size:.81rem">' . $label . '</dt>'
               . '<dd class="col-sm-8 mb-1" style="font-size:.81rem;color:var(--text-2)">'  . $text  . '</dd>';
    }

    return '<div class="mb-3">'
         .   '<a class="d-inline-flex align-items-center gap-1 text-decoration-none" '
         .     'style="font-size:.79rem;color:var(--text-3);user-select:none" '
         .     'data-bs-toggle="collapse" href="#' . $id . '" '
         .     'role="button" aria-expanded="false" aria-controls="' . $id . '">'
         .     '<i class="bi bi-info-circle"></i> ' . $title
         .     ' <i class="bi bi-chevron-down" style="font-size:.6em;transition:transform .2s"></i>'
         .   '</a>'
         .   '<div class="collapse mt-2" id="' . $id . '">'
         .     '<div class="p-3 rounded" '
         .       'style="background:var(--surface-2);border:1px solid var(--border)">'
         .       '<dl class="row mb-0">' . $rows . '</dl>'
         .     '</div>'
         .   '</div>'
         . '</div>';
}
