<?php

/*
	Extension:PrevNextImageLinks - MediaWiki extension.
	Copyright (C) 2021 Edward Chernenko.

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
*/

/**
 * @file
 *
 */

namespace MediaWiki\PrevNextImageLinks;

use Parser;

class AssociatedImage {
	/**
	 * Remember the parameter of {{#set_associated_image:}} in page_props table.
	 * @param Parser $parser
	 * @param string $parameter
	 * @return array
	 */
	public static function pfSetAssociatedImage( Parser $parser, $parameter ) {
		$parameter = trim( strtr( $parameter, ' ', '_' ) );
		if ( $parameter ) {
			$parser->getOutput()->setProperty( 'associatedImage', $parameter );
		}

		// Return the text received as a parameter (for convenient use in templates).
		return $parameter;
	}

	/**
	 * Remember the parameter of {{#set_associated_index:}} in page_props table.
	 * @param Parser $parser
	 * @param string $parameter
	 * @return array
	 */
	public static function pfSetAssociatedIndex( Parser $parser, $parameter ) {
		$parameter = intval( $parameter );
		if ( $parameter ) {
			$parser->getOutput()->setProperty( 'associatedPageIndex', $parameter );
		}

		// Return the text received as a parameter (for convenient use in templates).
		return $parameter;
	}
}
