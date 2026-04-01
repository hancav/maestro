<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('User Model', function () {
    it('has correct initials generation', function () {
        $user = User::factory()->create(['name' => 'John Doe']);
        expect($user->initials())->toBe('JD');

        $user2 = User::factory()->create(['name' => 'Maria Silva Santos']);
        expect($user2->initials())->toBe('MS');

        $user3 = User::factory()->create(['name' => 'Alice']);
        expect($user3->initials())->toBe('A');
    });

    it('hides sensitive attributes from JSON', function () {
        $user = User::factory()->create();
        $json = json_decode($user->toJson(), true);

        expect($json)->not->toHaveKey('password')
            ->and($json)->not->toHaveKey('two_factor_secret')
            ->and($json)->not->toHaveKey('two_factor_recovery_codes');
    });
});
