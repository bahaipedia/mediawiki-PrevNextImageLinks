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

use Html;
use ImagePage;
use Parser;
use Title;

class PageFinder {
	/** @var ImagePage */
	protected $page;

	/**
	 * @var int|null
	 * Number of PDF page (if applicable), null otherwise.
	 */
	protected $index;

	/**
	 * @param ImagePage $page Currently viewed file.
	 * @param int|null $index
	 */
	public function __construct( ImagePage $page, $index ) {
		$this->page = $page;
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

		// Check for {{#set_prev_next:}} override.
		$lang = $this->page->getContext()->getLanguage();
		$description = $this->page->getFile()->getDescriptionText( $lang );

		$matches = null;
		if ( preg_match( '/data-prevnext="([^"]*)"/', $description, $matches ) ) {
			$overrides = explode( '|', html_entity_decode( $matches[1], ENT_QUOTES ) );
			$prevOverride = $overrides[0];
			$nextOverride = $overrides[1] ?? ''; // In case someone added "data-prevnext" manually.

			if ( $prevOverride ) {
				$overrideTitle = Title::makeTitleSafe( NS_FILE, $prevOverride );
				if ( $overrideTitle ) {
					$prevTitles[] = $overrideTitle;
				}
			}
			if ( $nextOverride ) {
				$overrideTitle = Title::makeTitleSafe( NS_FILE, $nextOverride );
				if ( $overrideTitle ) {
					$nextTitles[] = $overrideTitle;
				}
			}

			if ( $prevTitles && $nextTitles ) {
				// Both explicitly set.
				return [ $prevTitles, $nextTitles ];
			}
		}

		// Try to find a number before extension, e.g. "123" in "Something 123.png".
		$filename = $this->page->getTitle()->getText(); // E.g. "Something 123.png"
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
		if ( $newNumber < 0 ) {
			return [];
		}

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

		return $result;
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
		return AssociatedImage::findPageByImage( $this->page->getTitle(), $this->index );
	}

	/**
	 * Remember the parameter of {{#set_prev_next:}} in page_props table.
	 * @param Parser $parser @phan-unused-param
	 * @param string $prev
	 * @param string $next
	 * @return string
	 */
	public static function pfSetPrevNext( Parser $parser, $prev, $next = '' ) {
		$prev = trim( strtr( $prev, ' ', '_' ) );
		$next = trim( strtr( $next, ' ', '_' ) );

		if ( $prev || $next ) {
			// Invisible tag that will be parsed in ImagePageShowTOC hook.
			return Html::rawElement( 'span', [ 'data-prevnext' => $prev . '|' . $next ] );
		}

		return '';
	}
}
