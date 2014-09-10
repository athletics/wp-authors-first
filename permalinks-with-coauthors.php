<?php

/**
 * Plugin Name: Permalinks with Co-Authors
 * Plugin URI: https://github.com/athletics/permalinks-with-coauthors
 * Description: Allows use of co-author nicename in permalink structure.
 * Version: 1.0
 * Author: Athletics
 * Author URI: http://athleticsnyc.com/
 * License: GPL-2.0+
 */

namespace Athletics;
use WP_Error;

class CoAuthorsPermalinks {

	public function __construct() {

		add_action( 'init', function() {
			$this->drop_author_rewrites();
			$this->add_rewrite_tag();
		} );

		add_action( 'parse_request', array( $this, 'maninpulate_request' ) );
		add_action( 'template_redirect', array( $this, 'setup_author_archive' ) );
		add_action( 'template_redirect', array( $this, 'redirect_primary_coauthor' ) );

		add_filter( 'author_link', array( $this, 'remove_author_prefix' ), 20 ); // Needs to be >10 for some reason
		add_filter( 'post_link', array( $this, 'replace_rewrite_tag' ), 10, 2 );

	}

	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/**
	 * Remove Default Author Rewrite Rules
	 *
	 * @uses __return_empty_array()
	 */
	public function drop_author_rewrites() {
		add_filter( 'author_rewrite_rules', '__return_empty_array' );
	}

	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/**
	 * Add Co-Author Rewrite Tag
	 * %__coauthor%
	 */
	public function add_rewrite_tag() {
		add_rewrite_tag( '%__coauthor%', '([^/]+)' );
	}

	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/**
	 * Manipulate Request
	 *
	 * @param object $wp
	 */
	public function maninpulate_request( $wp ) {
		$coauthor = isset( $wp->query_vars['__coauthor'] ) ? $wp->query_vars['__coauthor'] : false;

		// Is this a historical request in need of redirection?
		if ( ! $coauthor ) {
			$this->redirect_historical();
		}

		// Is this a page?
		if ( $page = $this->cached_get_page_by_path( $coauthor ) ) {
			$wp->query_vars['page'] = '';
			$wp->query_vars['pagename'] = $page->post_name;
			$wp->matched_query = 'pagename='. $page->post_name .'&page=';

			unset( $wp->query_vars['__coauthor'] );
		}

		// Is this an author archive?
		if (
			! isset( $wp->query_vars['year'] ) &&
			! isset( $wp->query_vars['name'] ) &&
			! isset( $wp->query_vars['pagename'] )
		) {
			unset( $wp->query_vars['__coauthor'] );
			unset( $wp->matched_query );
			$is_feed = isset($wp->query_vars['feed']) && ! empty($wp->query_vars['feed']) ? true : false;

			$wp->query_vars['author_name'] = $coauthor;
			$wp->matched_query = 'author_name=' . $coauthor;

			if ( $is_feed ) {
				unset( $wp->matched_rule );
				$wp->matched_rule = 'author/([^/]+)/(feed|rdf|rss|rss2|atom)/?$';
				$wp->matched_query .= '&feed=feed';
			}
		}
	}

	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/**
	 * Redirect Historical to New Permalink Structure
	 *
	 * @todo consider different kinds of permalink structure
	 * @param object $wp
	 */
	public function redirect_historical() {
		$pagename = isset( $wp->query_vars['pagename'] ) ? $wp->query_vars['pagename'] : false;
		if ( ! $pagename ) return;

		$parts = explode( '/', $pagename );
		$count = count( $parts );

		if (
			(isset($parts[1]) && strlen($parts[1]) > 2) &&
			($parts[0] == '2010' || $parts[0] == '2011' || $parts[0] == '2012' || $parts[0] == '2013')
		) {
			$this->redirect_historical_post( $parts, $wp );
		}
		else if (
			$count > 1 &&
			$parts[0] === 'author' &&
			strlen( $parts[1] ) > 3
		) {
			$this->redirect_historical_author( $parts, $wp );
		}

	}

	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/**
	 * Redirect Historical Posts to New Permalink Structure
	 *
	 * @param array $parts
	 * @param object $wp
	 */
	public function redirect_historical_post( $parts, $wp ) {
		$_post = $this->cached_get_page_by_path( $parts[1], OBJECT, 'post' );
		if ( ! $_post ) return;

		$location = get_permalink( $_post->ID );
		if ( ! $location ) return;
		$location = $this->formatted_url( $location, $wp );

		wp_safe_redirect( $location, 301 );
		exit();
	}

	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/**
	 * Redirect Historical Author Archive to New Permalink Structure
	 *
	 * @param array $parts
	 * @param object $wp
	 */
	public function redirect_historical_author( $parts, $wp ) {
		$location = home_url( "/{$parts[1]}/" );
		$location = $this->formatted_url( $location, $wp );

		wp_safe_redirect( $location, 301 );
		exit();
	}

	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/**
	 * Set author query_var for author archive
	 * Only allow author archive for members of this blog
	 */
	public function setup_author_archive() {
		if ( ! is_author() ) return;

		$coauthor = $this->get_queried_coauthor_object();
		set_query_var( 'author', (int) $coauthor->ID );

		if ( strpos( get_class($coauthor), 'WP_User' ) === false ) return;

		if ( ! is_user_member_of_blog( $coauthor->ID, get_current_blog_id() ) ) {
			wp_safe_redirect( home_url(), 301 );
			exit();
		}
	}

	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/**
	 * Allow Post to be Accessed by the Primary Co-Author
	 */
	public function redirect_primary_coauthor() {
		if ( ! is_singular() ) return;

		$queried = get_query_var( '__coauthor' );
		if ( empty( $queried ) ) return;

		$ID = get_the_ID();
		$coauthor = $this->get_coauthor( $ID );

		if ( $coauthor->user_nicename === $queried ) return;

		$permalink = get_permalink( $ID );

		wp_safe_redirect( $permalink, 301 );
		exit();
	}

	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/**
	 * Filter the post permalink
	 * Replace the %__coauthor% rewrite tag
	 *
	 * @param string $permalink
	 * @param object $post
	 * @return string $permalink
	 */
	public function replace_rewrite_tag( $permalink, $post ) {
		if ( strpos( $permalink, '%__coauthor%' ) === false ) return $permalink;

		$coauthor = $this->get_coauthor( $post->ID );

		if ( ! is_wp_error( $coauthor ) ) {
			$permalink = str_replace( '%__coauthor%', $coauthor->user_nicename, $permalink );
		}

		return apply_filters( 'athletics_permalink_post_link', $permalink, $post );
	}

	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/**
	 * Filter the author archive permalink
	 * Remove the /author/ prefix
	 *
	 * @param string $link
	 * @return string $link
	 */
	public function remove_author_prefix( $link ) {
		$link = str_replace( '/author/', '/', $link );
		return apply_filters( 'athletics_author_link', $link );
	}

	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/**
	 * Get primary (first) coauthor for post
	 *
	 * @param int $ID
	 * @return WP_Error|object $coauthor
	 */
	public function get_coauthor( $ID ) {
		$coauthors = get_coauthors( $ID );

		if ( empty( $coauthors ) ) return new WP_Error( 'not_found', "No coauthors associated with {$ID}" );

		$coauthor = current( $coauthors );

		return $coauthor;
	}

	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/**
	 * Retrieve the currently-queried coauthor object.
	 *
	 * @uses $coauthors_plus
	 * @uses get_queried_object()
	 * @return WP_Error|object $coauthor
	 */
	public function get_queried_coauthor_object() {
		if ( ! is_author() ) return new WP_Error( 'not_found', 'Not an author archive.' );

		global $coauthors_plus;
		$coauthor = $coauthors_plus->guest_authors->get_guest_author_by( 'user_nicename', get_query_var( 'author_name' ) );

		if ( empty( $coauthor ) ) {
			$coauthor = get_queried_object();
		}

		return $coauthor;
	}

	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/**
	 * Cached version of get_page_by_path so that we're not making unnecessary SQL all the time
	 *
	 * @param string $page_path Page path
	 * @param string $output Optional. Output type; OBJECT*, ARRAY_N, or ARRAY_A.
	 * @param string $post_type Optional. Post type; default is 'page'.
	 * @return WP_Post|null WP_Post on success or null on failure
	 * @link http://vip.wordpress.com/documentation/uncached-functions/ Uncached Functions
	 */
	public function cached_get_page_by_path( $page_path, $output = OBJECT, $post_type = 'page' ) {
		if ( is_array( $post_type ) )
			$cache_key = sanitize_key( $page_path ) . '_' . md5( serialize( $post_type ) );
		else
			$cache_key = $post_type . '_' . sanitize_key( $page_path );

		$page_id = wp_cache_get( $cache_key, 'get_page_by_path' );

		if ( $page_id === false ) {
			$page = get_page_by_path( $page_path, $output, $post_type );
			$page_id = $page ? $page->ID : 0;
			wp_cache_set( $cache_key, $page_id, 'get_page_by_path' ); // We only store the ID to keep our footprint small
		}

		if ( $page_id )
			return get_page( $page_id, $output );

		return null;
	}

	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/**
	 * Formatted URL
	 *
	 * @param string $url
	 * @param object $wp
	 * @return string $url
	 */
	private function formatted_url( $url, $wp ) {
		if ( $wp->query_vars['feed'] ) {
			$url .= 'feed/';
		}
		else if ( $page = $this->current_page($wp) ) {
			$url .= "page/{$page}";
		}

		return $url;
	}

	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/**
	 * Current Page
	 *
	 * @param object $wp
	 * @return bool|int
	 */
	private function current_page( $wp ) {
		$page = $wp->query_vars['paged'];
		if ( empty( $page ) ) return false;

		$page = absint( $page );
		if ( $page === 1 ) return false;

		return $page;
	}

}

return new CoAuthorsPermalinks();