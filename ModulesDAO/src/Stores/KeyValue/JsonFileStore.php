<?php

namespace Ksfraser\ModulesDAO\Stores\KeyValue;

/**
 * JSON file key/value store.
 *
 * Stores a JSON object of string->string.
 */
class JsonFileStore extends AbstractFileKeyValueStore
{
    protected function decode(string $contents): array
    {
        $decoded = json_decode($contents, true);
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
        ksort($data);
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
