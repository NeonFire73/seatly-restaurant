<?php

return [
    'title' => 'Book a table',

    'steps' => [
        'label' => 'Booking steps',
        'when' => 'Date & time',
        'table' => 'Table',
        'contacts' => 'Contacts',
        'confirm' => 'Confirmation',
        'of' => 'Step :current of :total',
    ],

    'when' => [
        'guests' => 'Number of guests',
        'fewer' => 'Fewer guests',
        'more' => 'More guests',
        'big_party' => 'For parties larger than :count, please call us — we will arrange tables side by side.',
        'date' => 'Date',
        'prev_month' => 'Previous month',
        'next_month' => 'Next month',
        'time' => 'Time',
        'pick_date' => 'Pick a date to see available times.',
        'no_slots' => 'No times left on this date. Please try another one.',
        'slot_taken' => 'no tables available',
        'duration' => 'The table is booked for :hours.',
    ],

    'table' => [
        'heading' => 'Choose a table',
        'halls' => 'Halls',
        'auto' => 'Pick for me',
        'plan' => 'Floor plan: :hall',
        'list' => 'Available tables as a list',
        'none' => 'No tables available in this hall at the chosen time.',
        'none_anywhere' => 'No tables left at this time. Please choose another time.',
        'name' => 'Table :number',
        'seats' => '{1} :count seat|[2,*] :count seats',
        'aria' => 'Table :number, :seats, :state',
        'legend' => 'Legend',
        'state' => [
            'free' => 'available',
            'busy' => 'taken',
            'unfit' => 'does not fit your party size',
            'selected' => 'selected',
        ],
        'hold' => 'We are holding this table for you for',
        'hold_expired' => 'The table hold has expired. Please choose a table again.',
    ],

    'contacts' => [
        'name' => 'Name',
        'phone' => 'Phone',
        'phone_hint' => 'Format: +380 XX XXX XX XX',
        'email' => 'E-mail',
        'email_hint' => 'We will send the confirmation and a link to your booking.',
        'comment' => 'Comment',
        'comment_hint' => 'High chair, birthday, allergies — anything we should know.',
        'optional' => 'optional',
        'consent' => 'I agree to the <a href=":offer" target="_blank" rel="noopener">public offer</a> and the <a href=":privacy" target="_blank" rel="noopener">privacy policy</a>.',
        'consent_required' => 'Please accept the terms to book a table.',
        'name_invalid' => 'Enter your name: 2 to 80 characters.',
        'email_invalid' => 'Enter a valid e-mail address.',
        'comment_long' => 'The comment is too long: 500 characters at most.',
        'phone_invalid' => 'Enter a Ukrainian number in the format +380 XX XXX XX XX.',
    ],

    'summary' => [
        'heading' => 'Check your details',
        'code' => 'Booking number',
        'date' => 'Date',
        'time' => 'Time',
        'table' => 'Table',
        'guests' => 'Guests',
        'name' => 'Name',
        'phone' => 'Phone',
        'email' => 'E-mail',
        'comment' => 'Comment',
        'status' => 'Status',
        'free' => 'Booking is free of charge, no payment needed.',
    ],

    'actions' => [
        'next' => 'Next',
        'back' => 'Back',
        'book' => 'Book the table',
        'booking' => 'Booking…',
        'change' => 'Change',
    ],

    'done' => [
        'title' => 'Your table is booked',
        'text' => 'See you soon! The confirmation has been sent to :email.',
        'code' => 'Booking number',
        'calendar' => 'Add to calendar',
        'manage' => 'Booking page',
        'again' => 'Book another table',
    ],

    'errors' => [
        'slot' => 'This time can no longer be booked. Please choose another time.',
        'table_taken' => 'Sorry, this table has just been taken. Please choose another one — your details are saved.',
        'table_unfit' => 'This table does not fit your party size.',
        'pick_slot' => 'Choose a date and time.',
        'pick_table' => 'Choose a table.',
        'too_many' => 'Too many attempts. Please try again in a few minutes.',
    ],

    'page' => [
        'title' => 'Booking :code',
        'heading' => 'Your booking',
        'cancel' => 'Cancel booking',
        'cancel_title' => 'Cancel this booking?',
        'cancel_text' => 'The table will become available to other guests. The booking is free, so there is nothing to refund.',
        'cancel_confirm' => 'Yes, cancel',
        'cancel_keep' => 'Keep it',
        'cancelled' => 'The booking has been cancelled. We hope to see you another time.',
        'cannot_cancel' => 'This booking can no longer be cancelled online. Please call us if your plans have changed.',
        'calendar' => 'Add to calendar',
        'new' => 'New booking',
        'questions' => 'Any questions? Call us:',
    ],

    'status' => [
        'pending_payment' => 'Awaiting payment',
        'confirmed' => 'Confirmed',
        'seated' => 'You are at the table',
        'completed' => 'Visit completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'Visit did not take place',
        'expired' => 'Not paid in time',
    ],

    'mail' => [
        'greeting' => 'Hello :name!',
        'signature' => 'See you soon,',
        'confirmed' => [
            'subject' => 'Booking :code confirmed',
            'title' => 'Your table is booked',
            'intro' => 'Your booking is confirmed. Here are the details:',
            'button' => 'View booking',
            'outro' => 'You can view or cancel the booking on its page. A calendar event is attached to this e-mail.',
        ],
        'cancelled' => [
            'subject' => 'Booking :code cancelled',
            'title' => 'Booking cancelled',
            'intro' => 'Booking :code for :date at :time has been cancelled. If this is a mistake, book a table again or give us a call.',
            'button' => 'Book again',
        ],
    ],

    'ics' => [
        'summary' => 'Table at :brand',
        'description' => 'Booking :code. Table :table, guests: :guests.',
    ],
];
