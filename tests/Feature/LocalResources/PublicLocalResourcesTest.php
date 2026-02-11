<?php

use App\Livewire\ResourceSuggestionForm;
use App\Models\LocalResource;
use App\Models\ResourceList;
use App\Notifications\ResourceSuggestionNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

describe('public local resources page', function () {
    it('displays the local resources page', function () {
        $this->get(route('local-resources'))
            ->assertOk()
            ->assertSee('Local Resources');
    });

    it('shows resource lists with published resources', function () {
        $list = ResourceList::factory()->create([
            'name' => 'Music Shops',
        ]);
        LocalResource::factory()->published()->forList($list)->create();

        $this->get(route('local-resources'))
            ->assertOk()
            ->assertSee('Music Shops');
    });

    it('shows published resources within lists', function () {
        $list = ResourceList::factory()->create([
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

    it('hides categories with only draft resources', function () {
        $list = ResourceList::factory()->create([
            'name' => 'Music Teachers',
        ]);

        $draftResource = LocalResource::factory()->draft()->forList($list)->create([
            'name' => 'Draft Teacher Resource',
        ]);

        $this->get(route('local-resources'))
            ->assertOk()
            ->assertDontSee('Music Teachers')
            ->assertDontSee('Draft Teacher Resource');
    });

    it('displays resource contact information', function () {
        $list = ResourceList::factory()->create();

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
        $secondList = ResourceList::factory()->create([
            'name' => 'Second Category',
            'display_order' => 2,
        ]);
        LocalResource::factory()->published()->forList($secondList)->create();

        $firstList = ResourceList::factory()->create([
            'name' => 'First Category',
            'display_order' => 1,
        ]);
        LocalResource::factory()->published()->forList($firstList)->create();

        $response = $this->get(route('local-resources'));
        $response->assertOk();

        // First Category should appear before Second Category
        $content = $response->getContent();
        $firstPosition = strpos($content, 'First Category');
        $secondPosition = strpos($content, 'Second Category');

        expect($firstPosition)->toBeLessThan($secondPosition);
    });

    it('orders resources by sort_order within a list', function () {
        $list = ResourceList::factory()->create();

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
        $musicShops = ResourceList::factory()->create(['name' => 'Music Shops', 'slug' => 'music-shops']);
        LocalResource::factory()->published()->forList($musicShops)->create();

        $studios = ResourceList::factory()->create(['name' => 'Recording Studios', 'slug' => 'recording-studios']);
        LocalResource::factory()->published()->forList($studios)->create();

        $this->get(route('local-resources'))
            ->assertOk()
            ->assertSee('href="#music-shops"', false)
            ->assertSee('href="#recording-studios"', false);
    });

    it('displays the resource suggestion button', function () {
        $this->get(route('local-resources'))
            ->assertOk()
            ->assertSee('Suggest a Resource')
            ->assertSeeLivewire(ResourceSuggestionForm::class);
    });
});

describe('resource suggestion form', function () {
    it('submits a resource suggestion and sends notification', function () {
        Notification::fake();
        Http::fake(['*turnstile*' => Http::response(['success' => true])]);

        $list = ResourceList::factory()->create(['name' => 'Music Shops']);

        Livewire::test(ResourceSuggestionForm::class)
            ->callAction('suggestResource', data: [
                'resource_name' => 'Test Music Store',
                'category' => $list->id,
                'website' => 'https://testmusicstore.com',
                'description' => 'A great place for guitars',
                'contact_name' => 'John Owner',
                'contact_phone' => '541-555-1234',
                'address' => '123 Main St, Corvallis, OR',
                'captcha' => 'test-token',
            ])
            ->assertHasNoActionErrors();

        Notification::assertSentOnDemand(ResourceSuggestionNotification::class);

        $resource = LocalResource::where('name', 'Test Music Store')->first();
        expect($resource)
            ->not->toBeNull()
            ->resource_list_id->toBe($list->id)
            ->website->toBe('https://testmusicstore.com')
            ->description->toBe('A great place for guitars')
            ->contact_name->toBe('John Owner')
            ->published_at->toBeNull();
    });

    it('requires resource name', function () {
        Http::fake(['*turnstile*' => Http::response(['success' => true])]);

        Livewire::test(ResourceSuggestionForm::class)
            ->callAction('suggestResource', data: [
                'resource_name' => '',
                'captcha' => 'test-token',
            ])
            ->assertHasActionErrors([
                'resource_name' => 'required',
            ]);
    });

    it('handles other category in submission', function () {
        Notification::fake();
        Http::fake(['*turnstile*' => Http::response(['success' => true])]);

        Livewire::test(ResourceSuggestionForm::class)
            ->callAction('suggestResource', data: [
                'resource_name' => 'New Resource',
                'category' => 'other',
                'new_category' => 'Vinyl Pressing',
                'captcha' => 'test-token',
            ])
            ->assertHasNoActionErrors();

        Notification::assertSentOnDemand(
            ResourceSuggestionNotification::class,
            function ($notification) {
                return $notification->submissionData['category_name'] === 'Vinyl Pressing';
            }
        );

        $resource = LocalResource::where('name', 'New Resource')->first();
        expect($resource)->not->toBeNull();

        $newList = ResourceList::where('name', 'Vinyl Pressing')->first();
        expect($newList)->not->toBeNull();
        expect($resource->resource_list_id)->toBe($newList->id);
    });
});
