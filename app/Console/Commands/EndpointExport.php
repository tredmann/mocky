<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Endpoint;
use Illuminate\Console\Command;

class EndpointExport extends Command
{
    protected $signature = 'endpoint:export {slug} {--output= : Output file path (defaults to <slug>.json)}';

    protected $description = 'Export an endpoint definition to a JSON file';

    public function handle(): int
    {
        $slug = $this->argument('slug');

        $endpoint = Endpoint::where('slug', $slug)->first();

        if (! $endpoint) {
            $this->error("Endpoint with slug [{$slug}] not found.");

            return self::FAILURE;
        }

        $data = [
            'name' => $endpoint->name,
            'slug' => $endpoint->slug,
            'method' => $endpoint->method,
            'status_code' => $endpoint->status_code,
            'content_type' => $endpoint->content_type,
            'response_body' => $endpoint->response_body,
            'is_active' => $endpoint->is_active,
            'conditional_responses' => $endpoint->conditionalResponses()
                ->get()
                ->map(fn ($cr) => [
                    'condition_source' => $cr->condition_source,
                    'condition_field' => $cr->condition_field,
                    'condition_operator' => $cr->condition_operator,
                    'condition_value' => $cr->condition_value,
                    'status_code' => $cr->status_code,
                    'content_type' => $cr->content_type,
                    'response_body' => $cr->response_body,
                    'priority' => $cr->priority,
                ])
                ->all(),
        ];

        $output = $this->option('output') ?? "{$slug}.json";

        file_put_contents($output, json_encode($data, JSON_PRETTY_PRINT));

        $this->info("Exported [{$endpoint->name}] to {$output}");

        return self::SUCCESS;
    }
}
