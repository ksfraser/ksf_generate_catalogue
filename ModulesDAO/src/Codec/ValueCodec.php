<?php

namespace Ksfraser\ModulesDAO\Codec;

/**
 * Encodes values as strings for storage backends.
 */
class ValueCodec
{
    private const JSON_PREFIX = '__json__:';

    /**
     * @param mixed $value
     */
    public static function encode($value): string
    {
        if (is_array($value) || is_object($value)) {
            return self::JSON_PREFIX . json_encode($value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null) {
            return '';
        }

        return (string)$value;
    }

    /**
     * @param string|null $raw
     * @param mixed $default
     * @return mixed
     */
    public static function decode($raw, $default = null)
    {
        if ($raw === null) {
            return $default;
        }

        if (strncmp($raw, self::JSON_PREFIX, strlen(self::JSON_PREFIX)) === 0) {
            $json = substr($raw, strlen(self::JSON_PREFIX));
            $decoded = json_decode($json, true);
            return $decoded === null && $json !== 'null' ? $default : $decoded;
        }

        return $raw;
    }
}
