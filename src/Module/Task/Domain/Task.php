<?php

declare(strict_types=1);

namespace App\Module\Task\Domain;

final class Task
{
    public ?string $parentId = null;

    /** @var list<string> */
    public array $tags = [];

    /** @var list<Task> */
    public array $children = [];

    public readonly string $id;
    public readonly \DateTimeImmutable $createdAt;
    public string $title;
    public bool $isDone;

    public function __construct(
        string $id,
        string $title,
        bool $isDone,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->isDone = $isDone;
        $this->createdAt = $createdAt;
    }

    /**
     * @param list<Task> $children
     */
    public function attachChildren(array $children): void
    {
        $this->children = $children;
        foreach ($children as $child) {
            $child->parentId = $this->id;
        }
    }
}
