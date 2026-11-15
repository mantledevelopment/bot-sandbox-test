<?php

declare(strict_types=1);

namespace App\Module\Task\Domain;

final class Tags
{
    private const int MAX_TAGS = 5;

    /**
     * Normalise and validate a raw tags payload into a list of tag strings.
     *
     * @return list<string>
     *
     * @throws \InvalidArgumentException with a per-case reason message
     */
    public static function normalize(mixed $raw): array
    {
        if (!is_array($raw) || array_is_list($raw) === false) {
            throw new \InvalidArgumentException('tags must be an array');
        }

        if (count($raw) > self::MAX_TAGS) {
            throw new \InvalidArgumentException(sprintf('a maximum of %d tags are allowed', self::MAX_TAGS));
        }

        $tags = [];
        foreach ($raw as $tag) {
            if (!is_string($tag)) {
                throw new \InvalidArgumentException('each tag must be a string');
            }

            $tag = trim($tag);
            if ('' === $tag) {
                throw new \InvalidArgumentException('tags cannot be empty');
            }

            $tag = strtolower($tag);
            if (preg_match('/^[a-z0-9-]{1,30}$/', $tag) !== 1) {
                throw new \InvalidArgumentException(sprintf('invalid tag "%s": tags may only contain letters, digits and hyphens', $tag));
            }

            $tags[] = $tag;
        }

        return $tags;
    }
}
