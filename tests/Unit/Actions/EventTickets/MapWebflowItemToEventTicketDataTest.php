<?php

use App\Actions\EventTickets\MapWebflowItemToEventTicketData;
use Carbon\Carbon;
use Positiv\FilamentWebflow\Models\WebflowItem;

beforeEach(function () {
    $this->collection = new \Positiv\FilamentWebflow\Models\WebflowCollection;
    $this->collection->id = 1;
});

it('maps webflow item field_data to event ticket attributes', function () {
    $item = new WebflowItem;
    $item->id = 1;
    $item->field_data = [
        'name' => 'Test Event',
        'slug' => 'test-event',
        'beskrivelse' => 'Description',
        'datofelt' => '2025-12-01 19:00:00',
        'klokkeslett' => '19:00',
        'venue' => 'Main Hall',
        'billett-1-tilgjengelig' => 100,
        'billett-2-tilgjengelig' => 50,
    ];
    $item->is_archived = false;

    $data = MapWebflowItemToEventTicketData::map($item);

    expect($data['name'])->toBe('Test Event');
    expect($data['slug'])->toBe('test-event');
    expect($data['description'])->toBe('Description');
    expect($data['event_time'])->toBe('19:00');
    expect($data['venue'])->toBe('Main Hall');
    expect($data['ticket_1_available'])->toBe(100);
    expect($data['ticket_2_available'])->toBe(50);
    expect($data['event_date'])->toBeInstanceOf(Carbon::class);
    expect($data['ticket_1_label'])->toBe('Billett 1');
    expect($data['ticket_2_label'])->toBe('Billett 2');
});

it('returns untitled when name is missing', function () {
    $item = new WebflowItem;
    $item->id = 1;
    $item->field_data = [];
    $item->is_archived = false;

    $data = MapWebflowItemToEventTicketData::map($item);

    expect($data['name'])->toBe('Untitled');
});
