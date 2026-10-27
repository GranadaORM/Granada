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
        public readonly ?string $auto_order_column = null,
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
            if (in_array($field, $this->ignored, true)) {
                continue;
            }
            if ($this->is_auto_order_zero($field, $values)) {
                continue;
            }

            // Something not ignored has changed, so set the updated stamp
            return [$this->updated => $now];
        }

        return [];
    }

    /**
     * Replaces a written 0 in the order column with the next number.
     *
     * @param array<string, mixed> $values
     * @param callable(): int $next
     * @return array<string, int>
     */
    public function auto_order(array $values, callable $next): array
    {
        if (!$this->auto_order_column) {
            return [];
        }

        if (!array_key_exists($this->auto_order_column, $values)) {
            return [];
        }

        if (!$this->is_zero($values[$this->auto_order_column])) {
            return [];
        }

        return [$this->auto_order_column => $next()];
    }

    /**
     * True when the field is the auto-order column and the value
     * written is 0.
     *
     * @param array<string, mixed> $values
     */
    private function is_auto_order_zero(string $field, array $values): bool
    {
        return $field === $this->auto_order_column && $this->is_zero($values[$field] ?? null);
    }

    private function is_zero(mixed $value): bool
    {
        return is_numeric($value) && $value == 0;
    }
}
