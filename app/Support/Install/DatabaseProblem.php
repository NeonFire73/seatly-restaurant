<?php

namespace App\Support\Install;

use Throwable;

/**
 * Помилка з'єднання з базою людською мовою (частина 2, п. 11.4): що сталося, чому і що зробити.
 * Та сама таблиця наведена в README, розділ «База даних».
 */
final class DatabaseProblem
{
    public function __construct(
        public readonly string $title,
        public readonly string $cause,
        public readonly string $fix,
    ) {}

    public static function explain(Throwable $error, string $host, string $port, string $database, string $user): self
    {
        $message = $error->getMessage();

        if (str_contains($message, 'could not find driver')) {
            return new self(
                'У PHP вимкнено розширення pdo_mysql',
                'Без нього PHP не вміє розмовляти з MySQL.',
                'Відкрийте php.ini (його шлях показує `php --ini`), приберіть крапку з комою перед рядком `extension=pdo_mysql` і запустіть команду ще раз.',
            );
        }

        if (str_contains($message, '[1045]')) {
            return new self(
                'MySQL не прийняв логін або пароль',
                "Сервер працює, але користувача «{$user}» з таким паролем не знає.",
                'Перевірте DB_USERNAME і DB_PASSWORD. У OSPanel, Laragon і XAMPP типовий користувач — root із порожнім паролем.',
            );
        }

        if (str_contains($message, '[1049]')) {
            return new self(
                "Бази «{$database}» не існує",
                'Сервер і логін правильні, але бази з такою назвою ще не створено.',
                'Погодьтеся на пропозицію створити базу або створіть її самі (README, розділ «База даних») і перевірте DB_DATABASE.',
            );
        }

        if (str_contains($message, '[1044]')) {
            return new self(
                "Користувач «{$user}» не має доступу до бази «{$database}»",
                'Логін і пароль правильні, але цьому користувачеві не дозволено працювати з цією базою чи створювати її.',
                'Увійдіть під користувачем із потрібними правами (наприклад, root) або попросіть адміністратора сервера надати доступ.',
            );
        }

        if (str_contains($message, 'getaddrinfo') || str_contains($message, 'Name or service not known') || str_contains($message, 'No such host')) {
            return new self(
                "Сервер з адресою «{$host}» не знайдено",
                'Такої назви комп\'ютер не знає. Найчастіше це адреса з чужого набору: наприклад, `MySQL-8.4` існує лише там, де запущено OSPanel 6.',
                'Вкажіть у DB_HOST `127.0.0.1`; в OSPanel 6 — назву модуля MySQL (наприклад, `MySQL-8.4`) і переконайтеся, що OSPanel запущено.',
            );
        }

        if (str_contains($message, '[2002]')) {
            return new self(
                "Не вдалося з'єднатися з MySQL за адресою {$host}:{$port}",
                'Сервер MySQL не запущено або в адресі чи порту помилка.',
                'Запустіть MySQL (у OSPanel, Laragon чи XAMPP — кнопкою запуску) і перевірте DB_HOST та DB_PORT; типовий порт — 3306.',
            );
        }

        return new self(
            'Не вдалося підключитися до бази даних',
            $message,
            'Перевірте DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME і DB_PASSWORD у файлі .env; підказки є в README, розділ «База даних».',
        );
    }
}
