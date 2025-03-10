<?php

namespace Automattic\WooCommerce\Admin\Features;

use Automattic\WooCommerce\Admin\PageController;
use Automattic\WooCommerce\Blocks\Utils\BlockTemplateUtils;

/**
 * Takes care of Launch Your Store related actions.
 */
class LaunchYourStore {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_update_options_site-visibility', array( $this, 'save_site_visibility_options' ) );
		add_action( 'current_screen', array( $this, 'maybe_create_coming_soon_page' ) );
		if ( is_admin() ) {
			add_filter( 'woocommerce_admin_shared_settings', array( $this, 'preload_settings' ) );
		}
		add_action( 'wp_footer', array( $this, 'maybe_add_coming_soon_banner_on_frontend' ) );
		add_action( 'init', array( $this, 'register_launch_your_store_user_meta_fields' ) );
	}

	/**
	 * Save values submitted from WooCommerce -> Settings -> General.
	 *
	 * @return void
	 */
	public function save_site_visibility_options() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_REQUEST['_wpnonce'] ), 'woocommerce-settings' ) ) {
			return;
		}

		$options = array(
			'woocommerce_coming_soon'      => array( 'yes', 'no' ),
			'woocommerce_store_pages_only' => array( 'yes', 'no' ),
			'woocommerce_private_link'     => array( 'yes', 'no' ),
		);

		if ( isset( $_POST['woocommerce_store_pages_only'] ) ) {
			$this->possibly_update_coming_soon_page( wc_clean( wp_unslash( $_POST['woocommerce_store_pages_only'] ) ) );
		}

		$at_least_one_saved = false;
		foreach ( $options as $name => $option ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( isset( $_POST[ $name ] ) && in_array( $_POST[ $name ], $option, true ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				update_option( $name, wp_unslash( $_POST[ $name ] ) );
				$at_least_one_saved = true;
			}
		}

		if ( $at_least_one_saved ) {
			wc_admin_record_tracks_event( 'site_visibility_saved' );
		}
	}

	/**
	 * Update the contents of the coming soon page on settings change. Do not update if the post request
	 * doesn't change the store pages only setting, if the setting is unchanged, or if the page has been edited.
	 *
	 * @param string $next_store_pages_only The next store pages only setting.
	 * @return void
	 */
	public function possibly_update_coming_soon_page( $next_store_pages_only ) {
		$option_name              = 'woocommerce_store_pages_only';
		$current_store_pages_only = get_option( $option_name, null );

		// If the current and next store pages only values are the same, return.
		if ( $current_store_pages_only && $current_store_pages_only === $next_store_pages_only ) {
			return;
		}

		$page_id               = get_option( 'woocommerce_coming_soon_page_id' );
		$page                  = get_post( $page_id );
		$original_page_content = 'yes' === $current_store_pages_only
				? $this->get_store_only_coming_soon_content()
				: $this->get_entire_site_coming_soon_content();

		// If the page exists and the content is not the same as the original content, its been edited from its original state. Return early to respect any changes.
		if ( $page && $page->post_content !== $original_page_content ) {
			return;
		}

		if ( $page_id ) {
			$next_page_content = 'yes' === $next_store_pages_only
				? $this->get_store_only_coming_soon_content()
				: $this->get_entire_site_coming_soon_content();
			wp_update_post(
				array(
					'ID'           => $page_id,
					'post_content' => $next_page_content,
				)
			);

			$template_id = 'yes' === $next_store_pages_only
				? 'coming-soon-store-only'
				: 'coming-soon-entire-site';
			update_post_meta( $page_id, '_wp_page_template', $template_id );
		}
	}

	/**
	 * Create a pattern for the store only coming soon page.
	 *
	 * @return string
	 */
	public function get_store_only_coming_soon_content() {
		$heading    = __( 'Great things coming soon', 'woocommerce' );
		$subheading = __( 'Something big is brewing! Our store is in the works - Launching shortly!', 'woocommerce' );

		return sprintf(
			'<!-- wp:group {"layout":{"type":"constrained"}} -->
			<div class="wp-block-group"><!-- wp:spacer -->
			<div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div>
			<!-- /wp:spacer -->

			<!-- wp:heading {"textAlign":"center","level":1} -->
			<h1 class="wp-block-heading has-text-align-center">%s</h1>
			<!-- /wp:heading -->

			<!-- wp:spacer {"height":"10px"} -->
			<div style="height:10px" aria-hidden="true" class="wp-block-spacer"></div>
			<!-- /wp:spacer -->

			<!-- wp:paragraph {"align":"center"} -->
			<p class="has-text-align-center">%s</p>
			<!-- /wp:paragraph -->

			<!-- wp:spacer -->
			<div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div>
			<!-- /wp:spacer --></div>
			<!-- /wp:group -->',
			$heading,
			$subheading
		);
	}

	/**
	 * Create a pattern for the entire site coming soon page.
	 *
	 * @return string
	 */
	public function get_entire_site_coming_soon_content() {
		$heading = __( 'Pardon our dust! We\'re working on something amazing -- check back soon!', 'woocommerce' );

		return sprintf(
			'<!-- wp:group {"layout":{"type":"constrained"}} -->
			<div class="wp-block-group"><!-- wp:spacer -->
			<div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div>
			<!-- /wp:spacer -->

			<!-- wp:heading {"textAlign":"center","level":1,"align":"wide"} -->
			<h1 class="wp-block-heading alignwide has-text-align-center">%s</h1>
			<!-- /wp:heading -->

			<!-- wp:spacer -->
			<div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div>
			<!-- /wp:spacer --></div>
			<!-- /wp:group -->',
			$heading
		);
	}

	/**
	 * Add `coming soon` page when it hasn't been created yet.
	 *
	 * @param WP_Screen $current_screen Current screen object.
	 *
	 * @return void
	 */
	public function maybe_create_coming_soon_page( $current_screen ) {
		$option_name    = 'woocommerce_coming_soon_page_id';
		$current_page   = PageController::get_instance()->get_current_page();
		$is_home        = isset( $current_page['id'] ) && 'woocommerce-home' === $current_page['id'];
		$page_id_option = get_option( $option_name, false );
		if ( $current_screen && 'woocommerce_page_wc-admin' === $current_screen->id && $is_home && ! $page_id_option ) {
			$store_pages_only = 'yes' === get_option( 'woocommerce_store_pages_only', 'no' );
			$page_id          = wc_create_page(
				esc_sql( _x( 'Coming Soon', 'Page slug', 'woocommerce' ) ),
				$option_name,
				_x( 'Coming Soon', 'Page title', 'woocommerce' ),
				$store_pages_only ? $this->get_store_only_coming_soon_content() : $this->get_entire_site_coming_soon_content(),
			);
			$template_id      = $store_pages_only ? 'coming-soon-store-only' : 'coming-soon-entire-site';
			update_post_meta( $page_id, '_wp_page_template', $template_id );
			// wc_create_page doesn't create options with autoload = yes.
			// Since we'll querying the option on WooCommerce home,
			// we should update the option to set autoload to yes.
			$page_id_option = get_option( $option_name );
			update_option( $option_name, $page_id_option, true );
		}
	}

	/**
	 * Preload settings for Site Visibility.
	 *
	 * @param array $settings settings array.
	 *
	 * @return mixed
	 */
	public function preload_settings( $settings ) {
		if ( ! is_admin() ) {
			return $settings;
		}

		$current_screen  = get_current_screen();
		$is_setting_page = $current_screen && 'woocommerce_page_wc-settings' === $current_screen->id;

		if ( $is_setting_page ) {
			$settings['siteVisibilitySettings'] = array(
				'shop_permalink'               => get_permalink( wc_get_page_id( 'shop' ) ),
				'woocommerce_coming_soon'      => get_option( 'woocommerce_coming_soon' ),
				'woocommerce_store_pages_only' => get_option( 'woocommerce_store_pages_only' ),
				'woocommerce_private_link'     => get_option( 'woocommerce_private_link' ),
				'woocommerce_share_key'        => get_option( 'woocommerce_share_key' ),
			);
		}

		return $settings;
	}

	/**
	 * Add 'coming soon' banner on the frontend when the following conditions met.
	 *
	 * - User must be either an admin or store editor (must be logged in).
	 * - 'woocommerce_coming_soon' option value must be 'yes'
	 * - The page must not be the Coming soon page itself.
	 */
	public function maybe_add_coming_soon_banner_on_frontend() {
		// Do not show the banner if the site is being previewed.
		if ( isset( $_GET['site-preview'] ) ) { // @phpcs:ignore
			return false;
		}

		// User must be an admin or editor.
		// phpcs:ignore
		if ( ! current_user_can( 'shop_manager' ) && ! current_user_can( 'administrator' ) ) {
			return false;
		}

		// 'woocommerce_coming_soon' must be 'yes'
		if ( get_option( 'woocommerce_coming_soon', 'no' ) !== 'yes' ) {
			return false;
		}

		// No need to show the banner on the Coming soon page itself.
		$page_id             = get_the_ID();
		$coming_soon_page_id = intval( get_option( 'woocommerce_coming_soon_page_id' ) );
		if ( $page_id === $coming_soon_page_id ) {
			return false;
		}

		$link = admin_url( 'admin.php?page=wc-settings#wc_settings_general_site_visibility_slotfill' );

		$text = sprintf(
			// translators: no need to translate it. It's a link.
			__(
				"
			This page is in \"Coming soon\" mode and is only visible to you and those who have permission. To make it public to everyone,&nbsp;<a href='%s'>change visibility settings</a>.
		",
				'woocommerce'
			),
			$link
		);
		// phpcs:ignore
		echo "<div id='coming-soon-footer-banner'>$text</div>";
	}

	/**
	 * Register user meta fields for Launch Your Store.
	 */
	public function register_launch_your_store_user_meta_fields() {
		register_meta(
			'user',
			'woocommerce_launch_your_store_tour_hidden',
			array(
				'type'         => 'string',
				'description'  => 'Indicate whether the user has dismissed the site visibility tour on the home screen.',
				'single'       => true,
				'show_in_rest' => true,
			)
		);
	}
}
