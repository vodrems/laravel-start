<?php

namespace App\Services\FileStorage;

use App\Contracts\FileRemover;
use App\DTO\DeletedFileData;
use App\Enums\DeletionReason;
use App\Events\StoredFileDeleted;
use App\Models\StoredFile;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

/**
 * The single deletion path for both manual and automatic removal,
 * so every deletion produces exactly one notification.
 */
final class FileDeletionService implements FileRemover
{
    public function __construct(
        private readonly Filesystem $disk,
        private readonly Dispatcher $events,
        private readonly LoggerInterface $logger,
    ) {}

    public function delete(StoredFile $file, DeletionReason $reason): bool
    {
        return DB::transaction(function () use ($file, $reason): bool {
            // Lock the row so a manual delete and the purge command cannot both notify.
            $locked = StoredFile::query()->lockForUpdate()->find($file->getKey());

            if ($locked === null) {
                return false;
            }

            if ($this->disk->exists($locked->path)) {
                $this->disk->delete($locked->path);
            } else {
                $this->logger->warning('Stored file is missing on disk, removing the record only.', [
                    'id' => $locked->id,
                    'path' => $locked->path,
                ]);
            }

            $data = DeletedFileData::fromModel($locked, $reason);
            $locked->delete();

            // Dispatched after commit (see the event), so a rollback sends nothing.
            $this->events->dispatch(new StoredFileDeleted($data));

            return true;
        });
    }
}
