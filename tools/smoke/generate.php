<?php

declare(strict_types=1);

/**
 * Smoke test helper: generate an export file using either legacy (pre-refactor)
 * classes or the refactored composer-lib generators.
 *
 * This script is designed to run INSIDE a FrontAccounting installation
 * (staging/prod clone) where db_query/db_fetch/TB_PREF are available.
 */

function stderr(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
}

function stdout(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function fail(string $message, int $code = 1): void
{
    stderr($message);
    exit($code);
}

function usage(): void
{
    $msg = <<<TXT
Usage:
  php tools/smoke/generate.php \
    --fa-root=/path/to/frontaccounting \
    --module=/path/to/module (e.g. .../modules/ksf_generate_catalogue) \
    --mode=legacy|refactor \
    --generator=pricebook|square|woocommerce|labels|woopos|woocommerce_stock_price|square_stock_price \
    --prefs-table=ksf_prefs \
    --out-file=/path/to/output.csv \
    [--tb-pref=0_] \
    [--autoload=/path/to/vendor/autoload.php]

Notes:
- This script MUST run in an environment where FrontAccounting DB functions are available.
- Use --autoload if the refactored generator autoloader is not in a standard location.
TXT;

    stdout($msg);
}

$opts = getopt('', [
    'fa-root:',
    'module:',
    'mode:',
    'generator:',
    'prefs-table:',
    'out-file:',
    'tb-pref::',
    'autoload::',
    'help::',
]);

if (isset($opts['help'])) {
    usage();
    exit(0);
}

$faRoot = $opts['fa-root'] ?? null;
$modulePath = $opts['module'] ?? null;
$mode = $opts['mode'] ?? null;
$generator = $opts['generator'] ?? null;
$prefsTable = $opts['prefs-table'] ?? null;
$outFile = $opts['out-file'] ?? null;
$tbPref = $opts['tb-pref'] ?? null;
$autoloadOverride = $opts['autoload'] ?? null;

if (!$faRoot || !$modulePath || !$mode || !$generator || !$prefsTable || !$outFile) {
    usage();
    fail('Missing required arguments.', 2);
}

$faRootReal = realpath($faRoot);
if ($faRootReal === false) {
    fail("FA root not found: {$faRoot}", 2);
}

$moduleReal = realpath($modulePath);
if ($moduleReal === false) {
    fail("Module path not found: {$modulePath}", 2);
}

$outDir = dirname($outFile);
if (!is_dir($outDir) && !@mkdir($outDir, 0777, true)) {
    fail("Could not create output directory: {$outDir}", 2);
}

// Bootstrap FrontAccounting DB layer.
// Many FA scripts expect $path_to_root to exist.
$path_to_root = $faRootReal;

$sessionInc = $faRootReal . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'session.inc';
$connectDbInc = $faRootReal . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'connect_db.inc';

if (is_file($sessionInc)) {
    require_once $sessionInc;
} elseif (is_file($connectDbInc)) {
    require_once $connectDbInc;
} else {
    fail("Could not find FrontAccounting bootstrap files under: {$faRootReal}", 2);
}

if ($tbPref !== null && !defined('TB_PREF')) {
    define('TB_PREF', (string)$tbPref);
}

if (!function_exists('db_query') || !function_exists('db_fetch')) {
    fail('FrontAccounting DB functions not available (db_query/db_fetch).', 3);
}

// Switch working directory to module so relative includes like ../ksf_modules_common work.
chdir($moduleReal);

// Ensure we start clean.
if (file_exists($outFile)) {
    @unlink($outFile);
}

if ($mode === 'legacy') {
    $legacyMap = [
        'pricebook' => ['file' => 'class.pricebook_file.php', 'class' => 'pricebook_file', 'needs_set_query' => false],
        'square' => ['file' => 'class.square_catalog.php', 'class' => 'square_catalog', 'needs_set_query' => false],
        'woocommerce' => ['file' => 'class.woocommerce_import.php', 'class' => 'woocommerce_import', 'needs_set_query' => true],
        'labels' => ['file' => 'class.labels_file.php', 'class' => 'labels_file', 'needs_set_query' => false],
        'woopos' => ['file' => 'class.WooPOS_Count.php', 'class' => 'WooPOS_Count_file', 'needs_set_query' => false],
    ];

    if (!isset($legacyMap[$generator])) {
        fail("Unsupported legacy generator: {$generator}", 2);
    }

    $file = $legacyMap[$generator]['file'];
    $class = $legacyMap[$generator]['class'];
    $needsSetQuery = $legacyMap[$generator]['needs_set_query'];

    if (!is_file($file)) {
        fail("Legacy generator file not found in module: {$file}", 2);
    }

    require_once $file;

    if (!class_exists($class)) {
        fail("Legacy class not found after include: {$class}", 3);
    }

    $instance = new $class($prefsTable);

    // Force output location and name.
    $instance->tmp_dir = $outDir;
    $instance->filename = basename($outFile);

    // Avoid emailing during smoke tests.
    $instance->b_email = false;

    if ($needsSetQuery && method_exists($instance, 'setQuery')) {
        $instance->setQuery();
    }

    $rows = $instance->create_file();

    if (!file_exists($outFile)) {
        fail("Legacy generator did not create output file: {$outFile}", 4);
    }

    stdout(json_encode([
        'mode' => 'legacy',
        'generator' => $generator,
        'out_file' => $outFile,
        'rows' => $rows,
    ], JSON_PRETTY_PRINT));

    exit(0);
}

if ($mode === 'refactor') {
    // Locate autoloader for composer-lib.
    $autoloadCandidates = [];
    if ($autoloadOverride) {
        $autoloadCandidates[] = $autoloadOverride;
    }
    $autoloadCandidates[] = $moduleReal . DIRECTORY_SEPARATOR . 'composer-lib' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    $autoloadCandidates[] = $moduleReal . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    $autoloadCandidates[] = $faRootReal . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

    $autoload = null;
    foreach ($autoloadCandidates as $candidate) {
        if ($candidate && is_file($candidate)) {
            $autoload = $candidate;
            break;
        }
    }

    if ($autoload === null) {
        fail("Could not locate composer autoload.php for refactored mode. Tried: " . implode(', ', $autoloadCandidates), 2);
    }

    require_once $autoload;

    $factoryClass = 'Ksfraser\\Frontaccounting\\GenCat\\CatalogueGeneratorFactory';
    $dbClass = 'Ksfraser\\Frontaccounting\\GenCat\\FrontAccountingDatabase';

    if (!class_exists($factoryClass) || !class_exists($dbClass)) {
        fail('Refactored classes not found; check autoloader path.', 3);
    }

    $database = new $dbClass();
    $factory = new $factoryClass($database, $prefsTable);

    $gen = $factory->createGeneratorByName($generator, []);

    // Force output location and name.
    if (method_exists($gen, 'setTmpDir')) {
        $gen->setTmpDir($outDir);
    }
    if (method_exists($gen, 'setOutputFilename')) {
        $gen->setOutputFilename(basename($outFile));
    }
    if (property_exists($gen, 'b_email')) {
        $gen->b_email = false;
    }

    $rows = $gen->createFile();

    if (!file_exists($outFile)) {
        fail("Refactored generator did not create output file: {$outFile}", 4);
    }

    stdout(json_encode([
        'mode' => 'refactor',
        'generator' => $generator,
        'out_file' => $outFile,
        'rows' => $rows,
        'autoload' => $autoload,
    ], JSON_PRETTY_PRINT));

    exit(0);
}

fail("Unknown mode: {$mode} (expected legacy|refactor)", 2);
