<?php

/**
 * Copyright © 2026 Saint Vertex.
 * Original Saint Vertex branding integration; upstream components retain their ownership.
 */

namespace Modules\SaintVertex\Providers;

use App\Classes\Hook;
use App\Widgets\MyNexoPosWidget;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as BladeView;
use Modules\SaintVertex\Http\Middleware\LocalOnlyIntegrations;
use Modules\SaintVertex\Services\BrandingText;

class BrandingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        config( ['saintvertex.offline' => true] );
        config( ['mail.markdown.paths' => array_merge(
            [__DIR__ . '/../Resources/Views/mail'],
            config( 'mail.markdown.paths', [] )
        )] );
        $this->callAfterResolving( Kernel::class, function ( Kernel $kernel ): void {
            $kernel->appendMiddlewareToGroup( 'web', LocalOnlyIntegrations::class );
            $kernel->appendMiddlewareToGroup( 'api', LocalOnlyIntegrations::class );
        } );
        Hook::addFilter( 'ns.langFiles', function ( array $files ): array {
            $files['NexoPOS'] = asset( 'modules/saintvertex/lang/' . app()->getLocale() . '.json' );

            return $files;
        } );
        Hook::addFilter( 'ns-dashboard-menus', function ( array $menus ): array {
            unset( $menus['modules']['childrens']['marketplace'] );

            return $menus;
        } );
        Hook::addFilter( 'ns-user-profile-form', function ( array $tabs ): array {
            foreach ( $tabs['attribute']['fields'] as &$field ) {
                if ( $field['name'] === 'theme' ) {
                    array_unshift( $field['options'], ['label' => __m( 'Ikuti tema toko', 'SaintVertex' ), 'value' => ''] );
                    $field['description'] = __m( 'Tema pribadi diterapkan setelah disimpan. Pilih Ikuti tema toko untuk memakai tema default toko.', 'SaintVertex' );
                }
            }

            return $tabs;
        } );
        View::getFinder()->prependLocation( __DIR__ . '/../Resources/Overrides' );
        View::composer( 'mails.welcome-mail', function ( BladeView $view ): void {
            $view->with( 'options', ns()->option );
        } );
        View::composer( 'pages.dashboard.about', function ( BladeView $view ): void {
            $view->with( 'details', array_merge( [
                __m( 'Produk', 'SaintVertex' ) => 'Vertex POS',
                __m( 'Brand', 'SaintVertex' ) => 'Saint Vertex',
                __m( 'Hak cipta kontribusi asli Saint Vertex', 'SaintVertex' ) => 'Copyright © 2026 Saint Vertex',
            ], $view->getData()['details'] ?? [] ) );
        } );
        Hook::addFilter( 'ns-views:pages.sign-in', fn ( string $view ): string => 'SaintVertex::auth.sign-in' );
        // The optional marketplace promotion is not part of this local store's dashboard.
        // Keep module administration and every operational widget available.
        Hook::addFilter( 'ns-dashboard-widgets', fn ( array $widgets ): array => array_values( array_filter(
            $widgets, fn ( string $widget ): bool => $widget !== MyNexoPosWidget::class
        ) ) );
        // Extend JSON translations so punctuation in native tour strings stays literal.
        // Append after the core language path so only these product-copy keys win.
        app( 'translation.loader' )->addPath( __DIR__ . '/../Resources/Lang' );
        // Core/module discovery may already have populated the JSON translation cache.
        app( 'translator' )->setLoaded( [] );
        View::composer( ['layout.base', 'layout.default', 'layout.dashboard', 'layout.dashboard-blank'], function ( BladeView $view ): void {
            $title = BrandingText::product( $view->getData()['title'] ?? 'Vertex POS' );
            if ( ! str_contains( $title, 'Vertex POS' ) ) {
                $title .= ' — Vertex POS';
            }
            $view->with( 'title', $title );
        } );
        Hook::addFilter( 'ns-page-title', fn ( string $format ): string => '%s — Vertex POS | Saint Vertex' );
        Hook::addFilter( 'ns-footer-signature', fn ( string $signature ): string => '© 2026 Saint Vertex' );
    }
}
