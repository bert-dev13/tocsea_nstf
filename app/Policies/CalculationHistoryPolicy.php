<?php

namespace App\Policies;

use App\Models\CalculationHistory;
use App\Models\User;

class CalculationHistoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CalculationHistory $calculationHistory): bool
    {
        return $calculationHistory->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, CalculationHistory $calculationHistory): bool
    {
        return $calculationHistory->user_id === $user->id;
    }
}
