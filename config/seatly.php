<?php

return [

    // Назва в заголовках вкладок і og:site_name; APP_NAME довший і лишається для листів та службових місць
    'brand' => 'Seatly',

    'demo' => [
        // Спільний пароль для всіх демо-користувачів, яких створюють сидери
        'password' => env('DEMO_PASSWORD', 'password'),
        'email_domain' => env('DEMO_EMAIL_DOMAIN', 'seatly.test'),
    ],

    // Час у базі — UTC; години роботи, слоти й усе, що бачать люди, — у часовому поясі закладу
    'timezone' => 'Europe/Kyiv',

    /*
     * Типові параметри бронювання (частина 2, п. 3.1), усі тривалості — у хвилинах.
     * Значення із settings (ключ `booking`) перекривають ці; читати через BookingSettings.
     */
    'booking' => [
        'duration' => 120,
        'slot_step' => 30,
        // Перерва між бронюваннями одного столика: прибрати й накрити
        'buffer' => 15,
        'min_lead' => 60,
        'horizon_days' => 30,
        'last_slot_before_close' => 90,
        // Скільки столик лишається за гостем, поки він заповнює майстер бронювання
        'hold' => 10,
        'no_show_after' => 30,
        'max_guests' => 12,
    ],

    // Мови сайту й адмінки; перша — основна, з неї береться текст, якщо перекладу немає
    'locales' => [
        'uk' => 'Українська',
        'en' => 'English',
    ],

    /*
     * Блоки сторінок, вміст яких редагує власник (частина 2, п. 7.10).
     * Набір і порядок блоків задає верстка; тут лише назва для адмінки й поля, які блок використовує.
     * Новий блок додає розробник: сюди, у ContentSeeder і в шаблон сторінки.
     */
    'content_blocks' => [
        'home.hero' => ['label' => 'Головна: перший екран', 'fields' => ['title', 'subtitle', 'button_label', 'button_url', 'image']],
        'home.about' => ['label' => 'Головна: коротко про ресторан', 'fields' => ['title', 'body', 'button_label', 'button_url', 'image']],
        'home.how_it_works' => ['label' => 'Головна: «Як це працює», заголовок', 'fields' => ['title', 'subtitle']],
        'home.step_book' => ['label' => 'Головна: крок 1 «Забронюйте»', 'fields' => ['title', 'body']],
        'home.step_preorder' => ['label' => 'Головна: крок 2 «Замовте заздалегідь»', 'fields' => ['title', 'body']],
        'home.step_pay' => ['label' => 'Головна: крок 3 «Оплатіть за QR»', 'fields' => ['title', 'body']],
        'about.story' => ['label' => 'Про ресторан: історія', 'fields' => ['title', 'subtitle', 'body', 'image']],
        'about.kitchen' => ['label' => 'Про ресторан: кухня', 'fields' => ['title', 'body', 'image']],
    ],

    // Сторінки, для яких редагуються SEO-дані
    'seo_pages' => [
        'home' => 'Головна',
        'menu' => 'Меню',
        'booking' => 'Бронювання',
        'about' => 'Про ресторан',
        'contacts' => 'Контакти',
    ],

];
