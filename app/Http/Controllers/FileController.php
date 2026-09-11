<?php

namespace App\Http\Controllers;

use App\Contracts\FileRemover;
use App\Contracts\FileUploader;
use App\Enums\DeletionReason;
use App\Http\Requests\StoreFileRequest;
use App\Http\Resources\StoredFileResource;
use App\Models\StoredFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function create(): View
    {
        return view('files.create', [
            'extensions' => config('filestorage.allowed_extensions'),
            'maxSizeBytes' => config('filestorage.max_size_kb') * 1024,
            'ttlHours' => config('filestorage.ttl_hours'),
        ]);
    }

    public function store(StoreFileRequest $request, FileUploader $uploader): JsonResponse
    {
        $file = $uploader->upload($request->file('file'));

        return StoredFileResource::make($file)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function index(): View
    {
        return view('files.index', [
            'files' => StoredFile::query()->orderByDesc('id')->paginate(20),
        ]);
    }

    public function download(StoredFile $file): StreamedResponse
    {
        $disk = Storage::disk(config('filestorage.disk'));

        abort_unless($disk->exists($file->path), Response::HTTP_NOT_FOUND);

        return $disk->download($file->path, $file->original_name);
    }

    public function destroy(Request $request, StoredFile $file, FileRemover $remover): Response|RedirectResponse
    {
        $remover->delete($file, DeletionReason::Manual);

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return redirect()
            ->route('files.index')
            ->with('status', "Файл «{$file->original_name}» удалён.");
    }
}
