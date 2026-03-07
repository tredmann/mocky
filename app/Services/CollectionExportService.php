<?php

namespace App\Services;

use App\Models\EndpointCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CollectionExportService
{
    public function __construct(private EndpointExportService $endpointExportService) {}

    /** @return array<string, mixed> */
    public function toArray(EndpointCollection $collection): array
    {
        return [
            'name' => $collection->name,
            'description' => $collection->description,
            'endpoints' => $collection->endpoints()
                ->get()
                ->map(fn ($endpoint) => $this->endpointExportService->toArray($endpoint))
                ->all(),
        ];
    }

    public function export(EndpointCollection $collection): StreamedResponse
    {
        $data = $this->toArray($collection);
        $filename = str($collection->name)->slug()->append('.json')->toString();

        return response()->streamDownload(
            fn () => print json_encode($data, JSON_PRETTY_PRINT),
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }
}
