<?php

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Force the test env even when the container injects a real APP_ENV (e.g. dev) via env_file.
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
putenv('APP_ENV=test');

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Recreate the test database schema once before the suite runs.
$kernel = new Kernel('test', (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$application = new Application($kernel);
$application->setAutoExit(false);
$output = new NullOutput();
try {
    $application->run(new ArrayInput(['command' => 'doctrine:schema:drop', '--full-database' => true, '--force' => true]), $output);
} catch (Exception $e) {

}
try {
    $application->run(new ArrayInput(['command' => 'doctrine:schema:create']), $output);
} catch (Exception $e) {

}

$kernel->shutdown();
