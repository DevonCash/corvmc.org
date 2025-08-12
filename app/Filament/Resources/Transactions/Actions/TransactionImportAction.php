<?php

namespace App\Filament\Resources\Transactions\Actions;

use App\Models\Transaction;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class TransactionImportAction
{
    public static function make(): Action
    {
        return Action::make('import_zeffy_data')
            ->label('Import Zeffy Data')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('success')
            ->form([
                FileUpload::make('file')
                    ->label('Zeffy Export File')
                    ->required()
                    ->acceptedFileTypes([
                        'text/csv',
                        'application/csv',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        '.csv',
                        '.xlsx',
                        '.xls'
                    ])
                    ->helperText('Upload a CSV or Excel (.xlsx, .xls) file exported from Zeffy.')
                    ->disk('local')
                    ->directory('imports')
                    ->maxSize(10240), // 10MB limit
            ])
            ->modalHeading('Import Zeffy Transactions')
            ->modalDescription('Upload a CSV or Excel file exported from Zeffy to import transaction data. The system will automatically detect file format, map columns, and handle duplicates.')
            ->modalSubmitActionLabel('Import Data')
            ->action(function (array $data) {
                return static::processImport($data['file']);
            });
    }

    public static function processImport(string $filePath): void
    {
        try {
            // Handle both direct paths and storage paths
            if (Storage::disk('local')->exists($filePath)) {
                $fullPath = Storage::disk('local')->path($filePath);
            } else {
                // Fallback to direct path resolution
                $fullPath = storage_path('app/' . ltrim($filePath, '/'));
            }
            
            if (!file_exists($fullPath)) {
                // Debug info
                \Log::error('File not found during import', [
                    'original_path' => $filePath,
                    'attempted_path' => $fullPath,
                    'storage_exists' => Storage::disk('local')->exists($filePath),
                    'files_in_imports' => Storage::disk('local')->files('imports')
                ]);
                throw new \Exception("Uploaded file not found. Path: {$filePath}");
            }

            // Detect file type
            $fileExtension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            $isExcel = in_array($fileExtension, ['xlsx', 'xls']);

            // Read the file data
            if ($isExcel) {
                $data = Excel::toArray([], $fullPath)[0]; // Get first sheet
            } else {
                $data = static::readCsvFile($fullPath);
            }

            if (empty($data)) {
                throw new \Exception('No data found in the file');
            }

            // Extract headers and data rows
            $headers = array_shift($data);
            $rows = $data;

            // Process the data
            $results = static::processTransactionData($headers, $rows);

            // Clean up the uploaded file
            if (Storage::disk('local')->exists($filePath)) {
                Storage::disk('local')->delete($filePath);
            } else {
                unlink($fullPath);
            }

            // Show success notification
            Notification::make()
                ->title('Import completed successfully!')
                ->body("Imported {$results['imported']} transactions. Skipped {$results['skipped']} duplicates/invalid records.")
                ->success()
                ->send();

        } catch (\Exception $e) {
            // Clean up the uploaded file on error
            if (isset($filePath) && Storage::disk('local')->exists($filePath)) {
                Storage::disk('local')->delete($filePath);
            } elseif (isset($fullPath) && file_exists($fullPath)) {
                unlink($fullPath);
            }

            Notification::make()
                ->title('Import failed')
                ->body("Error: {$e->getMessage()}")
                ->danger()
                ->send();
        }
    }

    private static function readCsvFile(string $filePath): array
    {
        $data = [];
        $handle = fopen($filePath, 'r');
        
        if (!$handle) {
            throw new \Exception("Could not open CSV file");
        }

        while (($row = fgetcsv($handle)) !== FALSE) {
            $data[] = $row;
        }

        fclose($handle);
        return $data;
    }

    private static function processTransactionData(array $headers, array $rows): array
    {
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            try {
                $data = array_combine($headers, $row);
                
                if (empty($data) || !static::isValidTransactionRow($data)) {
                    $skipped++;
                    continue;
                }

                $transactionData = static::mapRowToTransaction($data);
                
                if (static::isDuplicate($transactionData['transaction_id'])) {
                    $skipped++;
                    continue;
                }

                Transaction::create($transactionData);
                $imported++;

            } catch (\Exception $e) {
                $skipped++;
                // Log the error but continue processing
                \Log::warning('Failed to process transaction row', [
                    'error' => $e->getMessage(),
                    'row' => $row
                ]);
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    private static function isValidTransactionRow(array $data): bool
    {
        // Check required fields
        $requiredFields = [
            'Payment Date (America/Los_Angeles)',
            'Total Amount',
            'Email',
            'Payment Status'
        ];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }

        // Check if payment is successful
        $status = strtolower($data['Payment Status'] ?? '');
        if (in_array($status, ['failed', 'cancelled', 'declined'])) {
            return false;
        }

        // Check if amount is positive
        if ((float)($data['Total Amount'] ?? 0) <= 0) {
            return false;
        }

        return true;
    }

    private static function mapRowToTransaction(array $data): array
    {
        // Generate transaction ID
        $transactionId = static::generateTransactionId($data);
        
        // Parse payment date
        $paymentDate = Carbon::createFromFormat('Y-m-d H:i:s', $data['Payment Date (America/Los_Angeles)'], 'America/Los_Angeles');
        
        // Determine transaction type
        $type = static::determineTransactionType($data);
        
        // Build response payload with all data
        $response = [
            'donation_id' => $transactionId,
            'donor_email' => $data['Email'],
            'amount' => (float) $data['Total Amount'],
            'currency' => 'USD',
            'status' => static::mapPaymentStatus($data['Payment Status']),
            'type' => $type,
            'is_recurring' => ($data['Recurring Status'] ?? '') === 'Active',
            'donor_name' => trim(($data['First Name'] ?? '') . ' ' . ($data['Last Name'] ?? '')),
            'payment_method' => static::mapPaymentMethod($data['Payment Method'] ?? ''),
            'timestamp' => $paymentDate->toISOString(),
            'campaign' => static::mapCampaign($data['Campaign Title'] ?? ''),
            'note' => $data['Note'] ?? null,
            'tax_receipt_number' => $data['Tax Receipt #'] ?? null,
            'tax_receipt_url' => $data['Tax Receipt URL'] ?? null,
            'eligible_amount' => isset($data['Eligible Amount']) ? (float) $data['Eligible Amount'] : null,
            'company_name' => $data['Company Name'] ?? null,
            'address' => $data['Address'] ?? null,
            'city' => $data['City'] ?? null,
            'postal_code' => $data['Postal Code'] ?? null,
            'state' => $data['State'] ?? null,
            'country' => $data['Country'] ?? null,
            'in_honour_memory_of' => $data['In Honour/Memory of'] ?? null,
            'source' => 'zeffy_import'
        ];

        // Add any additional questions dynamically
        $additionalQuestions = static::getAdditionalQuestions($data);
        if (!empty($additionalQuestions)) {
            $response['additional_questions'] = $additionalQuestions;
        }

        return [
            'transaction_id' => $transactionId,
            'email' => strtolower(trim($data['Email'])),
            'amount' => (float) $data['Total Amount'],
            'currency' => 'USD',
            'type' => $type,
            'response' => $response,
            'created_at' => $paymentDate,
        ];
    }

    private static function generateTransactionId(array $data): string
    {
        $email = $data['Email'];
        $amount = $data['Total Amount'];
        $date = $data['Payment Date (America/Los_Angeles)'];
        
        $hash = substr(md5($email . $amount . $date), 0, 8);
        return "zeffy_import_{$hash}";
    }

    private static function determineTransactionType(array $data): string
    {
        if (($data['Recurring Status'] ?? '') === 'Active') {
            return 'recurring';
        }
        
        if (!empty($data['Company Name'])) {
            return 'sponsorship';
        }
        
        return 'donation';
    }

    private static function mapPaymentStatus(string $status): string
    {
        return match (strtolower($status)) {
            'completed', 'success', 'paid' => 'completed',
            'pending', 'processing' => 'pending',
            'failed', 'cancelled', 'declined' => 'failed',
            default => strtolower($status),
        };
    }

    private static function mapPaymentMethod(string $method): string
    {
        return match (strtolower($method)) {
            'credit card', 'card' => 'credit_card',
            'bank transfer', 'ach' => 'bank_transfer',
            'paypal' => 'paypal',
            default => str_replace(' ', '_', strtolower($method)),
        };
    }

    private static function mapCampaign(string $campaign): string
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

    private static function getAdditionalQuestions(array $data): array
    {
        $standardColumns = [
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

        $additionalQuestions = [];
        
        foreach ($data as $column => $value) {
            if (!in_array($column, $standardColumns) && !empty($value)) {
                $additionalQuestions[$column] = $value;
            }
        }
        
        return $additionalQuestions;
    }

    private static function isDuplicate(string $transactionId): bool
    {
        return Transaction::where('transaction_id', $transactionId)->exists();
    }
}