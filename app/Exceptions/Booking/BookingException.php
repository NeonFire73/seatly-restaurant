<?php

namespace App\Exceptions\Booking;

use DomainException;

/**
 * Порушення правил бронювання. Повідомлення призначене для людини й показується в інтерфейсі.
 */
class BookingException extends DomainException {}
