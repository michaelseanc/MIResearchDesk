<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the request to the authenticated user's tenant: activates the BelongsToOrganization global
 * scope for their organization and tells Spatie which team (organization) to resolve roles against.
 * Runs inside the authenticated middleware stack, so $request->user() is always present here.
 */
class SetOrganizationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            Organization::useOrganization($user->organization_id);
            app(PermissionRegistrar::class)->setPermissionsTeamId($user->organization_id);
        }

        return $next($request);
    }
}
