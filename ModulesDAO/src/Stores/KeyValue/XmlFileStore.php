<?php

namespace Ksfraser\ModulesDAO\Stores\KeyValue;

/**
 * XML key/value store.
 *
 * Schema:
 * <prefs>
 *   <pref key="foo">bar</pref>
 * </prefs>
 */
class XmlFileStore extends AbstractFileKeyValueStore
{
    protected function decode(string $contents): array
    {
        if (trim($contents) === '') {
            return [];
        }

        $xml = @simplexml_load_string($contents);
        if ($xml === false) {
            return [];
        }

        $out = [];
        foreach ($xml->pref as $pref) {
            $attrs = $pref->attributes();
            $k = isset($attrs['key']) ? (string)$attrs['key'] : '';
            if ($k === '') {
                continue;
            }
            $out[$k] = (string)$pref;
        }

        return $out;
    }

    protected function encode(array $data): string
    {
        ksort($data);

        $xml = new \SimpleXMLElement('<prefs/>');
        foreach ($data as $k => $v) {
            $child = $xml->addChild('pref', htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            $child->addAttribute('key', (string)$k);
        }

        $raw = $xml->asXML();
        return ($raw === false ? '<prefs/>' : $raw) . "\n";
    }
}
