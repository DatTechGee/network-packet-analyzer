<?php
require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$stages = [
    Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
    Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
    Illuminate\Foundation\Bootstrap\HandleExceptions::class,
    Illuminate\Foundation\Bootstrap\RegisterFacades::class,
    Illuminate\Foundation\Bootstrap\SetRequestForConsole::class,
    Illuminate\Foundation\Bootstrap\RegisterProviders::class,
    Illuminate\Foundation\Bootstrap\BootProviders::class,
];

foreach ($stages as $stage) {
    echo "BOOTSTRAP: {$stage}\n";
    try {
        $app->bootstrapWith([$stage]);
        echo "OK\n";
    } catch (Throwable $e) {
        echo "FAILED: ", get_class($e), "\n";
        echo $e->getMessage(), "\n";
        echo $e->getTraceAsString(), "\n";
        exit(1);
    }
}
