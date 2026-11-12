<?php

use App\Models\ContentBlock;
use App\Models\Dish;
use App\Models\Page;
use App\Services\SiteContent;
use Database\Seeders\ContentSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\WorkingHourSeeder;

beforeEach(function () {
    $this->seed([ContentSeeder::class, MenuSeeder::class, WorkingHourSeeder::class]);
});

dataset('pages', ['/', '/menu', '/booking', '/about', '/contacts', '/privacy', '/offer']);

it('відкриває сторінку обома мовами', function (string $path) {
    $this->get($path)->assertOk()->assertSee('<html lang="uk"', false);
    $this->get('/en'.rtrim($path, '/'))->assertOk()->assertSee('<html lang="en"', false);
})->with('pages');

it('показує блоки головної з бази відповідною мовою', function () {
    $this->get('/')->assertSee('Ваш столик біля води')->assertSee('Як це працює');
    $this->get('/en')->assertSee('Your table by the water')->assertSee('How it works');
});

it('не виводить блок, вимкнений в адмінці', function () {
    ContentBlock::where('key', 'home.how_it_works')->update(['is_visible' => false]);
    SiteContent::flush();

    $this->get('/')->assertDontSee('Як це працює');
});

it('показує український текст, якщо англійського перекладу немає', function () {
    $block = ContentBlock::where('key', 'home.hero')->first();
    $block->setTranslation('title', 'en', '');
    $block->save();

    $this->get('/en')->assertSee('Ваш столик біля води');
});

it('одразу показує на сайті зміни з адмінки, попри кеш', function () {
    $this->get('/')->assertSee('Ваш столик біля води');

    $block = ContentBlock::where('key', 'home.hero')->first();
    $block->setTranslation('title', 'uk', 'Новий заголовок');
    $block->save();

    $this->get('/')->assertSee('Новий заголовок');
});

it('показує на головній лише популярні доступні страви', function () {
    $popular = Dish::where('is_popular', true)->first();
    $hidden = Dish::where('is_popular', true)->skip(1)->first();
    $hidden->update(['is_available' => false]);

    $this->get('/')->assertSee($popular->name)->assertDontSee($hidden->name);
});

it('вирізає небезпечну розмітку з текстових сторінок', function () {
    $page = Page::where('slug', 'offer')->first();
    $page->setTranslation('body', 'uk', '<p>Текст</p><script>alert(1)</script><a href="javascript:alert(1)" onclick="x()">посилання</a>');
    $page->save();

    expect($page->fresh()->getTranslation('body', 'uk'))
        ->toContain('<p>Текст</p>')
        ->not->toContain('<script')
        ->not->toContain('javascript:')
        ->not->toContain('onclick');
});

it('дає перемикач мови на ту саму сторінку', function () {
    $this->get('/menu')->assertSee('href="'.url('/en/menu').'"', false);
    $this->get('/en/menu')->assertSee('href="'.url('/menu').'"', false);
});

it('показує сторінку 404 мовою адреси', function () {
    $this->get('/nope')->assertNotFound()->assertSee('Сторінку не знайдено');
    $this->get('/en/nope')->assertNotFound()->assertSee('Page not found');
});

it('на кожній сторінці є позначка демо-проєкту', function () {
    $this->get('/contacts')->assertSee(__('site.demo_notice'));
});

it('закінчує заголовок вкладки однією назвою на всіх сторінках', function (string $path, int $status) {
    $this->get($path)->assertStatus($status)->assertSee('— '.config('seatly.brand').'</title>', false);
})->with([
    'меню (SEO з адмінки)' => ['/menu', 200],
    'оферта' => ['/offer', 200],
    'сторінка помилки' => ['/en/nope', 404],
]);
