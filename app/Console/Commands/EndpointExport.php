<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Endpoint;
use App\Services\EndpointExportService;
use Illuminate\Console\Command;

class EndpointExport extends Command
{
    protected $signature = 'endpoint:export {slug} {--output= : Output file path (defaults to <slug>.json)}';

    protected $description = 'Export an endpoint definition to a JSON file';

    public function handle(EndpointExportService $exportService): int
    {
        $slug = $this->argument('slug');

        $endpoint = Endpoint::where('slug', $slug)->first();

        if (! $endpoint) {
            $this->error("Endpoint with slug [{$slug}] not found.");

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
