<?php

return [
    'title' => 'Payment',

    'invoice' => [
        'destination' => 'Payment for order no. :number, table :table',
        'bill_part' => 'Payment for order no. :number',
        'tip' => 'Tip',
        'unit' => 'pcs',
    ],

    'errors' => [
        'order_closed' => 'This order is already closed.',
        'nothing_due' => 'The bill has already been paid.',
        'bill_changed' => 'The bill has changed. Please check the amount and try again.',
        'tip_too_big' => 'The tip is too large for this bill.',
        'tip_invalid' => 'Enter the tip as a number, no more than :max.',
        'in_progress' => 'Someone is paying this bill right now. Wait a minute and refresh the page.',
        'gateway' => 'We could not open the payment. Try again or call your waiter.',
        'too_many' => 'Too many attempts. Please wait a minute.',
    ],

    'result' => [
        'pending_title' => 'Checking your payment…',
        'pending_text' => 'This takes a few seconds. The page will update by itself.',
        'unfinished_title' => 'Payment not completed',
        'unfinished_text' => 'You came back from the payment page without paying. The bill is still waiting.',
        'continue' => 'Continue payment',
        'success_title' => 'Payment received',
        'success_text' => 'Thank you! We hope to see you again.',
        'failure_title' => 'Payment failed',
        'failure_text' => 'The bank declined the payment and no money was taken. Try again or use another card.',
        'expired_title' => 'Payment time ran out',
        'expired_text' => 'The invoice is no longer valid and no money was taken. Please start the payment again.',
        'refunded_title' => 'Payment refunded',
        'refunded_text' => 'The payment was cancelled; the money will return to your card within a few days.',
        'retry' => 'Try again',
        'back' => 'Back to the bill',
        'receipt' => 'Download receipt',
        'bill' => 'Bill',
        'tip' => 'Tip',
        'total' => 'Paid',
        'to_pay' => 'To pay',
    ],

    'receipt' => [
        'title' => 'Receipt',
        'order' => 'Order no. :number',
        'table' => 'Table :number',
        'paid_at' => 'Paid',
        'payment' => 'Payment',
        'items' => 'Order items',
        'bill_paid' => 'Paid towards the bill',
        'tip' => 'Tip',
        'total' => 'Total',
        'print' => 'Print or save as PDF',
        'note' => 'Portfolio project: this is a test payment and the receipt is not a fiscal document.',
    ],

    'fake' => [
        'title' => 'Test payment page',
        'text' => 'This page stands in for the bank: no money is taken. Choose how the payment ends.',
        'amount' => 'To pay',
        'success' => 'Pay successfully',
        'failure' => 'Decline the payment',
        'leave' => 'Go back without paying',
    ],
];
