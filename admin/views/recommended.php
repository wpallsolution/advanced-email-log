<?php
/**
 * View template for Recommended Plugins tab.
 *
 * @package WPAllSolution\AdvancedEmailLog
 */

namespace WPAllSolution\AdvancedEmailLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ael-recommended-container">
	<div class="ael-recommended-intro">
		<h2><?php esc_html_e( 'More Plugins by WP All Solution', 'advanced-email-log' ); ?></h2>
		<p>
			<?php esc_html_e( 'Explore our officially maintained WordPress and WooCommerce plugins, engineered with the same dedication to performance, security, and usability.', 'advanced-email-log' ); ?>
		</p>
	</div>

	<div class="ael-cards-grid">
		<!-- Card 1: Autocomplete for WooCommerce Orders -->
		<div class="ael-product-card">
			<div class="ael-product-icon ael-icon-woo">
				<span class="dashicons dashicons-cart"></span>
			</div>
			<div class="ael-product-details">
				<h3 class="ael-product-title">
					<a href="https://wordpress.org/plugins/autocomplete-for-woo-orders/" target="_blank" rel="noopener noreferrer">
						Autocomplete for WooCommerce Orders
					</a>
				</h3>
				<span class="ael-product-badge"><?php esc_html_e( 'WooCommerce Automation', 'advanced-email-log' ); ?></span>
				<p class="ael-product-desc">
					<?php esc_html_e( 'Automatically marks paid or virtual WooCommerce orders as completed upon successful payment. Eliminates repetitive manual order status changes for store owners.', 'advanced-email-log' ); ?>
				</p>
				<div class="ael-product-footer">
					<a href="https://wordpress.org/plugins/autocomplete-for-woo-orders/" target="_blank" rel="noopener noreferrer" class="button button-secondary ael-view-plugin-btn">
						<span class="dashicons dashicons-external"></span>
						<span><?php esc_html_e( 'View on WordPress.org', 'advanced-email-log' ); ?></span>
					</a>
				</div>
			</div>
		</div>

		<!-- Card 2: H-Shipping Multiple Shipping Addresses for WooCommerce -->
		<div class="ael-product-card">
			<div class="ael-product-icon ael-icon-shipping">
				<span class="dashicons dashicons-location-alt"></span>
			</div>
			<div class="ael-product-details">
				<h3 class="ael-product-title">
					<a href="https://wordpress.org/plugins/h-shipping-multiple-addresses-for-woocommerce/" target="_blank" rel="noopener noreferrer">
						H-Shipping Multiple Shipping Addresses
					</a>
				</h3>
				<span class="ael-product-badge"><?php esc_html_e( 'Shipping & Logistics', 'advanced-email-log' ); ?></span>
				<p class="ael-product-desc">
					<?php esc_html_e( 'Allows WooCommerce customers to ship individual cart items to different recipient addresses during a single checkout session. Perfect for gift ordering and corporate orders.', 'advanced-email-log' ); ?>
				</p>
				<div class="ael-product-footer">
					<a href="https://wordpress.org/plugins/h-shipping-multiple-addresses-for-woocommerce/" target="_blank" rel="noopener noreferrer" class="button button-secondary ael-view-plugin-btn">
						<span class="dashicons dashicons-external"></span>
						<span><?php esc_html_e( 'View on WordPress.org', 'advanced-email-log' ); ?></span>
					</a>
				</div>
			</div>
		</div>
	</div>
</div>
