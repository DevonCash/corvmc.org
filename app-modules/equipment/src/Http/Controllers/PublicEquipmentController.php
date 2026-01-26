<?php

namespace CorvMC\Equipment\Http\Controllers;

use CorvMC\Equipment\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class PublicEquipmentController extends Controller
{
    /**
     * Display the equipment lending library catalog.
     */
    public function index(Request $request): View
    {
        $query = Equipment::query()
            ->with(['provider', 'currentLoan.borrower'])
            ->where('ownership_status', 'cmc_owned')
            ->whereIn('status', ['available', 'checked_out'])
            ->orderBy('type')
            ->orderBy('name');

        // Filter by type if specified
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by availability
        if ($request->filled('availability')) {
            if ($request->availability === 'available') {
                $query->available();
            } elseif ($request->availability === 'checked_out') {
                $query->where('status', 'checked_out');
            }
        }

        // Search by name, brand, or model
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $equipment = $query->paginate(12)->withQueryString();

        $statistics = [
            'total_equipment' => Equipment::where('ownership_status', 'cmc_owned')->count(),
            'available_equipment' => Equipment::available()->count(),
            'checked_out_equipment' => Equipment::where('status', 'checked_out')->count(),
        ];

        $equipmentTypes = Equipment::where('ownership_status', 'cmc_owned')
            ->whereIn('status', ['available', 'checked_out'])
            ->distinct()
            ->pluck('type')
            ->sort()
            ->values();

        return view('public.equipment.index', compact(
            'equipment',
            'statistics',
            'equipmentTypes'
        ));
    }

    /**
     * Display detailed view of specific equipment.
     */
    public function show(Equipment $equipment): View
    {
        // Only show CMC owned equipment
        if ($equipment->ownership_status !== 'cmc_owned') {
            abort(404);
        }

        $equipment->load([
            'provider',
            'currentLoan.borrower',
            'loans' => function ($query) {
                $query->latest('checked_out_at')->limit(5);
            },
            'loans.borrower',
            'media',
        ]);

        $relatedEquipment = Equipment::available()
            ->where('type', $equipment->type)
            ->where('id', '!=', $equipment->id)
            ->limit(4)
            ->get();

        return view('public.equipment.show', compact(
            'equipment',
            'relatedEquipment'
        ));
    }
}
