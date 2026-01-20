<?php

namespace Ksfraser\Frontaccounting\GenCat\Tests\Support;

class InMemoryWriteFile
{
    /** @var string[] */
    private array $lines = [];

    public function write_line($line)
    {
        // Keep exact header formatting but normalize line endings.
        $this->lines[] = rtrim((string) $line, "\r\n");
    }

    public function write_array_to_csv($fields)
    {
        $fields = is_array($fields) ? $fields : [];

        $escaped = array_map(
            static function ($value): string {
                $value = (string) $value;
                $value = str_replace('"', '""', $value);
                return '"' . $value . '"';
            },
            $fields
        );

        $this->lines[] = implode(',', $escaped);
    }

    public function close()
    {
        // no-op for in-memory writer
    }

    /**
     * @return string[]
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    public function getContent(string $newline = "\n"): string
    {
        if (empty($this->lines)) {
            return '';
        }
        return implode($newline, $this->lines) . $newline;
    }
}
