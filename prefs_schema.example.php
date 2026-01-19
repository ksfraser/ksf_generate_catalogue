<?php

use Ksfraser\Prefs\Schema\GlobalPrefsSchemaRegistry;
use Ksfraser\Prefs\Schema\PrefsSchema;

// Example schema registration.
// Copy this file to prefs_schema.php and adjust keys/defaults for your module/app.

$schema = (new PrefsSchema())
    ->addKey('gencat.output.enabled', true, true, 'Enable catalogue output generation')
    ->addKey('gencat.output.destinations', 'all', false, 'Output destinations to generate')
    ->addKey('gencat.square.location_id', '', false, 'Square location id');

GlobalPrefsSchemaRegistry::addSchema($schema);
