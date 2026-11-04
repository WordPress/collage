<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/TestRunner.php';

$runner = new TestRunner();
foreach (glob(__DIR__ . '/*/*Test.php') as $file) {
    $runner->runTests(require $file, basename($file));
}

printf("%d assertions, %d failures\n", $runner->assertions(), $runner->failures());
exit($runner->failures() > 0 ? 1 : 0);
