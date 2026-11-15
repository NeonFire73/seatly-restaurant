<x-layouts::app seo="menu" :schema="\App\Support\StructuredData::menu()">
    <div class="container-site pb-6 pt-12">
        <h1 class="text-4xl font-bold tracking-tight">{{ __('site.menu.title') }}</h1>
    </div>

    <livewire:menu-browser />
</x-layouts::app>
