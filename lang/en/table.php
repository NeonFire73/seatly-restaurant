<?php

return [
    'title' => 'Table :number',

    'tabs' => [
        'label' => 'Table page sections',
        'menu' => 'Menu',
        'bill' => 'Bill',
    ],

    'menu_empty' => 'The menu is being updated. Please ask your waiter.',

    'bill' => [
        'heading' => 'Order no. :number',
        'empty_title' => 'No order yet',
        'empty_text' => 'Your waiter will take the order and it will appear here.',
        'no_items' => 'Your waiter is still adding dishes — they will appear here in a moment.',
        'subtotal' => 'Total',
        'paid' => 'Already paid',
        'due' => 'Amount due',
        'pay_hint' => 'Pay online with the button below, or call your waiter to pay by cash or card.',
        'paid_in_full' => 'The bill is paid. Thank you!',
    ],

    'pay' => [
        'button' => 'Pay',
        'title' => 'Pay the bill',
        'close' => 'Close payment',
        'tip' => 'Tip',
        'waiter' => 'Your waiter is :name',
        'custom' => 'Custom',
        'custom_label' => 'Tip, UAH',
        'bill' => 'Bill',
        'total' => 'Total',
        'proceed' => 'Continue to payment',
        'note' => 'You will pay on the bank\'s secure page. Payments are in test mode.',
    ],

    'item_status' => [
        'accepted' => 'Accepted',
        'cooking' => 'Being prepared',
        'served' => 'Served',
    ],

    'call' => [
        'button' => 'Call the waiter',
        'hint' => 'Need a hand?',
        'sent' => 'The waiter has been called and will be with you shortly.',
        'already' => 'The waiter already knows and is on the way.',
    ],

    'stale' => [
        'title' => 'This QR code is out of date',
        'text' => 'Scan the code on your table again or ask a waiter.',
    ],
];
