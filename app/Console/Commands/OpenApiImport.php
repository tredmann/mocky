<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\OpenApiImportService;
use Illuminate\Console\Command;

class OpenApiImport extends Command
{
    protected $signature = 'openapi:import {file} {--user= : User email or ID to assign the collection to}';

    protected $description = 'Import an OpenAPI (YAML/JSON) specification as a collection with mock endpoints';

    public function handle(OpenApiImportService $importService): int
    {
        $file = $this->argument('file');
        $realPath = realpath($file);

        if ($realPath === false) {
            $this->error("File [{$file}] not found.");

            return self::FAILURE;
        }

        $maxSize = 5 * 1024 * 1024;

        if (filesize($realPath) > $maxSize) {
            $this->error('File is too large (max 5MB).');

            return self::FAILURE;
        }

        $userIdentifier = $this->option('user');

        if ($userIdentifier) {
            $user = User::find($userIdentifier) ?? User::where('email', $userIdentifier)->first();
        } else {
            $user = User::first();
        }

        if (! $user) {
            $this->error('User not found. Use --user=<email|id> to specify a user.');

            return self::FAILURE;
        }

        try {
            $collection = $importService->importFromFile($user, $realPath);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $endpointCount = $collection->endpoints()->count();
        $this->info("Imported OpenAPI spec as collection [{$collection->name}] with {$endpointCount} endpoint(s) for user [{$user->email}].");

        return self::SUCCESS;
    }
}
