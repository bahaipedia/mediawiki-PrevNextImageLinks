<?php

/**
 * Implements SubpageAnchorNavigation extension for MediaWiki.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\PrevNextImageLinks;

use Linker;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageIdentity;
use Parser;
use Title;
use Xml;

class NavigationTemplate {
	/**
	 * Converts {{#subpage_anchor_navigation:}} wikitext into HTML output.
	 * @param Parser $parser
	 * @param ?string $pageName
	 * @return array|string
	 */
	public static function pfSubpageAnchorNavigation( Parser $parser, $pageName ) {
		$title = $pageName ? Title::newFromText( $pageName ) : null;
		if ( !$title ) {
			$title = $parser->getTitle();
		}

		$template = new self;
		return $template->generate( $title );
	}

	/**
	 * Find all subpages of $title, find all tags like <span id="pg123"> inside each subpage,
	 * then generate links to every #pg<number> anchor, sorted by number after "pg".
	 * @param PageIdentity $title
	 * @return string|array
	 */
	protected function generate( PageIdentity $title ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$ns = $title->getNamespace();

		// Find all subpages of $title.
		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'page_title AS title', 'pp_value AS anchor' ] )
			->from( 'page' )
			->join( 'page_props', null, [ 'pp_page = page_id' ] )
			->where( [
				'page_namespace' => $ns,
				'page_title ' . $dbr->buildLike( $title->getDBKey(), '/', $dbr->anyString() )
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		if ( $res->numRows() == 0 ) {
			// None of the subpages have anchors.
			return '';
		}

		// Gather and sort all anchors.
		$anchorsFound = [];
		foreach ( $res as $row ) {
			// Strip common prefixes ("pg" and "pg-").
			$shownAnchor = preg_replace( '/^pg-?/', '', $row->anchor );
			$anchorsFound[] = [
				'title' => $row->title,
				'anchor' => $row->anchor,
				'text' => $shownAnchor,
				'number' => $this->parseAnchorNumber( $row->anchor )
			];
		}

		uasort( $anchorsFound, static function ( $a, $b ) {
			return $a['number'] - $b['number'];
		} );

		// Generate navigation links.
		$links = [];
		foreach ( $anchorsFound as $info ) {
			$anchorTitle = Title::makeTitle( $ns, $info['title'], $info['anchor'] );
			$links[] = Linker::link( $anchorTitle, $info['text'] );
		}

		$resultHtml = Xml::tags( 'div',
			[ 'class' => 'mw-subpage-navtemplate' ],
			implode( ' ', $links )
		);
		return [ $resultHtml, 'isHTML' => true ];
	}

	/**
	 * Detect the number inside the string $anchor, e.g. "15" in "pg15" or "9" in "pg-ix".
	 * @param string $anchor
	 * @return int
	 */
	protected function parseAnchorNumber( $anchor ) {
		// Decimal number?
		if ( preg_match( '/[0-9]+/', $anchor, $matches ) ) {
			return (int)$matches[0];
		}

		// Roman numeral?
		// TODO
		return 0;
	}
}
