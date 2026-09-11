@extends('layouts.app')

@section('title', 'Files')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Uploaded files</h1>
        <a href="{{ route('files.create') }}" class="btn btn-primary">
            <i class="bi bi-upload"></i> Upload
        </a>
    </div>

    @if ($files->isEmpty())
        <div class="card card-body text-center text-body-secondary py-5">
            <p class="mb-0">No files yet. <a href="{{ route('files.create') }}">Upload the first one</a>.</p>
        </div>
    @else
        <div class="card">
            <div class="table-responsive">
                <table id="files-table" class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Size</th>
                            <th>Uploaded</th>
                            <th>Deleted in</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($files as $file)
                            <tr>
                                <td class="text-break">
                                    @if ($file->mime_type === 'application/pdf')
                                        <i class="bi bi-file-earmark-pdf text-danger"></i>
                                    @else
                                        <i class="bi bi-file-earmark-word text-primary"></i>
                                    @endif
                                    {{ $file->original_name }}
                                </td>
                                <td class="text-nowrap">{{ \Illuminate\Support\Number::fileSize($file->size, maxPrecision: 1) }}</td>
                                <td class="text-nowrap">
                                    <time class="js-local-time" datetime="{{ $file->created_at->toIso8601String() }}">
                                        {{ $file->created_at->format('d M Y, H:i T') }}
                                    </time>
                                </td>
                                <td class="text-nowrap">
                                    <time class="js-countdown" datetime="{{ $file->expires_at->toIso8601String() }}">
                                        {{ $file->expires_at->format('d M Y, H:i T') }}
                                    </time>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('files.download', $file) }}" class="btn btn-sm btn-outline-secondary" title="Download">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <form method="POST" action="{{ route('files.destroy', $file) }}"
                                          class="d-inline js-delete-form" data-name="{{ $file->original_name }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">
            {{ $files->links() }}
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/files.js') }}"></script>
@endpush
