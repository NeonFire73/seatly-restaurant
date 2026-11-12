<?php

use App\Support\Money;

it('переводить гривні в копійки без похибки float', function (string|int $input, int $expected) {
    expect(Money::toKopecks($input))->toBe($expected);
})->with([
    ['185', 18500],
    ['185.5', 18550],
    ['185,50', 18550],
    ['19.99', 1999],
    ['0.01', 1],
    ['1 250', 125000],
    [300, 30000],
]);

it('відхиляє некоректні суми', function (string $input) {
    Money::toKopecks($input);
})->throws(InvalidArgumentException::class)->with(['', 'abc', '-5', '1.999', '1.2.3']);

it('готує значення для поля форми', function () {
    expect(Money::toInput(12050))->toBe('120.50')
        ->and(Money::toInput(12000))->toBe('120')
        ->and(Money::toInput(5))->toBe('0.05')
        ->and(Money::toInput(null))->toBeNull();
});

it('форматує суму для показу відповідно до мови', function () {
    expect(Money::format(125050, 'uk'))->toBe('1 250,50 ₴')
        ->and(Money::format(18500, 'uk'))->toBe('185 ₴')
        ->and(Money::format(125050, 'en'))->toBe('₴1,250.50')
        ->and(Money::format(18500, 'en'))->toBe('₴185');
});
