<?php

declare(strict_types=1);

// Generate an isolated consumer; never rewrite the package manifest or lock file.
$laravel = $argv[1] ?? '13';
$resolution = $argv[2] ?? 'latest';
if (! in_array($laravel, ['12', '13'], true) || ! in_array($resolution, ['latest', 'lowest'], true)) {
    throw new InvalidArgumentException('Usage: php tools/prepare-matrix.php <12|13> <latest|lowest>');
}

$root = dirname(__DIR__);
$php = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
$directory = $root.'/.build/matrix/php'.$php.'-laravel'.$laravel.'-'.$resolution;
if (! is_dir($directory) && ! mkdir($directory, 0777, true)) {
    throw new RuntimeException('Cannot create matrix directory.');
}
if (! is_dir($directory.'/tests') && ! symlink($root.'/tests', $directory.'/tests')) {
    throw new RuntimeException('Cannot link the shared test suite.');
}

$legacy = PHP_VERSION_ID < 80400 || $laravel === '12';
$require = [
    'php' => '^'.$php,
    'citricguy/postmark-webhooks-laravel' => '^2.0',
    'laravel/framework' => '^'.$laravel.'.0',
];
if ($php === '8.5' && $laravel === '13') {
    $require['symfony/http-client'] = '^8.1';
    $require['symfony/postmark-mailer'] = '^8.1';
}
$runner = $legacy ? 'phpunit' : 'pest';
$manifest = [
    'name' => 'citricguy/postmark-consumer-check',
    'description' => 'Isolated installation and compatibility checks for Postmark Webhooks.',
    'type' => 'project',
    'license' => 'MIT',
    'require' => $require,
    'require-dev' => [
        'orchestra/testbench' => $laravel === '12' ? '^10.11' : '^11.2',
        $legacy ? 'phpunit/phpunit' : 'pestphp/pest' => $legacy ? '^12.5.33' : '^5.1.4',
    ],
    'repositories' => [[
        'type' => 'path',
        'url' => $root,
        'options' => [
            'symlink' => true,
            // A fixture-only stable version lets us verify the real ^2.0 constraint.
            'versions' => ['citricguy/postmark-webhooks-laravel' => '2.2.0'],
        ],
    ]],
    'autoload-dev' => ['psr-4' => ['Citricguy\\PostmarkWebhooks\\Tests\\' => $root.'/tests/']],
    'minimum-stability' => 'stable',
    'prefer-stable' => true,
    'config' => ['allow-plugins' => ['pestphp/pest-plugin' => true]],
    'scripts' => [
        'test' => '@php vendor/bin/'.$runner.' --configuration '.escapeshellarg($root.'/phpunit.xml.dist').' --bootstrap vendor/autoload.php',
        'test:consumer' => '@php '.escapeshellarg($root.'/tools/consumer-check.php').' '.escapeshellarg($directory),
    ],
];
file_put_contents($directory.'/composer.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
echo $directory, PHP_EOL;
