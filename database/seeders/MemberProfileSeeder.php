<?php

namespace Database\Seeders;

use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;

class MemberProfileSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedProfiles();
    }

    private function seedProfiles(): void
    {
        // Create a diverse set of member profiles for testing

        // 1. Admin user with full profile
        UserFactory::createAdmin();

        // 2. Professional musician with public profile
        UserFactory::createProfessionalMusician();

        // 3. Beginner with minimal profile
        UserFactory::createBeginnerMusician();

        // 4. Producer with members-only profile
        UserFactory::createProducer();

        // 5. Private profile user
        UserFactory::createPrivateUser();

        // 6. Multi-instrumentalist with extensive profile
        UserFactory::createMultiInstrumentalist();

        // Generate additional random profiles
        UserFactory::createRandomUsers(15);

        // Create some profiles with specific visibility settings
        UserFactory::createRandomUsers(3, ['visibility' => 'private']);
        UserFactory::createRandomUsers(5, ['visibility' => 'public']);
        UserFactory::createRandomUsers(4, ['visibility' => 'members']);
        UserFactory::createRandomUsers(3, ['bio' => null]);

        $this->command->info('Created member profiles with diverse configurations for testing');
    }
}
