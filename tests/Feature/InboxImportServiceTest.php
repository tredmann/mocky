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

test('imports a valid collection JSON file for a user with auto-import enabled', function () {
    $user = User::factory()->create(['inbox_auto_import' => true]);
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

test('does not import when no user has auto-import enabled', function () {
    User::factory()->create(['inbox_auto_import' => false]);
    $disk = setupInbox();

    $disk->put('inbox/collection.json', validCollectionJson());

    $processed = inboxService()->processInbox();

    expect($processed)->toBe(0);
    expect(FileInboxLog::count())->toBe(0);
});

test('does nothing when no users exist', function () {
    $disk = setupInbox();

    $disk->put('inbox/collection.json', validCollectionJson());

    $processed = inboxService()->processInbox();

    expect($processed)->toBe(0);
    expect(FileInboxLog::count())->toBe(0);
});

test('imports for each user with auto-import enabled', function () {
    $userA = User::factory()->create(['inbox_auto_import' => true]);
    $userB = User::factory()->create(['inbox_auto_import' => true]);
    User::factory()->create(['inbox_auto_import' => false]);

    $disk = setupInbox();
    $disk->put('inbox/collection.json', validCollectionJson());

    $processed = inboxService()->processInbox();

    expect($processed)->toBe(2);
    expect(FileInboxLog::count())->toBe(2);
    expect(FileInboxLog::where('user_id', $userA->id)->count())->toBe(1);
    expect(FileInboxLog::where('user_id', $userB->id)->count())->toBe(1);
});

test('skips already-processed file for the same user', function () {
    User::factory()->create(['inbox_auto_import' => true]);
    $disk = setupInbox();

    $disk->put('inbox/collection.json', validCollectionJson());

    inboxService()->processInbox();
    expect(FileInboxLog::count())->toBe(1);

    $processed = inboxService()->processInbox();
    expect($processed)->toBe(0);
    expect(FileInboxLog::count())->toBe(1);
});

test('imports same file for a second user who enables auto-import later', function () {
    $userA = User::factory()->create(['inbox_auto_import' => true]);
    $disk = setupInbox();
    $disk->put('inbox/collection.json', validCollectionJson());

    inboxService()->processInbox();
    expect(FileInboxLog::where('user_id', $userA->id)->count())->toBe(1);

    // User B enables auto-import later
    $userB = User::factory()->create(['inbox_auto_import' => true]);

    $processed = inboxService()->processInbox();

    // Only user B gets the file (user A already processed it)
    expect($processed)->toBe(1);
    expect(FileInboxLog::where('user_id', $userB->id)->count())->toBe(1);
});

test('handles invalid JSON gracefully', function () {
    User::factory()->create(['inbox_auto_import' => true]);
    $disk = setupInbox();

    $disk->put('inbox/bad.json', 'not valid json {{{');

    inboxService()->processInbox();

    $log = FileInboxLog::first();
    expect($log->status)->toBe('failed')
        ->and($log->error_message)->toBe('Invalid JSON file.');
});

test('handles missing name field', function () {
    User::factory()->create(['inbox_auto_import' => true]);
    $disk = setupInbox();

    $disk->put('inbox/no-name.json', json_encode(['endpoints' => []]));

    inboxService()->processInbox();

    $log = FileInboxLog::first();
    expect($log->status)->toBe('failed')
        ->and($log->error_message)->toBe('Missing required field: name.');
});

test('does nothing when inbox is empty', function () {
    User::factory()->create(['inbox_auto_import' => true]);
    setupInbox();

    $processed = inboxService()->processInbox();

    expect($processed)->toBe(0);
    expect(FileInboxLog::count())->toBe(0);
});

test('handles oversized file', function () {
    User::factory()->create(['inbox_auto_import' => true]);
    $disk = setupInbox();

    $disk->put('inbox/large.json', str_repeat('x', 5 * 1024 * 1024 + 1));

    inboxService()->processInbox();

    $log = FileInboxLog::first();
    expect($log->status)->toBe('failed')
        ->and($log->error_message)->toBe('File exceeds 5MB size limit.');
});

test('only processes json files', function () {
    User::factory()->create(['inbox_auto_import' => true]);
    $disk = setupInbox();

    $disk->put('inbox/readme.txt', 'not a json file');
    $disk->put('inbox/collection.json', validCollectionJson());

    $processed = inboxService()->processInbox();

    expect($processed)->toBe(1);
    expect(FileInboxLog::count())->toBe(1);
    expect(FileInboxLog::first()->filename)->toBe('collection.json');
});

test('files remain in inbox after processing', function () {
    User::factory()->create(['inbox_auto_import' => true]);
    $disk = setupInbox();

    $disk->put('inbox/collection.json', validCollectionJson());

    inboxService()->processInbox();

    expect($disk->exists('inbox/collection.json'))->toBeTrue();
});

test('processes multiple files in one run', function () {
    User::factory()->create(['inbox_auto_import' => true]);
    $disk = setupInbox();

    $disk->put('inbox/first.json', validCollectionJson(['name' => 'First']));
    $disk->put('inbox/second.json', validCollectionJson(['name' => 'Second']));

    $processed = inboxService()->processInbox();

    expect($processed)->toBe(2);
    expect(FileInboxLog::where('status', 'imported')->count())->toBe(2);
});
