<?php

namespace App\Contracts;

use App\Models\StoredFile;
use Illuminate\Http\UploadedFile;

interface FileUploader
{
    /**
     * Persist an already validated upload and register it for expiration.
     */
    public function upload(UploadedFile $file): StoredFile;
}
