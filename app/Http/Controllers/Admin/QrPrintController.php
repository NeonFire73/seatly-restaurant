<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Support\QrCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Сторінка друку табличок із QR-кодами (частина 2, п. 7.6). Це звичайна сторінка поза панеллю Filament:
 * без навігації й завжди світла, тому права перевіряються тут, а не засобами панелі.
 */
class QrPrintController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('filament.admin.auth.login'));
        }

        abort_unless($user->is_active && Gate::forUser($user)->allows('viewAny', DiningTable::class), 403);

        $ids = array_filter(explode(',', (string) $request->query('ids', '')), 'ctype_digit');

        $tables = DiningTable::query()
            ->with('hall')
            ->when($ids !== [], fn ($query) => $query->whereKey($ids))
            // Без вибору друкуються всі столики, що працюють
            ->when($ids === [], fn ($query) => $query->where('is_active', true))
            ->get()
            ->sortBy('number', SORT_NATURAL)
            ->values();

        return view('admin.qr-print', [
            'cards' => $tables->map(fn (DiningTable $table) => [
                'table' => $table,
                'svg' => QrCode::svg($table->qrUrl()),
            ]),
        ]);
    }
}
