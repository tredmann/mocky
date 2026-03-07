<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EndpointImportService;
use Illuminate\Console\Command;

class EndpointImport extends Command
{
    protected $signature = 'endpoint:import {file} {--user= : User email or ID to assign the endpoint to}';

    protected $description = 'Import an endpoint definition from a JSON file';

    public function handle(EndpointImportService $importService): int
    {
        $file = $this->argument('file');
        $realPath = realpath($file);

        if ($realPath === false) {
            $this->error("File [{$file}] not found.");

            return self::FAILURE;
        }

        $maxSize = 5 * 1024 * 1024; // 5MB

        if (filesize($realPath) > $maxSize) {
            $this->error('File is too large (max 5MB).');

            return self::FAILURE;
        }

        $data = json_decode(file_get_contents($realPath), true);

        if (! is_array($data)) {
            $this->error('Invalid JSON file.');

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

        $endpoint = $importService->import($user, $data);

        $this->info("Imported [{$endpoint->name}] with slug [{$endpoint->slug}] for user [{$user->email}].");

        return self::SUCCESS;
    }
}
