<?php

namespace Ksfraser\ModulesDAO\Stores\KeyValue;

/**
 * INI file key/value store.
 *
 * Stores values as strings under a single INI section.
 */
class IniFileStore extends AbstractFileKeyValueStore
{
    /** @var string */
    private $section;

    public function __construct(string $filePath, string $section = 'prefs')
    {
        parent::__construct($filePath);
        $this->section = $section;
    }

    protected function decode(string $contents): array
    {
        // parse_ini_string does not support comments perfectly, but is adequate.
        $parsed = parse_ini_string($contents, true, INI_SCANNER_RAW);
        if (!is_array($parsed)) {
            return [];
        }

        $section = $parsed[$this->section] ?? [];
        if (!is_array($section)) {
            return [];
        }

        $out = [];
        foreach ($section as $k => $v) {
            if (is_array($v)) {
                // Flatten arrays; callers should use codec at a higher layer.
                continue;
            }
            $out[(string)$k] = (string)$v;
        }
        return $out;
    }

    protected function encode(array $data): string
    {
        ksort($data);

        $lines = [];
        $lines[] = '[' . $this->section . ']';
        foreach ($data as $k => $v) {
            $k = (string)$k;
            $v = (string)$v;
            $v = str_replace('"', '\\"', $v);
            $lines[] = $k . ' = "' . $v . '"';
        }
        $lines[] = '';

        return implode("\n", $lines);
    }
}
