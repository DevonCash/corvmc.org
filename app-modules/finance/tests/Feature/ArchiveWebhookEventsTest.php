<?php

use CorvMC\Finance\Facades\Finance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function seedWebhookEvents(int $count, int $daysOld): void
{
    $timestamp = now()->subDays($daysOld);

    for ($i = 0; $i < $count; $i++) {
        DB::table('stripe_webhook_events')->insert([
            'event_id' => 'evt_test_' . uniqid(),
            'event_type' => 'checkout.session.completed',
            'processed_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }
}

// =========================================================================
// Finance::archiveWebhookEvents()
// =========================================================================

describe('Finance::archiveWebhookEvents()', function () {
    it('archives events older than the threshold and deletes them', function () {
        seedWebhookEvents(5, daysOld: 120);

        $result = Finance::archiveWebhookEvents(90);

        expect($result['archived'])->toBe(5);
        expect($result['file'])->toStartWith('finance/archives/webhook-events-');
        expect($result['file'])->toEndWith('.jsonl');

        // Events should be gone from the database
        expect(DB::table('stripe_webhook_events')->count())->toBe(0);

        // Archive file should exist with 5 lines
        $contents = Storage::disk('local')->get($result['file']);
        $lines = array_filter(explode("\n", trim($contents)));
        expect($lines)->toHaveCount(5);

        // Each line should be valid JSON with expected fields
        $firstLine = json_decode($lines[0], true);
        expect($firstLine)->toHaveKey('event_id');
        expect($firstLine)->toHaveKey('event_type');
    });

    it('does not archive events newer than the threshold', function () {
        seedWebhookEvents(3, daysOld: 120); // old — should be archived
        seedWebhookEvents(2, daysOld: 30);  // recent — should stay

        $result = Finance::archiveWebhookEvents(90);

        expect($result['archived'])->toBe(3);

        // 2 recent events remain
        expect(DB::table('stripe_webhook_events')->count())->toBe(2);
    });

    it('returns zero and no file when nothing to archive', function () {
        seedWebhookEvents(3, daysOld: 30); // all recent

        $result = Finance::archiveWebhookEvents(90);

        expect($result['archived'])->toBe(0);
        expect($result['file'])->toBeNull();
    });

    it('returns zero and no file when table is empty', function () {
        $result = Finance::archiveWebhookEvents(90);

        expect($result['archived'])->toBe(0);
        expect($result['file'])->toBeNull();
    });
});
