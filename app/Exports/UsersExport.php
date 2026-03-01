<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UsersExport implements FromQuery, WithHeadings, WithMapping, WithStyles
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
            'Name',
            'Email',
            'Role',
            'Status',
            'Province',
            'Municipality/City',
            'Barangay',
            'Date Created',
            'Last Login',
        ];
    }

    public function map($user): array
    {
        return [
            $user->name,
            $user->email,
            $user->role_label,
            $user->status_label,
            $user->province ?? '—',
            $user->municipality ?? '—',
            $user->barangay ?? '—',
            $user->created_at->format('M j, Y'),
            $user->last_login_at?->format('M j, Y • g:i A') ?? '—',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
