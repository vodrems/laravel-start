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
            'file.required' => 'Please choose a file to upload.',
            'file.file' => 'The file was not uploaded.',
            'file.uploaded' => "The file could not be uploaded. The maximum size is {$maxSize}.",
            'file.max' => "The maximum file size is {$maxSize}.",
            'file.mimes' => "Only {$types} files are allowed.",
            'file.extensions' => "Only {$types} files are allowed.",
        ];
    }
}
