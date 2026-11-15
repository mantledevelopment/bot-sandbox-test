<?php

declare(strict_types=1);

namespace App\Module\Task\Repository;

use App\Module\Task\Domain\Page;
use App\Module\Task\Domain\Task;
use Symfony\Component\Clock\ClockInterface;

/**
 * Fixed in-memory task list, so the sandbox needs no database.
 */
final class TaskRepository
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    private const TITLES = [
        'Plan the sprint',
        'Write the tests',
        'Build the controller',
        'Wire the repository',
        'Add the view layer',
        'Run the suite',
        'Refactor the edge cases',
        'Document the contract',
        'Ship to staging',
        'Verify in production',
        'Write the retrospective',
        'Close the ticket',
    ];

    /** @var array<string, list<string>> */
    private const PARENT_CHILDREN = [
        'task-01' => ['task-02', 'task-03'],
        'task-04' => ['task-05', 'task-06'],
    ];

    /** @var list<Task>|null */
    private ?array $tasks = null;

    private int $nextIdCounter = 13;

    /** @return list<Task> */
    public function all(): array
    {
        if (null === $this->tasks) {
            $tasksById = [];
            foreach (self::TITLES as $i => $title) {
                $id = sprintf('task-%02d', $i + 1);
                $tasksById[$id] = new Task(
                    id: $id,
                    title: $title,
                    isDone: $i < 3,
                    createdAt: new \DateTimeImmutable(sprintf('2026-02-%02dT10:00:00+00:00', $i + 1)),
                );
            }

            foreach (self::PARENT_CHILDREN as $parentId => $childrenIds) {
                $parent = $tasksById[$parentId];
                $children = [];
                foreach ($childrenIds as $childId) {
                    $children[] = $tasksById[$childId];
                }
                $parent->attachChildren($children);
            }

            $this->tasks = array_values($tasksById);
        }

        return $this->tasks;
    }

    public function paginate(int $page, int $perPage, bool $done = false, string $sort = 'id', ?string $tag = null, ?bool $overdue = null): Page
    {
        $tasks = $this->all();

        if (null !== $tag) {
            $tasks = array_values(array_filter(
                $tasks,
                static fn (Task $task): bool => in_array($tag, $task->tags, true),
            ));
        }

        $tasks = array_values(array_filter(
            $tasks,
            static fn (Task $task): bool => $task->isDone === $done,
        ));

        if (true === $overdue) {
            $today = $this->clock->now()->format('Y-m-d');
            $tasks = array_values(array_filter(
                $tasks,
                static fn (Task $task): bool => !$task->isDone && null !== $task->dueDate && $task->dueDate < $today,
            ));
        }

        $tasks = $this->sort($tasks, $sort);
        $offset = ($page - 1) * $perPage;

        return new Page(
            items: array_slice($tasks, $offset, $perPage),
            page: $page,
            perPage: $perPage,
            total: count($tasks),
        );
    }

    /** @return list<array{tag: string, count: int}> */
    public function tagCounts(): array
    {
        $all = [];
        foreach ($this->all() as $task) {
            foreach ($task->tags as $tag) {
                $all[] = $tag;
            }
        }

        $counts = array_count_values($all);
        $out = [];
        foreach ($counts as $tag => $count) {
            $out[] = ['tag' => $tag, 'count' => $count];
        }

        usort($out, static function (array $a, array $b): int {
            return $a['count'] === $b['count']
                ? strcmp($a['tag'], $b['tag'])
                : $b['count'] <=> $a['count'];
        });

        return $out;
    }

    /** @return array{total: int, done: int, overdue: int} */
    public function count(): array
    {
        $tasks = $this->all();
        $today = $this->clock->now()->format('Y-m-d');

        return [
            'total' => count($tasks),
            'done' => count(array_filter($tasks, static fn (Task $task): bool => $task->isDone)),
            'overdue' => count(array_filter($tasks, static fn (Task $task): bool => !$task->isDone && null !== $task->dueDate && $task->dueDate < $today)),
        ];
    }

    public function find(string $id): ?Task
    {
        foreach ($this->all() as $task) {
            if ($task->id === $id) {
                return $task;
            }
        }

        return null;
    }

    public function nextId(): string
    {
        return sprintf('task-%02d', $this->nextIdCounter++);
    }

    public function add(Task $task): void
    {
        $this->all();
        $this->tasks[] = $task;
    }

    /** @param list<Task> $tasks
     *  @return list<Task>
     */
    private function sort(array $tasks, string $sort): array
    {
        if ('id' === $sort) {
            return $tasks;
        }

        usort($tasks, static function (Task $a, Task $b) use ($sort): int {
            return '-' === $sort[0]
                ? strcmp($b->title, $a->title)
                : strcmp($a->title, $b->title);
        });

        return $tasks;
    }
}
