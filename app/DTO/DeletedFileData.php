<?php

namespace App\DTO;

use App\Enums\DeletionReason;
use App\Models\StoredFile;
use Carbon\CarbonImmutable;

/**
 * Snapshot of a deleted file. The notification is handled asynchronously,
 * after the database row is gone, so it carries plain data instead of a model.
 */
final readonly class DeletedFileData
{
    public function __construct(
        public int $id,
        public string $originalName,
        public string $mimeType,
        public int $size,
        public CarbonImmutable $uploadedAt,
        public CarbonImmutable $deletedAt,
        public DeletionReason $reason,
    ) {}

    public static function fromModel(StoredFile $file, DeletionReason $reason): self
    {
        return new self(
            id: $file->id,
            originalName: $file->original_name,
            mimeType: $file->mime_type,
            size: $file->size,
            uploadedAt: $file->created_at->toImmutable(),
            deletedAt: CarbonImmutable::now(),
            reason: $reason,
        );
    }
}
