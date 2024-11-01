<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! class_exists( 'WSCS_Settings' ) ) :

	/**
	 * Woo Sale Countdown Settings Class.
	 */
	class WSCS_Settings {

		/**
		 * Settings Tabs
		 *
		 * @var Array
		 */
		protected $settings_tabs;

		/**
		 * Active Settings Tab
		 *
		 * @var String
		 */
		protected $current_active_tab;

		/**
		 * Settings Tab Slug
		 *
		 * @var String
		 */
		protected $settings_slug;

		/**
		 * Settings Tab Fields
		 *
		 * @var Array
		 */
		protected $fields;

		/**
		 * Countdown Panel Background
		 *
		 * @var String
		 */
		public $panel_background;

		/**
		 * Countdown Panel Number Color
		 *
		 * @var String
		 */
		public $panel_color;

		/**
		 * Countdown Panel Label Color
		 *
		 * @var String
		 */
		public $panel_label_color;

		/**
		 * Stock Progress bar Status
		 *
		 * @var String
		 */
		public $stock_status;

		/**
		 * Stock Progress bar Label
		 *
		 * @var String
		 */
		public $stock_label;

		/**
		 * Stock Progress bar Color
		 *
		 * @var String
		 */
		public $stock_color;

		/**
		 * Constructor
		 */
		public function __construct() {
			$this->settings_tabs      = array( 'wscs-sale-countdown' => __( 'Sale Countdown', 'woocommrece' ) );
			$this->current_active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';
			$this->settings_slug      = 'woocommerce';

			$this->create_settings_fields();
			$this->get_settings_vars();
			$this->register_hooks();
		}

		/**
		 * Register the Settings Hooks.
		 */
		public function register_hooks() {

			add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 100, 1 );

			foreach ( array_keys( $this->settings_tabs ) as $name ) {
				add_action( 'woocommerce_settings_' . $name, array( $this, 'settings_tab_action' ), 10 );
				add_action( 'woocommerce_update_options_' . $name, array( $this, 'save_settings' ), 10 );
			}

			add_action( 'admin_enqueue_scripts', array( $this, 'add_settings_assets' ), 1000 );
		}

		/**
		 * Enqueue Admin assets
		 *
		 * @return void
		 */
		public function add_settings_assets() {

			if ( ! empty( $_GET['tab'] ) && in_array( wp_unslash( $_GET['tab'] ), array_keys( $this->settings_tabs ) ) ) {
				wp_enqueue_style( WCSC_PREFIX . '_admin-flipclock-styles', WCSC_ASSETS_URL . '/css/flipclock.css', array(), WCSC_VERSION, false );
				wp_add_inline_style( WCSC_PREFIX . '_admin-flipclock-styles', $this->admin_settings_styles() );

				if ( wp_script_is( 'jquery' ) ) {
					wp_enqueue_script( 'jquery' );
				}

				wp_enqueue_script( WCSC_PREFIX . '_admin_actions', WCSC_ASSETS_URL . '/js/settings-actions.js', array( 'woocommerce_settings' ), WCSC_VERSION, true );

			}

			$screen = get_current_screen();

			if ( ( 'post' === $screen->base ) && ( 'product' === $screen->post_type ) ) {
				// Product edit Page.
				if ( wp_script_is( 'jquery' ) ) {
					wp_enqueue_script( 'jquery' );
				}
				wp_enqueue_script( WCSC_PREFIX . '_admin_actions', WCSC_ASSETS_URL . '/js/edit-product-actions.js', array( 'jquery' ), WCSC_VERSION, true );

				wp_localize_script(
					WCSC_PREFIX . '_admin_actions',
					WCSC_PREFIX . '_ajax_data',
					array(
						'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
						'nonce'     => wp_create_nonce( WCSC_PREFIX . '_nonce' ),
						'endDate'   => $this->get_product_sale_time(),
						'startDate' => $this->get_product_sale_time( 'from' ),
					)
				);
			}

		}

		/**
		 * Get Product Sale
		 *
		 * @return DATETIME
		 */
		public function get_product_sale_time( $which = 'to', $side = 'back' ) {
			global $post;
			if ( ( 'product' !== get_post_type( $post->ID ) ) || ( 'to' !== $which && 'from' !== $which ) ) {
				return;
			}
			$product              = wc_get_product( $post->ID );
			$product_due_date_obj = call_user_func( array( $product, 'get_date_on_sale_' . $which ) );

			if ( empty( $product_due_date_obj ) ) {
				return;
			}

			$sale_date = $product_due_date_obj->date_i18n( 'Y-m-d H:i:s' );
			if ( 'back' === $side ) {
				$sale_date = date( 'Y-m-d H:i:s', strtotime( $sale_date ) );
			}

			return $sale_date;
		}

		/**
		 * Get Settings Variables Values.
		 *
		 * @return void
		 */
		private function get_settings_vars() {
			$this->panel_background  = get_option( 'wscs-sale-countdown-panel-background' );
			$this->panel_color       = get_option( 'wscs-sale-countdown-number-color' );
			$this->panel_label_color = get_option( 'wscs-sale-countdown-label-color' );
			$this->stock_status      = get_option( 'wscs-sale-countdown-stock-progress' );
			$this->stock_label       = get_option( 'wscs-sale-countdown-stock-progress-label' );
			$this->stock_color       = get_option( 'wscs-sale-countdown-stock-progress-color', '#3882d0' );
		}

		/**
		 * Settings Tab styles.
		 *
		 * @return String
		 */
		private function admin_settings_styles() {
			$this->get_settings_vars();
			ob_start();
			?>
			.description .flip-clock-wrapper {
				position: absolute;
				top: 145px;
				zoom: 0.6;
			}

			.description .flip-clock-wrapper .inn {
				background: <?php echo esc_attr( $this->panel_background ); ?>;
				color: <?php echo esc_attr( $this->panel_color ); ?>;
			}

			.wscs-product-coutdown-wrapper .product-stock{
				position: absolute;
				background: #f1f1f1;
				border-radius: 16px;
				height: 14px;
				width: 80px;
				top: 200px;
			}


			.product-stock-wrapper {
				width: 150px;
				height: 14px;
				border-radius: 16px;
				background: #ddd;
				position: absolute;
				top: 160px;
				margin-left: 10px;
			}

			.product-stock-wrapper .percent {
				background: <?php echo esc_attr( $this->stock_color ); ?>;
				border-radius: 16px;
				height: 14px;
			}
			<?php
			return ob_get_clean();
		}

		/**
		 * Simple HTML for the countdown single panel.
		 *
		 * @return String
		 */
		private function countdown_panel_html() {
			ob_start();
			?>
			<span class="flip-clock-wrapper">
				<ul class="flip ">
					<li class="flip-clock-before">
						<a href="#">
							<div class="up">
								<div class="shadow">

								</div><div class="inn">1</div>
							</div>
							<div class="down">
								<div class="shadow"></div>
								<div class="inn">1</div>
							</div>
						</a>
					</li>
					<li class="flip-clock-active">
						<a href="#">
							<div class="up">
								<div class="shadow"></div>
								<div class="inn">1</div>
							</div>
							<div class="down">
								<div class="shadow"></div>
								<div class="inn">1</div>
							</div>
						</a>
					</li>
				</ul>
			</span>

			<?php
			return ob_get_clean();
		}

		/**
		 * Stock Progress Bar HTML.
		 *
		 * @return String
		 */
		public function stock_progressbar_html() {
			ob_start();
			?>
			<span class="product-stock-wrapper">
				<div class="product-stock">
					<div class="percent" style="width:80%"></div>
				</div>
			</span>
			<?php
			return ob_get_clean();
		}

		/**
		 * Plugin Settings Tab in WordPress Settings Page.
		 *
		 * @return void
		 */
		public function add_settings_tab( $settings_tabs ) {
			foreach ( array_keys( $this->settings_tabs ) as $name ) {
				$settings_tabs[ $name ] = $this->settings_tabs[ $name ];
			}

			return $settings_tabs;
		}

		/**
		 * SHow the Settings Tab Fields.
		 *
		 * @return void
		 */
		public function settings_tab_action() {
			global $current_tab;
			woocommerce_admin_fields( $this->fields[ $current_tab ] );
		}

		/**
		 * Save Tab Settings.
		 *
		 * @return void
		 */
		public function save_settings() {
			global $current_tab;
			woocommerce_update_options( $this->fields[ $current_tab ] );
		}

		/**
		 * Create the Tab Fields
		 *
		 * @return void
		 */
		public function create_settings_fields() {
			$this->fields['wscs-sale-countdown'] = array(
				array(
					'name' => __( 'Settings', WSCS_DOMAIN ),
					'type' => 'title',
					'id'   => 'wscs-main-title',
				),

				array(
					'name'        => __( 'Countdown Title', WSCS_DOMAIN ),
					'desc_tip'    => 'The Title above the CountDown',
					'id'          => 'wscs-sale-countdown',
					'type'        => 'text',
					'default'     => __( 'Hurry up! Sale Ends in', WSCS_DOMAIN ),
					'placeholder' => __( 'some placeholder', WSCS_DOMAIN ),
				),
				array(
					'name'     => __( 'CountDown Panel Background', WSCS_DOMAIN ),
					'desc_tip' => __( 'The countDown panel Background', WSCS_DOMAIN ),
					'id'       => 'wscs-sale-countdown-panel-background',
					'type'     => 'color',
					'default'  => '#1A1A1A',
					'desc'     => $this->countDown_panel_html(),
				),
				array(
					'name'     => __( 'CountDown Panel Color', WSCS_DOMAIN ),
					'desc_tip' => __( 'The Countdown number color', WSCS_DOMAIN ),
					'id'       => 'wscs-sale-countdown-number-color',
					'type'     => 'color',
					'default'  => '#ccc',
				),
				array(
					'name'     => __( 'CountDown label Color', WSCS_DOMAIN ),
					'desc_tip' => __( 'Days, Hours, Minutes, Seconds Labels Color', WSCS_DOMAIN ),
					'id'       => 'wscs-sale-countdown-label-color',
					'type'     => 'color',
					'default'  => '#000',
				),
				array(
					'name' => '',
					'type' => 'sectionend',
					'id'   => 'wscs_main_settings_sectionend',
				),
				// Stock Progress Bar
				array(
					'name' => __( 'Stock Progress bar', WSCS_DOMAIN ),
					'type' => 'title',
					'id'   => 'wscs-stock-progress-bar',
				),
				array(
					'name'     => __( 'Enable Product Stock Progress bar?', WSCS_DOMAIN ),
					'id'       => 'wscs-sale-countdown-stock-progress',
					'type'     => 'checkbox',
					'desc_tip' => 'The bar will appear only if the product has stock managment enabled and has stock quantity',
				),
				array(
					'name'     => __( 'Stock Progress bar Label', WSCS_DOMAIN ),
					'id'       => 'wscs-sale-countdown-stock-progress-label',
					'type'     => 'text',
					'default'  => 'Only {{quantity}} Items Left in stock!',
					'desc_tip' => '{{quantity}} is mandatory to be replaced with the product stock number!',
					'desc'     => '{{quantity}} is mandatory to be replaced with the product stock number!',

				),
				array(
					'name'    => __( 'Stock Progress bar Color', WSCS_DOMAIN ),
					'id'      => 'wscs-sale-countdown-stock-progress-color',
					'desc'    => $this->stock_progressbar_html(),
					'type'    => 'color',
					'default' => '#2196F3',
				),
				array(
					'name' => '',
					'type' => 'sectionend',
					'id'   => 'wscs_main_settings_sectionend',
				),
			);

		}
	}

endif;
?>
