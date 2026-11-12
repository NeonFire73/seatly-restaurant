<?php

namespace App\Models\Concerns;

use App\Services\SiteContent;

/**
 * Вміст сайту кешується; будь-яка зміна запису скидає кеш, тож правки з адмінки видно одразу.
 */
trait FlushesSiteContent
{
    public static function bootFlushesSiteContent(): void
    {
        static::saved(fn () => SiteContent::flush());
        static::deleted(fn () => SiteContent::flush());
    }
}
