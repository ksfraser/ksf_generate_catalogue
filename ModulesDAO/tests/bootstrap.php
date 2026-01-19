<?php

$local = __DIR__ . '/../vendor/autoload.php';
if (is_file($local)) {
	require_once $local;
	return;
}

$monorepo = __DIR__ . '/../../composer-lib/vendor/autoload.php';
if (is_file($monorepo)) {
	require_once $monorepo;
	return;
}

throw new RuntimeException('Could not locate Composer autoloader for ModulesDAO tests');
