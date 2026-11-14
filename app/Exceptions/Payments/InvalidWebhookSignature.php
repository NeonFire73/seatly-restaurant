<?php

namespace App\Exceptions\Payments;

/**
 * Вебхук без чинного підпису: дані з нього не обробляються (частина 2, розділ 9).
 */
class InvalidWebhookSignature extends GatewayException {}
