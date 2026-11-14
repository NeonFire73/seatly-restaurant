<?php

namespace App\Exceptions\Orders;

use DomainException;

/**
 * Порушення правил роботи із замовленням. Повідомлення призначене для людини й показується в інтерфейсі.
 */
class OrderException extends DomainException {}
