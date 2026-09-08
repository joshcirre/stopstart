<?php

namespace App\Http\Requests\Concerns;

use App\Models\Project;
use App\Support\OwnerToken;

/**
 * Runs the owner check during authorization, which happens before
 * validation. Without it a non-owner's malformed request fails validation
 * first and answers with a 422, which tells them the project id exists;
 * aborting with a 404 keeps unowned ids indistinguishable from missing ones.
 */
trait AuthorizesProjectOwner
{
    public function authorize(): bool
    {
        $route = $this->route();

        // Routes bound by {project:remote_token} are already authorized by
        // the unguessable token in the URL — a paired phone has no owner
        // cookie, so demanding one here would lock the dub flow out.
        if ($route?->bindingFieldFor('project') === 'remote_token') {
            return true;
        }

        $project = $route?->parameter('project');

        abort_unless(
            $project instanceof Project
                && $project->owner_token === OwnerToken::from($this),
            404,
        );

        return true;
    }
}
