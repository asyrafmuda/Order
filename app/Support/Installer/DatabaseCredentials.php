<?php

namespace App\Support\Installer;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

/**
 * The MySQL / MariaDB details typed into the installer, and the checks run
 * on them before anything is written.
 */
final readonly class DatabaseCredentials
{
    public function __construct(
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        public string $password,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            host: trim((string) ($input['host'] ?? 'localhost')),
            port: (int) ($input['port'] ?? 3306),
            database: trim((string) ($input['database'] ?? '')),
            username: trim((string) ($input['username'] ?? '')),
            password: (string) ($input['password'] ?? ''),
        );
    }

    /**
     * @return array<string, string|int>
     */
    public function toArray(): array
    {
        return ['host' => $this->host, 'port' => $this->port, 'database' => $this->database, 'username' => $this->username, 'password' => $this->password];
    }

    /**
     * A connection with these details. The "mysql" connection also becomes the
     * app's default for the rest of the request, which is what installing
     * needs; checking uses a separate one and leaves the app alone.
     */
    public function connect(string $name = 'mysql'): Connection
    {
        config([
            "database.connections.{$name}" => [
                ...config('database.connections.mysql', []),
                'driver' => 'mysql',
                'url' => null,
                'host' => $this->host,
                'port' => $this->port,
                'database' => $this->database,
                'username' => $this->username,
                'password' => $this->password,
                'options' => [PDO::ATTR_TIMEOUT => 5],
            ],
        ]);

        if ($name === 'mysql') {
            config(['database.default' => 'mysql']);
        }

        DB::purge($name);

        return DB::connection($name);
    }

    /**
     * Connect and check the server. Returns what was found on success, with
     * the state of the database:
     *  - empty: nothing there yet
     *  - ours: a run of this installer that stopped part-way (carry on)
     *  - existing: a finished shop (offer to reconnect to it)
     *  - foreign: tables from some other program
     *
     * @return array{server: string, state: string}
     *
     * @throws RuntimeException with a message for the person installing
     */
    public function check(): array
    {
        try {
            $connection = $this->connect('installer_check');
            $connection->getPdo();
        } catch (Throwable $exception) {
            throw new RuntimeException($this->explain($exception->getMessage()), previous: $exception);
        }

        $version = (string) $connection->selectOne('select version() as v')->v;
        $mariadb = stripos($version, 'mariadb') !== false;
        $number = preg_match('/\d+\.\d+\.\d+/', $version, $match) ? $match[0] : '0.0.0';
        $minimum = (string) config($mariadb ? 'installer.mariadb' : 'installer.mysql');

        if (version_compare($number, $minimum, '<')) {
            throw new RuntimeException(($mariadb ? 'MariaDB' : 'MySQL')." {$number} is too old. Version {$minimum} or newer is needed.");
        }

        $tables = array_map(fn ($row) => (string) array_values((array) $row)[0], $connection->select('show tables'));

        $state = match (true) {
            $tables === [] => 'empty',
            ! in_array('migrations', $tables, true) => 'foreign',
            in_array('users', $tables, true) && in_array('outlets', $tables, true)
                && $connection->table('users')->where('role', 'admin')->exists() => 'existing',
            default => 'ours',
        };

        $connection->disconnect();

        return ['server' => ($mariadb ? 'MariaDB ' : 'MySQL ').$number, 'state' => $state];
    }

    private function explain(string $error): string
    {
        return match (true) {
            str_contains($error, '[1045]') => 'The username or password was not accepted. In cPanel → MySQL Databases, check the user and that it is added to the database with ALL PRIVILEGES.',
            str_contains($error, '[1044]') => 'This user has no access to that database. In cPanel → MySQL Databases, add the user to the database with ALL PRIVILEGES.',
            str_contains($error, '[1049]') => 'That database does not exist. Create it first in cPanel → MySQL Databases. Its full name usually starts with your cPanel username, e.g. myuser_order.',
            str_contains($error, '[2002]'), str_contains($error, 'getaddrinfo') => 'Could not reach the database server. On most shared hosting the host is "localhost".',
            default => 'Could not connect to the database: '.$error,
        };
    }
}
