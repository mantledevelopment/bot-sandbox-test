<?php

declare(strict_types=1);

namespace App\Module\Task\Domain;

final class DueDate
{
    /**
     * Normalise and validate a raw due-date payload into a `YYYY-MM-DD` string, or `null`.
     *
     * @return string|null
     *
     * @throws \InvalidArgumentException with a per-case reason message
     */
    public static function normalize(mixed $raw): ?string
    {
        if (null === $raw) {
            return null;
        }

        if (!is_string($raw)) {
            throw new \InvalidArgumentException('dueDate must be a string in YYYY-MM-DD format');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) !== 1) {
            throw new \InvalidArgumentException(sprintf('invalid dueDate "%s": expected a YYYY-MM-DD date', $raw));
        }

        [$year, $month, $day] = array_map('intval', explode('-', $raw));
        if (!checkdate($month, $day, $year)) {
            throw new \InvalidArgumentException(sprintf('invalid dueDate "%s": not a real calendar date', $raw));
        }

        return $raw;
    }
}
