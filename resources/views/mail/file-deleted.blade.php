<x-mail::message>
# Файл удалён

Файл **{{ $file->originalName }}** удалён из хранилища.

<x-mail::table>
| Параметр | Значение |
|:---------|:---------|
| Причина  | {{ $file->reason->label() }} |
| Размер   | {{ \Illuminate\Support\Number::fileSize($file->size, maxPrecision: 1) }} |
| Тип      | {{ $file->mimeType }} |
| Загружен | {{ $file->uploadedAt->format('d.m.Y H:i T') }} |
| Удалён   | {{ $file->deletedAt->format('d.m.Y H:i T') }} |
</x-mail::table>

{{ config('app.name') }}
</x-mail::message>
