<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Concerns\ResolvesImportFile;
use App\Services\OpenApiImportService;
use Illuminate\Console\Command;

class OpenApiImport extends Command
{
    use ResolvesImportFile;

    protected $signature = 'openapi:import {file} {--user= : User email or ID to assign the collection to}';

    protected $description = 'Import an OpenAPI (YAML/JSON) specification as a collection with mock endpoints';

    public function handle(OpenApiImportService $importService): int
    {
        $realPath = $this->validateImportFile($this->argument('file'));
        if ($realPath === null) {
            return self::FAILURE;
        }

        $user = $this->resolveUser($this->option('user'));
        if (! $user) {
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
