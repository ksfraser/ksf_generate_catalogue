<?php

$page_security = 'SA_ksf_generate_catalogue';
$path_to_root = "../..";

include($path_to_root . "/includes/session.inc");
add_access_extensions();
set_ext_domain('modules/ksf_generate_catalogue');

include_once($path_to_root . "/includes/ui.inc");

// Composer autoload (Prefs/ModulesDAO/HTML module)
$autoload = $path_to_root . "/modules/ksf_generate_catalogue/composer-lib/vendor/autoload.php";
if (is_file($autoload)) {
    require_once $autoload;
}

// Optional schema bootstrap for this module/app.
$schemaBootstrap = $path_to_root . "/modules/ksf_generate_catalogue/prefs_schema.php";
if (is_file($schemaBootstrap)) {
    require_once $schemaBootstrap;
}

use Ksfraser\Prefs\Config\IniConfig;
use Ksfraser\Prefs\Manager\PrefsStoreManager;
use Ksfraser\Prefs\Runtime\FrameworkDetector;
use Ksfraser\Prefs\Schema\GlobalPrefsSchemaRegistry;
use Ksfraser\Prefs\Sync\PrefsDiffEngine;
use Ksfraser\HTML\FaUiFunctions;

$configPath = $path_to_root . "/modules/ksf_generate_catalogue/prefs.ini";

page(_("Prefs Store"));

try {
    $cfg = new IniConfig($configPath);
    $schema = GlobalPrefsSchemaRegistry::getSchema();
    // If nothing is registered, treat schema as absent.
    if (count($schema->all()) === 0) {
        $schema = null;
    }
    $manager = new PrefsStoreManager($cfg, null, null, $schema);

    $current = $manager->getCurrentDaoConfig();

    $action = $_POST['prefs_action'] ?? '';

    $availableTypes = FrameworkDetector::getAvailableStoreTypeLabels();

    $buildTargetConfig = static function (array $post): array {
        $targetType = (string)($post['target_type'] ?? '');
        $codec = isset($post['target_codec']) ? (bool)$post['target_codec'] : null;

        $target = ['type' => $targetType];
        if ($codec !== null) {
            $target['codec'] = $codec;
        }

        foreach (['dsn','user','password','table','name_col','value_col','category','prefix','autoload','path','section'] as $k) {
            if (isset($post['target_' . $k]) && $post['target_' . $k] !== '') {
                $target[$k] = $post['target_' . $k];
            }
        }

        return $target;
    };

    $parseKnownKeys = static function (array $post) use ($cfg): ?array {
        $keys = [];

        // UI-provided keys (one per line).
        if (isset($post['sync_keys']) && trim((string)$post['sync_keys']) !== '') {
            foreach (array_map('trim', preg_split('/\R+/', (string)$post['sync_keys'])) as $k) {
                if ($k !== '') {
                    $keys[] = $k;
                }
            }
        }

        // Config-provided keys: [sync] keys[] = "foo"
        $configKeys = $cfg->get('sync', 'keys', null);
        if (is_array($configKeys)) {
            foreach ($configKeys as $k) {
                $k = trim((string)$k);
                if ($k !== '') {
                    $keys[] = $k;
                }
            }
        }

        $keys = array_values(array_unique($keys));
        return count($keys) > 0 ? $keys : null;
    };

    if ($action === 'sync' || $action === 'switch') {
        $target = $buildTargetConfig($_POST);
        $keys = $parseKnownKeys($_POST);
        $prefix = isset($_POST['sync_prefix']) && trim((string)$_POST['sync_prefix']) !== '' ? (string)$_POST['sync_prefix'] : null;

        if ($action === 'sync') {
            $count = $manager->syncTo($target, $keys, $prefix);
            display_notification("Synced {$count} keys (no switch)");
        } else {
            $count = $manager->switchTo($target, $keys, $prefix);
            display_notification("Switched store; synced {$count} keys");
            $current = $manager->getCurrentDaoConfig();
        }
    }

    if ($action === 'apply_defaults_current') {
        $count = $manager->applyDefaultsToCurrent();
        display_notification("Applied {$count} default(s) to current store");
    }

    if ($action === 'apply_defaults_target') {
        $target = $buildTargetConfig($_POST);
        $count = $manager->applyDefaultsToTarget($target);
        display_notification("Applied {$count} default(s) to target store");
    }

    if ($action === 'review_action') {
        $target = $buildTargetConfig($_POST);
        $keys = $parseKnownKeys($_POST);
        $prefix = isset($_POST['sync_prefix']) && trim((string)$_POST['sync_prefix']) !== '' ? (string)$_POST['sync_prefix'] : null;

        $from = $manager->getCurrentStore();
        $to = $manager->createPrefsStoreFromDaoConfig($target);

        $selected = isset($_POST['selected_keys']) && is_array($_POST['selected_keys'])
            ? array_values(array_filter(array_map('strval', $_POST['selected_keys'])))
            : [];

        $op = (string)($_POST['review_op'] ?? '');
        $sync = new \Ksfraser\Prefs\Sync\PrefsSynchronizer();

        if ($op === 'copy_from_to') {
            $count = $sync->copyKeys($from, $to, $selected);
            display_notification("Copied {$count} key(s) current → target");
        } elseif ($op === 'copy_to_from') {
            $count = $sync->copyKeys($to, $from, $selected);
            display_notification("Copied {$count} key(s) target → current");
        } elseif ($op === 'delete_from') {
            $count = $sync->deleteKeys($from, $selected);
            display_notification("Deleted {$count} key(s) from current");
        } elseif ($op === 'delete_to') {
            $count = $sync->deleteKeys($to, $selected);
            display_notification("Deleted {$count} key(s) from target");
        } elseif ($op === 'prune_to') {
            $diffEngine = new PrefsDiffEngine();
            $schemaForDiff = $manager->getSchema();
            $diff = $diffEngine->diff($from, $to, $keys, $prefix, $schemaForDiff);
            $count = $sync->deleteKeys($to, array_keys($diff['onlyInTo']));
            display_notification("Pruned {$count} extra key(s) from target");
        }

        // Show updated review table.
        $action = 'review';
    }

    start_form(true);

    FaUiFunctions::start_table(FaUiFunctions::TABLESTYLE2, "width='80%'");
    FaUiFunctions::table_header([_('Setting'), _('Value')]);

    FaUiFunctions::label_row(_("Config file"), htmlspecialchars($configPath));
    FaUiFunctions::label_row(_("Current store type"), htmlspecialchars((string)$current['type']));
    FaUiFunctions::label_row(_("Current codec"), !empty($current['codec']) ? 'true' : 'false');

    FaUiFunctions::end_table(1);

    echo "<h3>Sync / Switch / Review</h3>";

    FaUiFunctions::start_table(FaUiFunctions::TABLESTYLE2, "width='80%'");
    FaUiFunctions::table_header([_('Field'), _('Value')]);

    echo "<tr><td>" . _("Target type") . "</td><td>";
    echo "<select name='target_type'>";
    $selectedType = (string)($_POST['target_type'] ?? (string)$current['type']);
    foreach ($availableTypes as $type => $label) {
        $sel = ($type === $selectedType) ? " selected='selected'" : "";
        echo "<option value='" . htmlspecialchars($type) . "'{$sel}>" . htmlspecialchars($label) . "</option>";
    }
    echo "</select>";
    echo "</td></tr>";
    check_row(_("Target codec (encode/decode)"), 'target_codec', isset($_POST['target_codec']) ? (bool)$_POST['target_codec'] : (bool)($current['codec'] ?? true));

    text_row(_("File path (ini/json/xml/csv/yaml)"), 'target_path', $_POST['target_path'] ?? ($current['path'] ?? ''), 60, 255);
    text_row(_("INI section (ini_file only)"), 'target_section', $_POST['target_section'] ?? ($current['section'] ?? 'prefs'), 30, 60);

    text_row(_("PDO DSN"), 'target_dsn', $_POST['target_dsn'] ?? '', 60, 255);
    text_row(_("PDO User"), 'target_user', $_POST['target_user'] ?? '', 30, 255);
    text_row(_("PDO Password"), 'target_password', $_POST['target_password'] ?? '', 30, 255);

    text_row(_("Table"), 'target_table', $_POST['target_table'] ?? ($current['table'] ?? ''), 40, 255);
    text_row(_("Name column"), 'target_name_col', $_POST['target_name_col'] ?? ($current['name_col'] ?? 'pref_name'), 30, 255);
    text_row(_("Value column"), 'target_value_col', $_POST['target_value_col'] ?? ($current['value_col'] ?? 'pref_value'), 30, 255);

    text_row(_("SuiteCRM category"), 'target_category', $_POST['target_category'] ?? 'ksf', 30, 60);
    text_row(_("WP option prefix"), 'target_prefix', $_POST['target_prefix'] ?? '', 30, 120);
    check_row(_("WP autoload"), 'target_autoload', isset($_POST['target_autoload']) ? (bool)$_POST['target_autoload'] : false);

    FaUiFunctions::end_table(1);

    echo "<p>Optional prefix filter (applies to all()/diff).</p>";
    echo "<input type='text' name='sync_prefix' size='40' value='" . htmlspecialchars((string)($_POST['sync_prefix'] ?? '')) . "' />";

    echo "<p>Known keys (optional, one per line). Used for Review and for sync when a backend cannot enumerate all().</p>";
    echo "<textarea name='sync_keys' rows='6' cols='80'>" . htmlspecialchars((string)($_POST['sync_keys'] ?? '')) . "</textarea>";

    echo "<br/><br/>";
    echo "<input type='hidden' name='prefs_action' value='' />";
    echo "<input type='submit' name='do_sync' value='Sync' onclick=\"this.form.prefs_action.value='sync'\" /> ";
    echo "<input type='submit' name='do_switch' value='Switch Store' onclick=\"this.form.prefs_action.value='switch'\" />";

    if ($manager->getSchema() !== null) {
        echo " ";
        echo "<input type='submit' name='do_defaults_current' value='Apply Defaults (Current)' onclick=\"this.form.prefs_action.value='apply_defaults_current'\" /> ";
        echo "<input type='submit' name='do_defaults_target' value='Apply Defaults (Target)' onclick=\"this.form.prefs_action.value='apply_defaults_target'\" />";
    }

    echo "<hr/>";
    echo "<h3>Review (Diff)</h3>";
    echo "<input type='submit' name='do_review' value='Review Current vs Target' onclick=\"this.form.prefs_action.value='review'\" />";

    if ($action === 'review') {
        $target = $buildTargetConfig($_POST);
        $keys = $parseKnownKeys($_POST);
        $prefix = isset($_POST['sync_prefix']) && trim((string)$_POST['sync_prefix']) !== '' ? (string)$_POST['sync_prefix'] : null;

        $from = $manager->getCurrentStore();
        $to = $manager->createPrefsStoreFromDaoConfig($target);

        $diffEngine = new PrefsDiffEngine();
        $diff = $diffEngine->diff($from, $to, $keys, $prefix, $manager->getSchema());

        echo "<p>Only-in-current: " . count($diff['onlyInFrom']) . "; only-in-target: " . count($diff['onlyInTo']) . "; different: " . count($diff['different']) . ".</p>";

        $knownCount = $keys === null ? 0 : count($keys);
        $schemaCount = $manager->getSchema() === null ? 0 : count($manager->getSchema()->keys());
        echo "<p>Known keys: " . (int)$knownCount . "; schema keys: " . (int)$schemaCount . ".</p>";

        if (!empty($diff['missingRequiredInFrom']) || !empty($diff['missingRequiredInTo'])) {
            echo "<p><strong>Missing required keys</strong></p>";
            if (!empty($diff['missingRequiredInFrom'])) {
                echo "<div>Current missing: " . htmlspecialchars(implode(', ', $diff['missingRequiredInFrom'])) . "</div>";
            }
            if (!empty($diff['missingRequiredInTo'])) {
                echo "<div>Target missing: " . htmlspecialchars(implode(', ', $diff['missingRequiredInTo'])) . "</div>";
            }
        }

        FaUiFunctions::start_table(FaUiFunctions::TABLESTYLE2, "width='95%'");
        FaUiFunctions::table_header([_('Select'), _('Key'), _('Current'), _('Target'), _('Status')]);

        $renderRow = static function (string $key, $vFrom, $vTo, string $status): void {
            echo "<tr>";
            echo "<td><input type='checkbox' name='selected_keys[]' value='" . htmlspecialchars($key) . "' /></td>";
            echo "<td>" . htmlspecialchars($key) . "</td>";
            echo "<td><pre style='margin:0;white-space:pre-wrap'>" . htmlspecialchars(var_export($vFrom, true)) . "</pre></td>";
            echo "<td><pre style='margin:0;white-space:pre-wrap'>" . htmlspecialchars(var_export($vTo, true)) . "</pre></td>";
            echo "<td>" . htmlspecialchars($status) . "</td>";
            echo "</tr>";
        };

        foreach ($diff['onlyInFrom'] as $k => $v) {
            $renderRow((string)$k, $v, null, 'only in current');
        }
        foreach ($diff['onlyInTo'] as $k => $v) {
            $renderRow((string)$k, null, $v, 'only in target');
        }
        foreach ($diff['different'] as $k => $pair) {
            $renderRow((string)$k, $pair['from'], $pair['to'], 'different');
        }

        FaUiFunctions::end_table(1);

        echo "<p>With selected keys:</p>";
        echo "<button type='submit' name='review_op' value='copy_from_to' onclick=\"this.form.prefs_action.value='review_action'\">Copy Current → Target</button> ";
        echo "<button type='submit' name='review_op' value='copy_to_from' onclick=\"this.form.prefs_action.value='review_action'\">Copy Target → Current</button> ";
        echo "<button type='submit' name='review_op' value='delete_to' onclick=\"this.form.prefs_action.value='review_action'\">Delete From Target</button> ";
        echo "<button type='submit' name='review_op' value='delete_from' onclick=\"this.form.prefs_action.value='review_action'\">Delete From Current</button> ";
        echo "<button type='submit' name='review_op' value='prune_to' onclick=\"this.form.prefs_action.value='review_action'\">Prune Target Extras</button>";
    }

    end_form();

} catch (\Throwable $e) {
    display_error($e->getMessage());
}

end_page();
