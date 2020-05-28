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
		list( $prevTitle, $nextTitle ) = self::getPrevNextTitles( $page->getTitle() );

		$services = MediaWikiServices::getInstance();
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$repo = $services->getRepoGroup();

		// "Previous file" link. Not shown if the previous file doesn't exist.
		if ( $prevTitle && $repo->findFile( $prevTitle ) ) {
			$prevLink = $linkRenderer->makeKnownLink( $prevTitle,
				wfMessage( 'prevnextimage-previous-file' )->plain()
			);
			$toc[] = Html::rawElement( 'li', [ 'id' => 'prevnextlinks-prev' ], $prevLink );
		}

		// Between Prev/Next link: direct link to Download the currently viewed file.
		$downloadLink = $page->getTitle()->getText() . ' (' . Html::element( 'a', [
			'href' => $page->getFile()->getFullURL()
		], wfMessage( 'prevnextimage-download' )->plain() ) . ')';
		$toc[] = Html::rawElement( 'li', [ 'id' => 'prevnextlinks-download' ], $downloadLink );

		// "Next file" link. Not shown if the next file doesn't exist.
		if ( $nextTitle && $repo->findFile( $nextTitle ) ) {
			$nextLink = $linkRenderer->makeKnownLink( $nextTitle,
				wfMessage( 'prevnextimage-next-file' )->plain()
			);
			$toc[] = Html::rawElement( 'li', [ 'id' => 'prevnextlinks-next' ], $nextLink );
		}

		return true;
	}

	/**
	 * Return an array of prev/next titles (if filename ends with a number) or nulls (if it doesn't).
	 * Doesn't check for existence of these files.
	 * @param Title $title Name of the currently viewed file.
	 * @return array
	 * @phan-return array{0:Title|null,1:Title|null}
	 */
	protected static function getPrevNextTitles( Title $title ) {
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
