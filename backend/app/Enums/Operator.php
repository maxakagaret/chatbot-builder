<?php

namespace App\Enums;

enum Operator: string
{
    case Equals = 'eq';
    case NotEquals = 'neq';
    case Greater = 'gt';
    case GreaterOrEqual = 'gte';
    case Less = 'lt';
    case LessOrEqual = 'lte';
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case IsSet = 'is_set';
    case IsEmpty = 'is_empty';

    public function needsValue(): bool
    {
        return ! in_array($this, [self::IsSet, self::IsEmpty], true);
    }

    /**
     * Compares an actual value (from contact data or session variables) with the
     * value typed in the editor. Numeric comparisons only happen when both sides
     * are numeric — "abc" > 5 is false, not a PHP juggling surprise.
     */
    public function compare(mixed $actual, ?string $expected): bool
    {
        $isEmpty = $actual === null || $actual === '' || $actual === [];

        return match ($this) {
            self::IsSet => ! $isEmpty,
            self::IsEmpty => $isEmpty,
            self::Contains => is_array($actual)
                ? in_array(mb_strtolower((string) $expected), array_map(fn ($v) => mb_strtolower((string) $v), $actual), true)
                : str_contains(mb_strtolower((string) $actual), mb_strtolower((string) $expected)),
            self::NotContains => ! self::Contains->compare($actual, $expected),
            self::Equals => $this->bothNumeric($actual, $expected)
                ? (float) $actual === (float) $expected
                : mb_strtolower((string) (is_array($actual) ? '' : $actual)) === mb_strtolower((string) $expected),
            self::NotEquals => ! self::Equals->compare($actual, $expected),
            self::Greater => $this->bothNumeric($actual, $expected) && (float) $actual > (float) $expected,
            self::GreaterOrEqual => $this->bothNumeric($actual, $expected) && (float) $actual >= (float) $expected,
            self::Less => $this->bothNumeric($actual, $expected) && (float) $actual < (float) $expected,
            self::LessOrEqual => $this->bothNumeric($actual, $expected) && (float) $actual <= (float) $expected,
        };
    }

    private function bothNumeric(mixed $a, mixed $b): bool
    {
        return is_numeric($a) && is_numeric($b);
    }
}
