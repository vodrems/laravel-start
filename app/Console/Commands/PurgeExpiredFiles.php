<?php

namespace App\Console\Commands;

use App\Contracts\FileRemover;
use App\Enums\DeletionReason;
use App\Models\StoredFile;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

final class PurgeExpiredFiles extends Command
{
    protected $signature = 'files:purge-expired';

    protected $description = 'Delete files whose retention period has expired';

    public function handle(FileRemover $remover): int
    {
        $deleted = 0;

        StoredFile::query()->expired()->chunkById(100, function (Collection $files) use ($remover, &$deleted) {
            foreach ($files as $file) {
                try {
                    if ($remover->delete($file, DeletionReason::Expired)) {
                        $deleted++;
                    }
                } catch (Throwable $e) {
                    // One broken file must not block the rest; it is retried on the next run.
                    report($e);
                    $this->error("Failed to delete file #{$file->id}: {$e->getMessage()}");
                }
            }
        });

        $this->info("Expired files deleted: {$deleted}");

        return self::SUCCESS;
    }
}
