<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lab404\Impersonate\Services\ImpersonateManager;
use Symfony\Component\HttpFoundation\Response;

class BlockImpersonationInStaffPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app(ImpersonateManager::class)->isImpersonating()) {
            return redirect('/member');
        }

        return $next($request);
    }
}
