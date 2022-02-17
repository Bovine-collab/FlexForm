<?php

namespace FlexForm\Render\Themes\Plain;

use FlexForm\Core\Core;
use FlexForm\Render\Themes\CreateRenderer;

class PlainCreateRenderer implements CreateRenderer {
    /**
     * @inheritDoc
     */
    public function render_create( ?string $follow, ?string $template, ?string $createId, ?string $write, ?string $slot, ?string $option, ?string $fields, bool $leadingZero ): string {
        $template = $template !== null ? htmlspecialchars( $template ) : '';
        $createId = $createId !== null ? htmlspecialchars( $createId ) : '';
        $write = $write !== null ? htmlspecialchars( $write ) : '';
        $slot = $slot !== null ? htmlspecialchars( $slot ) : '';
        $option = $option !== null ? htmlspecialchars( $option ) : '';
        $fields = $fields !== null ? htmlspecialchars( $fields ) : '';

        if ( $follow !== null ) {
            $follow = $follow === '' || $follow === '1' ? 'true' : htmlspecialchars( $follow );
            $follow = Core::createHiddenField( 'mwfollow', $follow );
        } else {
            $follow = '';
        }

        if ( $fields !== '' ) {
            // TODO: Support mwleadingzero with mwcreatemultiple
            $createValue =
                $template . '-^^-' .
                $write . '-^^-' .
                $option . '-^^-' .
                $fields . '-^^-' .
                $slot . '-^^-' .
                $createId;

            return Core::createHiddenField( 'mwcreatemultiple[]', $createValue ) . $follow;
        } else {
            if ( $template !== '' ) {
                $template = Core::createHiddenField( 'mwtemplate', $template );
            }

            if ( $write !== '' ) {
                $write = Core::createHiddenField( 'mwwrite', $write );
            }

            if ( $option !== '' ) {
                $option = Core::createHiddenField( 'mwoption', $option );
            }

            if ( $slot !== '' ) {
                $slot = Core::createHiddenField( 'mwslot', $slot );
            }

            $leadingZero = $leadingZero ? Core::createHiddenField( 'mwleadingzero', 'true' ) : '';

            return $template . $write . $option . $follow . $leadingZero . $slot;
        }
    }
}