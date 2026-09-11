<x-mail::message>
# File deleted

The file **{{ $file->originalName }}** has been removed from storage.

<x-mail::table>
| Field    | Value |
|:---------|:------|
| Reason   | {{ $file->reason->label() }} |
| Size     | {{ \Illuminate\Support\Number::fileSize($file->size, maxPrecision: 1) }} |
| Type     | {{ $file->mimeType }} |
| Uploaded | {{ $file->uploadedAt->format('d M Y, H:i T') }} |
| Deleted  | {{ $file->deletedAt->format('d M Y, H:i T') }} |
</x-mail::table>

{{ config('app.name') }}
</x-mail::message>
