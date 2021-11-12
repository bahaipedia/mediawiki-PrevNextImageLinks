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

use MediaWiki\Linker\LinkTarget;
use Parser;
use Title;

class AssociatedImage {
	/**
	 * Remember the parameter of {{#set_associated_image:}} in page_props table.
	 * @param Parser $parser
	 * @param string $parameter
	 * @return string
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
	 * @return string
	 */
	public static function pfSetAssociatedIndex( Parser $parser, $parameter ) {
		$index = intval( $parameter );
		if ( $index ) {
			$parser->getOutput()->setProperty( 'associatedPageIndex', $index );
		}

		// Return the text received as a parameter (for convenient use in templates).
		return $parameter;
	}

	/**
	 * Find Title of the page that has {{#set_associated_image:}} pointed at $imageTitle.
	 * @param LinkTarget $imageTitle
	 * @param int|null $index
	 * @return Title|null
	 */
	public static function findPageByImage( LinkTarget $imageTitle, $index ) {
		$dbr = wfGetDB( DB_REPLICA );
		$articleId = $dbr->selectField( 'page_props', 'pp_page', [
			'pp_propname' => 'associatedImage',
			'pp_value' => $imageTitle->getDBKey()
		], __METHOD__ );

		// TODO: select only pages with valid $index

		if ( !$articleId ) {
			return null;
		}

		return Title::newFromID( $articleId );
	}
}
