<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

// Xdebug does not mark property initializers as executable lines. Exercise their
// mutations explicitly instead of claiming Pest's covered-only score includes them.
$root = dirname(__DIR__);
$source = file_get_contents($root.'/src/Http/Middleware/VerifyPostmarkWebhookAuth.php');
if ($source === false) {
    throw new RuntimeException('Cannot read middleware.');
}
foreach (['18.217.206.57', '3.134.147.250', '50.31.156.6', '50.31.156.77'] as $index => $ip) {
    $directory = $root.'/.build/ip-mutations/'.$index;
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
    $mutant = str_replace("        '".$ip."',\n", '', $source, $replacements);
    if ($replacements !== 1) {
        throw new RuntimeException('Expected exactly one allowlist entry for '.$ip);
    }
    file_put_contents($directory.'/middleware.php', $mutant);
    file_put_contents($directory.'/bootstrap.php', "<?php\nrequire_once ".var_export($root.'/vendor/autoload.php', true).";\nrequire __DIR__.'/middleware.php';\n");
    $process = new Process([
        PHP_BINARY, $root.'/vendor/bin/pest',
        '--bootstrap='.$directory.'/bootstrap.php',
        '--filter=test_production_ip_filter',
        '--log-junit='.$directory.'/junit.xml',
        '--colors=never',
    ], $root, ['XDEBUG_MODE' => 'off']);
    $process->setTimeout(60);
    $process->run();
    if ($process->isSuccessful() || ! is_file($directory.'/junit.xml')) {
        throw new RuntimeException('Allowlist mutation was not detected for '.$ip.': '.$process->getOutput().$process->getErrorOutput());
    }
    $report = simplexml_load_file($directory.'/junit.xml');
    if ($report === false) {
        throw new RuntimeException('Cannot read mutation test report.');
    }
    $failures = $report->xpath('//testcase/failure');
    $errors = $report->xpath('//testcase/error');
    if (! is_array($failures) || count($failures) !== 1 || ! isset($failures[0]) || ! is_array($errors) || count($errors) !== 0
        || ! str_contains((string) $failures[0], 'Expected response status code [200] but received 401.')) {
        throw new RuntimeException('Unexpected failure for allowlist mutant '.$ip.': '.$process->getOutput().$process->getErrorOutput());
    }
    echo 'Killed allowlist removal: '.$ip, PHP_EOL;
}
