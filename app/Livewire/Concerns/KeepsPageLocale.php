<?php

namespace App\Livewire\Concerns;

use App\Support\Localization;
use Livewire\Attributes\Locked;

/**
 * Запити Livewire йдуть на службову адресу без мовного префікса, тож мова сторінки
 * запам'ятовується під час першого показу й відновлюється на кожному наступному запиті.
 */
trait KeepsPageLocale
{
    #[Locked]
    public string $locale = '';

    public function mountKeepsPageLocale(): void
    {
        $this->locale = app()->getLocale();
    }

    public function hydrateKeepsPageLocale(): void
    {
        if (Localization::isSupported($this->locale)) {
            app()->setLocale($this->locale);
        }
    }
}
