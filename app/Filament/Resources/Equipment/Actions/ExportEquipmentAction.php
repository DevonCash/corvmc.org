<?php

namespace App\Filament\Resources\Equipment\Actions;

use App\Models\Equipment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Response;

class ExportEquipmentAction
{
    public static function make(): Action
    {
        return Action::make('export_equipment')
            ->label('Export Equipment')
            ->icon('tabler-file-export')
            ->color('info')
            ->modalWidth('md')
            ->modalHeading('Export Equipment Data')
            ->modalDescription('Export equipment information to CSV or Excel format')
            ->schema([
                Select::make('format')
                    ->label('Export Format')
                    ->options([
                        'csv' => 'CSV',
                        'xlsx' => 'Excel (XLSX)',
                    ])
                    ->default('csv')
                    ->required(),

                Select::make('scope')
                    ->label('Equipment to Export')
                    ->options([
                        'all' => 'All Equipment',
                        'available' => 'Available Only',
                        'checked_out' => 'Checked Out Only',
                        'kits' => 'Kits Only',
                        'components' => 'Components Only',
                        'donated' => 'Donated Equipment',
                        'loaned_to_cmc' => 'Loaned to CMC',
                    ])
                    ->default('all')
                    ->required(),

                CheckboxList::make('fields')
                    ->label('Fields to Include')
                    ->options([
                        'name' => 'Name',
                        'type' => 'Type',
                        'brand' => 'Brand',
                        'model' => 'Model',
                        'serial_number' => 'Serial Number',
                        'condition' => 'Condition',
                        'status' => 'Status',
                        'location' => 'Location',
                        'estimated_value' => 'Estimated Value',
                        'acquisition_type' => 'Acquisition Type',
                        'acquisition_date' => 'Acquisition Date',
                        'provider_display' => 'Provider',
                        'is_kit' => 'Is Kit',
                        'can_lend_separately' => 'Can Lend Separately',
                        'current_borrower' => 'Current Borrower',
                        'loan_due_date' => 'Loan Due Date',
                        'description' => 'Description',
                        'notes' => 'Notes',
                    ])
                    ->default([
                        'name', 'type', 'brand', 'model', 'condition',
                        'status', 'estimated_value', 'acquisition_type',
                    ])
                    ->required()
                    ->columns(2),
            ])
            ->action(function (array $data) {
                // Build query based on scope
                $query = Equipment::query();

                switch ($data['scope']) {
                    case 'available':
                        $query->available();
                        break;
                    case 'checked_out':
                        $query->where('status', 'checked_out');
                        break;
                    case 'kits':
                        $query->kits();
                        break;
                    case 'components':
                        $query->components();
                        break;
                    case 'donated':
                        $query->donated();
                        break;
                    case 'loaned_to_cmc':
                        $query->onLoanToCmc();
                        break;
                }

                // Get equipment with relationships
                $equipment = $query->with(['provider', 'currentLoan.borrower', 'parent'])->get();

                // Prepare data
                $exportData = $equipment->map(function ($item) use ($data) {
                    $row = [];

                    foreach ($data['fields'] as $field) {
                        $row[$field] = match ($field) {
                            'name' => $item->name,
                            'type' => ucwords(str_replace('_', ' ', $item->type)),
                            'brand' => $item->brand,
                            'model' => $item->model,
                            'serial_number' => $item->serial_number,
                            'condition' => ucwords(str_replace('_', ' ', $item->condition)),
                            'status' => ucwords(str_replace('_', ' ', $item->status)),
                            'location' => $item->location,
                            'estimated_value' => $item->estimated_value ? '$'.number_format($item->estimated_value, 2) : '',
                            'acquisition_type' => ucwords(str_replace('_', ' ', $item->acquisition_type)),
                            'acquisition_date' => $item->acquisition_date?->format('Y-m-d'),
                            'provider_display' => $item->provider_display,
                            'is_kit' => $item->is_kit ? 'Yes' : 'No',
                            'can_lend_separately' => $item->can_lend_separately ? 'Yes' : 'No',
                            'current_borrower' => $item->currentLoan?->borrower?->name ?? '',
                            'loan_due_date' => $item->currentLoan?->due_at?->format('Y-m-d H:i') ?? '',
                            'description' => $item->description,
                            'notes' => $item->notes,
                            default => $item->$field ?? '',
                        };
                    }

                    return $row;
                });

                // Generate filename
                $timestamp = now()->format('Y-m-d_H-i-s');
                $scope = $data['scope'] === 'all' ? 'equipment' : $data['scope'].'_equipment';
                $filename = "cmc_{$scope}_export_{$timestamp}.{$data['format']}";

                // Create headers
                $headers = array_map(fn ($field) => ucwords(str_replace('_', ' ', $field)), $data['fields']);

                if ($data['format'] === 'csv') {
                    // Generate CSV
                    $output = fopen('php://output', 'w');

                    return Response::streamDownload(function () use ($output, $headers, $exportData) {
                        fputcsv($output, $headers);
                        foreach ($exportData as $row) {
                            fputcsv($output, array_values($row));
                        }
                        fclose($output);
                    }, $filename, [
                        'Content-Type' => 'text/csv',
                    ]);
                }

                // For Excel format, you'd need a package like maatwebsite/excel
                // This is a placeholder for now
                throw new \Exception('Excel export not yet implemented. Please use CSV format.');
            })
            ->modalIcon('tabler-file-export')
            ->visible(fn () => User::me()?->can('export equipment'));
    }
}
