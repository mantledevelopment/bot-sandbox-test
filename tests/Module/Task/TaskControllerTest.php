<?php

declare(strict_types=1);

namespace App\Tests\Module\Task;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TaskControllerTest extends WebTestCase
{
    public function testListReturnsFirstPageByDefault(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tasks');

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertCount(9, $body['items']);
        self::assertSame(1, $body['page']);
        self::assertSame(20, $body['perPage']);
        self::assertSame(9, $body['total']);
        self::assertSame(
            [
                'id' => 'task-04',
                'title' => 'Wire the repository',
                'isDone' => false,
                'createdAt' => '2026-02-04T10:00:00+00:00',
                'parentId' => null,
                'dueDate' => null,
                'tags' => [],
                'children' => [
                    [
                        'id' => 'task-05',
                        'title' => 'Add the view layer',
                        'isDone' => false,
                        'createdAt' => '2026-02-05T10:00:00+00:00',
                        'parentId' => 'task-04',
                        'dueDate' => null,
                        'tags' => [],
                        'children' => [],
                    ],
                    [
                        'id' => 'task-06',
                        'title' => 'Run the suite',
                        'isDone' => false,
                        'createdAt' => '2026-02-06T10:00:00+00:00',
                        'parentId' => 'task-04',
                        'dueDate' => null,
                        'tags' => [],
                        'children' => [],
                    ],
                ],
            ],
            $body['items'][0],
        );
    }

    public function testListWithPageAndPerPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tasks?page=2&perPage=5');

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertCount(4, $body['items']);
        self::assertSame(2, $body['page']);
        self::assertSame(5, $body['perPage']);
        self::assertSame(9, $body['total']);
        self::assertSame('task-09', $body['items'][0]['id']);
    }

    public function testListCapsPerPageAndTreatsLowPageAsOne(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tasks?page=0&perPage=100');

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame(1, $body['page']);
        self::assertSame(50, $body['perPage']);
        self::assertSame(9, $body['total']);
    }

    public function testListWithDoneTrueReturnsOnlyFinishedTasks(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tasks?done=true');

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertCount(3, $body['items']);
        self::assertSame(3, $body['total']);
        foreach ($body['items'] as $item) {
            self::assertTrue($item['isDone']);
        }
    }

    public function testListWithDoneFalseReturnsOnlyOpenTasks(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tasks?done=false');

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertCount(9, $body['items']);
        self::assertSame(9, $body['total']);
        foreach ($body['items'] as $item) {
            self::assertFalse($item['isDone']);
        }
    }

    public function testListWithInvalidDoneReturns400(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tasks?done=maybe');

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'done must be true or false'], json_decode((string) $client->getResponse()->getContent(), true));
    }

    public function testListWithDoneRespectsPagination(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tasks?done=false&page=2&perPage=5');

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertCount(4, $body['items']);
        self::assertSame(2, $body['page']);
        self::assertSame(5, $body['perPage']);
        self::assertSame(9, $body['total']);
        foreach ($body['items'] as $item) {
            self::assertFalse($item['isDone']);
        }
    }

    public function testCountReturnsTotals(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tasks/count');

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['total' => 12, 'done' => 3],
            json_decode((string) $client->getResponse()->getContent(), true),
        );
    }

    public function testListSortByTitle(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tasks?sort=title');

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertCount(9, $body['items']);
        self::assertSame('Add the view layer', $body['items'][0]['title']);
        $titles = array_column($body['items'], 'title');
        $sorted = $titles;
        sort($sorted, SORT_STRING);
        self::assertSame($sorted, $titles);
    }

    public function testListSortByTitleDesc(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tasks?sort=-title');

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertCount(9, $body['items']);
        self::assertSame('Write the retrospective', $body['items'][0]['title']);
        $titles = array_column($body['items'], 'title');
        $sorted = $titles;
        rsort($sorted, SORT_STRING);
        self::assertSame($sorted, $titles);
    }

    public function testListDefaultSortIsId(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tasks');

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame('task-04', $body['items'][0]['id']);
    }

    public function testListUnknownSortIs400(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tasks?sort=colour');

        self::assertResponseStatusCodeSame(400);
        self::assertStringContainsString('id, title, -title', (string) $client->getResponse()->getContent());
    }

    public function testGetReturnsOneTask(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tasks/task-03');

        self::assertResponseIsSuccessful();
        $task = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame(
            [
                'id' => 'task-03',
                'title' => 'Build the controller',
                'isDone' => true,
                'createdAt' => '2026-02-03T10:00:00+00:00',
                'parentId' => 'task-01',
                'dueDate' => null,
                'tags' => [],
                'children' => [],
            ],
            $task,
        );
    }

    public function testGetParentTaskIncludesChildren(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tasks/task-01');

        self::assertResponseIsSuccessful();
        $task = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame('task-01', $task['id']);
        self::assertNull($task['parentId']);
        self::assertCount(2, $task['children']);
        self::assertSame('task-02', $task['children'][0]['id']);
        self::assertSame('task-03', $task['children'][1]['id']);
        self::assertSame('task-01', $task['children'][0]['parentId']);
        self::assertSame('task-01', $task['children'][1]['parentId']);
        self::assertSame([], $task['children'][0]['children']);
    }

    public function testGetChildTaskIncludesParentId(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tasks/task-02');

        self::assertResponseIsSuccessful();
        $task = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame(
            [
                'id' => 'task-02',
                'title' => 'Write the tests',
                'isDone' => true,
                'createdAt' => '2026-02-02T10:00:00+00:00',
                'parentId' => 'task-01',
                'dueDate' => null,
                'tags' => [],
                'children' => [],
            ],
            $task,
        );
    }

    public function testGetUnknownTaskIs404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tasks/nope');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertSame(['error' => 'Task not found'], json_decode((string) $client->getResponse()->getContent(), true));
    }

    public function testCreateTaskNormalizesTags(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->request(
            'POST',
            '/api/tasks',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => 'Tagged task', 'tags' => ['Urgent', ' billing ']], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);
        $task = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('Tagged task', $task['title']);
        self::assertSame(['urgent', 'billing'], $task['tags']);

        $client->request('GET', '/api/tasks/' . $task['id']);
        self::assertResponseIsSuccessful();
        $fetched = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(['urgent', 'billing'], $fetched['tags']);
    }

    public function testCreateTaskTooManyTagsIs422(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/tasks',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => 'x', 'tags' => ['a', 'b', 'c', 'd', 'e', 'f']], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsString($body['error']);
        self::assertNotSame('', $body['error']);
    }

    public function testCreateTaskEmptyTagIs422(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/tasks',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => 'x', 'tags' => ['']], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsString($body['error']);
        self::assertNotSame('', $body['error']);
    }

    public function testCreateTaskInternalSpaceTagIs422(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/tasks',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => 'x', 'tags' => ['has space']], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsString($body['error']);
        self::assertNotSame('', $body['error']);
    }

    public function testPatchTaskUpdatesTagsOnly(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tasks/task-04');
        $before = json_decode((string) $client->getResponse()->getContent(), true);

        $client->request(
            'PATCH',
            '/api/tasks/task-04',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['tags' => [' Billing ', 'urgent']], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $after = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(['billing', 'urgent'], $after['tags']);
        self::assertSame($before['title'], $after['title']);
        self::assertSame($before['isDone'], $after['isDone']);
    }

    public function testListWithTagReturnsOnlyTaggedTasks(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->request(
            'POST',
            '/api/tasks',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => 'Billing task', 'tags' => ['billing']], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);

        $client->request('GET', '/api/tasks?tag=billing');

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame(1, $body['total']);
        self::assertCount(1, $body['items']);
        self::assertSame('Billing task', $body['items'][0]['title']);
        self::assertSame(['billing'], $body['items'][0]['tags']);
    }

    public function testListWithTagAndDoneTrueInterplay(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->request(
            'POST',
            '/api/tasks',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => 'Done billing', 'tags' => ['billing'], 'isDone' => true], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);
        $client->request(
            'POST',
            '/api/tasks',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => 'Open billing', 'tags' => ['billing'], 'isDone' => false], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);

        $client->request('GET', '/api/tasks?tag=billing&done=true');

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame(1, $body['total']);
        self::assertCount(1, $body['items']);
        self::assertSame('Done billing', $body['items'][0]['title']);
        self::assertTrue($body['items'][0]['isDone']);
    }

    public function testListWithEmptyTagIsNoFilter(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tasks?tag=');

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame(9, $body['total']);
        self::assertCount(9, $body['items']);
    }

    public function testListWithTagRespectsPagination(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        for ($i = 1; $i <= 3; $i++) {
            $client->request(
                'POST',
                '/api/tasks',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['title' => sprintf('Billing %d', $i), 'tags' => ['billing']], JSON_THROW_ON_ERROR),
            );
            self::assertResponseStatusCodeSame(201);
        }

        $client->request('GET', '/api/tasks?tag=billing&page=2&perPage=2');

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame(3, $body['total']);
        self::assertSame(2, $body['page']);
        self::assertSame(2, $body['perPage']);
        self::assertCount(1, $body['items']);
    }

    public function testTagsReturnsCountsMostUsedFirst(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->request(
            'POST',
            '/api/tasks',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => 'A', 'tags' => ['billing', 'urgent']], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);
        $client->request(
            'POST',
            '/api/tasks',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => 'B', 'tags' => ['billing']], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);

        $client->request('GET', '/api/tags');

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame(
            [
                ['tag' => 'billing', 'count' => 2],
                ['tag' => 'urgent', 'count' => 1],
            ],
            $body,
        );
    }

    public function testTagsTieBreakByTagAscending(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->request(
            'POST',
            '/api/tasks',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => 'A', 'tags' => ['zeta', 'alpha']], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);

        $client->request('GET', '/api/tags');

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame(
            [
                ['tag' => 'alpha', 'count' => 1],
                ['tag' => 'zeta', 'count' => 1],
            ],
            $body,
        );
    }

    public function testTagsOmitsNeverUsedTags(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tags');

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame([], $body);
    }

    public function testCreateTaskWithDueDateStoresAndReturnsIt(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->request(
            'POST',
            '/api/tasks',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => 'Due task', 'dueDate' => '2026-12-01'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);
        $task = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('2026-12-01', $task['dueDate']);

        $client->request('GET', '/api/tasks/' . $task['id']);
        self::assertResponseIsSuccessful();
        $fetched = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('2026-12-01', $fetched['dueDate']);
    }

    public function testCreateTaskWithInvalidCalendarDateIs422(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/tasks',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => 'x', 'dueDate' => '2026-02-30'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsString($body['error']);
        self::assertNotSame('', $body['error']);
    }

    public function testCreateTaskWithNonDateStringIs422(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/tasks',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => 'x', 'dueDate' => 'tomorrow'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsString($body['error']);
        self::assertNotSame('', $body['error']);
    }

    public function testPatchTaskWithNullDueDateClearsIt(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->request(
            'POST',
            '/api/tasks',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => 'Due task', 'dueDate' => '2026-12-01'], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);
        $task = json_decode((string) $client->getResponse()->getContent(), true);

        $client->request(
            'PATCH',
            '/api/tasks/' . $task['id'],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['dueDate' => null], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $after = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertNull($after['dueDate']);
    }

    public function testPatchTaskWithDueDateSetsIt(): void
    {
        $client = static::createClient();
        $client->request(
            'PATCH',
            '/api/tasks/task-04',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['dueDate' => '2026-11-20'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $after = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('2026-11-20', $after['dueDate']);
    }

    public function testGetPreExistingTaskReturnsNullDueDate(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tasks/task-04');

        self::assertResponseIsSuccessful();
        $task = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertNull($task['dueDate']);
    }

    public function testPatchUnknownTaskIs404(): void
    {
        $client = static::createClient();
        $client->request(
            'PATCH',
            '/api/tasks/nope',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['tags' => ['a']], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => 'Task not found'], json_decode((string) $client->getResponse()->getContent(), true));
    }
}
