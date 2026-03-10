<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CollectionData;
use App\Events\InboxFileProcessed;
use App\Models\FileInboxLog;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InboxImportService
{
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

    public function __construct(
        private CollectionImportService $collectionImportService,
    ) {}

    /**
     * Returns disk-relative paths of all .json files in the configured inbox path.
     *
     * @return Collection<int, string>
     */
    public function listInboxFiles(): Collection
    {
        return collect($this->disk()->files(config('inbox.path')))
            ->filter(fn (string $file) => str_ends_with(strtolower($file), '.json'))
            ->values();
    }

    /**
     * Process a single file for the given user.
     *
     * Returns the FileInboxLog record, or null if the file cannot be read from disk.
     * When $force is true, skips the global MD5 dedup check (used for manual imports).
     */
    public function processFile(string $filePath, User $user, bool $force = false): ?FileInboxLog
    {
        $disk = $this->disk();
        $filename = basename($filePath);

        $size = $disk->size($filePath);
        if ($size > self::MAX_FILE_SIZE) {
            return $this->createLog($filename, md5(''), $user, 'failed', 'File exceeds 5MB size limit.');
        }

        $contents = $disk->get($filePath);
        if ($contents === null) {
            return null;
        }

        $md5 = md5($contents);

        if (! $force && FileInboxLog::where('file_md5', $md5)->exists()) {
            return null;
        }

        $data = json_decode($contents, true);
        if (! is_array($data)) {
            return $this->createLog($filename, $md5, $user, 'failed', 'Invalid JSON file.');
        }

        if (empty($data['name'])) {
            return $this->createLog($filename, $md5, $user, 'failed', 'Missing required field: name.');
        }

        try {
            $this->collectionImportService->import($user, CollectionData::fromArray($data));

            return $this->createLog($filename, $md5, $user, 'imported');
        } catch (Throwable $e) {
            return $this->createLog($filename, $md5, $user, 'failed', $e->getMessage());
        }
    }

    public function processInbox(): int
    {
        $user = $this->resolveAutoImportUser();

        if (! $user) {
            Log::warning('Inbox import: no user configured or found. Set INBOX_IMPORT_USER in .env or enable auto-import for a user on the Inbox page.');

            return 0;
        }

        $processed = 0;

        foreach ($this->listInboxFiles() as $file) {
            if ($this->processFile($file, $user) !== null) {
                $processed++;
            }
        }

        return $processed;
    }

    private function createLog(string $filename, string $md5, User $user, string $status, ?string $error = null): FileInboxLog
    {
        $log = FileInboxLog::create([
            'filename' => $filename,
            'file_md5' => $md5,
            'disk' => config('inbox.disk'),
            'status' => $status,
            'error_message' => $error,
            'user_id' => $user->id,
        ]);

        $message = $status === 'imported'
            ? "'{$filename}' imported successfully."
            : ($error ?? 'Import failed.');

        InboxFileProcessed::dispatch($filename, $status, $message, $user);

        return $log;
    }

    private function resolveAutoImportUser(): ?User
    {
        $autoUser = User::where('inbox_auto_import', true)->first();
        if ($autoUser) {
            return $autoUser;
        }

        $identifier = config('inbox.user');
        if ($identifier) {
            return User::find($identifier) ?? User::where('email', $identifier)->first();
        }

        return User::first();
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('inbox.disk'));
    }
}
