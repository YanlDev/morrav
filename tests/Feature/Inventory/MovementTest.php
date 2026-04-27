<?php

use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\User;
use Carbon\CarbonImmutable;

it('belongs to a creator user', function () {
    $user = User::factory()->create();
    $movement = Movement::factory()->create(['created_by' => $user->id]);

    expect($movement->creator->id)->toBe($user->id);
});

it('has many lines', function () {
    $movement = Movement::factory()->create();
    MovementLine::factory()->count(3)->create(['movement_id' => $movement->id]);

    expect($movement->lines)->toHaveCount(3);
});

it('filters confirmed movements via scope', function () {
    Movement::factory()->count(2)->confirmed()->create();
    Movement::factory()->create(['status' => 'draft']);
    Movement::factory()->voided()->create();

    expect(Movement::confirmed()->count())->toBe(2);
});

it('filters by type via scope', function () {
    Movement::factory()->count(2)->inbound()->create();
    Movement::factory()->outbound()->create();

    expect(Movement::ofType('inbound')->count())->toBe(2)
        ->and(Movement::ofType('outbound')->count())->toBe(1);
});

it('casts timestamps to Carbon instances', function () {
    $movement = Movement::factory()->confirmed()->create();

    expect($movement->occurred_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($movement->confirmed_at)->toBeInstanceOf(CarbonImmutable::class);
});
