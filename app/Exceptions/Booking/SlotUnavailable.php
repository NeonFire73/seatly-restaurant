<?php

namespace App\Exceptions\Booking;

/**
 * На цей час бронювати не можна: заклад зачинено, час уже минув або він надто близько чи далеко.
 */
class SlotUnavailable extends BookingException {}
