<?php

use App\Support\Install\DatabaseProblem;
use App\Support\Install\EnvFile;

beforeEach(function () {
    $this->path = tempnam(sys_get_temp_dir(), 'seatly-env');
    file_put_contents($this->path, "# База даних\nDB_HOST=127.0.0.1\nDB_PASSWORD=\nAPP_NAME=\"Seatly Restaurant\"\n# кінець\n");
    $this->env = new EnvFile($this->path);
});

afterEach(function () {
    @unlink($this->path);
});

// ---------- Файл .env (частина 2, п. 11.2) ----------

it('читає значення з .env разом із лапками', function () {
    expect($this->env->get('DB_HOST'))->toBe('127.0.0.1')
        ->and($this->env->get('APP_NAME'))->toBe('Seatly Restaurant')
        ->and($this->env->get('DB_PASSWORD'))->toBe('')
        ->and($this->env->get('MISSING'))->toBeNull()
        ->and($this->env->getOr('DB_PASSWORD', 'типове'))->toBe('типове');
});

it('змінює лише потрібні рядки .env і зберігає коментарі', function () {
    $this->env->set(['DB_HOST' => 'MySQL-8.4', 'DB_PORT' => '3307']);

    expect(file_get_contents($this->path))
        ->toContain("# База даних\nDB_HOST=MySQL-8.4\nDB_PASSWORD=\n")
        ->toContain('# кінець')
        ->toContain('DB_PORT=3307')
        ->and($this->env->get('DB_PORT'))->toBe('3307');
});

it('зберігає пароль зі службовими символами без спотворень', function (string $password) {
    $this->env->set(['DB_PASSWORD' => $password]);

    expect($this->env->get('DB_PASSWORD'))->toBe($password)
        // Те саме значення має прочитати й сам Laravel
        ->and(Dotenv\Dotenv::parse(file_get_contents($this->path))['DB_PASSWORD'])->toBe($password);
})->with(['p@ss word', 'a#b"c', 'ціна $100 \\1', 'simple-1']);

// ---------- Пояснення помилок бази (частина 2, п. 11.4) ----------

it('пояснює помилку бази людською мовою', function (string $message, string $title) {
    $problem = DatabaseProblem::explain(new PDOException($message), 'db.local', '3306', 'seatly', 'root');

    expect($problem->title)->toContain($title)
        ->and($problem->cause)->not->toBeEmpty()
        ->and($problem->fix)->not->toBeEmpty();
})->with([
    'сервер не запущено' => ['SQLSTATE[HY000] [2002] No connection could be made because the target machine actively refused it', 'Не вдалося з\'єднатися з MySQL за адресою db.local:3306'],
    'невідома адреса' => ['SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo for MySQL-8.4 failed', 'Сервер з адресою «db.local» не знайдено'],
    'пароль' => ['SQLSTATE[HY000] [1045] Access denied for user \'root\'@\'localhost\' (using password: YES)', 'не прийняв логін або пароль'],
    'бази немає' => ['SQLSTATE[HY000] [1049] Unknown database \'seatly\'', 'Бази «seatly» не існує'],
    'немає прав' => ['SQLSTATE[HY000] [1044] Access denied for user \'app\'@\'%\' to database \'seatly\'', 'не має доступу до бази'],
    'немає драйвера' => ['could not find driver', 'вимкнено розширення pdo_mysql'],
    'щось інше' => ['SQLSTATE[HY000] [2054] The server requested authentication method unknown to the client', 'Не вдалося підключитися до бази даних'],
]);
