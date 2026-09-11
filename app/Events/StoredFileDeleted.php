<?php

namespace App\Events;

use App\DTO\DeletedFileData;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final class StoredFileDeleted implements ShouldDispatchAfterCommit
{
    public function __construct(
        public readonly DeletedFileData $file,
    ) {}
}
