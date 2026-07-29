<?php
// DIAGNÓSTICO TEMPORAL - Borrar después
header('Content-Type: application/json');

echo json_encode([
    'php_self' => $_SERVER['PHP_SELF'],
    'document_root' => $_SERVER['DOCUMENT_ROOT'],
    'script_filename' => __FILE__,
    'dirname_file' => dirname(__FILE__),
    'dir_parent' => dirname(dirname(__FILE__)),
    'relative_gen' => dirname(dirname(__FILE__)) . '/landings_gen/',
    'relative_gen_exists' => is_dir(dirname(dirname(__FILE__)) . '/landings_gen/'),
    'relative_gen_files' => @scandir(dirname(dirname(__FILE__)) . '/landings_gen/'),
]);
?>
