<?php

declare(strict_types=1);

describe('Basic Assertions', function () {
    it('expects true to be true', function () {
        expect(true)->toBeTrue();
    });

    it('expects numbers to be comparable', function () {
        expect(5)->toBeGreaterThan(3)
            ->and(10)->toBeLessThan(20)
            ->and(5)->toEqual(5);
    });

    it('expects strings to match', function () {
        expect('Pest is awesome')
            ->toContain('Pest')
            ->toBeString();
    });

    it('expects arrays to have keys', function () {
        $array = ['name' => 'Laravel', 'version' => 13];

        expect($array)
            ->toHaveKey('name')
            ->toHaveKey('version');
    });
});
