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
	/**
	 * Return an array of prev/next titles (if filename ends with a number) or nulls (if it doesn't).
	 * Doesn't check for existence of these files.
	 * @param Title $title Name of the currently viewed file.
	 * @return array
	 * @phan-return array{0:Title|null,1:Title|null}
	 */
	public function getPrevNext( Title $title ) {
		$filename = $title->getText(); // E.g. "Something 123.png"

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
}
