<?php

/*
	Extension:PrevNextImageLinks - MediaWiki extension.
	Copyright (C) 2020-2021 Edward Chernenko.

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
use Title;

class PageFinder {
	/** @var Title */
	protected $title;

	/**
	 * @var int|null
	 * Number of PDF page (if applicable), null otherwise.
	 */
	protected $index;

	/**
	 * @param Title $title Name of the currently viewed file.
	 * @param int|null $index
	 */
	public function __construct( Title $title, $index ) {
		$this->title = $title;
		$this->index = $index;
	}

	/**
	 * Return an array of prev/next titles (if filename ends with a number) or nulls (if it doesn't).
	 * Doesn't check for existence of these files.
	 * @return array
	 * @phan-return array{0:Title|null,1:Title|null}
	 */
	public function findPrevNext() {
		$prevTitle = null;
		$nextTitle = null;

		// Check if Next/Prev links were explicitly set ({{#set_next_image:}}) on the page $this->title.
		$dbr = wfGetDB( DB_REPLICA );
		$prevNextOverride = $dbr->selectField( 'page_props', 'pp_value',
			[
				'pp_page' => $this->title->getArticleId(),
				'pp_propname' => 'prevNextImage'
			],
			__METHOD__
		);

		if ( $prevNextOverride ) {
			[ $prevOverride, $nextOverride ] = explode( '|', $prevNextOverride );
			if ( $prevOverride ) {
				$prevTitle = Title::makeTitleSafe( NS_FILE, $prevOverride );
			}
			if ( $nextOverride ) {
				$nextTitle = Title::makeTitleSafe( NS_FILE, $nextOverride );
			}
		}

		if ( !$prevTitle || !$nextTitle ) {
			// Try to find a number before extension, e.g. "123" in "Something 123.png".
			$filename = $this->title->getText(); // E.g. "Something 123.png"
			$matches = null;
			if ( preg_match( '/([0-9]+)\.([^.]+$)/', $filename, $matches ) ) {
				$baseFilename = substr( $filename, 0, -1 * strlen( $matches[0] ) ); // E.g. "Something ".
				$number = intval( $matches[1] ); // E.g. 123
				$extension = $matches[2]; // E.g. "png".

				if ( !$prevTitle ) {
					$prevTitle = Title::makeTitle( NS_FILE, $baseFilename . ( $number - 1 ) . '.' . $extension );
				}
				if ( !$nextTitle ) {
					$nextTitle = Title::makeTitle( NS_FILE, $baseFilename . ( $number + 1 ) . '.' . $extension );
				}
			}
		}

		return [ $prevTitle, $nextTitle ];
	}

	/**
	 * Find title of the article (if any) that (according to several naming conventions)
	 * would contain the formatted wikitext related/equal to what is shown on this image/PDF.
	 * For example, "Something_Vol5_Issue2.pdf" -> "Something/Volume_5/Issue_2/Text".
	 * @return Title|null
	 */
	public function findAssociatedArticle() {
		// It's possible that some article has {{#set_associated_image:}},
		// in which case we can easily detect it.
		return AssociatedImage::findPageByImage( $this->title, $this->index );
	}

	/**
	 * Remember the parameter of {{#set_prev_next:}} in page_props table.
	 * @param Parser $parser
	 * @param string $prev
	 * @param string $next
	 */
	public static function pfSetPrevNext( Parser $parser, $prev, $next = '' ) {
		$prev = trim( strtr( $prev, ' ', '_' ) );
		$next = trim( strtr( $next, ' ', '_' ) );

		if ( $prev || $next ) {
			$parser->getOutput()->setProperty( 'prevNextImage', $prev . '|' . $next );
		}
	}
}
