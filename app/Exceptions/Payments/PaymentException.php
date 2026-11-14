<?php

namespace App\Exceptions\Payments;

use DomainException;

/**
 * Порушення правил оплати. Повідомлення призначене для людини й показується в інтерфейсі,
 * тож для гостя воно складається мовою сторінки.
 */
class PaymentException extends DomainException {}
