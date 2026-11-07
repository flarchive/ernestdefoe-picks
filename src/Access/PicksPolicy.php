<?php

namespace Resofire\Picks\Access;

use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class PicksPolicy extends AbstractPolicy
{
    /**
     * Abilities passed here are the full strings e.g. 'picks.manage', 'picks.view'.
     * PHP method names cannot contain dots, so we use the can() catch-all.
     * Admins are always allowed. Other users need the matching permission.
     */
    public function can(User $actor, string $ability): ?string
    {
        if ($actor->isAdmin()) {
            return $this->allow();
        }

        // Only handle abilities prefixed with 'picks.'
        if (strncmp($ability, 'picks.', 6) !== 0) {
            return null;
        }

        return $actor->hasPermission($ability)
            ? $this->allow()
            : null;
    }
}
