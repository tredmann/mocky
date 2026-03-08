<?php

namespace App\Http\Controllers;

use App\Services\MockRequestPipeline;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MockController extends Controller
{
    public function __construct(private MockRequestPipeline $pipeline) {}

    public function handle(Request $request, string $collectionSlug, string $endpointSlug, string $path = ''): Response
    {
        return $this->pipeline->handle($request, $collectionSlug, $endpointSlug, $path);
    }
}
