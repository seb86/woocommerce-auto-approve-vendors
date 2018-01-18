<?php
/*
 * Plugin Name: WooCommerce Auto Approve Vendors
 * Plugin URI:  https://sebastiendumont.com
 * Version:     1.0.0
 * Description: Simply approves product vendors upon registration for new users or those who already have an account.
 * Author:      Sébastien Dumont
 * Author URI:  https://sebastiendumont.com
 *
 * Text Domain: woocommerce-auto-approve-vendors
 * Domain Path: /languages/
 *
 * Requires at least: 4.5
 * Tested up to: 4.9.2
 * WC requires at least: 3.0.0
 * WC tested up to: 3.2.6
 *
 * Copyright: © 2018 Sébastien Dumont
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! class_exists( 'WC_Dependencies' ) ) {
	require_once( 'woo-dependencies/woo-dependencies.php' );
}

// Quit right now if WooCommerce is not active
if ( ! is_woocommerce_active() ) {
	return;
}

if ( ! class_exists( 'WC_Auto_Approve_Vendors' ) ) {
	class WC_Auto_Approve_Vendors {

		/**
		 * @var WC_Auto_Approve_Vendors - the single instance of the class.
		 *
		 * @access protected
		 * @static
		 * @since 1.0.0
		 */
		protected static $_instance = null;

		/**
		 * Plugin Version
		 *
		 * @access public
		 * @static
		 * @since  1.0.0
		 */
		public static $version = '1.0.0';

		/**
		 * Required WooCommerce Version
		 *
		 * @access public
		 * @since  1.0.0
		 */
		public $required_woo = '3.0.0';

		/**
		 * Main WC_Auto_Approve_Vendors Instance.
		 *
		 * Ensures only one instance of WC_Auto_Approve_Vendors is loaded or can be loaded.
		 *
		 * @access public
		 * @static
		 * @since  1.0.0
		 * @see    WC_Auto_Approve_Vendors()
		 * @return WC_Auto_Approve_Vendors - Main instance
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Cloning is forbidden.
		 *
		 * @access public
		 * @since  1.0.0
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, __( 'Foul!', 'woocommerce-auto-approve-vendors' ), '1.0.0' );
		}

		/**
		 * Unserializing instances of this class is forbidden.
		 *
		 * @access public
		 * @since  1.0.0
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, __( 'Foul!', 'woocommerce-auto-approve-vendors' ), '1.0.0' );
		}

		/**
		 * Load the plugin.
		 *
		 * @access public
		 * @since  1.0.0
		 */
		public function __construct() {
			add_action( 'plugins_loaded', array( $this, 'load_plugin' ) );
			add_action( 'init', array( $this, 'init_plugin' ) );

			// Approve Vendor
			add_action( 'wcpv_shortcode_registration_form_process', array( $this, 'approve_vendor' ), 2, 5 );
		}

		/**
		 * Get the Plugin Path.
		 *
		 * @access public
		 * @static
		 * @since  1.0.0
		 * @return string
		 */
		public static function plugin_path() {
			return untrailingslashit( plugin_dir_path( __FILE__ ) );
		} // END plugin_path()

		/**
		 * Check requirements on activation.
		 *
		 * @access public
		 * @since  1.0.0
		 */
		public function load_plugin() {
			// Check we're running the required version of WooCommerce.
			if ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, $this->required_woo, '<' ) ) {
				add_action( 'admin_notices', array( $this, 'wc_auto_approve_vendors_admin_notice' ) );
				return false;
			}
		} // END load_plugin()

		/**
		 * Display a warning message if minimum version of WooCommerce check fails.
		 *
		 * @access public
		 * @since  1.0.0
		 * @return void
		 */
		public function wc_auto_approve_vendors_admin_notice() {
			echo '<div class="error"><p>' . sprintf( __( '%1$s requires at least %2$s v%3$s in order to function. Please upgrade %2$s.', 'woocommerce-auto-approve-vendors' ), 'WooCommerce Auto Approve Vendors', 'WooCommerce', $this->required_woo ) . '</p></div>';
		} // END wc_auto_approve_vendors_admin_notice()

		/**
		 * Initialize the plugin if ready.
		 *
		 * @access public
		 * @since  1.0.0
		 * @return void
		 */
		public function init_plugin() {
			// Load text domain.
			load_plugin_textdomain( 'woocommerce-auto-approve-vendors', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		} // END init_plugin()

		/**
		 * Approve the vendor once registration is complete.
		 *
		 * @access public
		 * @since  1.0.0
		 * @param  array  $args
		 * @param  string $new_role
		 * @param  array  $old_roles
		 * @return void
		 */
		public function approve_vendor( $args, $new_role, $old_roles ) {
			$user_id = $args['user_id'];

			/**
			 * Not actually used in the email but since the paramerter is
			 * used we need to return something to prevent any errors.
			 */
			$old_roles = array();

			/**
			 * Sets the new role for the vendors. Vendor Admin is Default.
			 *
			 * Vendor Admin   - `wc_product_vendors_admin_vendor` – Has access to all settings.
			 * Vendor Manager - `wc_product_vendors_manager_vendor` - Has limited access to the Vendor dashboard.
			 */
			$new_role = apply_filters( 'woocommerce_auto_approved_vendors_role', 'wc_product_vendors_admin_vendor' );

			/**
			 * Check that the user has not been approved as a vendor already
			 * so we don't trigger the email to be sent again.
			 */
			$approved_already = get_user_meta( $user_id, '_wcpv_vendor_approval', true );

			if ( 'yes' !== $approved_already ) {
				$emails = WC()->mailer()->get_emails();

				if ( ! empty( $emails ) ) {
					$emails['WC_Product_Vendors_Approval']->trigger( $user_id, $new_role, $old_roles );
				}
			}

			// Remove pending new vendor from saved list.
			WC_Product_Vendors_Utils::delete_new_pending_vendor( $user_id );
		} // END approve_vendor()

	} // END class

} // END if class exists

return WC_Auto_Approve_Vendors::instance();
