<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationSource;
use Carbon\CarbonInterface;

/**
 * Дані нового бронювання — однакові для майстра на сайті й форми в адмінці.
 */
final readonly class ReservationData
{
    public function __construct(
        public int $tableId,
        public CarbonInterface $startsAt,
        public int $guests,
        public string $name,
        public string $phone,
        public ?string $email,
        public ?string $comment,
        public string $locale,
        public ReservationSource $source,
        public ?int $createdBy = null,
        // Ідентифікатор утримання столика з майстра: власне утримання не заважає бронюванню
        public ?string $holdOwner = null,
        /** @var array<int, int> попереднє замовлення: ідентифікатор страви => кількість */
        public array $preorderItems = [],
    ) {}
}
