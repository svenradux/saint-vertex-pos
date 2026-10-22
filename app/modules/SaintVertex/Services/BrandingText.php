<?php

/** Copyright © 2026 Saint Vertex. Original display-copy adapter. */

namespace Modules\SaintVertex\Services;

class BrandingText
{
    /** Display copy only: never pass identifiers, URLs or code. */
    public static function product( string $text ): string
    {
        $parts = preg_split( '~(<[^>]*>|https?://[^\s<>"\x27]+)~u', $text, flags: PREG_SPLIT_DELIM_CAPTURE );

        return implode( '', array_map( fn( string $part ): string => str_starts_with( $part, '<' ) || preg_match( '~^https?://~', $part )
            ? $part : preg_replace( '/\b(?:My\s+)?NexoPOS\b/u', 'Vertex POS', $part ), $parts ) );
    }

    /** Keep original translation keys and placeholders intact. */
    public static function translations( string $locale ): array
    {
        $source = json_decode( file_get_contents( lang_path( $locale . '.json' ) ), true, flags: JSON_THROW_ON_ERROR );
        $overrides = __DIR__ . '/../Resources/Lang/' . $locale . '.json';
        if ( is_file( $overrides ) ) {
            $source = array_replace( $source, json_decode( file_get_contents( $overrides ), true, flags: JSON_THROW_ON_ERROR ) );
        }

        return array_map( fn( string $value ): string => self::product( $value ), $source );
    }
}
