<?php

namespace App\Policies;

use App\Models\SavedEquation;
use App\Models\User;

class SavedEquationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SavedEquation $savedEquation): bool
    {
        return $savedEquation->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SavedEquation $savedEquation): bool
    {
        return $savedEquation->user_id === $user->id;
    }

    public function delete(User $user, SavedEquation $savedEquation): bool
    {
        return $savedEquation->user_id === $user->id;
    }
}
