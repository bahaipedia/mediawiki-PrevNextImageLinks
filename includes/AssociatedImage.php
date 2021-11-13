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
		if ( $index > 1 ) {
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
		if ( $index <= 1 ) {
			// "File:Something.pdf?page=1" is the same thing as "File:Something.pdf".
			$index = null;
		}

		$dbr = wfGetDB( DB_REPLICA );
		$row = $dbr->selectRow(
			[
				'a' => 'page_props',
				'b' => 'page_props'
			],
			'a.pp_page AS page',
			[
				'a.pp_propname' => 'associatedImage',
				'a.pp_value' => $imageTitle->getDBKey(),
				'b.pp_value' => $index
			],
			__METHOD__,
			[],
			[
				'b' => [ 'LEFT JOIN', [
					'a.pp_page=b.pp_page',
					'b.pp_propname' => 'associatedPageIndex'
				] ]
			]
		);

		$articleId = $row->page;
		if ( !$articleId ) {
			return null;
		}

		return Title::newFromID( $articleId );
	}
}
