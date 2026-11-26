<?php
require __DIR__ . '/../vendor/autoload.php';
spl_autoload_register(function ($class) {
    $prefix = 'OfferteWizard\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $file = __DIR__ . '/../offerte-wizard/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
