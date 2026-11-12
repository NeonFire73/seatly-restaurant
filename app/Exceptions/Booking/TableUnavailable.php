<?php

namespace App\Exceptions\Booking;

/**
 * Столик на цей час уже зайнятий, вимкнений або не підходить за кількістю гостей.
 */
class TableUnavailable extends BookingException {}
