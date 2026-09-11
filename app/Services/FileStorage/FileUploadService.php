<?php

namespace App\Services\FileStorage;

use App\Contracts\FileUploader;
use App\Models\StoredFile;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class FileUploadService implements FileUploader
{
    public function __construct(
        private readonly Filesystem $disk,
        private readonly string $directory,
        private readonly int $ttlHours,
    ) {}

    public function upload(UploadedFile $file): StoredFile
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $this->disk->putFileAs(
            $this->directory.'/'.now()->format('Y/m/d'),
            $file,
            Str::uuid().'.'.$extension,
        );

        if ($path === false) {
            throw new RuntimeException('Не удалось сохранить файл.');
        }

        try {
            return StoredFile::create([
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'path' => $path,
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size' => $file->getSize(),
                'expires_at' => now()->addHours($this->ttlHours),
            ]);
        } catch (Throwable $e) {
            // Do not leave an orphaned file that nothing would ever clean up.
            $this->disk->delete($path);

            throw $e;
        }
    }
}
