<?php

use App\Support\Money;
use App\Support\Phone;

it('formats sen as ringgit', function () {
    expect(Money::format(2880))->toBe('RM 28.80')
        ->and(Money::format(120000))->toBe('RM 1,200.00')
        ->and(Money::format(5, withSymbol: false))->toBe('0.05');
});

it('converts typed ringgit amounts to sen without float drift', function () {
    expect(Money::toSen('28.80'))->toBe(2880)
        ->and(Money::toSen(19.99))->toBe(1999)
        ->and(Money::toSen('0.1'))->toBe(10);
});

it('extracts inclusive and exclusive tax', function () {
    expect(Money::tax(10600, 6, inclusive: true))->toBe(600)
        ->and(Money::tax(10000, 6, inclusive: false))->toBe(600)
        ->and(Money::tax(10000, 0, inclusive: true))->toBe(0);
});

it('normalises Malaysian phone numbers', function (string $input, string $expected) {
    expect(Phone::normalize($input))->toBe($expected);
})->with([
    ['012-345 6789', '60123456789'],
    ['+60 12 345 6789', '60123456789'],
    ['60123456789', '60123456789'],
    ['0123456789', '60123456789'],
    ['123456789', '60123456789'],
    ['+60 012-345 6789', '60123456789'],
    ['011-2345 6789', '601123456789'],
]);

it('tells Malaysian mobile numbers apart from everything else', function (string $input, bool $mobile) {
    expect(Phone::isMobile($input))->toBe($mobile);
})->with([
    ['012-345 6789', true],
    ['0123456789', true],
    ['60123456789', true],
    ['+60 11-2345 6789', true],
    ['03-1234 5678', false],
    ['01234567', false],
    ['0123456789012', false],
    ['', false],
]);

it('masks the middle of a phone number', function () {
    expect(Phone::mask('60123456789'))->toBe('6012••••789');
});
