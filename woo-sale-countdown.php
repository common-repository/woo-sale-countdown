<?php
use Faker\Provider\ka_GE\DateTime;

/**
 *
 * @package   WooCommerce Sale Countdown
 * @author    Abdelrahman Ashour < abdelrahman.ashour38@gmail.com >
 * @license   GPL-2.0+
 * @copyright 2018 Ash0ur


 * Plugin Name: WooCommerce Sale CountDown
 * Description: Sale Countdown Timer for Limited and Temporary Sale offers plus stock progress bar
 * Version:      1.0.0
 * Author:       Abdelrahman Ashour
 * Author URI:   https://profiles.wordpress.org/ashour
 * Contributors: ashour
 * License:      GPL2
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WCSC_Woo_Sale_Countdown' ) ) :

	define( 'WCSC_VERSION', '1.0.0' );
	define( 'WCSC_PREFIX', 'WCSC' );
	define( 'WCSC_BASE_URL', plugin_dir_url( __FILE__ ) );
	define( 'WCSC_ASSETS_URL', plugin_dir_url( __FILE__ ) . 'assets' );
	define( 'WCSC_PATH', plugin_dir_path( __FILE__ ) );
	define( 'WSCS_DOMAIN', 'wscs-domain' );

	require_once WCSC_PATH . '/settings.php';

	/**
	 * Woo Sale CountDown Main Class.
	 *
	 */
	class WCSC_Woo_Sale_Countdown {

		/**
		 * Class Instance.
		 */
		private static $instance;

		/**
		 * Plugin Settings Object.
		 *
		 * @var Object
		 */
		private $settings;

		/**
		 * Is valid Sale.
		 *
		 * @var boolean
		 */
		protected $is_sale_valid;

		/**
		 * Single Product ID.
		 *
		 * @var int
		 */
		protected $single_product_id;

		/**
		 * Single Instance Class Initialization.
		 *
		 * @return Object
		 */
		public static function init() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Class Constrcutor.
		 */
		public function __construct() {
			$this->settings = new WSCS_Settings();
			$this->setup_actions();
		}

		/**
		 * Plugin Activated Hook.
		 *
		 * @return void
		 */
		public static function plugin_activated() {
			if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
				die( '<h3> WooCommerce plugin must be active </h3>' );
			}
		}

		/**
		 * Frontend Assets.
		 *
		 * @return void
		 */
		public function frontend_enqueue_global() {

			wp_enqueue_style( WCSC_PREFIX . '_frontend-styles', WCSC_ASSETS_URL . '/css/flipclock.css', array(), WCSC_VERSION, false );

			if ( ! wp_script_is( 'jquery' ) ) {
				wp_enqueue_script( 'jquery' );
			}

			wp_enqueue_script( WCSC_PREFIX . '_countdown_timer', WCSC_ASSETS_URL . '/js/flipclock.min.js', array( 'jquery' ), WCSC_VERSION, true );

			wp_register_script( WCSC_PREFIX . '_actions', WCSC_ASSETS_URL . '/js/actions.js', array( 'jquery', WCSC_PREFIX . '_countdown_timer' ), WCSC_VERSION, true );

			$this->is_sale_valid = $this->is_valid_sale();

			wp_localize_script(
				WCSC_PREFIX . '_actions',
				WCSC_PREFIX . '_ajax_data',
				array(
					'stillValid'  => $this->is_sale_valid,
					'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
					'nonce'       => wp_create_nonce( WCSC_PREFIX . '_nonce' ),
					'currentDate' => current_time( 'timestamp', false ),
					'endDate'     => $this->is_sale_valid ? strtotime( $this->settings->get_product_sale_time( 'to', 'front' ), current_time( 'timestamp', false ) ) : null,
				)
			);

			wp_enqueue_script( WCSC_PREFIX . '_actions' );
		}

		/**
		 * Setup Plugin Actions.
		 *
		 * @return void
		 */
		public function setup_actions() {
			add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_global' ) );

			// Sale Countdown Filter Hook.
			add_action( 'woocommerce_single_product_summary', array( $this, 'sale_countdown' ), 15 );

			// Product Save Hook.
			add_action( 'woocommerce_admin_process_product_object', array( $this, 'adjust_product_sale_dates' ), 1000, 1 );

			add_action( 'woocommerce_before_single_product', array( $this, 'update_product_price_on_sale_end' ), 100 );
		}

		/**
		 * Filter is on sale product function.
		 *
		 * @return void
		 */
		public function filter_is_on_sale( $on_sale, $product_obj ) {
			if ( is_null( $this->single_product_id ) ) {
				return $on_sale;
			}
			if ( $product_obj->get_id() === $this->single_product_id ) {
				return $this->is_sale_valid;
			}
			return $on_sale;
		}

		/**
		 * Modify the Sale Dates before saving it.
		 *
		 * @param Object $product The Product Object.
		 * @return void
		 */
		public function adjust_product_sale_dates( $product ) {
			$date_on_sale_from = '';
			$date_on_sale_to   = '';

			// Force date from to beginning of day.
			if ( isset( $_POST['_sale_price_dates_from'] ) ) {
				$date_on_sale_from = wc_clean( wp_unslash( $_POST['_sale_price_dates_from'] ) );
				$date_on_sale_from = str_replace( 'T', ' ', $date_on_sale_from );
				if ( ! empty( $date_on_sale_from ) ) {
					$date_on_sale_from = date( 'Y-m-d H:i:s', strtotime( $date_on_sale_from ) );
				}
			}

			// Force date to to the end of the day.
			if ( isset( $_POST['_sale_price_dates_to'] ) ) {
				$date_on_sale_to = wc_clean( wp_unslash( $_POST['_sale_price_dates_to'] ) );
				$date_on_sale_to = str_replace( 'T', ' ', $date_on_sale_to );
				if ( ! empty( $date_on_sale_to ) ) {
					$date_on_sale_to = date( 'Y-m-d H:i:s', strtotime( $date_on_sale_to ) );
				}
			}

			$product->set_date_on_sale_to( $date_on_sale_to );
			$product->set_date_on_sale_from( $date_on_sale_from );

			// hook to product meta actions to trigger sale status meta.
			add_action( 'woocommerce_process_product_meta_' . $product->get_type(), array( $this, 'mark_product_sale_status_update' ), 1000, 1 );
		}

		/**
		 * Update the product meta that sale status has been updated.
		 *
		 * @param String $product_type The product Type.
		 * @param int $post_id The product ID.
		 * @return void
		 */
		function mark_product_sale_status_update( $post_id ) {
			update_post_meta( $post_id, WCSC_PREFIX . '_sale_status_updated', 1 );
		}

		/**
		 * Check the product sale end date and update price accordingly.
		 *
		 * @return void
		 */
		function update_product_price_on_sale_end() {
			global $product;

			if ( ! is_object( $product ) ) {
				return;
			}
			$this->single_product_id = $product->get_id();
			// Check if sale to is bigger than current time.
			if ( ! empty( $product->get_date_on_sale_to() ) ) {
				if ( strtotime( $product->get_date_on_sale_to()->format( 'Y-m-d H:i:s' ), current_time( 'timestamp', false ) ) <= current_time( 'timestamp', false ) ) {
					update_post_meta( $product->get_id(), '_price', $product->get_regular_price( 'edit' ) );
					$regular_price = $product->get_regular_price();
					$product->set_price( $regular_price );
					$product->set_sale_price( '' );
					$product->set_date_on_sale_to( '' );
					$product->set_date_on_sale_from( '' );
					$product->save();
				} elseif ( strtotime( $product->get_date_on_sale_to()->format( 'Y-m-d H:i:s' ), current_time( 'timestamp', false ) ) > current_time( 'timestamp', false ) ) {
					add_filter( 'woocommerce_product_is_on_sale', array( $this, 'filter_is_on_sale' ), 1000, 2 );

					$sale_status = get_post_meta( $product->get_id(), WCSC_PREFIX . '_sale_status_updated', true );
					if ( 1 == $sale_status ) {
						update_post_meta( $product->get_id(), WCSC_PREFIX . '_sale_status_updated', 0 );
						update_post_meta( $product->get_id(), '_price', $product->get_sale_price( 'edit' ) );
						$product->set_price( $product->get_sale_price( 'edit' ) );
						$product->save();
					}

				}
			}
		}

		/**
		 * Check if product has valid sale to date.
		 * returns false / remaining time otherwise.
		 *
		 * @return Boolean
		 */
		public function is_valid_sale( $product = '' ) {
			if ( is_object( $product ) && ( $product->get_id() !== get_the_ID() ) ) {
				return;
			}
			$product = wc_get_product( get_the_ID() );
			$is_sale = false;
			$context = 'edit';
			if ( ! is_object( $product ) ) {
				return $is_sale;
			}

			if ( '' !== (string) $product->get_sale_price( $context ) && $product->get_regular_price( $context ) > $product->get_sale_price( $context ) ) {
				$is_sale = true;

				if ( $product->get_date_on_sale_from( $context ) && strtotime( $product->get_date_on_sale_from( $context )->format( 'Y-m-d H:i:s' ), current_time( 'timestamp', false ) ) > current_time( 'timestamp', false ) ) {
					$is_sale = false;
				}

				if ( $product->get_date_on_sale_to( $context ) && strtotime( $product->get_date_on_sale_to( $context )->format( 'Y-m-d H:i:s' ), current_time( 'timestamp', false ) ) < current_time( 'timestamp', false ) ) {
					$is_sale = false;
				}
			} else {
				$is_sale = false;
			}

			return $is_sale;
		}

		/**
		 * Display Sale Countdown Single Product.
		 *
		 * @return void
		 */
		public function sale_countdown() {
			$stock_bar_status = $this->settings->stock_status;

			// Styles CSS.
			$this->frontend_css();
			?>
			<div class="wscs-product-coutdown-wrapper">
			<?php
			if ( $this->is_sale_valid ) :
			?>
				<h5><?php echo esc_html( get_option( 'wscs-sale-countdown' ) ); ?></h5>
			<?php
				// Count Down Timer HTML.
				$this->countdown_html();
			endif;

			if ( $stock_bar_status ) :
				// Stock Line HTML.
				$this->product_stock_progress_bar();

			endif;
			?>
			</div>
			<?php
		}

		/**
		 * Count Down HTML.
		 *
		 * @return void
		 */
		private function countdown_html() {
			ob_start();
			?>

			<div class="wcsc-product-countdown-timer">
			</div>

			<?php
			$html = ob_get_clean();
			echo $html;
		}


		/**
		 * Frontend Countdown CSS.
		 *
		 * @return void
		 */
		private function frontend_css() {
			$panel_background  = esc_html( $this->settings->panel_background );
			$panel_label_color = esc_html( $this->settings->panel_label_color );
			$panel_color       = esc_html( $this->settings->panel_color );
			ob_start();
			?>
			<style>
				/**
				*  Flip Clock
				*/
				.wscs-product-coutdown-wrapper {
					margin: 15px 0px 25px;
				}

				.flip-clock-wrapper {
					zoom: .5;
					margin: 10px 0px !important;
				}

				.flip-clock-divider {
					margin-right: 5px;
				}
				.up:after,
				.down:after,
				.flip-clock-before {
					background: <?php echo $panel_background; ?> !important;
				}

				.flip.play .flip-clock-active .shadow,
				.flip.play .flip-clock-before .shadow{
					background: -moz-linear-gradient(top, rgba(0, 0, 0, 0.1) 0%, <?php echo $panel_background; ?> 100%) !important;
					background: -webkit-gradient(linear, left top, left bottom, color-stop(0%, rgba(0, 0, 0, 0.1)), color-stop(100%, <?php echo $panel_background; ?>)) !important;
					background: linear, top, rgba(0, 0, 0, 0.1) 0%, <?php echo $panel_background; ?> 100% !important;
					background: -o-linear-gradient(top, rgba(0, 0, 0, 0.1) 0%, <?php echo $panel_background; ?> 100%) !important;
					background: -ms-linear-gradient(top, rgba(0, 0, 0, 0.1) 0%, <?php echo $panel_background; ?> 100%) !important;
					background: linear, to bottom, rgba(0, 0, 0, 0.1) 0%, <?php echo $panel_background; ?> 100% !important;
				}

				.wcsc-product-countdown-timer .flip-clock-label {
					font-weight: bold;
					font-size: 1.9em;
					color: <?php echo $panel_label_color; ?> !important;
				}

				.wcsc-product-countdown-timer.flip-clock-wrapper .flip {
					margin-bottom: 15px !important;
					margin-left: 0px !important;
				}

				.wcsc-product-countdown-timer.flip-clock-wrapper .flip .inn {
					background: <?php echo $panel_background; ?>;
					color: <?php echo $panel_color; ?>;
				}

				.wcsc-product-countdown-timer .flip-clock-divider.seconds .flip-clock-label {
					right: -105px;
				}

				.wcsc-product-countdown-timer .flip-clock-divider.minutes .flip-clock-label {
					right: -105px;
				}
				.wcsc-product-countdown-timer .flip-clock-divider.days .flip-clock-label,
				.wcsc-product-countdown-timer .flip-clock-divider.hours .flip-clock-label {
					right: -95px;
				}

				@media only screen and (max-width: 1024px) {
					.wcsc-product-countdown-timer.flip-clock-wrapper {
						zoom: 0.48
					}
					.wcsc-product-countdown-timer.flip-clock-wrapper .flip-clock-label {
						font-size: 2.2em;
					}

					.wcsc-product-countdown-timer .flip-clock-divider.seconds .flip-clock-label {
						right: -115px;
					}

					.wcsc-product-countdown-timer .flip-clock-divider.minutes .flip-clock-label {
						right: -110px;
					}
				}

				@media only screen and (max-width: 320px) {
					.wcsc-product-countdown-timer.flip-clock-wrapper {
						zoom: 0.4
					}

					.wcsc-product-countdown-timer.flip-clock-wrapper .flip-clock-label {
						font-size: 2.5em;
					}
					.wcsc-product-countdown-timer .flip-clock-divider.seconds .flip-clock-label {
						right: -130px;
					}

					.wcsc-product-countdown-timer .flip-clock-divider.minutes .flip-clock-label {
						right: -125px;
					}
				}


				/**
					* Stock Progressbar
					*/
				.wscs-product-coutdown-wrapper .product-stock{
					background: #f1f1f1;
					border-radius: 16px;
					height: 14px;
				}

				.wscs-product-coutdown-wrapper .product-stock .percent{
					background: <?php echo esc_attr( $this->settings->stock_color ); ?>;
					border-radius: 16px;
					height: 14px;
				}

				.wscs-product-coutdown-wrapper .product-stock-wrapper {
					margin-top: 25px;
				}

				.wscs-product-coutdown-wrapper .product-stock-wrapper h5 {
					margin-bottom: 10px !important;
				}
			</style>

			<?php
			$css = ob_get_clean();
			echo $css;
		}

		/**
		 * Stock Progress Bar.
		 *
		 * @return String
		 */
		private function product_stock_progress_bar() {
			global $post;

			$product = wc_get_product( $post->ID );
			if ( ! $product ) {
				return false;
			}

			if ( ( 'no' === $this->settings->stock_status ) || ( ! $product->get_manage_stock() ) || ( 0 == $product->get_stock_quantity() ) ) {
				return '';
			}

			add_filter(
				'woocommerce_get_stock_html',
				function( $html ) {
					return '';
				}
			);
			$total_sales = $product->get_total_sales();
			$stock_left  = $product->get_stock_quantity();

			$percent = ( ( $stock_left / ( $total_sales + $stock_left ) ) * 100 ) . '%';

			if ( false !== strpos( $this->settings->stock_label, '{{quantity}}' ) ) :

				$stock_label = '<h5>' . __( str_replace( '{{quantity}}', '<span class="wscs-stock-number" > ' . $stock_left . ' </span> ', esc_html( $this->settings->stock_label ) ), WSCS_DOMAIN ) . '</h5>';

			else :

				$stock_label = '<h5>' . __( 'Only<span class="wscs-stock-number" > ' . $stock_left . ' </span>Items Left in stock!', WSCS_DOMAIN ) . '</h5>';

			endif;
			ob_start();
			?>
			<div class="product-stock-wrapper">
				<h5><?php echo $stock_label; ?></h5>
				<div class="product-stock">
					<div class="percent" style="width:<?php echo $percent; ?>"></div>
				</div>
			</div>
			<?php
			$html = ob_get_clean();
			echo $html;
		}

	}



	add_action( 'plugins_loaded', array( 'WCSC_Woo_Sale_Countdown', 'init' ), 10 );
	register_activation_hook( __FILE__, array( 'WCSC_Woo_Sale_Countdown', 'plugin_activated' ) );

endif;
