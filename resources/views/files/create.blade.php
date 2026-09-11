@extends('layouts.app')

@section('title', 'Upload files')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="h3 mb-2">Upload files</h1>
            <p class="text-body-secondary mb-4">
                {{ mb_strtoupper(implode(', ', $extensions)) }} up to {{ \Illuminate\Support\Number::fileSize($maxSizeBytes) }}.
                Files are kept for {{ $ttlHours }} {{ \Illuminate\Support\Str::plural('hour', $ttlHours) }} and then deleted automatically.
            </p>

            <div id="dropzone" class="dropzone card card-body text-center py-5 mb-4"
                 data-upload-url="{{ route('files.store') }}"
                 data-max-size="{{ $maxSizeBytes }}"
                 data-extensions="{{ implode(',', $extensions) }}">
                <i class="bi bi-cloud-arrow-up display-4 text-primary"></i>
                <p class="mb-3">Drag and drop files here or</p>
                <div>
                    <label class="btn btn-primary" for="file-input">
                        <i class="bi bi-folder2-open"></i> Choose files
                    </label>
                </div>
                <input type="file" id="file-input" class="d-none" multiple
                       accept="{{ collect($extensions)->map(fn ($ext) => '.'.$ext)->implode(',') }}">
            </div>

            <ul id="upload-list" class="list-group mb-3"></ul>

            <a href="{{ route('files.index') }}">Go to the file list <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/upload.js') }}"></script>
@endpush
