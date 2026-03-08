<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\InboxImportService;
use Illuminate\Console\Command;

class InboxProcess extends Command
{
    protected $signature = 'inbox:process';

    protected $description = 'Process collection JSON files from the inbox folder';

    public function handle(InboxImportService $service): int
    {
        $processed = $service->processInbox();

        $this->info("Inbox processing complete. {$processed} file(s) processed.");

        return self::SUCCESS;
    }
}
