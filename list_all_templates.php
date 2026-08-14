<?php
$dir1 = 'template/';
$dir2 = 'uploads/supervision_docs/templates/';

function scanFiles($dir) {
    $result = [];
    if (!is_dir($dir)) return $result;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'pdf') {
            $path = str_replace('\\', '/', $file->getPathname());
            $result[$path] = [
                'size' => $file->getSize(),
                'md5' => md5_file($path),
                'name' => $file->getBasename()
            ];
        }
    }
    return $result;
}

echo "=== TEMPLATE FOLDER ===\n";
print_r(scanFiles($dir1));

echo "\n=== UPLOADS TEMPLATES ===\n";
print_r(scanFiles($dir2));
