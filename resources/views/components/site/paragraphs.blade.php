@props(['text'])

{{-- Текст блоку з адмінки: порожній рядок — новий абзац. Виводиться екранованим, HTML у звичайних полях не дозволено. --}}
@foreach (preg_split('/\R{2,}/', trim((string) $text)) as $paragraph)
    <p {{ $attributes }}>{!! nl2br(e($paragraph)) !!}</p>
@endforeach
