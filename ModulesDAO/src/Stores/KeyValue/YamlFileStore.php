<?php

namespace Ksfraser\ModulesDAO\Stores\KeyValue;

/**
 * YAML key/value store.
 *
 * Available only when ext-yaml is installed.
 */
class YamlFileStore extends AbstractFileKeyValueStore
{
    public function isAvailable(): bool
    {
        return parent::isAvailable() && function_exists('yaml_parse') && function_exists('yaml_emit');
    }

    protected function decode(string $contents): array
    {
        if (!function_exists('yaml_parse')) {
            return [];
        }

        $decoded = @yaml_parse($contents);
        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $out[(string)$k] = $v === null ? '' : (string)$v;
            }
        }

        return $out;
    }

    protected function encode(array $data): string
    {
        if (!function_exists('yaml_emit')) {
            return "";
        }

        ksort($data);
        $raw = @yaml_emit($data);
        return $raw === false ? "" : $raw;
    }
}
