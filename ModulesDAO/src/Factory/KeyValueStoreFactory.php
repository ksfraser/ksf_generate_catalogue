<?php

namespace Ksfraser\ModulesDAO\Factory;

use Ksfraser\ModulesDAO\Contracts\KeyValueStoreInterface;
use Ksfraser\ModulesDAO\Stores\KeyValue\CsvFileStore;
use Ksfraser\ModulesDAO\Stores\KeyValue\FrontAccountingDbTableStore;
use Ksfraser\ModulesDAO\Stores\KeyValue\FrontAccountingSysPrefsStore;
use Ksfraser\ModulesDAO\Stores\KeyValue\IniFileStore;
use Ksfraser\ModulesDAO\Stores\KeyValue\JsonFileStore;
use Ksfraser\ModulesDAO\Stores\KeyValue\PdoTableStore;
use Ksfraser\ModulesDAO\Stores\KeyValue\SuiteCrmAdministrationStore;
use Ksfraser\ModulesDAO\Stores\KeyValue\WordPressOptionsStore;
use Ksfraser\ModulesDAO\Stores\KeyValue\XmlFileStore;
use Ksfraser\ModulesDAO\Stores\KeyValue\YamlFileStore;
use PDO;
use RuntimeException;

/**
 * Build ModulesDAO stores from configuration arrays.
 */
class KeyValueStoreFactory
{
    /**
     * @param array<string,mixed> $config
     */
    public function create(array $config): KeyValueStoreInterface
    {
        $type = isset($config['type']) ? (string)$config['type'] : '';

        switch ($type) {
            case 'ini_file':
                return new IniFileStore(
                    (string)($config['path'] ?? ''),
                    (string)($config['section'] ?? 'prefs')
                );

            case 'json_file':
                return new JsonFileStore((string)($config['path'] ?? ''));

            case 'csv_file':
                return new CsvFileStore((string)($config['path'] ?? ''));

            case 'xml_file':
                return new XmlFileStore((string)($config['path'] ?? ''));

            case 'sgml_file':
                // Pragmatic alias: treat SGML as XML-like for simple key/value documents.
                return new XmlFileStore((string)($config['path'] ?? ''));

            case 'yaml_file':
                return new YamlFileStore((string)($config['path'] ?? ''));

            case 'pdo_table':
                return $this->createPdoTableStore($config);

            case 'wp_options':
                return new WordPressOptionsStore(
                    isset($config['prefix']) ? (string)$config['prefix'] : '',
                    isset($config['autoload']) ? (bool)$config['autoload'] : false
                );

            case 'suite_admin':
                return new SuiteCrmAdministrationStore(
                    isset($config['category']) ? (string)$config['category'] : 'ksf'
                );

            case 'fa_sys_prefs':
                return new FrontAccountingSysPrefsStore();

            case 'fa_table':
                return new FrontAccountingDbTableStore(
                    (string)($config['table'] ?? ''),
                    (string)($config['name_col'] ?? 'pref_name'),
                    (string)($config['value_col'] ?? 'pref_value')
                );

            default:
                throw new RuntimeException('Unknown key/value store type: ' . $type);
        }
    }

    /**
     * @param array<string,mixed> $config
     */
    private function createPdoTableStore(array $config): KeyValueStoreInterface
    {
        $dsn = (string)($config['dsn'] ?? '');
        $user = (string)($config['user'] ?? '');
        $password = (string)($config['password'] ?? '');
        $table = (string)($config['table'] ?? '');

        if ($dsn === '' || $table === '') {
            throw new RuntimeException('pdo_table requires dsn and table');
        }

        $pdo = new PDO($dsn, $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return new PdoTableStore(
            $pdo,
            $table,
            (string)($config['name_col'] ?? 'pref_name'),
            (string)($config['value_col'] ?? 'pref_value')
        );
    }
}
