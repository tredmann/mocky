<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Concerns\ResolvesImportFile;
use App\Data\EndpointData;
use App\Models\EndpointCollection;
use App\Services\EndpointImportService;
use Illuminate\Console\Command;

class EndpointImport extends Command
{
    use ResolvesImportFile;

    protected $signature = 'endpoint:import {file} {--user= : User email or ID to assign the endpoint to} {--collection= : Collection slug to import into}';

    protected $description = 'Import an endpoint definition from a JSON file';

    public function handle(EndpointImportService $importService): int
    {
        $realPath = $this->validateImportFile($this->argument('file'));
        if ($realPath === null) {
            return self::FAILURE;
        }

        $data = $this->readJsonImportFile($realPath);
        if ($data === null) {
            return self::FAILURE;
        }

        $user = $this->resolveUser($this->option('user'));
        if (! $user) {
            return self::FAILURE;
        }

        $collectionSlug = $this->option('collection');

        if ($collectionSlug) {
            $collection = EndpointCollection::where('slug', $collectionSlug)
                ->where('user_id', $user->id)
                ->first();
        } else {
            $collection = $user->endpointCollections()->first();
        }

        if (! $collection) {
            $this->error('Collection not found. Use --collection=<slug> to specify a collection.');

            return self::FAILURE;
        }

        $endpoint = $importService->import($user, EndpointData::fromArray($data), $collection);

        $this->info("Imported [{$endpoint->name}] with slug [{$endpoint->slug}] into collection [{$collection->slug}] for user [{$user->email}].");

        return self::SUCCESS;
    }
}
