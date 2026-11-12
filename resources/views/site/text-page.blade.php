{{-- Текст очищено від небезпечної розмітки під час збереження (App\Models\Page), тому виводиться як HTML --}}
<x-layouts::app :title="$page->title">
    <article class="container-site max-w-3xl pt-12">
        <h1 class="text-4xl font-bold tracking-tight">{{ $page->title }}</h1>
        <div class="prose-content mt-8">
            {!! $page->body !!}
        </div>
    </article>
</x-layouts::app>
