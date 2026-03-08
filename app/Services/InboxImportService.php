<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CollectionData;
use App\Models\FileInboxLog;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InboxImportService
{
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

    public function __construct(
        private CollectionImportService $collectionImportService,
    ) {}

    public function processInbox(): int
    {
        $user = $this->resolveUser();
        if (! $user) {
            Log::warning('Inbox import: no user configured or found. Set INBOX_IMPORT_USER in .env.');

            return 0;
        }

        $disk = $this->disk();
        $path = config('inbox.path');
        $processed = 0;

        $files = collect($disk->files($path))
            ->filter(fn (string $file) => str_ends_with(strtolower($file), '.json'));

        foreach ($files as $file) {
            if ($this->processFile($disk, $file, $user)) {
                $processed++;
            }
        }

        return $processed;
    }

    private function processFile(Filesystem $disk, string $file, User $user): bool
    {
        $filename = basename($file);

        $size = $disk->size($file);
        if ($size > self::MAX_FILE_SIZE) {
            $this->createLog($filename, md5(''), $user, 'failed', 'File exceeds 5MB size limit.');

            return true;
        }

        $contents = $disk->get($file);
        if ($contents === null) {
            return false;
        }

        $md5 = md5($contents);

        if (FileInboxLog::where('file_md5', $md5)->exists()) {
            return false;
        }

        $data = json_decode($contents, true);
        if (! is_array($data)) {
            $this->createLog($filename, $md5, $user, 'failed', 'Invalid JSON file.');

            return true;
        }

        if (empty($data['name'])) {
            $this->createLog($filename, $md5, $user, 'failed', 'Missing required field: name.');

            return true;
        }

        try {
            $this->collectionImportService->import($user, CollectionData::fromArray($data));
            $this->createLog($filename, $md5, $user, 'imported');
        } catch (Throwable $e) {
            $this->createLog($filename, $md5, $user, 'failed', $e->getMessage());
        }

        return true;
    }

    private function createLog(string $filename, string $md5, User $user, string $status, ?string $error = null): void
    {
        FileInboxLog::create([
            'filename' => $filename,
            'file_md5' => $md5,
            'disk' => config('inbox.disk'),
            'status' => $status,
            'error_message' => $error,
            'user_id' => $user->id,
        ]);
    }

    private function resolveUser(): ?User
    {
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
