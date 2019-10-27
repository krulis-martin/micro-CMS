<?php

require __DIR__ . '/vendor/autoload.php';

spl_autoload_register(function ($class) {
    $decomposed = explode('\\', $class);
    if (count($decomposed) > 1 && $decomposed[0] === 'uCMS') {
        $decomposed[0] = 'app';     // all our classes are in app dir
        $fullPath = __DIR__ . '/' . implode('/', $decomposed) . '.php';
        if (!is_file($fullPath)) {
            throw new Exception("Unable to autoload class $class.");
        }
        require_once __DIR__ . '/' . implode('/', $decomposed) . '.php';
    }
});

try {
    // In the first version, the config is loaded from exact file.
    $config = uCMS\Config::loadYaml(__DIR__ . '/config/config.yaml');
}
catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Internal Error: Unable to load configuration file.\n";
    exit;
}

try {
    $app = new uCMS\App($config);
    $app->execute($_SERVER['REQUEST_URI']);
}
catch (Exception $e) {
    $app->showErrorPage();
}
