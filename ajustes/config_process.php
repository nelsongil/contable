<?php
/**
 * Lógica de Exportación/Importación de Configuración JSON
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';

require_once __DIR__ . '/../includes/auth.php';
if (!isAdmin()) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'No autorizado.']);
    exit;
}

$action = get('action');
header('Content-Type: application/json');

switch ($action) {
    case 'export':
        $config = [
            'version' => APP_VERSION,
            'fecha_export' => date('Y-m-d'),
            'empresa' => [
                'empresa_nombre'   => getConfig('empresa_nombre'),
                'empresa_sociedad' => getConfig('empresa_sociedad'),
                'empresa_cif'      => getConfig('empresa_cif'),
                'empresa_dir1'     => getConfig('empresa_dir1'),
                'empresa_dir2'     => getConfig('empresa_dir2'),
                'empresa_tel'      => getConfig('empresa_tel'),
                'empresa_email'    => getConfig('empresa_email'),
                'empresa_web'      => getConfig('empresa_web'),
                'empresa_banco'    => getConfig('empresa_banco'),
                'empresa_iban'     => getConfig('empresa_iban'),
                'empresa_iva_def'  => getConfig('empresa_iva_def'),
                'empresa_irpf_def' => getConfig('empresa_irpf_def'),
            ],
            'numeracion' => [
                'factura_prefijo'  => getConfig('factura_prefijo'),
                'factura_usa_anio' => getConfig('factura_usa_anio'),
                'factura_digitos'  => getConfig('factura_digitos'),
                'factura_proximo'  => getConfig('factura_proximo'),
            ],
            'plantilla' => [
                'factura_color'    => getConfig('factura_color'),
                'factura_fuente'   => getConfig('factura_fuente'),
                'factura_pie'      => getConfig('factura_pie'),
                'factura_logo_width' => getConfig('factura_logo_width'),
                'factura_nota_legal' => getConfig('factura_nota_legal'),
            ],
            'tema' => [
                'theme_color_primary' => getConfig('theme_color_primary'),
                'theme_color_medium'  => getConfig('theme_color_medium'),
                'theme_color_accent'  => getConfig('theme_color_accent'),
                'theme_color_gold'    => getConfig('theme_color_gold'),
                'theme_color_bg'      => getConfig('theme_color_bg'),
            ]
        ];
        
        // El navegador manejará la descarga si devolvemos el JSON con headers de archivo
        if (get('download') === '1') {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="config_contable_' . date('Ymd') . '.json"');
            echo json_encode($config, JSON_PRETTY_PRINT);
            exit;
        }
        echo json_encode(['ok' => true, 'config' => $config]);
        break;

    case 'import_preview':
        if (!isset($_FILES['config_file'])) {
            echo json_encode(['ok' => false, 'error' => 'No se ha subido ningún archivo.']);
            exit;
        }
        $data = json_decode(file_get_contents($_FILES['config_file']['tmp_name']), true);
        if (!$data || !isset($data['version'])) {
            echo json_encode(['ok' => false, 'error' => 'Archivo JSON no válido.']);
            exit;
        }
        echo json_encode(['ok' => true, 'data' => $data]);
        break;

    case 'import_apply':
        $json = post('json');
        $sections = post('sections', []); // Array de secciones a importar
        $data = json_decode($json, true);

        if (!$data) {
            echo json_encode(['ok' => false, 'error' => 'Error al procesar los datos para importar.']);
            exit;
        }

        foreach ($sections as $section) {
            if (isset($data[$section])) {
                foreach ($data[$section] as $key => $val) {
                    setConfig($key, $val);
                }
            }
        }
        echo json_encode(['ok' => true]);
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Acción no permitida.']);
}
