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

use Title;

class PageFinder {
	/** @var Title */
	protected $title;

	/**
	 * @param Title $title Name of the currently viewed file.
	 */
	public function __construct( Title $title ) {
		$this->title = $title;
	}

	/**
	 * Return an array of prev/next titles (if filename ends with a number) or nulls (if it doesn't).
	 * Doesn't check for existence of these files.
	 * @return array
	 * @phan-return array{0:Title|null,1:Title|null}
	 */
	public function findPrevNext() {
		$filename = $this->title->getText(); // E.g. "Something 123.png"

		// Try to find a number before extension, e.g. "123" in "Something 123.png".
		$matches = null;
		if ( !preg_match( '/([0-9]+)\.([^.]+$)/', $filename, $matches ) ) {
			// Not found.
			return [ null, null ];
		}

		$baseFilename = substr( $filename, 0, -1 * strlen( $matches[0] ) ); // E.g. "Something ".
		$number = intval( $matches[1] ); // E.g. 123
		$extension = $matches[2]; // E.g. "png".

		$prevTitle = Title::makeTitle( NS_FILE, $baseFilename . ( $number - 1 ) . '.' . $extension );
		$nextTitle = Title::makeTitle( NS_FILE, $baseFilename . ( $number + 1 ) . '.' . $extension );

		return [ $prevTitle, $nextTitle ];
	}

	/**
	 * Find title of the article (if any) that (according to several naming conventions)
	 * would contain the formatted wikitext related/equal to what is shown on this image/PDF.
	 * For example, "Something_Vol5_Issue2.pdf" -> "Something/Volume_5/Issue_2/Text".
	 * @return Title
	 */
	public function findAssociatedArticle() {
		$varSeries = $varVolume = $varIssue = null;

		$filename = $this->title->getText();

		// Remove extension, e.g. ".pdf" or ".png". No need to verify if it's a correct extension,
		// because $this->title is an already uploaded (existing) file.
		$filename = preg_replace( '/\.[^\.]+$/', '', $filename );

		// Find variable "Volume", if any.
		$regex = '/Vol(ume|)([0-9]+)/';
		if ( preg_match( $regex, $filename, $matches ) ) {
			$varVolume = $matches[2];
			$filename = preg_replace( $regex, '', $filename );
		}

		// Find variable "Issue", if any.
		$regex = '/Issue([0-9]+)/';
		if ( preg_match( $regex, $filename, $matches ) ) {
			$varIssue = $matches[1];
			$filename = preg_replace( $regex, '', $filename );
		}

		// TODO: find strings like Something_January_1800, treat this as "Something/1800/January/Text".

		// Trim $filename and remove any duplicate spaces (caused by removal of Issue, etc.).
		$filename = trim( preg_replace( '/ {2}/', ' ', $filename ) );

		// If filename ends with a number, treat it as variable "Series".
		$regex = '/[0-9]+$/';
		if ( preg_match( $regex, $filename, $matches ) ) {
			$varSeries = $matches[0];
			$filename = preg_replace( $regex, '', $filename );
		}

		// Everything that remained in $filename is a potential article name.
		$potentialPageName = $filename;
		if ( $varSeries ) {
			// No space between "Series" and number.
			$potentialPageName .= '/Series' . $varSeries;
		}
		if ( $varVolume ) {
			$potentialPageName .= '/Volume ' . $varVolume;
		}
		if ( $varIssue ) {
			$potentialPageName .= '/Issue ' . $varIssue;
		}
		$potentialPageName .= '/Text';

		return Title::newFromText( $potentialPageName );
	}
}
