<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Number;

class StoreFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * "mimes" checks the real content type, "extensions" the file name,
     * so a renamed .txt or a .pdf.exe is rejected.
     */
    public function rules(): array
    {
        $extensions = implode(',', config('filestorage.allowed_extensions'));

        return [
            'file' => [
                'required',
                'file',
                'max:'.config('filestorage.max_size_kb'),
                'mimes:'.$extensions,
                'extensions:'.$extensions,
            ],
        ];
    }

    public function messages(): array
    {
        $types = mb_strtoupper(implode(', ', config('filestorage.allowed_extensions')));
        $maxSize = Number::fileSize(config('filestorage.max_size_kb') * 1024);

        return [
            'file.required' => 'Выберите файл для загрузки.',
            'file.file' => 'Файл не был загружен.',
            'file.uploaded' => "Не удалось загрузить файл. Максимальный размер — {$maxSize}.",
            'file.max' => "Максимальный размер файла — {$maxSize}.",
            'file.mimes' => "Допустимы только файлы {$types}.",
            'file.extensions' => "Допустимы только файлы {$types}.",
        ];
    }
}
