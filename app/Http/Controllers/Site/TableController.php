<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Support\Localization;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Сторінка столика, на яку веде QR-код (частина 1, п. 5.5). Невідомий чи перевипущений токен дає 404.
 */
class TableController extends Controller
{
    private const SESSION_KEY = 'table_locale_seen';

    public function __invoke(Request $request, string $token): View|RedirectResponse
    {
        $table = DiningTable::findByQrToken($token);

        abort_if($table === null, 404);

        // У QR-коді адреса без мовного префікса, тож під час першого відкриття сторінка сама переходить
        // на мову браузера. Далі мову визначає адреса: інакше перемикач мови повертав би гостя назад.
        if (! $request->session()->has(self::SESSION_KEY)) {
            $request->session()->put(self::SESSION_KEY, true);
            $preferred = $request->getPreferredLanguage(Localization::locales());

            if ($preferred !== null && $preferred !== app()->getLocale()) {
                return redirect()->to(locale_route('table.show', ['token' => $token], $preferred));
            }
        }

        return view('site.table', ['table' => $table]);
    }
}
