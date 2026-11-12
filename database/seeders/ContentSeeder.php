<?php

namespace Database\Seeders;

use App\Models\ContentBlock;
use App\Models\Page;
use App\Models\SeoEntry;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Демо-вміст сайту, щоб після встановлення жодна сторінка не була порожньою.
 * Фото додаються на етапі 8; верстка блоків працює й без них.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->blocks() as $key => $fields) {
            ContentBlock::updateOrCreate(['key' => $key], $fields + ['is_visible' => true]);
        }

        foreach ($this->pages() as $slug => $fields) {
            Page::updateOrCreate(['slug' => $slug], $fields);
        }

        foreach ($this->seo() as $pageKey => $fields) {
            SeoEntry::updateOrCreate(['page_key' => $pageKey], $fields);
        }

        Setting::put('contacts', [
            'address' => [
                'uk' => 'Набережне шосе, 2, Київ',
                'en' => '2 Naberezhne Highway, Kyiv',
            ],
            'phone' => '+380 44 000 00 00',
            'email' => 'hello@seatly.restaurant.ua',
            'lat' => 50.4565,
            'lng' => 30.5310,
            'socials' => [
                'instagram' => 'https://www.instagram.com/',
                'facebook' => 'https://www.facebook.com/',
                'telegram' => null,
            ],
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function blocks(): array
    {
        return [
            'home.hero' => [
                'title' => ['uk' => 'Ваш столик біля води', 'en' => 'Your table by the water'],
                'subtitle' => [
                    'uk' => 'Ресторан на набережній: оберіть місце на схемі залу, замовте страви заздалегідь і розрахуйтеся за QR-кодом.',
                    'en' => 'A riverside restaurant: pick your seat on the floor plan, pre-order your dishes and pay by QR code.',
                ],
                'button_label' => ['uk' => 'Забронювати столик', 'en' => 'Book a table'],
            ],
            'home.about' => [
                'title' => ['uk' => 'Про нас', 'en' => 'About us'],
                'body' => [
                    'uk' => 'Seatly — сучасна кухня з українськими продуктами й видом на Дніпро. Ми готуємо просто й точно, а бронювання й оплату зробили такими ж зручними, як у найкращих онлайн-сервісах.',
                    'en' => 'Seatly is modern cuisine with Ukrainian produce and a view of the Dnipro. We cook simply and precisely, and made booking and payment as easy as in the best online services.',
                ],
                'button_label' => ['uk' => 'Детальніше', 'en' => 'Learn more'],
            ],
            'home.how_it_works' => [
                'title' => ['uk' => 'Як це працює', 'en' => 'How it works'],
                'subtitle' => ['uk' => 'Три кроки — і жодного очікування.', 'en' => 'Three steps and no waiting.'],
            ],
            'home.step_book' => [
                'title' => ['uk' => 'Забронюйте', 'en' => 'Book'],
                'body' => [
                    'uk' => 'Оберіть дату, час і конкретний столик на схемі залу. Підтвердження прийде на пошту.',
                    'en' => 'Choose a date, time and the exact table on the floor plan. Confirmation arrives by email.',
                ],
            ],
            'home.step_preorder' => [
                'title' => ['uk' => 'Замовте заздалегідь', 'en' => 'Pre-order'],
                'body' => [
                    'uk' => 'Додайте страви до бронювання — кухня почне готувати так, щоб подати їх до вашого приходу.',
                    'en' => 'Add dishes to your booking and the kitchen will time them to be ready when you arrive.',
                ],
            ],
            'home.step_pay' => [
                'title' => ['uk' => 'Оплатіть за QR', 'en' => 'Pay by QR'],
                'body' => [
                    'uk' => 'Відскануйте код на столику, перегляньте рахунок, залиште чайові й оплатіть із телефона.',
                    'en' => 'Scan the code on your table, check the bill, leave a tip and pay from your phone.',
                ],
            ],
            'about.story' => [
                'title' => ['uk' => 'Наша історія', 'en' => 'Our story'],
                'subtitle' => ['uk' => 'Ресторан, у якому технології не заважають вечері.', 'en' => 'A restaurant where technology stays out of the way of dinner.'],
                'body' => [
                    'uk' => "Seatly відкрився на київській набережній як місце для неспішних вечерь і ділових обідів.\n\nМи хотіли, щоб гість не чекав ні на дзвінок, ні на офіціанта з терміналом: столик можна обрати самому, а рахунок оплатити тоді, коли зручно.",
                    'en' => "Seatly opened on the Kyiv riverside as a place for unhurried dinners and business lunches.\n\nWe wanted guests to wait neither for a phone call nor for a waiter with a card terminal: you choose your own table and pay the bill whenever it suits you.",
                ],
            ],
            'about.kitchen' => [
                'title' => ['uk' => 'Кухня', 'en' => 'The kitchen'],
                'body' => [
                    'uk' => 'Риба й морепродукти, сезонні овочі від фермерів Київщини, власна випічка. Меню змінюється разом із сезоном, а класика — борщ, вареники, сирники — лишається завжди.',
                    'en' => 'Fish and seafood, seasonal vegetables from Kyiv region farmers, our own bakery. The menu changes with the season, while the classics — borscht, varenyky, syrnyky — always stay.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function pages(): array
    {
        $demoUk = '<p><strong>Увага:</strong> це демонстраційний портфоліо-проєкт. Ресторан вигаданий, платежі проходять у тестовому режимі, справжні кошти не списуються.</p>';
        $demoEn = '<p><strong>Note:</strong> this is a demo portfolio project. The restaurant is fictional, payments run in test mode and no real money is charged.</p>';

        return [
            'privacy' => [
                'title' => ['uk' => 'Політика конфіденційності', 'en' => 'Privacy policy'],
                'body' => [
                    'uk' => $demoUk.'<h2>Які дані ми збираємо</h2><p>Під час бронювання — ім\'я, телефон і e-mail, щоб підтвердити візит і надіслати нагадування.</p><h2>Як ми їх використовуємо</h2><ul><li>для підтвердження й нагадування про бронювання;</li><li>для надсилання квитанцій про оплату.</li></ul><h2>Платежі</h2><p>Дані карток обробляє платіжний провайдер; сайт їх не отримує й не зберігає.</p><h2>Зв\'язок</h2><p>Питання щодо даних надсилайте на <a href="mailto:hello@seatly.restaurant.ua">hello@seatly.restaurant.ua</a>.</p>',
                    'en' => $demoEn.'<h2>What we collect</h2><p>When you book — your name, phone and email, to confirm the visit and send a reminder.</p><h2>How we use it</h2><ul><li>to confirm and remind you about the booking;</li><li>to send payment receipts.</li></ul><h2>Payments</h2><p>Card details are processed by the payment provider; the website never receives or stores them.</p><h2>Contact</h2><p>Send data questions to <a href="mailto:hello@seatly.restaurant.ua">hello@seatly.restaurant.ua</a>.</p>',
                ],
            ],
            'offer' => [
                'title' => ['uk' => 'Публічна оферта', 'en' => 'Terms of service'],
                'body' => [
                    'uk' => $demoUk.'<h2>1. Предмет</h2><p>Ресторан надає послуги бронювання столиків і попереднього замовлення страв через цей сайт.</p><h2>2. Бронювання</h2><p>Бронювання діє 15 хвилин після зазначеного часу. Безкоштовно скасувати попереднє замовлення можна не пізніше ніж за 6 годин до візиту.</p><h2>3. Оплата</h2><p>Оплата здійснюється онлайн через платіжного провайдера або на місці. Чайові добровільні й передаються офіціанту.</p><h2>4. Повернення коштів</h2><p>У разі вчасного скасування кошти повертаються на картку, з якої було здійснено оплату.</p>',
                    'en' => $demoEn.'<h2>1. Subject</h2><p>The restaurant provides table booking and dish pre-ordering services through this website.</p><h2>2. Bookings</h2><p>A booking is held for 15 minutes after the reserved time. A pre-order can be cancelled free of charge no later than 6 hours before the visit.</p><h2>3. Payment</h2><p>Payment is made online through the payment provider or on site. Tips are voluntary and go to the waiter.</p><h2>4. Refunds</h2><p>If cancelled in time, money is returned to the card used for payment.</p>',
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function seo(): array
    {
        return [
            'home' => [
                'title' => ['uk' => 'Seatly — ресторан на набережній у Києві', 'en' => 'Seatly — riverside restaurant in Kyiv'],
                'description' => ['uk' => 'Бронюйте столик на схемі залу, замовляйте страви заздалегідь і платіть за QR-кодом.', 'en' => 'Book a table on the floor plan, pre-order your dishes and pay by QR code.'],
            ],
            'menu' => [
                'title' => ['uk' => 'Меню — Seatly', 'en' => 'Menu — Seatly'],
                'description' => ['uk' => 'Закуски, супи, основні страви, десерти й напої з цінами та алергенами.', 'en' => 'Starters, soups, mains, desserts and drinks with prices and allergens.'],
            ],
            'booking' => [
                'title' => ['uk' => 'Бронювання столика — Seatly', 'en' => 'Book a table — Seatly'],
                'description' => ['uk' => 'Оберіть дату, час і столик онлайн.', 'en' => 'Choose a date, time and table online.'],
            ],
            'about' => [
                'title' => ['uk' => 'Про ресторан — Seatly', 'en' => 'About — Seatly'],
                'description' => ['uk' => 'Історія й кухня ресторану Seatly на київській набережній.', 'en' => 'The story and cuisine of Seatly on the Kyiv riverside.'],
            ],
            'contacts' => [
                'title' => ['uk' => 'Контакти — Seatly', 'en' => 'Contacts — Seatly'],
                'description' => ['uk' => 'Адреса, години роботи й телефон ресторану Seatly.', 'en' => 'Address, opening hours and phone of Seatly restaurant.'],
            ],
        ];
    }
}
