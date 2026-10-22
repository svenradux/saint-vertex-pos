<?php

/** Copyright © 2026 Saint Vertex. Original branding listener. */

namespace Modules\SaintVertex\Listeners;

use App\Events\RenderFooterEvent;

class BrandFooterListener
{
    public function handle( RenderFooterEvent $event ): void
    {
        $event->output->addView( 'SaintVertex::ui-assets' );
    }
}
