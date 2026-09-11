<?php

namespace App\Contracts;

use App\Enums\DeletionReason;
use App\Models\StoredFile;

interface FileRemover
{
    /**
     * Delete the file from storage and the database and announce the deletion.
     *
     * Returns false when the file had already been deleted by someone else.
     */
    public function delete(StoredFile $file, DeletionReason $reason): bool;
}
