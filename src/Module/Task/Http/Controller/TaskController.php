<?php

declare(strict_types=1);

namespace App\Module\Task\Http\Controller;

use App\Module\Task\Domain\Tags;
use App\Module\Task\Domain\Task;
use App\Module\Task\Http\View\TaskView;
use App\Module\Task\Repository\TaskRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class TaskController extends AbstractController
{
    private const int DEFAULT_PER_PAGE = 20;
    private const int MAX_PER_PAGE = 50;
    private const array ALLOWED_SORTS = ['id', 'title', '-title'];

    public function __construct(private readonly TaskRepository $tasks)
    {
    }

    #[Route('/api/tasks', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = (int) $request->query->get('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $perPage = (int) $request->query->get('perPage', self::DEFAULT_PER_PAGE);
        if ($perPage < 1) {
            $perPage = self::DEFAULT_PER_PAGE;
        }
        if ($perPage > self::MAX_PER_PAGE) {
            $perPage = self::MAX_PER_PAGE;
        }

        $sort = (string) $request->query->get('sort', 'id');
        if (!in_array($sort, self::ALLOWED_SORTS, true)) {
            return new JsonResponse([
                'error' => sprintf('Unknown sort "%s"; allowed values are: id, title, -title', $sort),
            ], 400);
        }

        $done = false;
        $doneParam = $request->query->get('done');
        if (null !== $doneParam && '' !== $doneParam) {
            $lower = strtolower((string) $doneParam);
            if ('true' !== $lower && 'false' !== $lower) {
                return new JsonResponse(['error' => 'done must be true or false'], 400);
            }
            $done = 'true' === $lower;
        }

        $tag = null;
        $tagParam = $request->query->get('tag');
        if (null !== $tagParam) {
            $tag = strtolower(trim((string) $tagParam));
            if ('' === $tag) {
                $tag = null;
            }
        }

        return new JsonResponse(TaskView::page($this->tasks->paginate($page, $perPage, $done, $sort, $tag)));
    }

    #[Route('/api/tags', methods: ['GET'])]
    public function tags(): JsonResponse
    {
        return new JsonResponse($this->tasks->tagCounts());
    }

    #[Route('/api/tasks/count', methods: ['GET'])]
    public function count(): JsonResponse
    {
        return new JsonResponse($this->tasks->count());
    }

    #[Route('/api/tasks/{id}', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $task = $this->tasks->find($id);

        if (null === $task) {
            return new JsonResponse(['error' => 'Task not found'], 404);
        }

        return new JsonResponse(TaskView::one($task));
    }

    #[Route('/api/tasks', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Request body must be a JSON object'], 400);
        }

        try {
            $title = $this->requireTitle($payload);
            $isDone = $this->parseIsDone($payload);
            $tags = $payload['tags'] ?? [];
            $tags = Tags::normalize($tags);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        $task = new Task(
            id: $this->tasks->nextId(),
            title: $title,
            isDone: $isDone,
            createdAt: new \DateTimeImmutable(),
        );
        $task->tags = $tags;
        $this->tasks->add($task);

        return new JsonResponse(TaskView::one($task), 201);
    }

    #[Route('/api/tasks/{id}', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $task = $this->tasks->find($id);

        if (null === $task) {
            return new JsonResponse(['error' => 'Task not found'], 404);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Request body must be a JSON object'], 400);
        }

        try {
            if (array_key_exists('title', $payload)) {
                $task->title = $this->requireTitle($payload);
            }
            if (array_key_exists('isDone', $payload)) {
                $task->isDone = $this->parseIsDone($payload);
            }
            if (array_key_exists('tags', $payload)) {
                $task->tags = Tags::normalize($payload['tags']);
            }
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse(TaskView::one($task));
    }

    private function requireTitle(array $payload): string
    {
        $title = $payload['title'] ?? null;
        if (!is_string($title)) {
            throw new \InvalidArgumentException('title must be a string');
        }
        $title = trim($title);
        if ('' === $title) {
            throw new \InvalidArgumentException('title cannot be empty');
        }

        return $title;
    }

    private function parseIsDone(array $payload): bool
    {
        $isDone = $payload['isDone'] ?? false;
        if (!is_bool($isDone)) {
            throw new \InvalidArgumentException('isDone must be a boolean');
        }

        return $isDone;
    }
}
