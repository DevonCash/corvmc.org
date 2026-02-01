<?php

namespace CorvMC\Moderation\Database\Seeders;

use CorvMC\Bands\Models\Band;
use CorvMC\Events\Models\Event;
use CorvMC\Membership\Models\MemberProfile;
use CorvMC\Moderation\Models\Revision;
use App\Models\User;
use Illuminate\Database\Seeder;

class RevisionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing content that can have revisions
        $profiles = MemberProfile::take(10)->get();
        $bands = Band::take(5)->get();
        $events = Event::take(5)->get();
        $users = User::take(10)->get();

        if ($profiles->isEmpty() && $bands->isEmpty() && $events->isEmpty()) {
            $this->command->warn('No member profiles, bands, or events found. Make sure to run other seeders first.');

            return;
        }

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Make sure to run UserSeeder first.');

            return;
        }

        $revisionsCreated = 0;

        // Create revisions for member profiles
        foreach ($profiles->take(8) as $profile) {
            $revisionCount = fake()->numberBetween(1, 3);

            for ($i = 0; $i < $revisionCount; $i++) {
                $status = fake()->randomElement([
                    Revision::STATUS_PENDING,
                    Revision::STATUS_APPROVED,
                    Revision::STATUS_REJECTED,
                ]);

                // Simulate realistic changes to different fields
                $fieldsToChange = fake()->randomElements(
                    ['bio', 'display_name', 'location', 'pronouns', 'website'],
                    fake()->numberBetween(1, 3)
                );

                $originalData = [];
                $proposedChanges = [];

                foreach ($fieldsToChange as $field) {
                    $originalData[$field] = $this->generateOriginalValue($field);
                    $proposedChanges[$field] = $this->generateProposedValue($field);
                }

                $revision = Revision::create([
                    'revisionable_type' => 'member_profile',
                    'revisionable_id' => $profile->id,
                    'original_data' => $originalData,
                    'proposed_changes' => $proposedChanges,
                    'status' => $status,
                    'submitted_by_id' => $users->random()->id,
                    'reviewed_by_id' => $status !== Revision::STATUS_PENDING ? $users->random()->id : null,
                    'reviewed_at' => $status !== Revision::STATUS_PENDING ? fake()->dateTimeBetween('-1 week', 'now') : null,
                    'review_reason' => $status !== Revision::STATUS_PENDING ? $this->getReviewReason($status) : null,
                    'revision_type' => Revision::TYPE_UPDATE,
                    'auto_approved' => false,
                ]);

                $revisionsCreated++;
            }
        }

        // Create some auto-approved revisions (high trust users)
        foreach ($profiles->take(3) as $profile) {
            $revision = Revision::create([
                'revisionable_type' => 'member_profile',
                'revisionable_id' => $profile->id,
                'original_data' => ['bio' => 'Original bio text'],
                'proposed_changes' => ['bio' => fake()->paragraph()],
                'status' => Revision::STATUS_APPROVED,
                'submitted_by_id' => $users->random()->id,
                'reviewed_by_id' => null,
                'reviewed_at' => now(),
                'review_reason' => null,
                'revision_type' => Revision::TYPE_UPDATE,
                'auto_approved' => true,
            ]);

            $revisionsCreated++;
        }

        // Create revisions for bands
        foreach ($bands->take(4) as $band) {
            $status = fake()->randomElement([
                Revision::STATUS_PENDING,
                Revision::STATUS_APPROVED,
            ]);

            $revision = Revision::create([
                'revisionable_type' => 'band',
                'revisionable_id' => $band->id,
                'original_data' => [
                    'bio' => 'Original band bio',
                    'name' => $band->name,
                ],
                'proposed_changes' => [
                    'bio' => fake()->paragraph(),
                ],
                'status' => $status,
                'submitted_by_id' => $users->random()->id,
                'reviewed_by_id' => $status !== Revision::STATUS_PENDING ? $users->random()->id : null,
                'reviewed_at' => $status !== Revision::STATUS_PENDING ? fake()->dateTimeBetween('-3 days', 'now') : null,
                'review_reason' => $status !== Revision::STATUS_PENDING ? $this->getReviewReason($status) : null,
                'revision_type' => Revision::TYPE_UPDATE,
                'auto_approved' => false,
            ]);

            $revisionsCreated++;
        }

        // Create some rejected revisions with specific reasons
        if ($profiles->count() > 5) {
            $revision = Revision::create([
                'revisionable_type' => 'member_profile',
                'revisionable_id' => $profiles->random()->id,
                'original_data' => ['bio' => 'Original bio'],
                'proposed_changes' => ['bio' => 'Promotional content with external links'],
                'status' => Revision::STATUS_REJECTED,
                'submitted_by_id' => $users->random()->id,
                'reviewed_by_id' => $users->random()->id,
                'reviewed_at' => fake()->dateTimeBetween('-1 week', 'now'),
                'review_reason' => 'Contains promotional links and violates community guidelines.',
                'revision_type' => Revision::TYPE_UPDATE,
                'auto_approved' => false,
            ]);

            $revisionsCreated++;
        }

        // Create a few event revisions (less common)
        foreach ($events->take(2) as $event) {
            $revision = Revision::create([
                'revisionable_type' => 'event',
                'revisionable_id' => $event->id,
                'original_data' => ['description' => 'Original event description'],
                'proposed_changes' => ['description' => fake()->paragraph()],
                'status' => Revision::STATUS_PENDING,
                'submitted_by_id' => $users->random()->id,
                'reviewed_by_id' => null,
                'reviewed_at' => null,
                'review_reason' => null,
                'revision_type' => Revision::TYPE_UPDATE,
                'auto_approved' => false,
            ]);

            $revisionsCreated++;
        }

        $this->command->info("Created {$revisionsCreated} revisions across member profiles, bands, and events.");
    }

    /**
     * Generate a realistic original value for a field.
     */
    private function generateOriginalValue(string $field): string
    {
        return match ($field) {
            'bio' => fake()->paragraph(),
            'display_name' => fake()->name(),
            'location' => fake()->city().', '.fake()->stateAbbr(),
            'pronouns' => fake()->randomElement(['he/him', 'she/her', 'they/them']),
            'website' => 'https://'.fake()->domainName(),
            default => 'Original '.$field,
        };
    }

    /**
     * Generate a realistic proposed value for a field.
     */
    private function generateProposedValue(string $field): string
    {
        return match ($field) {
            'bio' => fake()->paragraphs(2, true),
            'display_name' => fake()->name(),
            'location' => fake()->city().', '.fake()->stateAbbr(),
            'pronouns' => fake()->randomElement(['he/him', 'she/her', 'they/them', 'she/they', 'he/they']),
            'website' => 'https://'.fake()->domainName(),
            default => 'Updated '.$field,
        };
    }

    /**
     * Get a review reason based on status.
     */
    private function getReviewReason(string $status): string
    {
        if ($status === Revision::STATUS_APPROVED) {
            return fake()->randomElement([
                'Changes look good, approved.',
                'Approved by moderator.',
                'Meets community guidelines.',
            ]);
        }

        return fake()->randomElement([
            'Contains inappropriate content.',
            'Violates community guidelines.',
            'Promotional content not allowed in this field.',
            'Please provide more appropriate information.',
        ]);
    }
}
