<?php

namespace App\Filament\Imports;

use App\Models\Transaction;
use Carbon\Carbon;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class TransactionImporter extends Importer
{
    protected static ?string $model = Transaction::class;

    /**
     * Standard Zeffy CSV columns that we always expect
     */
    private static array $standardColumns = [
        'Payment Date (America/Los_Angeles)',
        'Total Amount',
        'Payment Method',
        'Payment Status',
        'Payout Date',
        'Extra Donation',
        'Refund Amount',
        'Recurring Status',
        'Discount',
        'First Name',
        'Last Name',
        'Email',
        'Company Name',
        'Address',
        'City',
        'Postal Code',
        'State',
        'Country',
        'Language',
        'Tax Receipt #',
        'Tax Receipt URL',
        'Eligible Amount',
        'Details',
        'Occurrence',
        'Expiration Date',
        'Campaign Title',
        'Campaign Link',
        'Note',
        'In Honour/Memory of',
    ];

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('payment_date')
                ->label('Payment Date (America/Los_Angeles)')
                ->requiredMapping()
                ->rules(['required', 'date']),
            
            ImportColumn::make('amount')
                ->label('Total Amount')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'numeric', 'min:0']),
            
            ImportColumn::make('email')
                ->label('Email')
                ->requiredMapping()
                ->rules(['required', 'email']),
            
            ImportColumn::make('payment_status')
                ->label('Payment Status')
                ->requiredMapping()
                ->rules(['required']),
            
            ImportColumn::make('payment_method')
                ->label('Payment Method')
                ->rules(['nullable']),
            
            ImportColumn::make('recurring_status')
                ->label('Recurring Status')
                ->rules(['nullable']),
            
            ImportColumn::make('first_name')
                ->label('First Name')
                ->rules(['nullable']),
            
            ImportColumn::make('last_name')
                ->label('Last Name')
                ->rules(['nullable']),
            
            ImportColumn::make('company_name')
                ->label('Company Name')
                ->rules(['nullable']),
            
            ImportColumn::make('campaign_title')
                ->label('Campaign Title')
                ->rules(['nullable']),
            
            ImportColumn::make('note')
                ->label('Note')
                ->rules(['nullable']),
            
            ImportColumn::make('in_honour_memory_of')
                ->label('In Honour/Memory of')
                ->rules(['nullable']),
            
            ImportColumn::make('address')
                ->label('Address')
                ->rules(['nullable']),
            
            ImportColumn::make('city')
                ->label('City')
                ->rules(['nullable']),
            
            ImportColumn::make('postal_code')
                ->label('Postal Code')
                ->rules(['nullable']),
            
            ImportColumn::make('state')
                ->label('State')
                ->rules(['nullable']),
            
            ImportColumn::make('country')
                ->label('Country')
                ->rules(['nullable']),
            
            ImportColumn::make('tax_receipt_number')
                ->label('Tax Receipt #')
                ->rules(['nullable']),
            
            ImportColumn::make('tax_receipt_url')
                ->label('Tax Receipt URL')
                ->rules(['nullable']),
            
            ImportColumn::make('eligible_amount')
                ->label('Eligible Amount')
                ->numeric()
                ->rules(['nullable', 'numeric']),
            
        ];
    }

    public function resolveRecord(): ?Model
    {
        // Skip if payment failed
        $status = $this->mapPaymentStatus($this->data['payment_status']);
        if ($status === 'failed') {
            return null;
        }

        // Skip if amount is 0
        if ($this->data['amount'] <= 0) {
            return null;
        }

        // Generate transaction ID
        $transactionId = $this->generateTransactionId();
        
        // Check for duplicates
        if (Transaction::where('transaction_id', $transactionId)->exists()) {
            return null;
        }

        // Parse payment date
        $paymentDate = Carbon::createFromFormat('Y-m-d H:i:s', $this->data['payment_date'], 'America/Los_Angeles');
        
        // Determine transaction type
        $type = $this->determineTransactionType();
        
        // Build response payload with standard fields
        $response = [
            'donation_id' => $transactionId,
            'donor_email' => $this->data['email'],
            'amount' => (float) $this->data['amount'],
            'currency' => 'USD',
            'status' => $status,
            'type' => $type,
            'is_recurring' => ($this->data['recurring_status'] ?? '') === 'Active',
            'donor_name' => trim(($this->data['first_name'] ?? '') . ' ' . ($this->data['last_name'] ?? '')),
            'payment_method' => $this->mapPaymentMethod($this->data['payment_method'] ?? ''),
            'timestamp' => $paymentDate->toISOString(),
            'campaign' => $this->mapCampaign($this->data['campaign_title'] ?? ''),
            'note' => $this->data['note'] ?? null,
            'tax_receipt_number' => $this->data['tax_receipt_number'] ?? null,
            'tax_receipt_url' => $this->data['tax_receipt_url'] ?? null,
            'eligible_amount' => isset($this->data['eligible_amount']) ? (float) $this->data['eligible_amount'] : null,
            'company_name' => $this->data['company_name'] ?? null,
            'address' => $this->data['address'] ?? null,
            'city' => $this->data['city'] ?? null,
            'postal_code' => $this->data['postal_code'] ?? null,
            'state' => $this->data['state'] ?? null,
            'country' => $this->data['country'] ?? null,
            'in_honour_memory_of' => $this->data['in_honour_memory_of'] ?? null,
            'source' => 'zeffy_csv_import'
        ];

        // Add any additional questions dynamically
        $additionalQuestions = $this->getAdditionalQuestions();
        if (!empty($additionalQuestions)) {
            $response['additional_questions'] = $additionalQuestions;
        }

        return new Transaction([
            'transaction_id' => $transactionId,
            'email' => strtolower(trim($this->data['email'])),
            'amount' => (float) $this->data['amount'],
            'currency' => 'USD',
            'type' => $type,
            'response' => $response,
            'created_at' => $paymentDate,
        ]);
    }

    private function generateTransactionId(): string
    {
        $email = $this->data['email'];
        $amount = $this->data['amount'];
        $date = $this->data['payment_date'];
        
        $hash = substr(md5($email . $amount . $date), 0, 8);
        return "zeffy_import_{$hash}";
    }

    private function determineTransactionType(): string
    {
        if (($this->data['recurring_status'] ?? '') === 'Active') {
            return 'recurring';
        }
        
        if (!empty($this->data['company_name'])) {
            return 'sponsorship';
        }
        
        return 'donation';
    }

    private function mapPaymentStatus(string $status): string
    {
        return match (strtolower($status)) {
            'completed', 'success', 'paid' => 'completed',
            'pending', 'processing' => 'pending',
            'failed', 'cancelled', 'declined' => 'failed',
            default => strtolower($status),
        };
    }

    private function mapPaymentMethod(string $method): string
    {
        return match (strtolower($method)) {
            'credit card', 'card' => 'credit_card',
            'bank transfer', 'ach' => 'bank_transfer',
            'paypal' => 'paypal',
            default => str_replace(' ', '_', strtolower($method)),
        };
    }

    private function mapCampaign(string $campaign): string
    {
        $campaign = strtolower($campaign);
        
        if (str_contains($campaign, 'sustaining') || str_contains($campaign, 'monthly')) {
            return 'sustaining_membership';
        }
        
        if (str_contains($campaign, 'equipment') || str_contains($campaign, 'gear')) {
            return 'equipment_fund';
        }
        
        if (str_contains($campaign, 'event') || str_contains($campaign, 'show')) {
            return 'event_support';
        }
        
        return 'general_support';
    }

    /**
     * Detect and collect any additional questions from the CSV that aren't standard Zeffy columns
     */
    private function getAdditionalQuestions(): array
    {
        $additionalQuestions = [];
        
        // Map internal column names to actual CSV headers from the original file
        $columnMappings = $this->getColumnMappings();
        
        foreach ($this->data as $columnKey => $value) {
            // Get the original CSV header name for this column
            $originalHeader = $columnMappings[$columnKey] ?? $columnKey;
            
            // Skip if it's a standard Zeffy column
            if (in_array($originalHeader, self::$standardColumns)) {
                continue;
            }
            
            // Skip if it's one of our internal mapping keys
            if (in_array($columnKey, [
                'payment_date', 'amount', 'email', 'payment_status', 'payment_method',
                'recurring_status', 'first_name', 'last_name', 'company_name',
                'campaign_title', 'note', 'in_honour_memory_of', 'address', 'city',
                'postal_code', 'state', 'country', 'tax_receipt_number', 'tax_receipt_url',
                'eligible_amount'
            ])) {
                continue;
            }
            
            // Skip empty values
            if (empty($value)) {
                continue;
            }
            
            // This is likely an additional question
            $additionalQuestions[$originalHeader] = $value;
        }
        
        return $additionalQuestions;
    }

    /**
     * Get the mapping between internal column keys and original CSV headers
     * This would ideally come from the import context, but for now we'll use a simple approach
     */
    private function getColumnMappings(): array
    {
        // This is a simplified approach - in a real implementation, you'd want to
        // store the original headers during the import process
        return [
            'payment_date' => 'Payment Date (America/Los_Angeles)',
            'amount' => 'Total Amount',
            'email' => 'Email',
            'payment_status' => 'Payment Status',
            'payment_method' => 'Payment Method',
            'recurring_status' => 'Recurring Status',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'company_name' => 'Company Name',
            'campaign_title' => 'Campaign Title',
            'note' => 'Note',
            'in_honour_memory_of' => 'In Honour/Memory of',
            'address' => 'Address',
            'city' => 'City',
            'postal_code' => 'Postal Code',
            'state' => 'State',
            'country' => 'Country',
            'tax_receipt_number' => 'Tax Receipt #',
            'tax_receipt_url' => 'Tax Receipt URL',
            'eligible_amount' => 'Eligible Amount',
        ];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your Zeffy transaction import has completed and ' . Number::format($import->successful_rows) . ' ' . str('transaction')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import (likely duplicates or failed payments).';
        }

        return $body;
    }
}
