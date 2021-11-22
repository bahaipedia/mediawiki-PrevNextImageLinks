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
	 * Return an array of possible prev/next titles (if filename ends with a number).
	 * Doesn't check for existence of these files.
	 * @return array
	 * @phan-return array{0:Title[],1:Title[]}
	 */
	public function findPrevNext() {
		$prevTitles = [];
		$nextTitles = [];

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
				$prevTitles[] = Title::makeTitle( NS_FILE, $prevOverride );
			}
			if ( $nextOverride ) {
				$nextTitles[] = Title::makeTitle( NS_FILE, $nextOverride );
			}
		}

		// Try to find a number before extension, e.g. "123" in "Something 123.png".
		$filename = $this->title->getText(); // E.g. "Something 123.png"
		$matches = null;
		if ( preg_match( '/([0-9]+)\.([^.]+$)/', $filename, $matches ) ) {
			$baseFilename = substr( $filename, 0, -1 * strlen( $matches[0] ) ); // E.g. "Something ".
			$numberAsString = $matches[1];
			$extension = $matches[2]; // E.g. "png".

			foreach ( $this->changeNumberInTitle( $numberAsString, -1 ) as $possiblePrevNumber ) {
				$prevTitles[] = Title::makeTitle( NS_FILE,
					$baseFilename . $possiblePrevNumber . '.' . $extension );
			}

			foreach ( $this->changeNumberInTitle( $numberAsString, 1 ) as $possibleNextNumber ) {
				$nextTitles[] = Title::makeTitle( NS_FILE,
					$baseFilename . $possibleNextNumber . '.' . $extension );
			}
		}

		return [ $prevTitles, $nextTitles ];
	}

	/**
	 * Given a number like "12" or "0012", add $diff (integer) to them
	 * and return an array of possible numbers (both preserving or not preserving leading zeroes).
	 *
	 * @param string $oldNumberAsString Part of filename that contains the number, e.g. "005".
	 * @param int $diff Change to the number, e.g. 1 or -1.
	 * @return string[]
	 */
	protected function changeNumberInTitle( $oldNumberAsString, $diff ) {
		$newNumber = intval( $oldNumberAsString ) + $diff;

		$result = [];
		$result[] = $newNumber;

		$newNumberAsString = (string)$newNumber;
		$oldLength = strlen( $oldNumberAsString );

		$lengthDecrease = $oldLength - strlen( $newNumberAsString );
		if ( $lengthDecrease > 0 ) {
			// When calculating $newNumber, we lost at least one digit.
			// We don't exactly know if we need a leading zero here: for example,
			// if $oldNumberAsString=100 and $diff=-1, then it's unknown if expected result is 99 or 099,
			// so we add both to $result.

			$result[] = str_pad( $newNumberAsString, $oldLength, '0', STR_PAD_LEFT );
		}

		// Filter out numbers below 0.
		return array_filter( $result, static function ( $value ) {
			return $value >= 0;
		} );
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
