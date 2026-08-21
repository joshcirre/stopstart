<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Project;
use App\Support\OwnerToken;
use Illuminate\Http\Request;

trait AuthorizesOwner
{
    /**
     * Abort with a 404 unless the request's owner cookie matches the project.
     */
    protected function authorizeOwner(Request $request, Project $project): void
    {
        abort_unless($project->owner_token === OwnerToken::from($request), 404);
    }
}
