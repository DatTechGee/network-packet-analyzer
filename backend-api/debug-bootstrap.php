<?php
require __DIR__ . '/vendor/autoload.php';

try {
    $app = require __DIR__ . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $status = $kernel->handle(
        new Symfony\Component\Console\Input\ArgvInput(),
        new Symfony\Component\Console\Output\ConsoleOutput()
    );
    echo "STATUS: {$status}\n";
} catch (Throwable $e) {
    echo $e, PHP_EOL;
}
