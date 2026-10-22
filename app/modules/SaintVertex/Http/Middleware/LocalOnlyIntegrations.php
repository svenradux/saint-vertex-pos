<?php

/** Copyright © 2026 Saint Vertex. Original local integration policy. */

namespace Modules\SaintVertex\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LocalOnlyIntegrations
{
    public function handle( Request $request, Closure $next ): Response
    {
        if ( $request->is( 'api/marketplace', 'api/marketplace/*', 'dashboard/modules/marketplace', 'dashboard/oauth/mynexopos/*', 'oauth/mynexopos/*' ) ) {
            if ( $request->is( 'api/*' ) || $request->expectsJson() ) {
                return response()->json( ['status' => 'error', 'message' => __m( 'Integrasi cloud dinonaktifkan pada build lokal Vertex POS.', 'SaintVertex' )], 409 );
            }

            return redirect( auth()->check() ? '/dashboard/modules' : '/sign-in' )
                ->with( 'info', __m( 'Integrasi cloud dinonaktifkan pada build lokal Vertex POS.', 'SaintVertex' ) );
        }

        return $next( $request );
    }
}
