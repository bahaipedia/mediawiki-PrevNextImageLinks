<?php

/*
	Extension:PrevNextImageLinks - MediaWiki extension.
	Copyright (C) 2020 Edward Chernenko.

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
use MediaWiki\MediaWikiServices;
use Title;

class Hooks {
	/**
	 * ImagePageShowTOC hook. Adds Prev/Next links to File: pages if their title ends with a number.
	 * @param ImagePage $page
	 * @param string[] &$toc
	 * @return bool
	 */
	public static function onImagePageShowTOC( ImagePage $page, array &$toc ) {
		$filename = $page->getTitle()->getText(); // E.g. "Something 123.png"

		// Try to find a number before extension, e.g. "123" in "Something 123.png".
		$matches = null;
		if ( !preg_match( '/([0-9]+)\.([^.]+$)/', $filename, $matches ) ) {
			// Not found.
			return true;
		}

		$numberAndExtension = $matches[0]; // E.g. "123.png".
		$baseFilename = substr( $filename, 0, -1 * strlen( $matches[0] ) ); // E.g. "Something ".

		$number = intval( $matches[1] ); // E.g. 123
		$extension = $matches[2]; // E.g. "png".

		$services = MediaWikiServices::getInstance();
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$repo = $services->getRepoGroup();

		$links = [];

		// "Previous file" link. Not shown if the previous file doesn't exist.
		$prevTitle = Title::makeTitle( NS_FILE, $baseFilename . ( $number - 1 ) . '.' . $extension );
		if ( $repo->findFile( $prevTitle ) ) {
			$links[] = $linkRenderer->makeKnownLink( $prevTitle,
				wfMessage( 'prevnextimage-previous-file' )->plain()
			);
		}

		// Between Prev/Next link: direct link to Download the currently viewed file.
		$links[] = $filename . ' (' . Html::element( 'a', [
			'href' => $page->getFile()->getFullURL()
		], wfMessage( 'prevnextimage-download' )->plain() ) . ')';

		// "Next file" link. Not shown if the next file doesn't exist.
		$nextTitle = Title::makeTitle( NS_FILE, $baseFilename . ( $number + 1 ) . '.' . $extension );
		if ( $repo->findFile( $nextTitle ) ) {
			$links[] = $linkRenderer->makeKnownLink( $nextTitle,
				wfMessage( 'prevnextimage-next-file' )->plain()
			);
		}

		foreach ( $links as $html ) {
			$toc[] = Html::rawElement( 'li', [], $html );
		}
		return true;
	}
}
