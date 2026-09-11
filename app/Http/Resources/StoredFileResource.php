<?php

namespace App\Http\Resources;

use App\Models\StoredFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Number;

/**
 * @mixin StoredFile
 */
class StoredFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'size_human' => Number::fileSize($this->size, maxPrecision: 1),
            'uploaded_at' => $this->created_at->toIso8601String(),
            'expires_at' => $this->expires_at->toIso8601String(),
            'download_url' => route('files.download', $this->resource),
            'delete_url' => route('files.destroy', $this->resource),
        ];
    }
}
