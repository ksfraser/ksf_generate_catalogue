<?php

namespace Ksfraser\ModulesDAO\Stores\Record;

use Ksfraser\ModulesDAO\Contracts\RecordStoreInterface;
use RuntimeException;

/**
 * Common base for file-backed record stores.
 *
 * Keeps file-path handling and safe writes in one place.
 */
abstract class AbstractFileRecordStore implements RecordStoreInterface
{
    /** @var string */
    protected $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function isAvailable(): bool
    {
        return is_string($this->filePath) && $this->filePath !== '';
    }

    protected function readFileContents(): string
    {
        if (!is_file($this->filePath)) {
            return '';
        }

        $contents = @file_get_contents($this->filePath);
        return $contents === false ? '' : $contents;
    }

    protected function writeFileContents(string $contents): void
    {
        $dir = dirname($this->filePath);
        if ($dir && !is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $tmp = $this->filePath . '.tmp.' . bin2hex(random_bytes(6));
        $bytes = @file_put_contents($tmp, $contents, LOCK_EX);
        if ($bytes === false) {
            throw new RuntimeException('Failed to write temp file: ' . $tmp);
        }

        // Best-effort atomic replace.
        if (is_file($this->filePath)) {
            @unlink($this->filePath);
        }

        if (!@rename($tmp, $this->filePath)) {
            @unlink($tmp);
            throw new RuntimeException('Failed to replace file: ' . $this->filePath);
        }
    }
}
