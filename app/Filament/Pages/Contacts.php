<?php

namespace App\Filament\Pages;

use App\Filament\Support\Translations;
use App\Models\Setting;
use App\Services\SiteContent;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Контакти закладу для сайту. Зберігаються в settings під ключем `contacts`.
 *
 * @property-read Schema $form
 */
class Contacts extends Page
{
    protected string $view = 'filament.pages.contacts';

    protected static ?string $slug = 'content/contacts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Контент сайту';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Контакти';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isOwner();
    }

    public function mount(): void
    {
        $this->form->fill(SiteContent::contacts());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(2)
            ->components([
                Section::make('Адреса й зв\'язок')
                    ->columnSpan(1)
                    ->schema([
                        Translations::tabs(fn (string $locale, bool $isMain) => [
                            TextInput::make("address.{$locale}")
                                ->label('Адреса')
                                ->required($isMain)
                                ->maxLength(200),
                        ]),
                        TextInput::make('phone')
                            ->label('Телефон')
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(255),
                    ]),
                Section::make('Мапа й соцмережі')
                    ->columnSpan(1)
                    ->columns(2)
                    ->schema([
                        TextInput::make('lat')
                            ->label('Широта')
                            ->helperText('Наприклад, 50.4565')
                            ->numeric()
                            ->minValue(-90)
                            ->maxValue(90),
                        TextInput::make('lng')
                            ->label('Довгота')
                            ->helperText('Наприклад, 30.5310')
                            ->numeric()
                            ->minValue(-180)
                            ->maxValue(180),
                        TextInput::make('socials.instagram')
                            ->label('Instagram')
                            ->url()
                            ->columnSpanFull(),
                        TextInput::make('socials.facebook')
                            ->label('Facebook')
                            ->url()
                            ->columnSpanFull(),
                        TextInput::make('socials.telegram')
                            ->label('Telegram')
                            ->url()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function save(): void
    {
        Setting::put('contacts', $this->form->getState());

        Notification::make()
            ->title('Контакти збережено')
            ->success()
            ->send();
    }
}
