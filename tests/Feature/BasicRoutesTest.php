<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Dashboard', function () {
    it('redirects to login if not authenticated', function () {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    });

    it('is accessible to authenticated users with verified email', function () {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
    });

    it('displays dashboard content for authenticated user', function () {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertViewIs('dashboard')
            ->assertSee('Dashboard');
    });
});

describe('Home Page', function () {
    it('displays welcome page', function () {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertViewIs('welcome');
    });

    it('is accessible without authentication', function () {
        $response = $this->get('/');

        expect($response->status())->toBe(200);
    });
});

