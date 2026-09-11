@extends('layouts.app')

@section('title', 'Загрузка файлов')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="h3 mb-2">Загрузка файлов</h1>
            <p class="text-body-secondary mb-4">
                {{ mb_strtoupper(implode(', ', $extensions)) }} до {{ \Illuminate\Support\Number::fileSize($maxSizeBytes) }}.
                Файлы хранятся {{ $ttlHours }} ч., после чего удаляются автоматически.
            </p>

            <div id="dropzone" class="dropzone card card-body text-center py-5 mb-4"
                 data-upload-url="{{ route('files.store') }}"
                 data-max-size="{{ $maxSizeBytes }}"
                 data-extensions="{{ implode(',', $extensions) }}">
                <i class="bi bi-cloud-arrow-up display-4 text-primary"></i>
                <p class="mb-3">Перетащите файлы сюда или</p>
                <div>
                    <label class="btn btn-primary" for="file-input">
                        <i class="bi bi-folder2-open"></i> Выберите файлы
                    </label>
                </div>
                <input type="file" id="file-input" class="d-none" multiple
                       accept="{{ collect($extensions)->map(fn ($ext) => '.'.$ext)->implode(',') }}">
            </div>

            <ul id="upload-list" class="list-group mb-3"></ul>

            <a href="{{ route('files.index') }}">Перейти к списку файлов <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/upload.js') }}"></script>
@endpush
