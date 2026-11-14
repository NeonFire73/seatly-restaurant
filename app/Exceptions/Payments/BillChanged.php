<?php

namespace App\Exceptions\Payments;

/**
 * Поки гість обирав чайові, сума до сплати змінилася: офіціант додав страву або рахунок уже сплатили.
 * Гість має побачити нову суму, перш ніж платити.
 */
class BillChanged extends PaymentException {}
