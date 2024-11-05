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
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Page\Hook\ImagePageShowTOCHook;
use RepoGroup;
use RequestContext;

class Hooks implements ImagePageShowTOCHook, ParserFirstCallInitHook {
	/** @var LinkRenderer */
	protected $linkRenderer;

	/** @var RepoGroup */
	protected $repoGroup;

	/**
	 * @param LinkRenderer $linkRenderer
	 * @param RepoGroup $repoGroup
	 */
	public function __construct( LinkRenderer $linkRenderer, RepoGroup $repoGroup ) {
		$this->linkRenderer = $linkRenderer;
		$this->repoGroup = $repoGroup;
	}

	/**
	 * ImagePageShowTOC hook. Adds Prev/Next links to File: pages if their title ends with a number.
	 *
	 * @inheritDoc
	 */
	public function onImagePageShowTOC( $page, &$toc ) {
		$title = $page->getTitle();

		$finder = new PageFinder(
			$page,
			RequestContext::getMain()->getRequest()->getIntOrNull( 'page' )
		);
		[ $prevTitles, $nextTitles ] = $finder->findPrevNext();
		$associatedArticleTitle = $finder->findAssociatedArticle();

		$logger = \MediaWiki\Logger\LoggerFactory::getInstance( 'ImageLinks' );
		if ( $associatedArticleTitle ) {
			$logger->debug( 'onImagePageShowTOC(): image="{image}", ' .
				'found associatedArticleTitle="{title}", exists={exists}',
				[
					'image' => $title->getText(),
					'title' => $associatedArticleTitle->getFullText(),
					'exists' => $associatedArticleTitle->exists() ? 'yes' : 'no'
				]
			);
		} else {
			$logger->debug( 'onImagePageShowTOC(): image="{image}", associatedArticleTitle not found',
				[
					'image' => $title->getText()
				]
			);
		}

		// "Previous file" link. Not shown if the previous file doesn't exist.
		foreach ( $prevTitles as $prevTitle ) {
			if ( $this->repoGroup->findFile( $prevTitle ) ) {
				$prevLink = $this->linkRenderer->makeKnownLink( $prevTitle,
					wfMessage( 'prevnextimage-previous-file' )->plain()
				);
				$toc[] = Html::rawElement( 'li', [ 'id' => 'prevnextlinks-prev' ], $prevLink );

				// Found.
				break;
			}
		}

		// Between Prev/Next link: if there is an existing article associated with this image
		// (which typically contains the text from this image/PDF in wikitext form),
		// then we display "Return to text" link to it.
		// If such article doesn't exist, then we show direct link to Download the currently viewed file.
		if ( $associatedArticleTitle && $associatedArticleTitle->exists() ) {
			$returnLink = $this->linkRenderer->makeKnownLink( $associatedArticleTitle,
				wfMessage( 'prevnextimage-return-to-text' )->plain()
			);
			$toc[] = Html::rawElement( 'li', [ 'id' => 'prevnextlinks-return-to-view' ], $returnLink );

			$logger->debug( 'onImagePageShowTOC(): image="{image}": added "Return to text view" link', [
				'image' => $title->getText()
			] );
		} else {
			$downloadLink = $page->getTitle()->getText() . ' (' . Html::element( 'a', [
				'href' => $page->getFile()->getFullURL()
			], wfMessage( 'prevnextimage-download' )->plain() ) . ')';
			$toc[] = Html::rawElement( 'li', [ 'id' => 'prevnextlinks-download' ], $downloadLink );
		}

		// "Next file" link. Not shown if the next file doesn't exist.
		foreach ( $nextTitles as $nextTitle ) {
			if ( $this->repoGroup->findFile( $nextTitle ) ) {
				$nextLink = $this->linkRenderer->makeKnownLink( $nextTitle,
					wfMessage( 'prevnextimage-next-file' )->plain()
				);
				$toc[] = Html::rawElement( 'li', [ 'id' => 'prevnextlinks-next' ], $nextLink );

				// Found.
				break;
			}
		}
	}

	/**
	 * Register {{#set_associated_image:}} and {{#set_associated_index:}} syntax.
	 *
	 * @inheritDoc
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'set_associated_image',
			[ AssociatedImage::class, 'pfSetAssociatedImage' ] );
		$parser->setFunctionHook( 'set_associated_index',
			[ AssociatedImage::class, 'pfSetAssociatedIndex' ] );

		$parser->setFunctionHook( 'set_prev_next',
			[ PageFinder::class, 'pfSetPrevNext' ] );

		$parser->setFunctionHook( 'subpage_anchor_navigation',
			[ NavigationTemplate::class, 'pfSubpageAnchorNavigation' ] );
	}
}
