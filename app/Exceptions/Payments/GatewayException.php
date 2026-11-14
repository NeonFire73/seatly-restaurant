<?php

namespace App\Exceptions\Payments;

use RuntimeException;

/**
 * Платіжний провайдер недоступний або відповів помилкою. Повідомлення технічне: воно йде в журнал
 * і в адмінку, гостеві показується загальний текст.
 */
class GatewayException extends RuntimeException {}
