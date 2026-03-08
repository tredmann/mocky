<?php

use App\Models\FileInboxLog;
use App\Models\User;
use App\Services\InboxImportService;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function inboxService(): InboxImportService
{
    return app(InboxImportService::class);
}

function validCollectionJson(array $overrides = []): string
{
    return json_encode(array_merge([
        'name' => 'Test Collection',
        'description' => 'A test collection',
        'endpoints' => [
            [
                'name' => 'Get user',
                'slug' => 'get-user',
                'method' => 'GET',
                'status_code' => 200,
                'content_type' => 'application/json',
                'response_body' => '{"id":1}',
                'is_active' => true,
                'conditional_responses' => [],
            ],
        ],
    ], $overrides));
}

function setupInbox(string $diskName = 'inbox-test'): Illuminate\Filesystem\FilesystemAdapter
{
    $disk = Storage::fake($diskName);
    config()->set('inbox.disk', $diskName);
    config()->set('inbox.path', 'inbox');

    return $disk;
}

test('imports a valid collection JSON file', function () {
    $user = User::factory()->create();
    config()->set('inbox.user', $user->email);
    $disk = setupInbox();

    $json = validCollectionJson();
    $disk->put('inbox/collection.json', $json);

    $processed = inboxService()->processInbox();

    expect($processed)->toBe(1);
    expect(FileInboxLog::count())->toBe(1);

    $log = FileInboxLog::first();
    expect($log->filename)->toBe('collection.json')
        ->and($log->status)->toBe('imported')
        ->and($log->file_md5)->toBe(md5($json))
        ->and($log->user_id)->toBe($user->id)
        ->and($log->error_message)->toBeNull();
});

test('skips already-processed file with same MD5', function () {
    $user = User::factory()->create();
    config()->set('inbox.user', $user->email);
    $disk = setupInbox();

    $json = validCollectionJson();
    $disk->put('inbox/collection.json', $json);

    // First run imports the file
    inboxService()->processInbox();
    expect(FileInboxLog::count())->toBe(1);

    // Second run skips it (same MD5 already in DB)
    $processed = inboxService()->processInbox();
    expect($processed)->toBe(0);
    expect(FileInboxLog::count())->toBe(1);
});

test('handles invalid JSON gracefully', function () {
    $user = User::factory()->create();
    config()->set('inbox.user', $user->email);
    $disk = setupInbox();

    $disk->put('inbox/bad.json', 'not valid json {{{');

    inboxService()->processInbox();

    $log = FileInboxLog::first();
    expect($log->status)->toBe('failed')
        ->and($log->error_message)->toBe('Invalid JSON file.');
});

test('handles missing name field', function () {
    $user = User::factory()->create();
    config()->set('inbox.user', $user->email);
    $disk = setupInbox();

    $disk->put('inbox/no-name.json', json_encode(['endpoints' => []]));

    inboxService()->processInbox();

    $log = FileInboxLog::first();
    expect($log->status)->toBe('failed')
        ->and($log->error_message)->toBe('Missing required field: name.');
});

test('does nothing when inbox is empty', function () {
    $user = User::factory()->create();
    config()->set('inbox.user', $user->email);
    setupInbox();

    $processed = inboxService()->processInbox();

    expect($processed)->toBe(0);
    expect(FileInboxLog::count())->toBe(0);
});

test('resolves user by email', function () {
    $user = User::factory()->create(['email' => 'inbox@example.com']);
    config()->set('inbox.user', 'inbox@example.com');
    $disk = setupInbox();

    $disk->put('inbox/collection.json', validCollectionJson());

    inboxService()->processInbox();

    $log = FileInboxLog::first();
    expect($log->user_id)->toBe($user->id);
});

test('resolves user by UUID', function () {
    $user = User::factory()->create();
    config()->set('inbox.user', $user->id);
    $disk = setupInbox();

    $disk->put('inbox/collection.json', validCollectionJson());

    inboxService()->processInbox();

    $log = FileInboxLog::first();
    expect($log->user_id)->toBe($user->id);
});

test('returns zero when no user is configured and none exists', function () {
    config()->set('inbox.user', 'nonexistent@example.com');
    setupInbox();

    $processed = inboxService()->processInbox();

    expect($processed)->toBe(0);
    expect(FileInboxLog::count())->toBe(0);
});

test('handles oversized file', function () {
    $user = User::factory()->create();
    config()->set('inbox.user', $user->email);
    $disk = setupInbox();

    // Create a file larger than 5MB
    $disk->put('inbox/large.json', str_repeat('x', 5 * 1024 * 1024 + 1));

    inboxService()->processInbox();

    $log = FileInboxLog::first();
    expect($log->status)->toBe('failed')
        ->and($log->error_message)->toBe('File exceeds 5MB size limit.');
});

test('only processes json files', function () {
    $user = User::factory()->create();
    config()->set('inbox.user', $user->email);
    $disk = setupInbox();

    $disk->put('inbox/readme.txt', 'not a json file');
    $disk->put('inbox/collection.json', validCollectionJson());

    $processed = inboxService()->processInbox();

    expect($processed)->toBe(1);
    expect(FileInboxLog::count())->toBe(1);
    expect(FileInboxLog::first()->filename)->toBe('collection.json');
});

test('files remain in inbox after processing', function () {
    $user = User::factory()->create();
    config()->set('inbox.user', $user->email);
    $disk = setupInbox();

    $disk->put('inbox/collection.json', validCollectionJson());

    inboxService()->processInbox();

    expect($disk->exists('inbox/collection.json'))->toBeTrue();
});

test('processes multiple files in one run', function () {
    $user = User::factory()->create();
    config()->set('inbox.user', $user->email);
    $disk = setupInbox();

    $disk->put('inbox/first.json', validCollectionJson(['name' => 'First']));
    $disk->put('inbox/second.json', validCollectionJson(['name' => 'Second']));

    $processed = inboxService()->processInbox();

    expect($processed)->toBe(2);
    expect(FileInboxLog::where('status', 'imported')->count())->toBe(2);
});
