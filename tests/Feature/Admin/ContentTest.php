<?php

use App\Enums\UserRole;
use App\Filament\Pages\Contacts;
use App\Filament\Resources\ContentBlocks\Pages\EditContentBlock;
use App\Models\ContentBlock;
use App\Models\GalleryImage;
use App\Models\User;
use App\Services\SiteContent;
use App\Support\Images;
use Database\Seeders\ContentSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(ContentSeeder::class);
});

it('відкриває розділ «Контент сайту» лише власникові', function (string $url) {
    foreach (UserRole::cases() as $role) {
        $response = $this->actingAs(User::factory()->role($role)->create())->get($url);

        if ($role === UserRole::Owner) {
            $response->assertOk();
        } else {
            $response->assertForbidden();
        }
    }
})->with(['/admin/content/blocks', '/admin/content/gallery', '/admin/content/pages', '/admin/content/seo', '/admin/content/contacts']);

it('не дає створювати чи видаляти блоки сторінок', function () {
    $owner = User::factory()->owner()->create();
    $block = ContentBlock::first();

    expect($owner->can('create', ContentBlock::class))->toBeFalse()
        ->and($owner->can('delete', $block))->toBeFalse()
        ->and($owner->can('update', $block))->toBeTrue();
});

it('зберігає зміну блоку й автора правки', function () {
    $this->actingAs($owner = User::factory()->owner()->create());
    $block = ContentBlock::where('key', 'home.hero')->first();

    livewire(EditContentBlock::class, ['record' => $block->getRouteKey()])
        ->fillForm(['title' => ['uk' => 'Оновлений заголовок', 'en' => 'Updated title']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($block->fresh()->getTranslation('title', 'en'))->toBe('Updated title')
        ->and($block->fresh()->updated_by)->toBe($owner->id)
        ->and(SiteContent::block('home.hero')->title)->toBe('Оновлений заголовок');
});

it('зберігає контакти закладу', function () {
    $this->actingAs(User::factory()->owner()->create());

    livewire(Contacts::class)
        ->fillForm(['phone' => '+380 67 111 22 33', 'address' => ['uk' => 'Нова адреса, 1', 'en' => 'New address, 1']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SiteContent::contacts()['phone'])->toBe('+380 67 111 22 33')
        ->and(SiteContent::address())->toBe('Нова адреса, 1');
});

it('створює WebP-копії фото й прибирає їх разом зі старим файлом', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->image('hall.jpg', 2000, 1500)->store('gallery', 'public');

    $photo = GalleryImage::create(['image' => $path, 'alt' => ['uk' => 'Зал']]);

    foreach (Images::WIDTHS as $width) {
        Storage::disk('public')->assertExists(Images::variantPath($path, $width));
    }

    $newPath = UploadedFile::fake()->image('new.jpg', 600, 400)->store('gallery', 'public');
    $photo->update(['image' => $newPath]);

    Storage::disk('public')->assertMissing($path);
    Storage::disk('public')->assertMissing(Images::variantPath($path, 480));
    // Вузьке фото не збільшується: лише одна копія
    Storage::disk('public')->assertExists(Images::variantPath($newPath, 480));
    Storage::disk('public')->assertMissing(Images::variantPath($newPath, 960));

    $photo->delete();

    Storage::disk('public')->assertMissing($newPath);
    Storage::disk('public')->assertMissing(Images::variantPath($newPath, 480));
});
