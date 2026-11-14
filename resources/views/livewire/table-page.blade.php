@php
    use App\Enums\OrderItemStatus;
    use App\Services\Payments\TipSettings;
    use App\Support\Money;

    $table = $this->table;
    $order = $this->order;
    $pollSeconds = (int) config('seatly.orders.bill_poll_seconds');
@endphp

{{-- Нижній відступ лишає місце під закріплену панель із сумою й кнопкою виклику --}}
<div class="container-site max-w-2xl pb-32 pt-4">
    @if ($table === null)
        {{-- Токен перевипустили, поки сторінка була відкрита --}}
        <div class="rounded-card bg-muted p-6 text-center">
            <x-site.icon name="qr" class="mx-auto size-10 text-ink-soft" />
            <h2 class="mt-3 text-lg font-semibold">{{ __('table.stale.title') }}</h2>
            <p class="mt-1 text-sm text-ink-soft">{{ __('table.stale.text') }}</p>
        </div>
    @else
        <div role="tablist" aria-label="{{ __('table.tabs.label') }}" class="grid grid-cols-2 gap-1 rounded-full bg-muted p-1">
            @foreach (\App\Livewire\TablePage::TABS as $name)
                <button
                    type="button"
                    role="tab"
                    id="tab-{{ $name }}"
                    aria-controls="panel-{{ $name }}"
                    aria-selected="{{ var_export($tab === $name, true) }}"
                    wire:click="showTab('{{ $name }}')"
                    class="min-h-11 rounded-full text-sm font-semibold text-ink-soft transition-colors aria-selected:bg-surface aria-selected:text-ink aria-selected:shadow-sm"
                >{{ __('table.tabs.'.$name) }}</button>
            @endforeach
        </div>

        @if ($tab === 'bill')
            {{-- Опитування йде лише на вкладці рахунку: меню не змінюється щохвилини, а сторінка має лишатися легкою --}}
            <section id="panel-bill" role="tabpanel" aria-labelledby="tab-bill" wire:poll.{{ $pollSeconds }}s class="mt-6">
                @if ($order === null)
                    <div class="rounded-card bg-muted p-6 text-center">
                        <x-site.icon name="utensils" class="mx-auto size-10 text-ink-soft" />
                        <h2 class="mt-3 text-lg font-semibold">{{ __('table.bill.empty_title') }}</h2>
                        <p class="mt-1 text-sm text-ink-soft">{{ __('table.bill.empty_text') }}</p>
                    </div>
                @else
                    <h2 class="text-lg font-semibold">{{ __('table.bill.heading', ['number' => $order->number]) }}</h2>

                    @if ($order->items->isEmpty())
                        <p class="mt-3 text-sm text-ink-soft">{{ __('table.bill.no_items') }}</p>
                    @else
                        <ul class="mt-3 divide-y divide-line border-y border-line">
                            @foreach ($order->items as $item)
                                @php
                                    // Гостеві байдуже, готова страва на кухні чи ще ні: він бачить «готується», доки її не подали
                                    $guestStatus = match ($item->status) {
                                        OrderItemStatus::New => 'accepted',
                                        OrderItemStatus::Served => 'served',
                                        default => 'cooking',
                                    };
                                @endphp
                                <li wire:key="item-{{ $item->id }}" class="flex items-start justify-between gap-4 py-3">
                                    <div class="min-w-0">
                                        <p class="font-medium break-words">
                                            {{ $item->name }}
                                            @if ($item->quantity > 1)
                                                <span class="whitespace-nowrap text-ink-soft">× {{ $item->quantity }}</span>
                                            @endif
                                        </p>
                                        @if ($guestStatus === 'served')
                                            <x-badge tone="outline" class="mt-1">{{ __('table.item_status.served') }}</x-badge>
                                        @else
                                            <x-badge class="mt-1">{{ __('table.item_status.'.$guestStatus) }}</x-badge>
                                        @endif
                                    </div>
                                    <p class="shrink-0 font-semibold">{{ Money::format($item->total()) }}</p>
                                </li>
                            @endforeach
                        </ul>

                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-ink-soft">{{ __('table.bill.subtotal') }}</dt>
                                <dd class="font-medium">{{ Money::format($order->subtotal) }}</dd>
                            </div>
                            @if ($order->paid_total > 0)
                                <div class="flex justify-between gap-4">
                                    <dt class="text-ink-soft">{{ __('table.bill.paid') }}</dt>
                                    <dd class="font-medium">{{ Money::format($order->paid_total) }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between gap-4 border-t border-line pt-2 text-base">
                                <dt class="font-semibold">{{ __('table.bill.due') }}</dt>
                                <dd class="font-bold">{{ Money::format($order->amountDue()) }}</dd>
                            </div>
                        </dl>

                        @if ($order->subtotal > 0 && $order->amountDue() === 0)
                            <p class="mt-4 flex items-center gap-2 rounded-card bg-muted p-4 text-sm font-medium">
                                <x-site.icon name="check" class="size-5 shrink-0 text-success" />
                                {{ __('table.bill.paid_in_full') }}
                            </p>
                        @else
                            <p class="mt-4 text-sm text-ink-soft">{{ __('table.bill.pay_hint') }}</p>
                        @endif
                    @endif
                @endif
            </section>
        @else
            <section id="panel-menu" role="tabpanel" aria-labelledby="tab-menu" class="mt-4">
                <nav aria-label="{{ __('site.menu.categories') }}" class="-mx-4 overflow-x-auto px-4">
                    <ul class="flex gap-2">
                        @foreach ($this->categories as $category)
                            <li>
                                <a href="#category-{{ $category->slug }}" class="inline-flex min-h-11 items-center whitespace-nowrap rounded-full border border-line px-4 text-sm font-medium hover:bg-muted">{{ $category->name }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>

                {{-- Компактний список без фото: сторінка столика має важити якнайменше (частина 1, п. 7.3) --}}
                @foreach ($this->categories as $category)
                    <section wire:key="category-{{ $category->id }}" id="category-{{ $category->slug }}" class="mt-6 scroll-mt-20">
                        <h2 class="text-lg font-semibold">{{ $category->name }}</h2>
                        <ul class="mt-2 divide-y divide-line border-y border-line">
                            @foreach ($category->dishes as $dish)
                                <li wire:key="dish-{{ $dish->id }}" @class(['flex items-start justify-between gap-4 py-3', 'opacity-60' => ! $dish->is_available])>
                                    <div class="min-w-0">
                                        <p class="font-medium break-words">{{ $dish->name }}</p>
                                        @if ($dish->description)
                                            <p class="line-clamp-2 text-sm text-ink-soft">{{ $dish->description }}</p>
                                        @endif
                                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                            @if ($dish->weight && $dish->unit)
                                                <span class="mr-1 text-xs text-ink-soft">{{ $dish->weight }} {{ __('site.units.'.$dish->unit->value) }}</span>
                                            @endif
                                            @if (! $dish->is_available)
                                                <x-badge>{{ __('site.menu.unavailable') }}</x-badge>
                                            @endif
                                            @foreach ($dish->tags as $tag)
                                                <x-badge tone="outline">{{ __('site.tags.'.$tag->value) }}</x-badge>
                                            @endforeach
                                        </div>
                                    </div>
                                    <p class="shrink-0 font-semibold">{{ Money::format($dish->price) }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach

                @if ($this->categories->isEmpty())
                    <p class="mt-6 text-sm text-ink-soft">{{ __('table.menu_empty') }}</p>
                @endif
            </section>
        @endif

        {{-- Закріплена панель: сума до сплати, оплата й виклик офіціанта --}}
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface/95 pb-[env(safe-area-inset-bottom)] backdrop-blur">
            <div class="container-site flex max-w-2xl items-center justify-between gap-3 py-3">
                @if ($order !== null && $order->amountDue() > 0)
                    <div class="min-w-0">
                        <p class="text-xs text-ink-soft">{{ __('table.bill.due') }}</p>
                        <p class="text-lg font-bold leading-tight">{{ Money::format($order->amountDue()) }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        {{-- Поруч із головною кнопкою виклик офіціанта стискається до значка, підпис лишається для читача екрана --}}
                        <x-button variant="secondary" wire:click="callWaiter" wire:loading.attr="disabled" wire:target="callWaiter" class="w-11 !px-0" aria-label="{{ __('table.call.button') }}" title="{{ __('table.call.button') }}">
                            <x-site.icon name="bell" class="size-5" />
                        </x-button>
                        <x-button wire:click="openPay" wire:loading.attr="disabled" wire:target="openPay">{{ __('table.pay.button') }}</x-button>
                    </div>
                @else
                    <p class="min-w-0 text-sm text-ink-soft">{{ __('table.call.hint') }}</p>
                    <x-button wire:click="callWaiter" wire:loading.attr="disabled" wire:target="callWaiter" class="shrink-0">
                        <x-site.icon name="bell" class="size-4" />
                        {{ __('table.call.button') }}
                    </x-button>
                @endif
            </div>
        </div>

        @if ($paying && $order !== null)
            @php
                $due = $order->amountDue();
                $tip = $this->tipAmount;
                $tipLabel = '—';

                if ($tip !== null) {
                    $tipLabel = Money::format($tip);
                }

                $showWaiter = TipSettings::showWaiterName() && $order->waiter !== null;
            @endphp

            {{-- Панель оплати знизу (частина 1, п. 5.6). Опитування тримає суму актуальною й тоді, коли відкрито вкладку меню --}}
            <div class="fixed inset-0 z-40 flex items-end justify-center bg-ink/50 sm:items-center" wire:poll.{{ $pollSeconds }}s x-data x-on:keydown.escape.window="$wire.closePay()">
                <button type="button" class="absolute inset-0 cursor-default" wire:click="closePay" aria-label="{{ __('table.pay.close') }}" tabindex="-1"></button>

                <section role="dialog" aria-modal="true" aria-labelledby="pay-title" class="relative max-h-[92vh] w-full max-w-md overflow-y-auto rounded-t-card bg-surface p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] shadow-xl sm:rounded-card">
                    <div class="flex items-start justify-between gap-4">
                        <h2 id="pay-title" class="text-xl font-bold">{{ __('table.pay.title') }}</h2>
                        <button type="button" wire:click="closePay" class="-mr-2 -mt-2 inline-flex size-11 items-center justify-center rounded-full hover:bg-muted" aria-label="{{ __('table.pay.close') }}">
                            <x-site.icon name="x" class="size-5" />
                        </button>
                    </div>

                    <fieldset class="mt-4">
                        <legend class="text-sm font-semibold">{{ __('table.pay.tip') }}</legend>
                        @if ($showWaiter)
                            <p class="mt-0.5 text-sm text-ink-soft">{{ __('table.pay.waiter', ['name' => $order->waiter->name]) }}</p>
                        @endif

                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach (TipSettings::percents() as $percent)
                                <button
                                    type="button"
                                    wire:key="tip-{{ $percent }}"
                                    wire:click="selectTip({{ $percent }})"
                                    aria-pressed="{{ var_export(! $customTipMode && $tipPercent === $percent, true) }}"
                                    class="min-h-11 min-w-14 rounded-full border border-line px-4 text-sm font-semibold transition-colors hover:bg-muted aria-pressed:border-brand aria-pressed:bg-brand aria-pressed:text-on-brand"
                                >{{ $percent }} %</button>
                            @endforeach
                            <button
                                type="button"
                                wire:click="useCustomTip"
                                aria-pressed="{{ var_export($customTipMode, true) }}"
                                class="min-h-11 rounded-full border border-line px-4 text-sm font-semibold transition-colors hover:bg-muted aria-pressed:border-brand aria-pressed:bg-brand aria-pressed:text-on-brand"
                            >{{ __('table.pay.custom') }}</button>
                        </div>

                        @if ($customTipMode)
                            <div class="mt-3">
                                <label for="custom-tip" class="text-sm font-medium">{{ __('table.pay.custom_label') }}</label>
                                <input
                                    id="custom-tip"
                                    type="text"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    placeholder="0"
                                    wire:model.live.debounce.400ms="customTip"
                                    @error('customTip') aria-invalid="true" aria-describedby="custom-tip-error" @enderror
                                    class="mt-1 block min-h-11 w-full rounded-xl border border-line bg-surface px-4 text-base aria-[invalid=true]:border-danger"
                                >
                                @error('customTip')
                                    <p id="custom-tip-error" class="mt-1 text-sm text-danger">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    </fieldset>

                    <dl class="mt-5 space-y-2 border-t border-line pt-4 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-soft">{{ __('table.pay.bill') }}</dt>
                            <dd class="font-medium">{{ Money::format($due) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-soft">{{ __('table.pay.tip') }}</dt>
                            <dd class="font-medium">{{ $tipLabel }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-t border-line pt-2 text-base">
                            <dt class="font-semibold">{{ __('table.pay.total') }}</dt>
                            <dd class="font-bold">{{ Money::format($due + (int) $tip) }}</dd>
                        </div>
                    </dl>

                    <x-button size="lg" wire:click="pay" wire:loading.attr="disabled" wire:target="pay" class="mt-5 w-full">
                        {{ __('table.pay.proceed') }}
                    </x-button>
                    <p class="mt-3 text-center text-xs text-ink-soft">{{ __('table.pay.note') }}</p>
                </section>
            </div>
        @endif
    @endif
</div>
