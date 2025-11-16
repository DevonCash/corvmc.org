<?php

namespace Database\Seeders;

use App\Data\ContactData;
use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\User;
use Illuminate\Database\Seeder;

class EquipmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some users to be donors/lenders
        $users = User::limit(5)->get();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run UserSeeder first.');

            return;
        }

        // Create donated equipment
        $donatedEquipment = [
            [
                'name' => 'Fender American Standard Stratocaster',
                'type' => 'guitar',
                'brand' => 'Fender',
                'model' => 'American Standard Stratocaster',
                'serial_number' => 'US12345678',
                'description' => 'Classic electric guitar in excellent condition. Sunburst finish with maple neck.',
                'condition' => 'excellent',
                'estimated_value' => 1200.00,
                'location' => 'Main storage',
                'acquisition_notes' => 'Donated by longtime member who upgraded to a custom shop model.',
            ],
            [
                'name' => 'Gibson Les Paul Studio',
                'type' => 'guitar',
                'brand' => 'Gibson',
                'model' => 'Les Paul Studio',
                'serial_number' => 'LP87654321',
                'description' => 'Solid mahogany body with humbucker pickups. Great for rock and blues.',
                'condition' => 'good',
                'estimated_value' => 800.00,
                'location' => 'Main storage',
                'acquisition_notes' => 'Estate donation from community member.',
            ],
            [
                'name' => 'Fender Jazz Bass',
                'type' => 'bass',
                'brand' => 'Fender',
                'model' => 'Player Jazz Bass',
                'description' => 'Versatile 4-string bass guitar with active pickups.',
                'condition' => 'good',
                'estimated_value' => 650.00,
                'location' => 'Main storage',
            ],
            [
                'name' => 'Marshall DSL40CR Combo Amp',
                'type' => 'amplifier',
                'brand' => 'Marshall',
                'model' => 'DSL40CR',
                'description' => '40-watt tube combo amplifier with classic Marshall tone.',
                'condition' => 'excellent',
                'estimated_value' => 600.00,
                'location' => 'Practice room',
                'acquisition_notes' => 'Donated by departing board member.',
            ],
            [
                'name' => 'Shure SM57 Dynamic Microphone',
                'type' => 'microphone',
                'brand' => 'Shure',
                'model' => 'SM57',
                'description' => 'Industry standard dynamic microphone for instruments and vocals.',
                'condition' => 'good',
                'estimated_value' => 99.00,
                'location' => 'Storage cabinet',
            ],
            [
                'name' => 'Pearl Export 5-Piece Drum Kit',
                'type' => 'percussion',
                'brand' => 'Pearl',
                'model' => 'Export EXX',
                'description' => 'Complete 5-piece drum kit with hardware. Cymbals not included.',
                'condition' => 'fair',
                'estimated_value' => 500.00,
                'location' => 'Practice room',
                'notes' => 'Some wear on drum heads, but all shells in good condition.',
            ],
        ];

        foreach ($donatedEquipment as $index => $equipmentData) {
            Equipment::create(array_merge($equipmentData, [
                'acquisition_type' => 'donated',
                'provider_id' => $users->get($index % $users->count())->id,
                'acquisition_date' => now()->subMonths(rand(1, 24)),
                'ownership_status' => 'cmc_owned',
                'status' => 'available',
            ]));
        }

        // Create equipment on loan to CMC
        $loanedEquipment = [
            [
                'name' => 'Roland TD-17KVX Electronic Drum Kit',
                'type' => 'percussion',
                'brand' => 'Roland',
                'model' => 'TD-17KVX',
                'description' => 'Professional electronic drum kit with mesh heads and natural feel.',
                'condition' => 'excellent',
                'estimated_value' => 1800.00,
                'location' => 'Practice room',
                'return_due_date' => now()->addMonths(6),
                'acquisition_notes' => 'Loaned while owner travels for work. Return in spring.',
            ],
            [
                'name' => 'Yamaha P-45 Digital Piano',
                'type' => 'specialty',
                'brand' => 'Yamaha',
                'model' => 'P-45',
                'description' => '88-key weighted digital piano with sustain pedal.',
                'condition' => 'good',
                'estimated_value' => 450.00,
                'location' => 'Main room',
                'return_due_date' => now()->addMonths(3),
                'acquisition_notes' => 'Short-term loan from member who is moving.',
            ],
        ];

        foreach ($loanedEquipment as $index => $equipmentData) {
            Equipment::create(array_merge($equipmentData, [
                'acquisition_type' => 'loaned_to_us',
                'provider_id' => $users->get(($index + 3) % $users->count())->id,
                'acquisition_date' => now()->subMonths(rand(1, 6)),
                'ownership_status' => 'on_loan_to_cmc',
                'status' => 'available',
            ]));
        }

        // Create equipment with external donors
        $externalDonations = [
            [
                'name' => 'Audio-Technica AT2020 Condenser Mic',
                'type' => 'microphone',
                'brand' => 'Audio-Technica',
                'model' => 'AT2020',
                'description' => 'Large-diaphragm condenser microphone for studio recording.',
                'condition' => 'excellent',
                'estimated_value' => 99.00,
                'location' => 'Storage cabinet',
                'provider_contact' => new ContactData(
                    email: 'donor@example.com',
                    phone: '555-0123',
                    address: '123 Music St, Corvallis OR'
                ),
                'acquisition_notes' => 'Donated by local music store owner.',
            ],
            [
                'name' => 'Boss RC-30 Loop Station',
                'type' => 'specialty',
                'brand' => 'Boss',
                'model' => 'RC-30',
                'description' => 'Dual-track loop station with built-in effects.',
                'condition' => 'good',
                'estimated_value' => 200.00,
                'location' => 'Storage cabinet',
                'provider_contact' => new ContactData(
                    email: 'community@supporter.org',
                    phone: '555-0456'
                ),
                'acquisition_notes' => 'Anonymous community donation.',
            ],
        ];

        foreach ($externalDonations as $equipmentData) {
            Equipment::create(array_merge($equipmentData, [
                'acquisition_type' => 'donated',
                'provider_id' => null,
                'acquisition_date' => now()->subMonths(rand(1, 12)),
                'ownership_status' => 'cmc_owned',
                'status' => 'available',
            ]));
        }

        // Create some equipment loans (members borrowing equipment)
        $availableEquipment = Equipment::available()->get();
        $borrowers = User::limit(3)->get();

        if ($availableEquipment->isNotEmpty() && $borrowers->count() >= 3) {
            // Active loan
            $activeEquipment = $availableEquipment->first();
            EquipmentLoan::create([
                'equipment_id' => $activeEquipment->id,
                'borrower_id' => $borrowers->first()->id,
                'reserved_from' => now()->subDays(5),
                'checked_out_at' => now()->subDays(5),
                'due_at' => now()->addDays(9),
                'state' => 'checked_out',
                'condition_out' => 'good',
                'security_deposit' => 50.00,
                'rental_fee' => 25.00,
                'notes' => 'For recording session at home studio.',
            ]);

            // Update equipment status
            $activeEquipment->update(['status' => 'checked_out']);

            // Overdue loan
            if ($availableEquipment->count() > 1) {
                $overdueEquipment = $availableEquipment->get(1);
                EquipmentLoan::create([
                    'equipment_id' => $overdueEquipment->id,
                    'borrower_id' => $borrowers->get(1)->id,
                    'reserved_from' => now()->subDays(20),
                    'checked_out_at' => now()->subDays(20),
                    'due_at' => now()->subDays(6),
                    'state' => 'overdue',
                    'condition_out' => 'good',
                    'security_deposit' => 75.00,
                    'rental_fee' => 30.00,
                    'notes' => 'For band practice and performance.',
                ]);

                // Update equipment status
                $overdueEquipment->update(['status' => 'checked_out']);
            }

            // Returned loan
            if ($availableEquipment->count() > 2) {
                $returnedEquipment = $availableEquipment->get(2);
                EquipmentLoan::create([
                    'equipment_id' => $returnedEquipment->id,
                    'borrower_id' => $borrowers->get(2)->id,
                    'reserved_from' => now()->subDays(30),
                    'checked_out_at' => now()->subDays(30),
                    'due_at' => now()->subDays(16),
                    'returned_at' => now()->subDays(15),
                    'state' => 'returned',
                    'condition_out' => 'good',
                    'condition_in' => 'good',
                    'security_deposit' => 25.00,
                    'rental_fee' => 15.00,
                    'notes' => 'Used for music video recording.',
                ]);
            }
        }

        $this->command->info('Equipment seeder completed:');
        $this->command->info('- '.Equipment::count().' pieces of equipment created');
        $this->command->info('- '.EquipmentLoan::count().' equipment loans created');
        $this->command->info('- '.Equipment::available()->count().' pieces available');
        $this->command->info('- '.Equipment::where('status', 'checked_out')->count().' pieces currently checked out');
    }
}
