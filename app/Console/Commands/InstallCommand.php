<?php

namespace App\Console\Commands;

use App\Support\Install\DatabaseProblem;
use App\Support\Install\EnvFile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use PDO;
use PDOException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Помічник встановлення (частина 2, п. 11.2): від свіжого клону до робочого сайту з демо-даними.
 * Запускається через `composer run setup`; повторний запуск нічого не затирає без запиту.
 *
 * Кроки, які залежать від .env (ключ, міграції, сидери), виконуються окремими процесами: цей процес
 * прочитав конфігурацію ще до того, як .env було створено чи змінено.
 */
#[Signature('seatly:install
    {--skip-build : не встановлювати пакети npm і не збирати фронтенд}
    {--fresh : видалити всі таблиці й наповнити базу демо-даними заново}')]
#[Description('Встановлює проєкт: .env, база даних, міграції, демо-дані, збірка фронтенду')]
class InstallCommand extends Command
{
    private const MIN_PHP = '8.3.0';

    // Розширення => навіщо воно проєкту
    private const EXTENSIONS = [
        'pdo_mysql' => 'з\'єднання з MySQL',
        'mbstring' => 'робота з кирилицею',
        'openssl' => 'шифрування й перевірка підпису вебхуків',
        'curl' => 'запити до платіжного провайдера',
        'fileinfo' => 'перевірка завантажених зображень',
        'gd' => 'зменшені копії зображень у WebP',
        'intl' => 'форматування дат і чисел в адмін-панелі',
        'dom' => 'шаблони листів і розбір XML',
    ];

    public function handle(): int
    {
        $this->components->info('Встановлення Seatly Restaurant');

        if (! $this->checkRequirements()) {
            return self::FAILURE;
        }

        $env = new EnvFile(base_path('.env'));

        if (! $this->prepareEnv($env)) {
            return self::FAILURE;
        }

        $hasTables = $this->configureDatabase($env);

        if ($hasTables === null) {
            return self::FAILURE;
        }

        if (! $this->migrate($hasTables)) {
            return self::FAILURE;
        }

        $this->artisan(['storage:link', '--force'], 'Посилання на сховище файлів (щоб відкривалися зображення)');

        if (! $this->option('skip-build') && ! $this->buildFrontend()) {
            return self::FAILURE;
        }

        $this->summary($env);

        return self::SUCCESS;
    }

    private function checkRequirements(): bool
    {
        $ok = true;

        if (version_compare(PHP_VERSION, self::MIN_PHP, '<')) {
            $this->components->error('Потрібен PHP '.self::MIN_PHP.' або новіший, а запущено '.PHP_VERSION.'. Оновіть PHP або оберіть новішу версію в налаштуваннях свого набору (OSPanel, Laragon, Herd).');
            $ok = false;
        }

        foreach (self::EXTENSIONS as $extension => $purpose) {
            if (! extension_loaded($extension)) {
                $this->components->error("У PHP вимкнено розширення {$extension} ({$purpose}). Відкрийте php.ini (шлях показує `php --ini`), приберіть крапку з комою перед `extension={$extension}` і запустіть команду ще раз.");
                $ok = false;
            }
        }

        if ($ok) {
            $this->components->twoColumnDetail('PHP '.PHP_VERSION.' і потрібні розширення', '<fg=green>є</>');
        }

        return $ok;
    }

    private function prepareEnv(EnvFile $env): bool
    {
        if ($env->exists()) {
            $this->components->twoColumnDetail('Файл .env', '<fg=green>уже є, лишаємо</>');
        } else {
            copy(base_path('.env.example'), base_path('.env'));
            $this->components->twoColumnDetail('Файл .env', '<fg=green>створено з .env.example</>');
        }

        if (blank($env->get('APP_KEY'))) {
            return $this->artisan(['key:generate', '--force'], 'Ключ застосунку');
        }

        return true;
    }

    /**
     * Запитує параметри бази, перевіряє з'єднання й за потреби створює базу.
     *
     * @return bool|null чи є в базі таблиці; null — підключитися не вдалося
     */
    private function configureDatabase(EnvFile $env): ?bool
    {
        $this->newLine();
        $this->line('  <options=bold>База даних.</> MySQL — окрема програма-сервер; застосунок підключається до неї за адресою, назвою бази, логіном і паролем.');

        if ($this->input->isInteractive()) {
            $this->line('  Натисніть Enter, щоб лишити запропоноване значення.');
        }

        $settings = [
            'DB_HOST' => $env->getOr('DB_HOST', '127.0.0.1'),
            'DB_PORT' => $env->getOr('DB_PORT', '3306'),
            'DB_DATABASE' => $env->getOr('DB_DATABASE', 'seatly'),
            'DB_USERNAME' => $env->getOr('DB_USERNAME', 'root'),
            'DB_PASSWORD' => (string) $env->get('DB_PASSWORD'),
        ];
        $connected = false;
        $hasTables = false;

        do {
            if ($this->input->isInteractive()) {
                $settings = $this->askDatabaseSettings($settings);
            }

            try {
                $hasTables = $this->connect($settings);
                $connected = true;
            } catch (PDOException $error) {
                $problem = DatabaseProblem::explain($error, $settings['DB_HOST'], $settings['DB_PORT'], $settings['DB_DATABASE'], $settings['DB_USERNAME']);

                $this->components->error($problem->title);
                $this->line("  <options=bold>Чому:</> {$problem->cause}");
                $this->line("  <options=bold>Що зробити:</> {$problem->fix}");
                $this->newLine();

                if (! $this->input->isInteractive() || ! confirm('Ввести параметри бази ще раз?')) {
                    return null;
                }
            }
        } while (! $connected);

        $env->set($settings);
        $this->components->twoColumnDetail("З'єднання з базою «{$settings['DB_DATABASE']}»", '<fg=green>працює, параметри записано в .env</>');

        return $hasTables;
    }

    /**
     * @param  array<string, string>  $settings
     * @return array<string, string>
     */
    private function askDatabaseSettings(array $settings): array
    {
        $settings['DB_HOST'] = text('Адреса сервера MySQL (в OSPanel 6 — назва модуля, наприклад MySQL-8.4)', default: $settings['DB_HOST'], required: true);
        $settings['DB_PORT'] = text('Порт', default: $settings['DB_PORT'], required: true, validate: function (string $value) {
            if (! ctype_digit($value)) {
                return 'Порт — це число, типово 3306.';
            }

            return null;
        });
        $settings['DB_DATABASE'] = text('Назва бази', default: $settings['DB_DATABASE'], required: true, validate: function (string $value) {
            if (preg_match('/^[A-Za-z0-9_]+$/', $value) !== 1) {
                return 'Лише латинські літери, цифри й підкреслення.';
            }

            return null;
        });
        $settings['DB_USERNAME'] = text('Користувач', default: $settings['DB_USERNAME'], required: true);
        $settings['DB_PASSWORD'] = password('Пароль (порожній — просто Enter)');

        return $settings;
    }

    /**
     * Підключається до сервера без назви бази: так «сервер недоступний» і «бази немає» — різні помилки,
     * а відсутню базу можна запропонувати створити.
     *
     * @param  array<string, string>  $settings
     * @return bool чи є в базі таблиці
     *
     * @throws PDOException
     */
    private function connect(array $settings): bool
    {
        $pdo = new PDO(
            "mysql:host={$settings['DB_HOST']};port={$settings['DB_PORT']};charset=utf8mb4",
            $settings['DB_USERNAME'],
            $settings['DB_PASSWORD'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
        );

        $database = $settings['DB_DATABASE'];
        $exists = $pdo->prepare('SELECT 1 FROM information_schema.schemata WHERE schema_name = ?');
        $exists->execute([$database]);

        if ($exists->fetchColumn() === false) {
            if ($this->input->isInteractive() && ! confirm("Бази «{$database}» ще немає. Створити її?")) {
                throw new PDOException("SQLSTATE[HY000] [1049] Unknown database '{$database}'");
            }

            // Назву перевірено на [A-Za-z0-9_], тож у запит вона підставляється безпечно
            $pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->components->twoColumnDetail("База «{$database}»", '<fg=green>створено</>');

            return false;
        }

        $tables = $pdo->prepare('SELECT count(*) FROM information_schema.tables WHERE table_schema = ?');
        $tables->execute([$database]);

        return (int) $tables->fetchColumn() > 0;
    }

    private function migrate(bool $hasTables): bool
    {
        if (! $hasTables) {
            return $this->artisan(['migrate', '--seed', '--force'], 'Таблиці й демо-дані');
        }

        $fresh = (bool) $this->option('fresh');

        if (! $fresh && $this->input->isInteractive()) {
            $fresh = confirm('У базі вже є таблиці. Видалити все й наповнити демо-даними заново?', default: false);
        }

        if ($fresh) {
            return $this->artisan(['migrate:fresh', '--seed', '--force'], 'Таблиці й демо-дані (з нуля)');
        }

        return $this->artisan(['migrate', '--force'], 'Нові міграції (наявні дані не чіпаємо)');
    }

    private function buildFrontend(): bool
    {
        if (! Process::run('npm --version')->successful()) {
            $this->components->error('Не знайдено npm. Встановіть Node.js (https://nodejs.org), відкрийте нове вікно термінала й запустіть команду ще раз — або додайте --skip-build і зберіть фронтенд пізніше командами `npm install` і `npm run build`.');

            return false;
        }

        return $this->runStep('npm install --ignore-scripts --no-audit --no-fund', 'Пакети npm')
            && $this->runStep('npm run build', 'Збірка стилів і скриптів');
    }

    private function summary(EnvFile $env): void
    {
        $url = rtrim((string) $env->get('APP_URL'), '/');
        $domain = $env->getOr('DEMO_EMAIL_DOMAIN', 'seatly.test');
        $demoPassword = $env->getOr('DEMO_PASSWORD', 'password');

        $this->newLine();
        $this->components->info('Готово. Запустіть `composer run dev` і відкрийте сайт.');
        $this->components->twoColumnDetail('Сайт', $url);
        $this->components->twoColumnDetail('Адмін-панель', "{$url}/admin");
        $this->newLine();
        $this->line("  Демо-користувачі (пароль для всіх — <options=bold>{$demoPassword}</>):");

        foreach (['owner' => 'власник', 'manager' => 'менеджер', 'waiter' => 'офіціант', 'kitchen' => 'кухня'] as $login => $role) {
            $this->components->twoColumnDetail("{$login}@{$domain}", $role);
        }

        $this->newLine();
        $this->line('  Якщо сайт відкривається за іншою адресою (наприклад, http://localhost:8000), запишіть її в APP_URL у файлі .env.');
    }

    /**
     * @param  list<string>  $arguments
     */
    private function artisan(array $arguments, string $title): bool
    {
        return $this->runStep([PHP_BINARY, 'artisan', ...$arguments], $title);
    }

    /**
     * @param  string|list<string>  $command
     */
    private function runStep(string|array $command, string $title): bool
    {
        $result = null;

        $this->components->task($title, function () use ($command, &$result) {
            $result = Process::path(base_path())->timeout(900)->run($command);

            return $result->successful();
        });

        if ($result !== null && $result->failed()) {
            $this->line(trim($result->output().PHP_EOL.$result->errorOutput()));
            $this->components->error("Крок «{$title}» не вдався — подробиці вище. Частий випадок описано в README, розділ «Типові проблеми».");

            return false;
        }

        return true;
    }
}
