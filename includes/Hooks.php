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
use MediaWiki\MediaWikiServices;

class Hooks {
	/**
	 * ImagePageShowTOC hook. Adds Prev/Next links to File: pages if their title ends with a number.
	 * @param ImagePage $page
	 * @param string[] &$toc
	 * @return bool
	 */
	public static function onImagePageShowTOC( ImagePage $page, array &$toc ) {
		$finder = new PageFinder;
		list( $prevTitle, $nextTitle ) = $finder->getPrevNext( $page->getTitle() );

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
}
