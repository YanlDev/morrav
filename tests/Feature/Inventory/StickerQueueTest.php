<?php

use App\Models\Family;
use App\Models\Product;
use App\Models\Sku;
use App\Models\Subfamily;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders the stickers index page', function () {
    $this->get(route('stickers.index'))
        ->assertOk()
        ->assertSeeLivewire('pages::stickers.index');
});

it('lists active SKUs and hides discontinued ones', function () {
    $active = Sku::factory()->create(['internal_code' => 'SKU-ACTIVE', 'status' => 'active']);
    $discontinued = Sku::factory()->create(['internal_code' => 'SKU-GONE', 'status' => 'discontinued']);

    Livewire::test('pages::stickers.index')
        ->assertSee('SKU-ACTIVE')
        ->assertDontSee('SKU-GONE');
});

it('search filters by SKU code, variant or product name', function () {
    $sku = Sku::factory()
        ->for(Product::factory()->state(['name' => 'Silla Milano']))
        ->create(['internal_code' => 'SKU-TARGET']);
    Sku::factory()->create(['internal_code' => 'SKU-OTHER']);

    Livewire::test('pages::stickers.index')
        ->set('search', 'Milano')
        ->assertSee('SKU-TARGET')
        ->assertDontSee('SKU-OTHER');
});

it('family filter narrows SKUs', function () {
    $famA = Family::factory()->create();
    $famB = Family::factory()->create();

    Sku::factory()
        ->for(Product::factory()->state(['family_id' => $famA->id]))
        ->create(['internal_code' => 'SKU-FA']);
    Sku::factory()
        ->for(Product::factory()->state(['family_id' => $famB->id]))
        ->create(['internal_code' => 'SKU-FB']);

    Livewire::test('pages::stickers.index')
        ->set('familyFilter', (string) $famA->id)
        ->assertSee('SKU-FA')
        ->assertDontSee('SKU-FB');
});

it('openAdd loads the selected SKU for the modal', function () {
    $sku = Sku::factory()->create();

    Livewire::test('pages::stickers.index')
        ->call('openAdd', $sku->id)
        ->assertSet('addingSkuId', $sku->id)
        ->assertSet('addingCopies', 1);
});

it('addToQueue validates copies > 0', function () {
    $sku = Sku::factory()->create();

    Livewire::test('pages::stickers.index')
        ->call('openAdd', $sku->id)
        ->set('addingCopies', 0)
        ->call('addToQueue')
        ->assertHasErrors(['addingCopies']);
});

it('addToQueue persists the SKU with its copies', function () {
    $sku = Sku::factory()->create();

    $component = Livewire::test('pages::stickers.index')
        ->call('openAdd', $sku->id)
        ->set('addingCopies', 5)
        ->call('addToQueue');

    expect($component->get('queue'))->toMatchArray([$sku->id => 5]);
});

it('re-adding the same SKU updates the copies instead of duplicating', function () {
    $sku = Sku::factory()->create();

    $component = Livewire::test('pages::stickers.index')
        ->call('openAdd', $sku->id)
        ->set('addingCopies', 3)
        ->call('addToQueue')
        ->call('openAdd', $sku->id)
        ->set('addingCopies', 10)
        ->call('addToQueue');

    $queue = $component->get('queue');
    expect($queue)->toHaveCount(1)
        ->and($queue[$sku->id])->toBe(10);
});

it('increment and decrement adjust queue copies', function () {
    $sku = Sku::factory()->create();

    $component = Livewire::test('pages::stickers.index')
        ->set('queue', [$sku->id => 3])
        ->call('incrementInQueue', $sku->id)
        ->assertSet('queue.'.$sku->id, 4)
        ->call('decrementInQueue', $sku->id)
        ->assertSet('queue.'.$sku->id, 3);
});

it('decrementing to zero removes the SKU from the queue', function () {
    $sku = Sku::factory()->create();

    $component = Livewire::test('pages::stickers.index')
        ->set('queue', [$sku->id => 1])
        ->call('decrementInQueue', $sku->id);

    expect($component->get('queue'))->toBeEmpty();
});

it('removeFromQueue drops the SKU', function () {
    $skuA = Sku::factory()->create();
    $skuB = Sku::factory()->create();

    $component = Livewire::test('pages::stickers.index')
        ->set('queue', [$skuA->id => 2, $skuB->id => 4])
        ->call('removeFromQueue', $skuA->id);

    $queue = $component->get('queue');
    expect($queue)->toHaveCount(1)
        ->and($queue[$skuB->id])->toBe(4);
});

it('clearQueue empties the queue', function () {
    $sku = Sku::factory()->create();

    $component = Livewire::test('pages::stickers.index')
        ->set('queue', [$sku->id => 5])
        ->call('clearQueue');

    expect($component->get('queue'))->toBeEmpty();
});

it('printUrl builds the correct items parameter from the queue', function () {
    $skuA = Sku::factory()->create();
    $skuB = Sku::factory()->create();

    $component = Livewire::test('pages::stickers.index')
        ->set('queue', [$skuA->id => 3, $skuB->id => 2]);

    $url = $component->invade()->printUrl();

    expect($url)->toContain('items=')
        ->and($url)->toContain($skuA->id.'x3')
        ->and($url)->toContain($skuB->id.'x2');
});

it('printUrl is null when the queue is empty', function () {
    expect(Livewire::test('pages::stickers.index')->invade()->printUrl())->toBeNull();
});

it('queueTotal sums all copies across SKUs', function () {
    $skuA = Sku::factory()->create();
    $skuB = Sku::factory()->create();

    $component = Livewire::test('pages::stickers.index')
        ->set('queue', [$skuA->id => 7, $skuB->id => 5]);

    expect($component->invade()->queueTotal())->toBe(12);
});

it('changing family clears subfamily filter', function () {
    $fam1 = Family::factory()->create();
    $fam2 = Family::factory()->create();
    $sub = Subfamily::factory()->create(['family_id' => $fam1->id]);

    Livewire::test('pages::stickers.index')
        ->set('familyFilter', (string) $fam1->id)
        ->set('subfamilyFilter', (string) $sub->id)
        ->set('familyFilter', (string) $fam2->id)
        ->assertSet('subfamilyFilter', '');
});
