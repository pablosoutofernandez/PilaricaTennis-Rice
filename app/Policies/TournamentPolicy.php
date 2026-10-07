<?php

namespace App\Policies;

use App\Models\Tournament;
use App\Models\User;

class TournamentPolicy
{
    /** Cambiar cualquier cosa del torneo: el administrador o quien organice ese torneo. */
    public function manage(User $user, Tournament $tournament): bool
    {
        return $user->is_admin || $user->organizes($tournament);
    }
}
