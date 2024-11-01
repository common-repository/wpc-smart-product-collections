<?php
/*
Plugin Name: WPC Smart Product Collections for WooCommerce
Plugin URI: https://wpclever.net/
Description: WPC Smart Product Collections allows you to manage product collections in the easiest.
Version: 1.1.2
Author: WPClever
Author URI: https://wpclever.net
Text Domain: wpc-smart-product-collections
Domain Path: /languages/
Requires Plugins: woocommerce
Requires at least: 4.0
Tested up to: 6.6
WC requires at least: 3.0
WC tested up to: 9.2
*/

defined( 'ABSPATH' ) || exit;

! defined( 'WPCPC_VERSION' ) && define( 'WPCPC_VERSION', '1.1.2' );
! defined( 'WPCPC_LITE' ) && define( 'WPCPC_LITE', __FILE__ );
! defined( 'WPCPC_FILE' ) && define( 'WPCPC_FILE', __FILE__ );
! defined( 'WPCPC_URI' ) && define( 'WPCPC_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WPCPC_REVIEWS' ) && define( 'WPCPC_REVIEWS', 'https://wordpress.org/support/plugin/wpc-smart-product-collections/reviews/?filter=5' );
! defined( 'WPCPC_CHANGELOG' ) && define( 'WPCPC_CHANGELOG', 'https://wordpress.org/plugins/wpc-smart-product-collections/#developers' );
! defined( 'WPCPC_DISCUSSION' ) && define( 'WPCPC_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-smart-product-collections' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WPCPC_URI );

include 'includes/dashboard/wpc-dashboard.php';
include 'includes/kit/wpc-kit.php';
include 'includes/hpos.php';

if ( ! function_exists( 'wpcpc_init' ) ) {
	require_once 'includes/class-helper.php';

	add_action( 'plugins_loaded', 'wpcpc_init', 11 );

	function wpcpc_init() {
		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
			add_action( 'admin_notices', 'wpcpc_notice_wc' );

			return null;
		}

		if ( ! class_exists( 'WPCleverWpcpc' ) && class_exists( 'WC_Product' ) ) {
			class WPCleverWpcpc {
				protected static $settings = [];
				protected static $instance = null;

				public static function instance() {
					if ( is_null( self::$instance ) ) {
						self::$instance = new self();
					}

					return self::$instance;
				}

				function __construct() {
					self::$settings = (array) get_option( 'wpcpc_settings', [] );

					// init
					add_action( 'init', [ $this, 'wp_init' ] );
					add_action( 'woocommerce_init', [ $this, 'woo_init' ] );

					// settings
					add_action( 'admin_init', [ $this, 'register_settings' ] );
					add_action( 'admin_menu', [ $this, 'admin_menu' ] );

					// backend scripts
					add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

					// frontend scripts
					add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

					// link
					add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
					add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

					// ajax backend
					add_action( 'wp_ajax_wpcpc_search_term', [ $this, 'ajax_search_term' ] );
					add_action( 'wp_ajax_wpcpc_add_condition', [ $this, 'ajax_add_condition' ] );

					// backend collections
					add_action( 'wpc-collection_add_form_fields', [ $this, 'add_form_fields' ] );
					add_action( 'wpc-collection_edit_form_fields', [ $this, 'edit_form_fields' ] );
					add_action( 'edit_wpc-collection', [ $this, 'save_form_fields' ] );
					add_action( 'create_wpc-collection', [ $this, 'save_form_fields' ] );
					add_filter( 'manage_edit-wpc-collection_columns', [ $this, 'collection_columns' ] );
					add_filter( 'manage_wpc-collection_custom_column', [ $this, 'collection_columns_content' ], 10, 3 );

					// backend products
					add_filter( 'woocommerce_product_filters', [ $this, 'product_filter' ] );

					// archive
					add_action( 'woocommerce_archive_description', [ $this, 'collection_banner' ], 15 );

					// product tab
					if ( apply_filters( 'wpcpc_single_position', self::get_setting( 'single_position', 'after_meta' ) ) === 'tab' ) {
						add_filter( 'woocommerce_product_tabs', [ $this, 'product_tabs' ] );
					}

					// products shortcode
					add_filter( 'woocommerce_shortcode_products_query', [ $this, 'shortcode_products_query' ] );
				}

				function wp_init() {
					// load text-domain
					load_plugin_textdomain( 'wpc-smart-product-collections', false, basename( __DIR__ ) . '/languages/' );

					// image sizes
					add_image_size( 'wpcpc-logo', 96, 96 );

					// shortcode
					add_shortcode( 'wpcpc', [ $this, 'collection_shortcode' ] );
					add_shortcode( 'wpcpc_banner', [ $this, 'collection_banner_shortcode' ] );

					// show image for archive
					$archive_position = apply_filters( 'wpcpc_archive_position', self::get_setting( 'archive_position', 'after_title' ) );

					switch ( $archive_position ) {
						case 'before_thumbnail':
							add_action( 'woocommerce_before_shop_loop_item', [ $this, 'collection_archive' ], 9 );
							break;
						case 'before_title':
							add_action( 'woocommerce_shop_loop_item_title', [ $this, 'collection_archive' ], 9 );
							break;
						case 'after_title':
							add_action( 'woocommerce_shop_loop_item_title', [ $this, 'collection_archive' ], 11 );
							break;
						case 'after_rating':
							add_action( 'woocommerce_after_shop_loop_item_title', [ $this, 'collection_archive' ], 6 );
							break;
						case 'after_price':
							add_action( 'woocommerce_after_shop_loop_item_title', [ $this, 'collection_archive' ], 11 );
							break;
						case 'before_add_to_cart':
							add_action( 'woocommerce_after_shop_loop_item', [ $this, 'collection_archive' ], 9 );
							break;
						case 'after_add_to_cart':
							add_action( 'woocommerce_after_shop_loop_item', [ $this, 'collection_archive' ], 11 );
							break;
					}

					// show image for single
					$single_position = apply_filters( 'wpcpc_single_position', self::get_setting( 'single_position', 'after_meta' ) );

					switch ( $single_position ) {
						case 'before_title':
							add_action( 'woocommerce_single_product_summary', [ $this, 'collection_single' ], 4 );
							break;
						case 'after_title':
							add_action( 'woocommerce_single_product_summary', [ $this, 'collection_single' ], 6 );
							break;
						case 'after_price':
							add_action( 'woocommerce_single_product_summary', [ $this, 'collection_single' ], 11 );
							break;
						case 'after_excerpt':
							add_action( 'woocommerce_single_product_summary', [ $this, 'collection_single' ], 21 );
							break;
						case 'before_add_to_cart':
							add_action( 'woocommerce_single_product_summary', [ $this, 'collection_single' ], 29 );
							break;
						case 'after_add_to_cart':
							add_action( 'woocommerce_single_product_summary', [ $this, 'collection_single' ], 31 );
							break;
						case 'after_meta':
							add_action( 'woocommerce_single_product_summary', [ $this, 'collection_single' ], 41 );
							break;
						case 'after_sharing':
							add_action( 'woocommerce_single_product_summary', [ $this, 'collection_single' ], 51 );
							break;
					}
				}

				function woo_init() {
					$labels = [
						'name'                       => esc_html__( 'Collections', 'wpc-smart-product-collections' ),
						'singular_name'              => esc_html__( 'Collection', 'wpc-smart-product-collections' ),
						'menu_name'                  => esc_html__( 'Collections', 'wpc-smart-product-collections' ),
						'all_items'                  => esc_html__( 'All Collections', 'wpc-smart-product-collections' ),
						'edit_item'                  => esc_html__( 'Edit Collection', 'wpc-smart-product-collections' ),
						'view_item'                  => esc_html__( 'View Collection', 'wpc-smart-product-collections' ),
						'update_item'                => esc_html__( 'Update Collection', 'wpc-smart-product-collections' ),
						'add_new_item'               => esc_html__( 'Add New Collection', 'wpc-smart-product-collections' ),
						'new_item_name'              => esc_html__( 'New Collection Name', 'wpc-smart-product-collections' ),
						'parent_item'                => esc_html__( 'Parent Collection', 'wpc-smart-product-collections' ),
						'parent_item_colon'          => esc_html__( 'Parent Collection:', 'wpc-smart-product-collections' ),
						'search_items'               => esc_html__( 'Search Collections', 'wpc-smart-product-collections' ),
						'popular_items'              => esc_html__( 'Popular Collections', 'wpc-smart-product-collections' ),
						'back_to_items'              => esc_html__( '&larr; Go to Collections', 'wpc-smart-product-collections' ),
						'separate_items_with_commas' => esc_html__( 'Separate collections with commas', 'wpc-smart-product-collections' ),
						'add_or_remove_items'        => esc_html__( 'Add or remove collections', 'wpc-smart-product-collections' ),
						'choose_from_most_used'      => esc_html__( 'Choose from the most used collections', 'wpc-smart-product-collections' ),
						'not_found'                  => esc_html__( 'No collections found', 'wpc-smart-product-collections' )
					];

					$args = [
						'hierarchical'       => true,
						'labels'             => $labels,
						'show_ui'            => true,
						'query_var'          => true,
						'public'             => true,
						'publicly_queryable' => true,
						'show_in_menu'       => true,
						'show_in_rest'       => true,
						'show_admin_column'  => true,
						'rewrite'            => [
							'slug'         => apply_filters( 'wpcpc_taxonomy_slug', self::get_setting( 'slug', 'collection' ) ),
							'hierarchical' => true,
							'with_front'   => apply_filters( 'wpcpc_taxonomy_with_front', true )
						]
					];

					register_taxonomy( 'wpc-collection', [ 'product' ], $args );
				}

				public static function get_settings() {
					return apply_filters( 'wpcpc_get_settings', self::$settings );
				}

				public static function get_setting( $name, $default = false ) {
					if ( ! empty( self::$settings ) && isset( self::$settings[ $name ] ) ) {
						$setting = self::$settings[ $name ];
					} else {
						$setting = get_option( 'wpcpc_' . $name, $default );
					}

					return apply_filters( 'wpcpc_get_setting', $setting, $name, $default );
				}

				function register_settings() {
					register_setting( 'wpcpc_settings', 'wpcpc_settings' );
				}

				function admin_menu() {
					add_submenu_page( 'wpclever', esc_html__( 'WPC Smart Product Collections', 'wpc-smart-product-collections' ), esc_html__( 'Smart Collections', 'wpc-smart-product-collections' ), 'manage_options', 'wpclever-wpcpc', [
						$this,
						'admin_menu_content'
					] );
				}

				function admin_menu_content() {
					add_thickbox();
					$active_tab = sanitize_key( $_GET['tab'] ?? 'settings' );
					?>
                    <div class="wpclever_settings_page wrap">
                        <h1 class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Smart Product Collections', 'wpc-smart-product-collections' ) . ' ' . esc_html( WPCPC_VERSION ); ?></h1>
                        <div class="wpclever_settings_page_desc about-text">
                            <p>
								<?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-smart-product-collections' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                                <br/>
                                <a href="<?php echo esc_url( WPCPC_REVIEWS ); ?>" target="_blank"><?php esc_html_e( 'Reviews', 'wpc-smart-product-collections' ); ?></a> |
                                <a href="<?php echo esc_url( WPCPC_CHANGELOG ); ?>" target="_blank"><?php esc_html_e( 'Changelog', 'wpc-smart-product-collections' ); ?></a> |
                                <a href="<?php echo esc_url( WPCPC_DISCUSSION ); ?>" target="_blank"><?php esc_html_e( 'Discussion', 'wpc-smart-product-collections' ); ?></a>
                            </p>
                        </div>
						<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
                            <div class="notice notice-success is-dismissible">
                                <p><?php esc_html_e( 'Settings updated.', 'wpc-smart-product-collections' ); ?></p>
                            </div>
						<?php } ?>
                        <div class="wpclever_settings_page_nav">
                            <h2 class="nav-tab-wrapper">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcpc&tab=settings' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'Settings', 'wpc-smart-product-collections' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=wpc-collection&post_type=product' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'collections' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'Product Collections', 'wpc-smart-product-collections' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcpc&tab=shortcodes' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'shortcodes' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'Shortcodes', 'wpc-smart-product-collections' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>" class="nav-tab">
									<?php esc_html_e( 'Essential Kit', 'wpc-smart-product-collections' ); ?>
                                </a>
                            </h2>
                        </div>
                        <div class="wpclever_settings_page_content">
							<?php if ( $active_tab === 'settings' ) {
								if ( isset( $_REQUEST['settings-updated'] ) && $_REQUEST['settings-updated'] === 'true' ) {
									flush_rewrite_rules();
								}
								?>
                                <form method="post" action="options.php">
                                    <table class="form-table">
                                        <tr class="heading">
                                            <th colspan="2">
												<?php esc_html_e( 'General', 'wpc-smart-product-collections' ); ?>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Slug', 'wpc-smart-product-collections' ); ?></th>
                                            <td>
                                                <label>
                                                    <input type="text" class="regular-text" name="wpcpc_settings[slug]" value="<?php echo esc_attr( self::get_setting( 'slug', 'collection' ) ); ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Logo size', 'wpc-smart-product-collections' ); ?></th>
                                            <td>
												<?php
												$logo_size          = self::get_setting( 'logo_size', 'wpcpc-logo' );
												$logo_sizes         = self::image_sizes();
												$logo_sizes['full'] = [
													'width'  => '',
													'height' => '',
													'crop'   => false
												];

												if ( ! empty( $logo_sizes ) ) {
													echo '<select name="wpcpc_settings[logo_size]">';

													foreach ( $logo_sizes as $logo_size_name => $logo_size_data ) {
														echo '<option value="' . esc_attr( $logo_size_name ) . '" ' . ( $logo_size_name === $logo_size ? 'selected' : '' ) . '>' . esc_attr( $logo_size_name ) . ( ! empty( $logo_size_data['width'] ) ? ' ' . $logo_size_data['width'] . '&times;' . $logo_size_data['height'] : '' ) . ( $logo_size_data['crop'] ? ' (cropped)' : '' ) . '</option>';
													}

													echo '</select>';
												}
												?>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th colspan="2">
												<?php esc_html_e( 'Products archive', 'wpc-smart-product-collections' ); ?>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Position', 'wpc-smart-product-collections' ); ?></th>
                                            <td>
												<?php $archive_position = apply_filters( 'wpcpc_archive_position', 'default' ); ?>
                                                <label>
                                                    <select name="wpcpc_settings[archive_position]" <?php echo esc_attr( $archive_position !== 'default' ? 'disabled' : '' ); ?>>
														<?php if ( $archive_position === 'default' ) {
															$archive_position = self::get_setting( 'archive_position', 'after_title' );
														} ?>
                                                        <option value="before_thumbnail" <?php selected( $archive_position, 'before_thumbnail' ); ?>><?php esc_html_e( 'Above thumbnail', 'wpc-smart-product-collections' ); ?></option>
                                                        <option value="before_title" <?php selected( $archive_position, 'before_title' ); ?>><?php esc_html_e( 'Above title', 'wpc-smart-product-collections' ); ?></option>
                                                        <option value="after_title" <?php selected( $archive_position, 'after_title' ); ?>><?php esc_html_e( 'Under title', 'wpc-smart-product-collections' ); ?></option>
                                                        <option value="after_rating" <?php selected( $archive_position, 'after_rating' ); ?>><?php esc_html_e( 'Under rating', 'wpc-smart-product-collections' ); ?></option>
                                                        <option value="after_price" <?php selected( $archive_position, 'after_price' ); ?>><?php esc_html_e( 'Under price', 'wpc-smart-product-collections' ); ?></option>
                                                        <option value="before_add_to_cart" <?php selected( $archive_position, 'before_add_to_cart' ); ?>><?php esc_html_e( 'Above add to cart button', 'wpc-smart-product-collections' ); ?></option>
                                                        <option value="after_add_to_cart" <?php selected( $archive_position, 'after_add_to_cart' ); ?>><?php esc_html_e( 'Under add to cart button', 'wpc-smart-product-collections' ); ?></option>
                                                        <option value="0" <?php echo esc_attr( ! $archive_position ? 'selected' : '' ); ?>><?php esc_html_e( 'None (hide it)', 'wpc-smart-product-collections' ); ?></option>
                                                    </select> </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Type', 'wpc-smart-product-collections' ); ?></th>
                                            <td>
												<?php $archive_type = apply_filters( 'wpcpc_archive_type', 'default' ); ?>
                                                <label>
                                                    <select name="wpcpc_settings[archive_type]" <?php echo esc_attr( $archive_type !== 'default' ? 'disabled' : '' ); ?>>
														<?php if ( $archive_type === 'default' ) {
															$archive_type = self::get_setting( 'archive_type', 'text' );
														} ?>
                                                        <option value="text" <?php selected( $archive_type, 'text' ); ?>><?php esc_html_e( 'Text', 'wpc-smart-product-collections' ); ?></option>
                                                        <option value="image" <?php selected( $archive_type, 'image' ); ?>><?php esc_html_e( 'Image', 'wpc-smart-product-collections' ); ?></option>
                                                        <option value="both" <?php selected( $archive_type, 'both' ); ?>><?php esc_html_e( 'Text & Image', 'wpc-smart-product-collections' ); ?></option>
                                                    </select> </label>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th colspan="2">
												<?php esc_html_e( 'Single product', 'wpc-smart-product-collections' ); ?>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Position', 'wpc-smart-product-collections' ); ?></th>
                                            <td>
												<?php $single_position = apply_filters( 'wpcpc_single_position', 'default' ); ?>
                                                <label>
                                                    <select name="wpcpc_settings[single_position]" <?php echo esc_attr( $single_position !== 'default' ? 'disabled' : '' ); ?>>
														<?php if ( $single_position === 'default' ) {
															$single_position = self::get_setting( 'single_position', 'after_meta' );
														} ?>
                                                        <option value="before_title" <?php selected( $single_position, 'before_title' ); ?>><?php esc_html_e( 'Above title', 'wpc-smart-product-collections' ); ?></option>
                                                        <option value="after_title" <?php selected( $single_position, 'after_title' ); ?>><?php esc_html_e( 'Under title', 'wpc-smart-product-collections' ); ?></option>
                                                        <option value="after_price" <?php selected( $single_position, 'after_price' ); ?>><?php esc_html_e( 'Under price', 'wpc-smart-product-collections' ); ?></option>
                                                        <option value="after_excerpt" <?php selected( $single_position, 'after_excerpt' ); ?>><?php esc_html_e( 'Under excerpt', 'wpc-smart-product-collections' ); ?></option>
                                                        <option value="before_add_to_cart" <?php selected( $single_position, 'before_add_to_cart' ); ?>><?php esc_html_e( 'Above add to cart button', 'wpc-smart-product-collections' ); ?></option>
                                                        <option value="after_add_to_cart" <?php selected( $single_position, 'after_add_to_cart' ); ?>><?php esc_html_e( 'Under add to cart button', 'wpc-smart-product-collections' ); ?></option>
                                                        <option value="after_meta" <?php selected( $single_position, 'after_meta' ); ?>><?php esc_html_e( 'Under meta', 'wpc-smart-product-collections' ); ?></option>
                                                        <option value="after_sharing" <?php selected( $single_position, 'after_sharing' ); ?>><?php esc_html_e( 'Under sharing', 'wpc-smart-product-collections' ); ?></option>
                                                        <option value="tab" <?php selected( $single_position, 'tab' ); ?>><?php esc_html_e( 'In a new tab', 'wpc-smart-product-collections' ); ?></option>
                                                        <option value="0" <?php echo esc_attr( ! $single_position ? 'selected' : '' ); ?>><?php esc_html_e( 'None (hide it)', 'wpc-smart-product-collections' ); ?></option>
                                                    </select> </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Type', 'wpc-smart-product-collections' ); ?></th>
                                            <td>
												<?php $single_type = apply_filters( 'wpcpc_single_type', 'default' ); ?>
                                                <label>
                                                    <select name="wpcpc_settings[single_type]" <?php echo esc_attr( $single_type !== 'default' ? 'disabled' : '' ); ?>>
														<?php if ( $single_type === 'default' ) {
															$single_type = self::get_setting( 'single_type', 'text' );
														} ?>
                                                        <option value="text" <?php selected( $single_type, 'text' ); ?>><?php esc_html_e( 'Text', 'wpc-smart-product-collections' ); ?></option>
                                                        <option value="image" <?php selected( $single_type, 'image' ); ?>><?php esc_html_e( 'Image', 'wpc-smart-product-collections' ); ?></option>
                                                        <option value="both" <?php selected( $single_type, 'both' ); ?>><?php esc_html_e( 'Text & Image', 'wpc-smart-product-collections' ); ?></option>
                                                    </select> </label>
                                            </td>
                                        </tr>
                                        <tr class="submit">
                                            <th colspan="2">
												<?php settings_fields( 'wpcpc_settings' ); ?><?php submit_button(); ?>
                                            </th>
                                        </tr>
                                    </table>
                                </form>
							<?php } elseif ( $active_tab === 'shortcodes' ) { ?>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">[wpcpc]</th>
                                        <td>
                                            Collections info for single product.

                                            <ul class="wpcpc_shortcode_attrs">
                                                <li><em>product_id</em> - (optional) product ID</li>
                                            </ul>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">[wpcpc_banner]</th>
                                        <td>
                                            Collection banner.

                                            <ul class="wpcpc_shortcode_attrs">
                                                <li><em>id</em> - (optional) collection ID</li>
                                            </ul>
                                        </td>
                                    </tr>
                                </table>
							<?php } ?>
                        </div><!-- /.wpclever_settings_page_content -->
                        <div class="wpclever_settings_page_suggestion">
                            <div class="wpclever_settings_page_suggestion_label">
                                <span class="dashicons dashicons-yes-alt"></span> Suggestion
                            </div>
                            <div class="wpclever_settings_page_suggestion_content">
                                <div>
                                    To display custom engaging real-time messages on any wished positions, please install
                                    <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC Smart Messages</a> plugin. It's free!
                                </div>
                                <div>
                                    Wanna save your precious time working on variations? Try our brand-new free plugin
                                    <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC Variation Bulk Editor</a> and
                                    <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC Variation Duplicator</a>.
                                </div>
                            </div>
                        </div>
                    </div>
					<?php
				}

				function admin_enqueue_scripts() {
					wp_enqueue_media();
					wp_enqueue_style( 'wpcpc-backend', WPCPC_URI . 'assets/css/backend.css', [ 'woocommerce_admin_styles' ], WPCPC_VERSION );
					wp_enqueue_script( 'wpcpc-backend', WPCPC_URI . 'assets/js/backend.js', [
						'jquery',
						'wc-enhanced-select',
						'selectWoo'
					], WPCPC_VERSION, true );
				}

				function enqueue_scripts() {
					wp_enqueue_style( 'wpcpc-frontend', WPCPC_URI . 'assets/css/frontend.css', [], WPCPC_VERSION );
				}

				function action_links( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$settings = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcpc&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'wpc-smart-product-collections' ) . '</a>';
						array_unshift( $links, $settings );
					}

					return (array) $links;
				}

				function row_meta( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$row_meta = [
							'support' => '<a href="' . esc_url( WPCPC_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-smart-product-collections' ) . '</a>',
						];

						return array_merge( $links, $row_meta );
					}

					return (array) $links;
				}

				function ajax_search_term() {
					$return = [];

					$args = [
						'taxonomy'   => sanitize_text_field( $_REQUEST['taxonomy'] ),
						'orderby'    => 'id',
						'order'      => 'ASC',
						'hide_empty' => false,
						'fields'     => 'all',
						'name__like' => sanitize_text_field( $_REQUEST['q'] ),
					];

					$terms = get_terms( $args );

					if ( count( $terms ) ) {
						foreach ( $terms as $term ) {
							$return[] = [ $term->slug, $term->name ];
						}
					}

					wp_send_json( $return );
				}

				function ajax_add_condition() {
					self::condition();
					wp_die();
				}

				function condition( $key = '', $condition = [] ) {
					if ( empty( $key ) ) {
						$key = uniqid();
					}

					$apply   = $condition['apply'] ?? 'sale';
					$compare = $condition['compare'] ?? 'is';
					$value   = (array) ( $condition['value'] ?? [] );
					?>
                    <div class="wpcpc_condition">
                        <div>
                            <span class="wpcpc_condition_remove"> &times; </span>
                        </div>
                        <div>
                            <div class="wpcpc_condition_apply_wrap">
                                <label>
                                    <select class="wpcpc_condition_apply" name="wpcpc_conditions[<?php echo esc_attr( $key ); ?>][apply]">
										<?php
										$taxonomies = get_object_taxonomies( 'product', 'objects' ); //$taxonomies = get_taxonomies( [ 'object_type' => [ 'product' ] ], 'objects' );

										foreach ( $taxonomies as $taxonomy ) {
											if ( $taxonomy->name != 'wpc-collection' ) {
												echo '<option value="' . esc_attr( $taxonomy->name ) . '" ' . ( $apply === $taxonomy->name ? 'selected' : '' ) . '>' . esc_html( $taxonomy->label ) . '</option>';
											}
										}
										?>
                                    </select> </label> <label>
                                    <select class="wpcpc_condition_compare" name="wpcpc_conditions[<?php echo esc_attr( $key ); ?>][compare]">
                                        <option value="is" <?php selected( $compare, 'is' ); ?>><?php esc_html_e( 'including', 'wpc-smart-product-collections' ); ?></option>
                                        <option value="is_not" <?php selected( $compare, 'is_not' ); ?>><?php esc_html_e( 'excluding', 'wpc-smart-product-collections' ); ?></option>
                                    </select> </label>
                            </div>
                            <div class="wpcpc_condition_value_wrap">
                                <label>
                                    <select class="wpcpc_condition_value" data-<?php echo esc_attr( $apply ); ?>="<?php echo esc_attr( implode( ',', $value ) ); ?>" name="wpcpc_conditions[<?php echo esc_attr( $key ); ?>][value][]" multiple="multiple">
										<?php if ( ! empty( $value ) ) {
											foreach ( $value as $t ) {
												if ( $term = get_term_by( 'slug', $t, $apply ) ) {
													echo '<option value="' . esc_attr( $t ) . '" selected>' . esc_html( $term->name ) . '</option>';
												}
											}
										} ?>
                                    </select> </label>
                            </div>
                        </div>
                    </div>
					<?php
				}

				function product_tabs( $tabs ) {
					global $product;

					if ( $product ) {
						$product_id  = $product->get_id();
						$collections = wc_get_product_terms( $product_id, 'wpc-collection' );

						if ( is_array( $collections ) && ! empty( $collections ) ) {
							$tabs['wpcpc'] = [
								'title'    => esc_html__( 'Collections', 'wpc-smart-product-collections' ),
								'priority' => 50,
								'callback' => [ $this, 'product_tabs_callback' ]
							];
						}
					}

					return $tabs;
				}

				function shortcode_products_query( $query_args ) {
					if ( isset( $query_args['tax_query'] ) ) {
						foreach ( $query_args['tax_query'] as $k => $q ) {
							if ( isset( $q['taxonomy'] ) && ( $q['taxonomy'] === 'pa_wpc-collection' ) ) {
								$query_args['tax_query'][ $k ]['taxonomy'] = 'wpc-collection';
							}
						}
					}

					return $query_args;
				}

				function product_tabs_callback() {
					echo do_shortcode( '[wpcpc context="tab"]' );
				}

				function add_form_fields() {
					self::form_fields();
				}

				function edit_form_fields( $term ) {
					self::form_fields( $term );
				}

				function form_fields( $term = null ) {
					if ( $term ) {
						$logo        = get_term_meta( $term->term_id, 'wpcpc_logo', true ) ?: '';
						$banner      = get_term_meta( $term->term_id, 'wpcpc_banner', true ) ?: '';
						$banner_link = get_term_meta( $term->term_id, 'wpcpc_banner_link', true ) ?: '';
						$conditions  = (array) ( get_term_meta( $term->term_id, 'wpcpc_conditions', true ) ?: [] );
						$include     = (array) ( get_term_meta( $term->term_id, 'wpcpc_include', true ) ?: [] );
						$exclude     = (array) ( get_term_meta( $term->term_id, 'wpcpc_exclude', true ) ?: [] );
						$table_start = '<table class="form-table">';
						$table_end   = '</table>';
						$tr_start    = '<tr class="form-field">';
						$tr_end      = '</tr>';
						$th_start    = '<th scope="row">';
						$th_end      = '</th>';
						$td_start    = '<td>';
						$td_end      = '</td>';
					} else {
						// add new
						$logo        = '';
						$banner      = '';
						$banner_link = '';
						$conditions  = [];
						$include     = [];
						$exclude     = [];
						$table_start = '';
						$table_end   = '';
						$tr_start    = '<div class="form-field">';
						$tr_end      = '</div>';
						$th_start    = '';
						$th_end      = '';
						$td_start    = '';
						$td_end      = '';
					}

					echo $table_start . $tr_start . $th_start;
					?>
                    <label for="wpcpc_logo"><?php esc_html_e( 'Logo', 'wpc-smart-product-collections' ); ?></label>
					<?php
					echo $th_end . $td_start;
					?>
                    <div class="wpcpc_image_uploader">
                        <input type="hidden" name="wpcpc_logo" id="wpcpc_logo" class="wpcpc_image_val" value="<?php echo esc_attr( $logo ); ?>"/>
                        <a href="#" id="wpcpc_logo_select" class="button"><?php esc_html_e( 'Select image', 'wpc-smart-product-collections' ); ?></a>
                        <div class="wpcpc_selected_image" <?php echo( empty( $logo ) ? 'style="display: none"' : '' ); ?>>
                            <span class="wpcpc_selected_image_img"><?php echo wp_get_attachment_image( $logo ); ?></span>
                            <span class="wpcpc_remove_image"><?php esc_html_e( '× remove', 'wpc-smart-product-collections' ); ?></span>
                        </div>
                    </div>
					<?php
					echo $td_end . $tr_end;
					// new row
					echo $tr_start . $th_start;
					?>
                    <label for="wpcpc_banner"><?php esc_html_e( 'Banner', 'wpc-smart-product-collections' ); ?></label>
					<?php
					echo $th_end . $td_start;
					?>
                    <div class="wpcpc_image_uploader">
                        <input type="hidden" name="wpcpc_banner" id="wpcpc_banner" class="wpcpc_image_val" value="<?php echo esc_html( $banner ); ?>"/>
                        <a href="#" id="wpcpc_banner_select" class="button"><?php esc_html_e( 'Select image', 'wpc-smart-product-collections' ); ?></a>
                        <div class="wpcpc_selected_image" <?php echo( empty( $banner ) ? 'style="display: none"' : '' ); ?>>
                            <span class="wpcpc_selected_image_img"><?php echo wp_get_attachment_image( $banner, 'full' ); ?></span>
                            <span class="wpcpc_remove_image"><?php esc_html_e( '× remove', 'wpc-smart-product-collections' ); ?></span>
                        </div>
                    </div>
					<?php
					echo $td_end . $tr_end;
					// new row
					echo $tr_start . $th_start;
					?>
                    <label for="wpcpc_banner_link"><?php esc_html_e( 'Banner link', 'wpc-smart-product-collections' ); ?></label>
					<?php
					echo $th_end . $td_start;
					?>
                    <input type="url" name="wpcpc_banner_link" id="wpcpc_banner_link" value="<?php echo esc_url( $banner_link ); ?>"/>
					<?php
					echo $td_end . $tr_end;
					// new row
					echo $tr_start . $th_start;
					?>
                    <label for="wpcpc_conditions"><?php esc_html_e( 'Conditions', 'wpc-smart-product-collections' ); ?></label>
					<?php
					echo $th_end . $td_start;
					?>
                    <div class="wpcpc_conditions_wrap">
                        <div class="wpcpc_conditions">
							<?php if ( ! empty( $conditions ) ) {
								foreach ( $conditions as $key => $condition ) {
									self::condition( $key, $condition );
								}
							} else {
								self::condition();
							} ?>
                        </div>
                        <div class="wpcpc_add_condition">
                            <input type="button" class="wpcpc_add_condition_btn button button-large" value="<?php esc_attr_e( '+ Add Condition', 'wpc-smart-product-collections' ); ?>"/>
                        </div>
                    </div>
					<?php
					echo $td_end . $tr_end;
					// new row
					echo $tr_start . $th_start;
					?>
                    <label for="wpcpc_include"><?php esc_html_e( 'Include products', 'wpc-smart-product-collections' ); ?></label>
					<?php
					echo $th_end . $td_start;
					?>
                    <div class="wpcpc-product-search">
                        <select class="wc-product-search" multiple="multiple" name="wpcpc_include[]" id="wpcpc_include" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'wpc-smart-product-collections' ); ?>" data-action="woocommerce_json_search_products">
							<?php
							if ( [ $include ] && count( $include ) ) {
								foreach ( $include as $_product_id ) {
									$_product = wc_get_product( $_product_id );

									if ( $_product ) {
										echo '<option value="' . esc_attr( $_product_id ) . '" selected>' . wp_kses_post( $_product->get_formatted_name() ) . '</option>';
									}
								}
							}
							?>
                        </select>
                    </div>
					<?php
					echo $td_end . $tr_end;
					// new row
					echo $tr_start . $th_start;
					?>
                    <label for="wpcpc_exclude"><?php esc_html_e( 'Exclude products', 'wpc-smart-product-collections' ); ?></label>
					<?php
					echo $th_end . $td_start;
					?>
                    <div class="wpcpc-product-search">
                        <select class="wc-product-search wpcpc-product-search" multiple="multiple" name="wpcpc_exclude[]" id="wpcpc_exclude" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'wpc-smart-product-collections' ); ?>" data-action="woocommerce_json_search_products">
							<?php
							if ( [ $exclude ] && count( $exclude ) ) {
								foreach ( $exclude as $_product_id ) {
									$_product = wc_get_product( $_product_id );

									if ( $_product ) {
										echo '<option value="' . esc_attr( $_product_id ) . '" selected>' . wp_kses_post( $_product->get_formatted_name() ) . '</option>';
									}
								}
							}
							?>
                        </select>
                    </div>
					<?php
					echo $td_end . $tr_end;
					// new row
					echo $tr_start . $th_start;
					?>
                    <label for="wpcpc_note"><?php esc_html_e( 'Note', 'wpc-smart-product-collections' ); ?></label>
					<?php
					echo $th_end . $td_start;
					?><?php esc_html_e( 'After creating/editing a collection, wait for a while when our plugin works on searching for products. You can check back later to see all changes saved to your site.', 'wpc-smart-product-collections' ); ?><?php
					echo $td_end . $tr_end . $table_end;
				}

				function save_form_fields( $term_id ) {
					if ( isset( $_POST['wpcpc_logo'] ) ) {
						update_term_meta( $term_id, 'wpcpc_logo', sanitize_text_field( $_POST['wpcpc_logo'] ) );
					}

					if ( isset( $_POST['wpcpc_banner'] ) ) {
						update_term_meta( $term_id, 'wpcpc_banner', sanitize_text_field( $_POST['wpcpc_banner'] ) );
					}

					if ( isset( $_POST['wpcpc_banner_link'] ) ) {
						update_term_meta( $term_id, 'wpcpc_banner_link', sanitize_url( $_POST['wpcpc_banner_link'] ) );
					}

					if ( isset( $_POST['wpcpc_conditions'] ) ) {
						update_term_meta( $term_id, 'wpcpc_conditions', self::sanitize_array( $_POST['wpcpc_conditions'] ) );
					}

					if ( isset( $_POST['wpcpc_include'] ) ) {
						$include = self::sanitize_array( $_POST['wpcpc_include'] );
						update_term_meta( $term_id, 'wpcpc_include', $include );

						// update products
						if ( is_array( $include ) && count( $include ) ) {
							foreach ( $include as $product_id ) {
								$terms   = wp_get_post_terms( $product_id, 'wpc-collection', [ 'fields' => 'ids' ] );
								$terms[] = (int) $term_id;

								wp_set_post_terms( $product_id, $terms, 'wpc-collection' );
							}
						}
					}

					if ( isset( $_POST['wpcpc_exclude'] ) ) {
						$exclude = self::sanitize_array( $_POST['wpcpc_exclude'] );
						update_term_meta( $term_id, 'wpcpc_exclude', $exclude );

						// update products
						if ( is_array( $exclude ) && count( $exclude ) ) {
							foreach ( $exclude as $product_id ) {
								wp_remove_object_terms( $product_id, [ (int) $term_id ], 'wpc-collection' );
							}
						}
					}
				}

				function sanitize_array( $arr ) {
					foreach ( (array) $arr as $k => $v ) {
						if ( is_array( $v ) ) {
							$arr[ $k ] = self::sanitize_array( $v );
						} else {
							$arr[ $k ] = sanitize_text_field( $v );
						}
					}

					return $arr;
				}

				function collection_columns( $columns ) {
					return [
						'cb'          => $columns['cb'] ?? 'cb',
						'logo'        => esc_html__( 'Logo', 'wpc-smart-product-collections' ),
						'name'        => esc_html__( 'Name', 'wpc-smart-product-collections' ),
						'description' => esc_html__( 'Description', 'wpc-smart-product-collections' ),
						'slug'        => esc_html__( 'Slug', 'wpc-smart-product-collections' ),
						'posts'       => esc_html__( 'Count', 'wpc-smart-product-collections' ),
					];
				}

				function collection_columns_content( $column, $column_name, $term_id ) {
					if ( $column_name === 'logo' ) {
						$image = wp_get_attachment_image( get_term_meta( $term_id, 'wpcpc_logo', true ), [
							'40',
							'40'
						] );

						return $image ?: wc_placeholder_img( [ '40', '40' ] );
					}

					return $column;
				}

				function product_filter( $filters ) {
					global $wp_query;

					$current_collection = ( ! empty( $wp_query->query['wpc-collection'] ) ? $wp_query->query['wpc-collection'] : '' );
					$terms              = get_terms( 'wpc-collection' );

					if ( empty( $terms ) ) {
						return $filters;
					}

					$args = [
						'pad_counts'         => 1,
						'count'              => 1,
						'hierarchical'       => 1,
						'hide_empty'         => 1,
						'show_uncategorized' => 1,
						'orderby'            => 'name',
						'selected'           => $current_collection,
						'menu_order'         => false
					];

					$filters = $filters . PHP_EOL;
					$filters .= '<select name="wpc-collection">';
					$filters .= '<option value="" ' . selected( $current_collection, '', false ) . '>' . esc_html__( 'Filter by collection', 'wpc-smart-product-collections' ) . '</option>';
					$filters .= wc_walk_category_dropdown_tree( $terms, 0, $args );
					$filters .= "</select>";

					return $filters;
				}

				function collection_shortcode( $attrs ) {
					ob_start();

					$attrs = shortcode_atts( [
						'product_id' => null,
						'context'    => null,
						'type'       => null
					], $attrs, 'wpcpc' );

					if ( ! $attrs['product_id'] ) {
						global $product;

						if ( $product ) {
							$attrs['product_id'] = $product->get_id();
						}
					}

					$collections = wc_get_product_terms( $attrs['product_id'], 'wpc-collection' );

					if ( is_array( $collections ) && ! empty( $collections ) ) {
						if ( 'single' === $attrs['context'] ) {
							$type = self::get_setting( 'single_type', 'text' );
						} elseif ( 'archive' === $attrs['context'] ) {
							$type = self::get_setting( 'archive_type', 'text' );
						} else {
							$type = 'full';
						}

						if ( $attrs['type'] ) {
							$type = $attrs['type'];
						}

						$wrap_class = 'wpcpc-wrap wpcpc-wrap-' . $type;

						echo '<div class="' . esc_attr( $wrap_class ) . '">';

						do_action( 'wpcpc_before_wrap', $attrs );

						if ( 'text' === $type ) {
							echo get_the_term_list( $attrs['product_id'], 'wpc-collection', esc_html__( 'Collection: ', 'wpc-smart-product-collections' ), ', ' );
						} else {
							echo '<div class="wpcpc-collections">';

							do_action( 'wpcpc_before_collections', $attrs );

							foreach ( $collections as $collection ) {
								$collection_class = 'wpcpc-collection wpcpc-collection-' . $collection->term_id;

								echo '<div class="' . esc_attr( $collection_class ) . '">';
								do_action( 'wpcpc_before_collection', $collection );

								$logo_id = get_term_meta( $collection->term_id, 'wpcpc_logo', true );

								if ( $logo_id && ( $type === 'image' || $type === 'both' || $type === 'full' ) ) {
									$logo_size = self::get_setting( 'logo_size', 'wpcpc-logo' );
									echo '<span class="wpcpc-collection-image">';
									do_action( 'wpcpc_before_collection_image', $collection );
									echo '<a href="' . esc_url( get_term_link( $collection->term_id ) ) . '" rel="collection"><img src="' . esc_url( wp_get_attachment_image_url( $logo_id, $logo_size ) ) . '" alt="' . esc_attr( $collection->name ) . '"/></a>';
									do_action( 'wpcpc_after_collection_image', $collection );
									echo '</span>';
								}

								if ( $type === 'both' || $type === 'full' ) {
									echo '<span class="wpcpc-collection-info">';
									do_action( 'wpcpc_before_collection_info', $collection );

									echo '<span class="wpcpc-collection-name">';
									do_action( 'wpcpc_before_collection_name', $collection );
									echo '<a href="' . esc_url( get_term_link( $collection->term_id ) ) . '" rel="collection">' . apply_filters( 'wpcpc_collection_name', $collection->name, $collection ) . '</a>';
									do_action( 'wpcpc_after_collection_name', $collection );
									echo '</span>';

									if ( ! empty( $collection->description ) && $type === 'full' ) {
										echo '<span class="wpcpc-collection-description">';
										do_action( 'wpcpc_before_collection_description', $collection );
										echo apply_filters( 'wpcpc_collection_description', $collection->description, $collection );
										do_action( 'wpcpc_after_collection_description', $collection );
										echo '</span>';
									}

									do_action( 'wpcpc_after_collection_info', $collection );
									echo '</span>';
								}

								do_action( 'wpcpc_after_collection', $collection );
								echo '</div>';
							}

							do_action( 'wpcpc_after_collections', $attrs );

							echo '</div>';
						}

						do_action( 'wpcpc_after_wrap', $attrs );

						echo '</div>';
					}

					return apply_filters( 'wpcpc_shortcode', ob_get_clean(), $attrs );
				}

				function collection_archive() {
					echo do_shortcode( '[wpcpc context="archive"]' );
				}

				function collection_single() {
					echo do_shortcode( '[wpcpc context="single"]' );
				}

				function collection_banner_shortcode( $attrs ) {
					ob_start();

					$attrs = shortcode_atts( [
						'id' => null,
					], $attrs, 'wpcpc_banner' );

					if ( ! $attrs['id'] ) {
						$collection = get_queried_object();

						if ( $collection && $collection->term_id ) {
							$attrs['id'] = $collection->term_id;
						}
					}

					if ( $attrs['id'] ) {
						$banner = get_term_meta( $attrs['id'], 'wpcpc_banner', true );
						$link   = get_term_meta( $attrs['id'], 'wpcpc_banner_link', true );

						if ( $banner ) {
							echo '<div class="wpcpc-banner">';

							if ( ! empty( $link ) ) {
								echo '<a href="' . esc_url( site_url( $link ) ) . '">' . wp_get_attachment_image( $banner, 'full' ) . '</a>';
							} else {
								echo wp_get_attachment_image( $banner, 'full' );
							}

							echo '</div>';
						}
					}

					return apply_filters( 'wpcpc_banner_shortcode', ob_get_clean(), $attrs );
				}

				function collection_banner() {
					if ( ! is_tax( 'wpc-collection' ) || is_paged() ) {
						return;
					}

					echo do_shortcode( '[wpcpc_banner]' );
				}

				// extra
				function image_sizes() {
					global $_wp_additional_image_sizes;
					$sizes = [];

					foreach ( get_intermediate_image_sizes() as $_size ) {
						if ( in_array( $_size, [ 'thumbnail', 'medium', 'medium_large', 'large' ] ) ) {
							$sizes[ $_size ]['width']  = get_option( "{$_size}_size_w" );
							$sizes[ $_size ]['height'] = get_option( "{$_size}_size_h" );
							$sizes[ $_size ]['crop']   = (bool) get_option( "{$_size}_crop" );
						} elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {
							$sizes[ $_size ] = [
								'width'  => $_wp_additional_image_sizes[ $_size ]['width'],
								'height' => $_wp_additional_image_sizes[ $_size ]['height'],
								'crop'   => $_wp_additional_image_sizes[ $_size ]['crop'],
							];
						}
					}

					return $sizes;
				}
			}

			return WPCleverWpcpc::instance();
		}

		return null;
	}
}

if ( ! function_exists( 'wpcpc_notice_wc' ) ) {
	function wpcpc_notice_wc() {
		?>
        <div class="error">
            <p><strong>WPC Smart Product Collections</strong> requires WooCommerce version 3.0 or greater.</p>
        </div>
		<?php
	}
}
