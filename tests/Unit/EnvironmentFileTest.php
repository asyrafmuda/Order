<?php

use App\Support\Installer\EnvironmentFile;
use Dotenv\Dotenv;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/env-test-'.bin2hex(random_bytes(4));
    mkdir($this->dir);
});

afterEach(function () {
    foreach (array_diff(scandir($this->dir) ?: [], ['.', '..']) as $file) {
        unlink($this->dir.'/'.$file);
    }

    @rmdir($this->dir);
});

it('writes values that read back exactly, whatever characters they hold', function (string $value) {
    $file = new EnvironmentFile($this->dir.'/.env');
    $file->write("APP_NAME=Old\n# a comment stays\nDB_PASSWORD=old\n");

    $file->set(['DB_PASSWORD' => $value, 'APP_NAME' => 'Kedai Burger Ali', 'NEW_KEY' => true]);

    $parsed = Dotenv::parse((string) file_get_contents($this->dir.'/.env'));

    expect($parsed['DB_PASSWORD'])->toBe($value)
        ->and($file->get('DB_PASSWORD'))->toBe($value)
        ->and($parsed['APP_NAME'])->toBe('Kedai Burger Ali')
        ->and($parsed['NEW_KEY'])->toBe('true')
        ->and(file_get_contents($this->dir.'/.env'))->toContain('# a comment stays');
})->with([
    'plain' => ['s3cret'],
    'spaces' => ['pass word'],
    'quotes' => ['p"a\'ss'],
    'dollar' => ['pa$$w0rd${HOME}'],
    'hash' => ['abc#def'],
    'backslash' => ['a\\b\\"c'],
    'empty' => [''],
]);

it('creates .env from .env.example with a key on the first web request', function () {
    file_put_contents($this->dir.'/.env.example', "APP_KEY=\nAPP_ENV=local\nAPP_DEBUG=true\nSESSION_DRIVER=database\n");

    EnvironmentFile::prepare($this->dir, web: true);

    $env = Dotenv::parse((string) file_get_contents($this->dir.'/.env'));

    expect($env['APP_KEY'])->toStartWith('base64:')
        ->and(strlen(base64_decode(substr($env['APP_KEY'], 7))))->toBe(32)
        ->and($env['APP_ENV'])->toBe('production')
        ->and($env['APP_DEBUG'])->toBe('false')
        ->and($env['SESSION_DRIVER'])->toBe('file')
        ->and($env['SESSION_COOKIE'])->toBe('order_session');

    // Once it exists, it is left alone.
    $before = file_get_contents($this->dir.'/.env');
    EnvironmentFile::prepare($this->dir, web: true);
    expect(file_get_contents($this->dir.'/.env'))->toBe($before);
});

it('fills in a missing key in a .env copied by hand', function () {
    file_put_contents($this->dir.'/.env', "APP_KEY=\nDB_DATABASE=shop\n");

    EnvironmentFile::prepare($this->dir, web: true);

    expect((new EnvironmentFile($this->dir.'/.env'))->get('APP_KEY'))->toStartWith('base64:')
        ->and((new EnvironmentFile($this->dir.'/.env'))->get('DB_DATABASE'))->toBe('shop');
});

it('does nothing on the command line', function () {
    file_put_contents($this->dir.'/.env.example', "APP_KEY=\n");

    EnvironmentFile::prepare($this->dir, web: false);

    expect(file_exists($this->dir.'/.env'))->toBeFalse();
});
