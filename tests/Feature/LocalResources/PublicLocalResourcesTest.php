<?php

use App\Models\LocalResource;
use App\Models\ResourceList;

describe('public local resources page', function () {
    it('displays the local resources page', function () {
        $this->get(route('local-resources'))
            ->assertOk()
            ->assertSee('Local Resources');
    });

    it('shows published resource lists', function () {
        $publishedList = ResourceList::factory()->published()->create([
            'name' => 'Music Shops',
        ]);

        $this->get(route('local-resources'))
            ->assertOk()
            ->assertSee('Music Shops');
    });

    it('hides draft resource lists', function () {
        $draftList = ResourceList::factory()->draft()->create([
            'name' => 'Draft Music Category',
        ]);

        $this->get(route('local-resources'))
            ->assertOk()
            ->assertDontSee('Draft Music Category');
    });

    it('shows published resources within published lists', function () {
        $list = ResourceList::factory()->published()->create([
            'name' => 'Recording Studios',
        ]);

        $publishedResource = LocalResource::factory()->published()->forList($list)->create([
            'name' => 'Sound Wave Studios',
        ]);

        $this->get(route('local-resources'))
            ->assertOk()
            ->assertSee('Recording Studios')
            ->assertSee('Sound Wave Studios');
    });

    it('hides draft resources within published lists', function () {
        $list = ResourceList::factory()->published()->create([
            'name' => 'Music Teachers',
        ]);

        $draftResource = LocalResource::factory()->draft()->forList($list)->create([
            'name' => 'Draft Teacher Resource',
        ]);

        $this->get(route('local-resources'))
            ->assertOk()
            ->assertSee('Music Teachers')
            ->assertDontSee('Draft Teacher Resource');
    });

    it('displays resource contact information', function () {
        $list = ResourceList::factory()->published()->create();

        $resource = LocalResource::factory()->published()->forList($list)->create([
            'name' => 'Test Music Shop',
            'contact_name' => 'John Doe',
            'contact_email' => 'john@example.com',
            'contact_phone' => '541-555-1234',
            'address' => '123 Main St, Corvallis, OR',
            'website' => 'https://example.com',
        ]);

        $this->get(route('local-resources'))
            ->assertOk()
            ->assertSee('Test Music Shop')
            ->assertSee('John Doe')
            ->assertSee('john@example.com')
            ->assertSee('541-555-1234')
            ->assertSee('123 Main St, Corvallis, OR');
    });

    it('orders lists by display_order', function () {
        $secondList = ResourceList::factory()->published()->create([
            'name' => 'Second Category',
            'display_order' => 2,
        ]);

        $firstList = ResourceList::factory()->published()->create([
            'name' => 'First Category',
            'display_order' => 1,
        ]);

        $response = $this->get(route('local-resources'));
        $response->assertOk();

        // First Category should appear before Second Category
        $content = $response->getContent();
        $firstPosition = strpos($content, 'First Category');
        $secondPosition = strpos($content, 'Second Category');

        expect($firstPosition)->toBeLessThan($secondPosition);
    });

    it('orders resources by sort_order within a list', function () {
        $list = ResourceList::factory()->published()->create();

        $secondResource = LocalResource::factory()->published()->forList($list)->create([
            'name' => 'Second Resource',
            'sort_order' => 2,
        ]);

        $firstResource = LocalResource::factory()->published()->forList($list)->create([
            'name' => 'First Resource',
            'sort_order' => 1,
        ]);

        $response = $this->get(route('local-resources'));
        $response->assertOk();

        $content = $response->getContent();
        $firstPosition = strpos($content, 'First Resource');
        $secondPosition = strpos($content, 'Second Resource');

        expect($firstPosition)->toBeLessThan($secondPosition);
    });

    it('shows empty state when no lists exist', function () {
        $this->get(route('local-resources'))
            ->assertOk()
            ->assertSee('No Resources Available');
    });

    it('shows quick navigation when multiple lists exist', function () {
        ResourceList::factory()->published()->create(['name' => 'Music Shops', 'slug' => 'music-shops']);
        ResourceList::factory()->published()->create(['name' => 'Recording Studios', 'slug' => 'recording-studios']);

        $this->get(route('local-resources'))
            ->assertOk()
            ->assertSee('href="#music-shops"', false)
            ->assertSee('href="#recording-studios"', false);
    });
});
