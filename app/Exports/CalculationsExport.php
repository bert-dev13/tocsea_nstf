<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CalculationsExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private Builder $query
    ) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'Date/Time',
            'User',
            'Location',
            'Model/Equation',
            'Predicted Soil Loss (m²/year)',
            'Risk Level',
            'Status',
        ];
    }

    public function map($row): array
    {
        $result = (float) $row->result;
        $formatted = number_format($result, 2) . ' m²/year';

        return [
            $row->created_at->format('M j, Y g:i A'),
            $row->user?->name ?? '—',
            $row->location_display,
            $row->equation_name ?? '—',
            $formatted,
            $row->risk_level,
            'Completed',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
