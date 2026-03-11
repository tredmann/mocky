<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Concerns\ResolvesImportFile;
use App\Services\WsdlImportService;
use Illuminate\Console\Command;

class WsdlImport extends Command
{
    use ResolvesImportFile;

    protected $signature = 'wsdl:import {file} {--user= : User email or ID to assign the collection to}';

    protected $description = 'Import a WSDL file (.wsdl or .xml) as a collection with SOAP mock endpoints';

    public function handle(WsdlImportService $importService): int
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
        $this->info("Imported WSDL [{$collection->name}] with {$endpointCount} endpoint(s) for user [{$user->email}].");

        return self::SUCCESS;
    }
}
