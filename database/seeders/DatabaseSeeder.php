<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use Illuminate\Database\Seeder;

use CorvMC\Equipment\Database\Seeders\EquipmentSeeder;
use CorvMC\Moderation\Database\Seeders\ReportSeeder;
use CorvMC\Moderation\Database\Seeders\RevisionSeeder;
use CorvMC\Sponsorship\Database\Seeders\SponsorSeeder;

/** @package Database\Seeders */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            MemberProfileSeeder::class,
            BandSeeder::class,
            ProductionSeeder::class,
            ReservationSeeder::class,
            StaffProfileSeeder::class,
            EquipmentSeeder::class,
            SponsorSeeder::class,
            ReportSeeder::class,
            RevisionSeeder::class,
        ]);
    }
}
