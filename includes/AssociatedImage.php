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
			$parser->getOutput()->setPageProperty( 'associatedImage', $parameter );
		}

		// Return the text received as a parameter (for convenient use in templates).
		return $parameter;
	}

	/**
	 * Remember the parameter of {{#set_associated_index:}} in page_props table.
	 * @param Parser $parser
	 * @param string $parameter
	 * @param string $htmlAnchor
	 * @return string
	 */
	public static function pfSetAssociatedIndex( Parser $parser, $parameter, $htmlAnchor = '' ) {
		$index = intval( $parameter );
		if ( $index < 1 ) {
			$index = 1;
		}
		$parser->getOutput()->setPageProperty( "associatedPageIndex.$index", $htmlAnchor );

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
		$index = intval( $index );
		if ( $index < 1 ) {
			// "File:Something.pdf?page=1" is the same thing as "File:Something.pdf".
			$index = 1;
		}

		$dbr = wfGetDB( DB_REPLICA );
		$row = $dbr->selectRow(
			[
				'a' => 'page_props',
				'b' => 'page_props'
			],
			[
				'a.pp_page AS page',
				'b.pp_value AS anchor'
			],
			[
				'a.pp_propname' => 'associatedImage',
				'a.pp_value' => $imageTitle->getDBKey()
			],
			__METHOD__,
			[],
			[
				'b' => [ 'INNER JOIN', [
					'a.pp_page=b.pp_page',
					'b.pp_propname' => "associatedPageIndex.$index"
				] ]
			]
		);

		$logger = \MediaWiki\Logger\LoggerFactory::getInstance( 'ImageLinks' );
		$logger->debug( 'findPageByImage(): looking for associatedImage="{image}" ' .
			'with {associatedPageIndex}={index}: {isFound}',
			[
				'image' => $imageTitle->getDBKey(),
				'index' => $index === null ? '(null)' : $index,
				'isFound' => $row ? ( 'Found: pageID=' . $row->page ) : 'Not found'
			]
		);

		if ( !$row ) {
			return null;
		}

		$title = Title::newFromID( $row->page );
		if ( $title && $row->anchor ) {
			$title = $title->createFragmentTarget( $row->anchor );
		}

		return $title;
	}
}
