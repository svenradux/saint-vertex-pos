<?php

/** Copyright © 2026 Saint Vertex. Original branding listener. */

namespace Modules\SaintVertex\Listeners;

use App\Events\RenderHeaderEvent;

class BrandHeaderListener
{
    public function handle( RenderHeaderEvent $event ): void
    {
        $event->output->addView( 'SaintVertex::header' );
    }
}
