<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EndpointCollection;
use App\Services\CollectionExportService;
use Illuminate\Console\Command;

class CollectionExport extends Command
{
    protected $signature = 'collection:export {slug : Collection slug} {--output= : Output file path (defaults to <name>.json)}';

    protected $description = 'Export a collection with all its endpoints to a JSON file';

    public function handle(CollectionExportService $exportService): int
    {
        $slug = $this->argument('slug');

        $collection = EndpointCollection::where('slug', $slug)->first();

        if (! $collection) {
            $this->error("Collection with slug [{$slug}] not found.");

            return self::FAILURE;
        }

        $data = $exportService->toArray($collection);

        $defaultName = str($collection->name)->slug()->append('.json')->toString();
        $output = $this->option('output') ?? $defaultName;
        $outputPath = realpath(dirname($output));

        if ($outputPath === false || str_contains($output, '..')) {
            $this->error('Invalid output path.');

            return self::FAILURE;
        }

        $resolvedOutput = $outputPath.'/'.basename($output);

        file_put_contents($resolvedOutput, json_encode($data, JSON_PRETTY_PRINT));

        $endpointCount = count($data['endpoints']);
        $this->info("Exported collection [{$collection->name}] with {$endpointCount} endpoint(s) to {$resolvedOutput}");

        return self::SUCCESS;
    }
}
