<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Concerns\ResolvesImportFile;
use App\Services\CollectionImportService;
use Illuminate\Console\Command;

class CollectionImport extends Command
{
    use ResolvesImportFile;

    protected $signature = 'collection:import {file} {--user= : User email or ID to assign the collection to}';

    protected $description = 'Import a collection with all its endpoints from a JSON file';

    public function handle(CollectionImportService $importService): int
    {
        $realPath = $this->validateImportFile($this->argument('file'));
        if ($realPath === null) {
            return self::FAILURE;
        }

        $data = $this->readJsonImportFile($realPath);
        if ($data === null) {
            return self::FAILURE;
        }

        if (empty($data['name'])) {
            $this->error('Missing required field: name');

            return self::FAILURE;
        }

        $user = $this->resolveUser($this->option('user'));
        if (! $user) {
            return self::FAILURE;
        }

        $collection = $importService->import($user, $data);

        $endpointCount = $collection->endpoints()->count();
        $this->info("Imported collection [{$collection->name}] with {$endpointCount} endpoint(s) for user [{$user->email}].");

        return self::SUCCESS;
    }
}
