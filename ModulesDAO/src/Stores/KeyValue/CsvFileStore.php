<?php

namespace Ksfraser\ModulesDAO\Stores\KeyValue;

/**
 * CSV key/value store.
 *
 * Header: key,value
 */
class CsvFileStore extends AbstractFileKeyValueStore
{
    protected function decode(string $contents): array
    {
        $fh = fopen('php://temp', 'rb+');
        if ($fh === false) {
            return [];
        }
        fwrite($fh, $contents);
        rewind($fh);

        $header = fgetcsv($fh);
        if (!is_array($header)) {
            fclose($fh);
            return [];
        }

        $keyIndex = array_search('key', $header, true);
        $valueIndex = array_search('value', $header, true);
        if ($keyIndex === false || $valueIndex === false) {
            // Assume two-column CSV without headers.
            $keyIndex = 0;
            $valueIndex = 1;
            rewind($fh);
        }

        $out = [];
        while (($row = fgetcsv($fh)) !== false) {
            $k = isset($row[$keyIndex]) ? (string)$row[$keyIndex] : '';
            if ($k === '') {
                continue;
            }
            $out[$k] = isset($row[$valueIndex]) ? (string)$row[$valueIndex] : '';
        }

        fclose($fh);
        return $out;
    }

    protected function encode(array $data): string
    {
        ksort($data);

        $fh = fopen('php://temp', 'rb+');
        if ($fh === false) {
            return "";
        }

        fputcsv($fh, ['key', 'value']);
        foreach ($data as $k => $v) {
            fputcsv($fh, [(string)$k, (string)$v]);
        }

        rewind($fh);
        $out = stream_get_contents($fh);
        fclose($fh);
        return $out === false ? "" : $out;
    }
}
