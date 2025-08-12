<?php

namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $users = User::all();
        
        // Create various transaction types with realistic Zeffy/Zapier webhook payloads
        $this->createMemberTransactions($users);
        $this->createNonMemberTransactions();
        $this->createMixedTransactions($users);
    }

    /**
     * Create transactions for existing members
     */
    private function createMemberTransactions($users): void
    {
        $memberEmails = $users->pluck('email')->take(6)->toArray();
        
        foreach ($memberEmails as $index => $email) {
            // Each member gets 1-3 transactions with different patterns
            $this->createTransactionForEmail($email, $index);
        }
    }

    /**
     * Create transactions for non-members (community supporters)
     */
    private function createNonMemberTransactions(): void
    {
        $nonMemberTransactions = [
            [
                'email' => 'community.supporter@gmail.com',
                'name' => 'Jennifer Martinez',
                'amount' => 35.00,
                'type' => 'donation',
                'campaign' => 'general_support'
            ],
            [
                'email' => 'local.business@oregonstate.edu',
                'name' => 'OSU Music Department',
                'amount' => 200.00,
                'type' => 'sponsorship',
                'campaign' => 'equipment_fund'
            ],
            [
                'email' => 'music.lover.corvallis@yahoo.com',
                'name' => 'Robert Kim',
                'amount' => 12.00,
                'type' => 'recurring',
                'campaign' => 'sustaining_membership'
            ],
            [
                'email' => 'parent.supporter@gmail.com',
                'name' => 'Carol Anderson',
                'amount' => 50.00,
                'type' => 'donation',
                'campaign' => 'youth_program'
            ],
            [
                'email' => 'anonymous.donor.2024@protonmail.com',
                'name' => 'Anonymous Donor',
                'amount' => 120.00,
                'type' => 'donation',
                'campaign' => 'general_support'
            ],
            [
                'email' => 'former.member@hotmail.com',
                'name' => 'Jessica Wong',
                'amount' => 25.00,
                'type' => 'recurring',
                'campaign' => 'alumni_support'
            ],
        ];

        foreach ($nonMemberTransactions as $index => $transaction) {
            $this->createNonMemberTransaction($transaction, $index);
        }
    }

    /**
     * Create additional mixed scenarios
     */
    private function createMixedTransactions($users): void
    {
        // Large donation from someone who later becomes a member
        if ($users->count() > 0) {
            $futureMember = $users->last();
            Transaction::create([
                'transaction_id' => 'zeffy_future_member_99887766',
                'email' => $futureMember->email,
                'amount' => 250.00,
                'currency' => 'USD',
                'type' => 'donation',
                'response' => [
                    'donation_id' => 'zeffy_future_member_99887766',
                    'donor_email' => $futureMember->email,
                    'amount' => 250.00,
                    'currency' => 'USD',
                    'status' => 'completed',
                    'type' => 'one-time',
                    'is_recurring' => false,
                    'donor_name' => 'Future Member',
                    'payment_method' => 'credit_card',
                    'timestamp' => '2024-10-15T14:30:00Z',
                    'zeffy_form_id' => 'form_big_donor_xyz789',
                    'campaign' => 'major_gift',
                    'note' => 'Excited to support the collective before joining!'
                ]
            ]);
        }

        // Failed/cancelled transaction example
        Transaction::create([
            'transaction_id' => 'zeffy_failed_11112222',
            'email' => 'failed.payment@example.com',
            'amount' => 0.00,
            'currency' => 'USD',
            'type' => 'donation',
            'response' => [
                'donation_id' => 'zeffy_failed_11112222',
                'donor_email' => 'failed.payment@example.com',
                'amount' => 30.00,
                'currency' => 'USD',
                'status' => 'failed',
                'type' => 'one-time',
                'is_recurring' => false,
                'donor_name' => 'Failed Payment User',
                'payment_method' => 'credit_card',
                'timestamp' => '2024-12-01T12:00:00Z',
                'zeffy_form_id' => 'form_abc123',
                'campaign' => 'general_support',
                'error_code' => 'insufficient_funds'
            ]
        ]);
    }

    private function createTransactionForEmail(string $email, int $index): void
    {
        $amounts = [10.00, 15.00, 25.00, 50.00, 75.00, 100.00];
        $campaigns = ['general_support', 'sustaining_membership', 'equipment_fund', 'event_support'];
        $names = ['Alex Johnson', 'Sam Chen', 'Taylor Davis', 'Jordan Wilson', 'Casey Brown', 'Riley Garcia'];
        
        $amount = $amounts[$index % count($amounts)];
        $campaign = $campaigns[$index % count($campaigns)];
        $name = $names[$index % count($names)];
        
        // Create primary transaction
        Transaction::create([
            'transaction_id' => "zeffy_member_{$index}_" . str_pad($index * 1000 + 1001, 8, '0', STR_PAD_LEFT),
            'email' => $email,
            'amount' => $amount,
            'currency' => 'USD',
            'type' => $amount >= 10 ? 'recurring' : 'donation',
            'response' => [
                'donation_id' => "zeffy_member_{$index}_" . str_pad($index * 1000 + 1001, 8, '0', STR_PAD_LEFT),
                'donor_email' => $email,
                'amount' => $amount,
                'currency' => 'USD',
                'status' => 'completed',
                'type' => $amount >= 10 ? 'monthly' : 'one-time',
                'is_recurring' => $amount >= 10,
                'donor_name' => $name,
                'payment_method' => ['credit_card', 'bank_transfer', 'paypal'][$index % 3],
                'timestamp' => now()->subDays(30 + $index * 5)->toISOString(),
                'zeffy_form_id' => 'form_member_' . chr(97 + $index) . chr(97 + $index) . chr(97 + $index) . '123',
                'campaign' => $campaign,
                'subscription_id' => $amount >= 10 ? "sub_zeffy_member_{$index}" : null
            ]
        ]);

        // Some members have multiple transactions
        if ($index % 3 === 0) {
            Transaction::create([
                'transaction_id' => "zeffy_member_{$index}_" . str_pad($index * 1000 + 2002, 8, '0', STR_PAD_LEFT),
                'email' => $email,
                'amount' => 45.00,
                'currency' => 'USD',
                'type' => 'donation',
                'response' => [
                    'donation_id' => "zeffy_member_{$index}_" . str_pad($index * 1000 + 2002, 8, '0', STR_PAD_LEFT),
                    'donor_email' => $email,
                    'amount' => 45.00,
                    'currency' => 'USD',
                    'status' => 'completed',
                    'type' => 'one-time',
                    'is_recurring' => false,
                    'donor_name' => $name,
                    'payment_method' => 'credit_card',
                    'timestamp' => now()->subDays(15 + $index)->toISOString(),
                    'zeffy_form_id' => 'form_special_bbb456',
                    'campaign' => 'equipment_fund',
                    'note' => 'Additional support for new gear'
                ]
            ]);
        }
    }

    private function createNonMemberTransaction(array $transaction, int $index): void
    {
        $transactionId = "zeffy_non_member_{$index}_" . str_pad(5000 + $index, 8, '0', STR_PAD_LEFT);
        
        Transaction::create([
            'transaction_id' => $transactionId,
            'email' => $transaction['email'],
            'amount' => $transaction['amount'],
            'currency' => 'USD',
            'type' => $transaction['type'],
            'response' => [
                'donation_id' => $transactionId,
                'donor_email' => $transaction['email'],
                'amount' => $transaction['amount'],
                'currency' => 'USD',
                'status' => 'completed',
                'type' => $transaction['type'] === 'recurring' ? 'monthly' : 'one-time',
                'is_recurring' => $transaction['type'] === 'recurring',
                'donor_name' => $transaction['name'],
                'payment_method' => ['credit_card', 'bank_transfer', 'paypal'][$index % 3],
                'timestamp' => now()->subDays(rand(1, 60))->toISOString(),
                'zeffy_form_id' => 'form_community_' . chr(120 + $index % 3) . chr(121 + $index % 2) . chr(122) . '789',
                'campaign' => $transaction['campaign'],
                'subscription_id' => $transaction['type'] === 'recurring' ? "sub_zeffy_community_{$index}" : null,
                'source' => 'community_outreach'
            ]
        ]);
    }
}