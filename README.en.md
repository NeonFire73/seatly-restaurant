# Seatly Restaurant

[Українська версія](README.md)

A web application for a fictional restaurant that covers the whole service cycle: a guest books a table online and may pre-order dishes, scans the QR code on the table to see the menu and the bill, pays online and leaves a tip — while the staff run all of this from an admin panel.

> A portfolio project. The restaurant is fictional and payments are test-only: by default a fake payment page stands in for the bank and no money is charged.

Laravel 13 · Filament 5 · Livewire 4 · Tailwind CSS 4 · MySQL 8 · Pest

## 1. Features

**For guests — no sign-up**

- Table booking in a few steps: date and time, a table on the floor plan, contact details, optional pre-order, confirmation. A confirmation e-mail with a calendar event, a booking page behind a personal link, cancellation.
- Pre-ordering dishes for the time of the visit with online payment — while booking or later.
- A table page behind a QR code: menu, the bill with dish statuses, payment with a tip, a "Call the waiter" button.
- Two languages: Ukrainian and English.

**For staff — a role-based admin panel** (the panel itself is in Ukrainian)

- **Waiter:** the "Hall" page with table states, seating guests, orders, cash or card-terminal payments, own tips.
- **Kitchen:** a board with the "Now" queue and scheduled pre-orders.
- **Manager:** bookings and the schedule, menu, halls and tables with QR codes, payments and refunds, the tips report, working hours, a dashboard with revenue.
- **Owner:** everything the manager has, plus site content (texts, translations, images, SEO), staff and settings — including paid booking and its price.

**Under the hood**

- Payments through Monobank Acquiring behind a swappable gateway contract, signature-checked webhooks, refunds.
- Background jobs: guest reminders, releasing unpaid bookings, no-show marking, payment status reconciliation.
- 360+ automated tests, static analysis (Larastan), checks in GitHub Actions.

## 2. Walkthroughs by role

Three routes that take you through the project in ten minutes. Demo accounts and addresses are in [section 9](#9-sign-in-and-demo-accounts); the password for every demo user is `password`. Interface labels below are given as they appear on the Ukrainian site and in the admin panel.

### A guest books a table

1. Open the site and press "Забронювати" (Book), or go straight to `/en/booking` for the English wizard.
2. Pick the number of guests, a date and a time, then a free table on the floor plan. Taken tables and tables of the wrong size are disabled.
3. Enter a name, phone and e-mail. On the dishes step you can add a pre-order or skip it.
4. Press "Book". If you added dishes, the button reads "Book and pay": a test payment page opens — choose "Оплатити успішно" (Pay successfully).
5. You should see a confirmation with the booking number and a link to the booking page, where the booking can be cancelled. By default the guest's e-mail is written to `storage/logs/laravel.log`.
6. Sign in to the admin panel as `manager@seatly.test`: the booking is under "Бронювання" (Bookings), and paid dishes are on the "Кухня" (Kitchen) page in the "До часу" (Scheduled) column.

### A waiter takes an order from seating to the kitchen

1. Sign in to the admin panel as `waiter@seatly.test` and open "Зал" (Hall).
2. Click a free table and press "Посадити без бронювання" (Seat without a booking) — or "Посадити" (Seat) next to one of today's bookings.
3. In the table panel find a dish, set the quantity and press "Додати" (Add).
4. In another window sign in as `kitchen@seatly.test`: the dish is already in the "Зараз" (Now) column. Press "Готується" (Cooking), then "Готово" (Ready) — the card leaves the kitchen board.
5. Back at the waiter's window the item is ready, and "Подано" (Served) completes it. Pages refresh on their own every 10 seconds.

### A guest pays by QR and leaves a tip

1. As `manager@seatly.test` open "Зали та столики" → "Столики" (Tables) and press "Показати QR" (Show QR) next to table 2 or 5 — the demo data already has open orders on them. Copy the address under the code and open it in a new tab (this is what a guest would get by pointing a camera at the code).
2. On the table page switch to the bill tab: items, their statuses and the amount due.
3. Press the pay button, choose a tip (0, 5, 10, 15 % or a custom amount) and proceed to payment.
4. On the test payment page choose "Оплатити успішно" (Pay successfully).
5. You should see the payment confirmation with a receipt. In the admin panel the payment is under "Платежі" (Payments), the table on the "Зал" page is marked as paid, and the tip is in the "Чайові" (Tips) report next to the waiter.

## 3. What is interesting in the code

The places where the project goes beyond CRUD. Each note takes 2–3 minutes to read: the task, why it is not trivial, the solution, the files, the tests. The notes are written in Ukrainian.

| Topic | What makes it hard | Note |
|---|---|---|
| Double booking | two guests take the same table and time at the same moment | [read](guide/01-podviine-broniuvannia.md) |
| Availability | gaps between bookings, closing after midnight, special days, time zones | [read](guide/02-dostupnist.md) |
| Payment gateway | the project must work both with the bank and with no token or internet at all | [read](guide/03-platizhnyi-shliuz.md) |
| Webhooks | they arrive twice, out of order, or never | [read](guide/04-vebkhuky.md) |
| Statuses | how to rule out a "seated" cancelled booking | [read](guide/05-statusy.md) |
| Refunds and tips | the sums must add up after any sequence of payments and refunds | [read](guide/06-povernennia-i-chaiovi.md) |
| Prepayment | a table is held for 15 minutes, and the money may arrive in the sixteenth | [read](guide/07-peredoplata.md) |

The index of all notes is [guide/README.md](guide/README.md).

## 4. Stack

| Layer | Technology |
|---|---|
| Language and framework | PHP 8.4, Laravel 13 |
| Admin panel | Filament 5 |
| Interactivity on the site | Livewire 4, Alpine.js |
| Styles and build | Tailwind CSS 4, Vite |
| Database | MySQL 8 |
| Queues, cache, sessions | the `database` driver — no Redis or other services |
| Payments | Monobank Acquiring (test mode) and a fake gateway |
| Tests and quality | Pest 4, Laravel Pint, Larastan (level 6), GitHub Actions |

---

The rest is a setup guide for those who want to run the project locally.

## 5. Requirements

| Software | Version | How to check | Where to get it |
|---|---|---|---|
| PHP | 8.3 or newer (the project is developed on 8.4) | `php -v` | https://www.php.net/downloads |
| Composer | 2.x | `composer -V` | https://getcomposer.org/download/ |
| Node.js | 22 or newer | `node -v` | https://nodejs.org |
| MySQL | 8.x | `mysql --version` | https://dev.mysql.com/downloads/ |

If you already use a bundle — OSPanel, Laragon, XAMPP or Herd — it ships PHP and MySQL, so you only need Composer and Node.js (Laragon and Herd include those too).

Required PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `gd`, `intl`, `dom`. You do not have to check them by hand: the installer tells you which one is missing and how to enable it.

## 6. Quick start

```bash
git clone https://github.com/NeonFire73/seatly-restaurant.git
cd seatly-restaurant
composer run setup
composer run dev
```

The site is then available at http://localhost:8000 and the admin panel at http://localhost:8000/admin.

The installer speaks Ukrainian. It asks five questions about the database — server address, port, database name, user, password — and offers a default for each; pressing Enter accepts it.

## 7. Step-by-step installation

**Step 1. Get the code.**

```bash
git clone https://github.com/NeonFire73/seatly-restaurant.git
```

```bash
cd seatly-restaurant
```

A `seatly-restaurant` folder appears; run every following command inside it.

**Step 2. Start MySQL.** In OSPanel, Laragon or XAMPP use the start button of the program itself. You do not have to create an empty database beforehand: the next step offers to do it.

**Step 3. Run the installer.**

```bash
composer run setup
```

The command downloads the PHP libraries and starts an assistant that:

1. checks the PHP version and extensions;
2. creates the `.env` settings file and the application key;
3. asks for database access — server address, port, database name, user and password. If you are not sure what to enter, see [section 8](#8-database);
4. tests the connection and, if the database does not exist yet, offers to create it;
5. creates the tables and fills them with demo data (menu, tables, bookings, a month of order history);
6. builds the styles and scripts.

At the end you should see "Готово" (Done) with the site and admin addresses and the list of demo users. If something goes wrong, the assistant explains the cause and what to do; the command is safe to run again — it never overwrites an existing `.env` or deletes data without asking.

**Step 4. Start the project.**

```bash
composer run dev
```

One command starts three processes at once: the web server, the queue worker (it sends e-mails and processes webhooks) and Vite. Keep this window open while you use the site. Open http://localhost:8000.

**Step 5 (optional). Background jobs.** Guest reminders, releasing unpaid bookings and no-show marking are run by the scheduler. To see them at work, start it in a separate window:

```bash
php artisan schedule:work
```

**If the site runs on its own domain** (OSPanel, Laragon, Herd), you do not need `composer run dev`: put the site address into `APP_URL` in `.env` and start the queue with `php artisan queue:work`.

## 8. Database

**What is going on.** MySQL is a separate server program that stores the data. The application connects to it using an address, a database name, a login and a password — all kept in the `.env` file. An empty database has to be created once; Laravel creates the tables in it by itself.

**How to create an empty database** — any of three ways:

- answer "yes" when the installer offers to create it — the easiest;
- in phpMyAdmin: the "Databases" tab → name `seatly`, collation `utf8mb4_unicode_ci` → "Create";
- with one terminal command:

```bash
mysql -u root -p -e "CREATE DATABASE seatly CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**What to put into `.env`.** The installer writes these values for you; by hand they are:

| Variable | Meaning | Where to get the value |
|---|---|---|
| `DB_HOST` | MySQL server address | usually `127.0.0.1`; in OSPanel 6 — the name of the MySQL module |
| `DB_PORT` | server port | `3306` by default |
| `DB_DATABASE` | database name | the one you created, e.g. `seatly` |
| `DB_USERNAME` | MySQL user | `root` in local bundles |
| `DB_PASSWORD` | that user's password | usually empty in local bundles |

Typical values for bundles:

| Bundle | `DB_HOST` | `DB_PORT` | `DB_USERNAME` | `DB_PASSWORD` |
|---|---|---|---|---|
| OSPanel 6 | the module name, e.g. `MySQL-8.4` | `3306` | `root` | empty |
| Laragon | `127.0.0.1` | `3306` | `root` | empty |
| XAMPP | `127.0.0.1` | `3306` | `root` | empty |

> The OSPanel 6 trap: the server address there is the name of the MySQL module (`MySQL-8.4`, `MySQL-8.0`), not `localhost`, and it only resolves while OSPanel is running. The OSPanel values were verified on a live installation; for Laragon and XAMPP their stock settings are listed.

**How to check that it works:**

```bash
php artisan db:show
```

You should get a table with the database name, the MySQL version and the list of tables (`reservations`, `orders`, `payments` and others). If you get an error instead, look it up below.

**Common database errors**

| What you see | Cause | What to do |
|---|---|---|
| `SQLSTATE[HY000] [2002]` — connection refused | the MySQL server is not running, or `DB_HOST` / `DB_PORT` is wrong | start MySQL, check the address and port |
| `SQLSTATE[HY000] [2002] ... getaddrinfo failed` | there is no server with that address (often `MySQL-8.4` without OSPanel running) | use `127.0.0.1` or start OSPanel |
| `SQLSTATE[HY000] [1045]` — access denied | wrong login or password | check `DB_USERNAME` and `DB_PASSWORD` |
| `SQLSTATE[HY000] [1049]` — unknown database | the database was not created, or its name is misspelled | create the database, check `DB_DATABASE` |
| `could not find driver` | the `pdo_mysql` PHP extension is disabled | enable it in `php.ini` (`php --ini` shows the path), open a new terminal window |
| changes in `.env` have no effect | Laravel is using a cached configuration | `php artisan config:clear` |
| table already exists / does not exist | migrations ran only partly | `php artisan migrate:fresh --seed` (deletes all data) |

The installer gives the same explanations when it cannot connect.

## 9. Sign-in and demo accounts

| What | Address |
|---|---|
| Site | http://localhost:8000 |
| English version | http://localhost:8000/en |
| Admin panel | http://localhost:8000/admin |

| Role | E-mail | What they see |
|---|---|---|
| Owner | `owner@seatly.test` | everything, including site content, staff and settings |
| Manager | `manager@seatly.test` | bookings, orders, payments, menu, tables, working hours |
| Waiter | `waiter@seatly.test`, `waiter2@seatly.test` | the hall, today's bookings, own orders and tips |
| Kitchen | `kitchen@seatly.test` | the kitchen board and dish availability |

The password for all of them is `password` (the `DEMO_PASSWORD` variable in `.env`, applied when the database is seeded). Two-factor authentication can be enabled in a staff member's profile.

## 10. How to try payments

**By default — the fake gateway** (`PAYMENT_GATEWAY=fake`). Instead of the bank's page a test page opens with three buttons: pay successfully, decline the payment, go back without paying. Nothing to configure: it works with no token and no internet, and all the code from creating a payment to closing the bill is the same as with the real bank.

**Monobank in test mode.** The bank sends webhooks to a public address, so you need a tunnel to your machine.

1. Get a test token at https://api.monobank.ua/ and put it into `MONOBANK_TOKEN`.
2. Start a tunnel, e.g. `cloudflared tunnel --url http://localhost:8000` or `ngrok http 8000`, and copy the address it gives you.
3. In `.env` set `PAYMENT_GATEWAY=monobank` and `MONOBANK_WEBHOOK_URL=https://<tunnel address>/webhooks/monobank`.
4. Run `php artisan config:clear`.

The author has not run a live payment with a Monobank test token: the integration was checked against the bank's documentation and is covered by tests on stubbed responses. Every webhook is visible to the owner in the admin panel under "Журнал вебхуків" (Webhook log).

## 11. Troubleshooting

| What I see | Why | What to do |
|---|---|---|
| `No application encryption key has been specified` | `APP_KEY` in `.env` is empty | `php artisan key:generate` |
| `Vite manifest not found` | styles and scripts are not built | `npm install`, then `npm run build` |
| images do not show | the storage link is missing | `php artisan storage:link` |
| error 419 after submitting a form | the page had been open for too long, or `APP_URL` does not match the address in the browser | reload the page; check `APP_URL` and run `php artisan config:clear` |
| e-mails do not arrive | by default they are written to a file; or the queue worker is not running | look for the e-mail in `storage/logs/laravel.log`; start `composer run dev` or `php artisan queue:work` |
| admin notifications (the bell) do not appear | the queue worker is not running | same as above |
| an unpaid booking does not disappear after 15 minutes | the scheduler is not running | `php artisan schedule:work` |
| some admin labels are in English | the Ukrainian translation of Filament is incomplete | this is expected |
| database errors | — | [section 8](#8-database) |

## 12. Useful commands

```bash
php artisan migrate:fresh --seed
```

Resets the database to the demo state: deletes all data and seeds it again.

```bash
composer run test
```

Runs all tests. They use in-memory SQLite and never touch your database.

```bash
vendor/bin/pint
```

Formats the code to the project style.

```bash
vendor/bin/phpstan analyse --memory-limit=1G
```

Static analysis (Larastan).

```bash
php artisan optimize:clear
```

Clears the configuration, route and view caches — the first thing to try when "changes have no effect".

```bash
php artisan seatly:install --fresh
```

Reinstalls from scratch with the same steps as `composer run setup`.

## 13. How the project is organised

| Directory | Contents |
|---|---|
| `app/Actions` | business operations, one class per operation (`CreateReservation`, `SeatReservation`, `StartPayment`, `RefundPayment`) |
| `app/Services` | availability calculation, payment gateways, reports, site content |
| `app/Models`, `app/Enums` | models and status enums with their transition rules |
| `app/Livewire` | interactive site pages: menu, booking wizard, table page, payment result |
| `app/Filament` | the admin panel: resources, the Hall, Kitchen and Settings pages, dashboard widgets |
| `app/Policies` | who can do what: role-based access rules |
| `app/Console/Commands` | background jobs and the installer |
| `resources/views`, `lang` | templates and interface texts in two languages |
| `tests/Feature` | tests, grouped the same way as the code |
| `guide` | engineering notes |

The main rule: there is no logic in controllers, Livewire components or Filament resources — they only call actions from `app/Actions`. That is why a booking made on the site and a booking a manager enters in the admin panel pass the same checks.

Main tables: `halls` → `tables` → `reservations` and `orders` → `order_items`; `payments` belong to an order, a booking or both; `dishes` are grouped into `menu_categories`; `users` are staff with roles; `settings`, `working_hours` and `special_days` hold the venue settings; `content_blocks`, `pages`, `gallery_images` and `seo_entries` hold the site content. Money is stored as integers in kopecks, time in UTC and shown in Kyiv time.

The most interesting parts are covered in the [engineering notes](guide/README.md).

## 14. Credits and licence

The code is released under the MIT licence. The restaurant's name, menu and texts are fictional. There are no photographs in the repository: the site blocks work without images, and you can upload your own in the admin panel under site content.
