<?php

namespace Granada\Orm;

/**
 * Applies patterns on write based on rules.
 *
 * @internal
 */
class WriteRules
{
    /**
     * @param string[] $ignored
     */
    public function __construct(
        public readonly ?string $soft_delete_column = null,
        public readonly ?string $created = null,
        public readonly ?string $updated = null,
        public readonly array $ignored = [],
    ) {}

    /**
     * Sets timestamps on the row based on rules.
     *
     * @param array<string, mixed> $values
     * @param string[]|null $changed
     * @return array<string, string>
     */
    public function stamp(array $values, bool $is_new, ?array $changed = null): array
    {
        if ($this->updated === null) {
            return [];
        }

        $now = gmdate('Y-m-d H:i:s');

        if ($is_new) {
            $stamp = [];
            if ($this->created !== null && !isset($values[$this->created])) {
                $stamp[$this->created] = $now;
            }
            if (!isset($values[$this->updated])) {
                $stamp[$this->updated] = $now;
            }

            return $stamp;
        }

        if (isset($values[$this->updated])) {
            return [];
        }

        foreach ($changed ?? array_keys($values) as $field) {
            if (!in_array($field, $this->ignored, true)) {
                return [$this->updated => $now];
            }
        }

        return [];
    }
}
