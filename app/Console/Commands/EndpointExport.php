<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EndpointCollection;
use App\Services\EndpointExportService;
use Illuminate\Console\Command;

class EndpointExport extends Command
{
    protected $signature = 'endpoint:export {collection : Collection slug} {slug : Endpoint slug} {--output= : Output file path (defaults to <slug>.json)}';

    protected $description = 'Export an endpoint definition to a JSON file';

    public function handle(EndpointExportService $exportService): int
    {
        $collectionSlug = $this->argument('collection');
        $slug = $this->argument('slug');

        $collection = EndpointCollection::where('slug', $collectionSlug)->first();

        if (! $collection) {
            $this->error("Collection with slug [{$collectionSlug}] not found.");

            return self::FAILURE;
        }

        $endpoint = $collection->endpoints()->where('slug', $slug)->first();

        if (! $endpoint) {
            $this->error("Endpoint with slug [{$slug}] not found in collection [{$collectionSlug}].");

            return self::FAILURE;
        }

        $data = $exportService->toArray($endpoint);

        $output = $this->option('output') ?? "{$slug}.json";
        $outputPath = realpath(dirname($output));

        if ($outputPath === false || str_contains($output, '..')) {
            $this->error('Invalid output path.');

            return self::FAILURE;
        }

        $resolvedOutput = $outputPath.'/'.basename($output);

        file_put_contents($resolvedOutput, json_encode($data, JSON_PRETTY_PRINT));

        $this->info("Exported [{$endpoint->name}] to {$resolvedOutput}");

        return self::SUCCESS;
    }
}
