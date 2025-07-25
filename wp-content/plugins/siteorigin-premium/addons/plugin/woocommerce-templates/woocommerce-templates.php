<?php
/*
Plugin Name: SiteOrigin WooCommerce Templates
Description: Create tailored templates for WooCommerce, customizing Product, Archives, and Checkout pages to boost sales and engagement.
Version: 1.0.0
Author: SiteOrigin
Author URI: https://siteorigin.com
License: GPL3
License URI: https://www.gnu.org/licenses/gpl-3.0.txt
Documentation: https://siteorigin.com/premium-documentation/plugin-addons/woocommerce-templates/
Tags: Page Builder, Widgets Bundle, WooCommerce
Requires: siteorigin-panels, so-widgets-bundle, woocommerce
*/

class SiteOrigin_Premium_Plugin_WooCommerce_Templates {
	const POST_TYPE = 'so_wc_template';
	private $so_wc_templates;
	private $template_widget_groups;
	private $has_shortcode = false;
	private $preview_rendered = false;

	public static function single() {
		static $single;

		return empty( $single ) ? $single = new self() : $single;
	}

	public function __construct() {
		add_action( 'widgets_init', array( $this, 'init_addon' ), 9 );

		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_filter( 'siteorigin_premium_addon_submenu_links-plugin/woocommerce-templates', array( $this, 'submenu_links' ) );
		add_filter( 'siteorigin_premium_addon_section_link-plugin/woocommerce-templates', array( $this, 'section_link' ) );

		add_filter( 'siteorigin_installer_products', array( $this, 'add_wc_to_installer' ) );

		add_action( 'siteorigin_premium_version_update', array( $this, 'settings_migration' ), 20, 3 );

		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		add_action( 'admin_init', array( $this, 'update_template' ) );

		// Ensure all WCTB requirements are met before allowing overrides.
		if (
			class_exists( 'WooCommerce' ) &&
			version_compare( wc()->version, '3.4.0', '>=' ) &&
			class_exists( 'SiteOrigin_Widgets_Bundle' ) &&
			class_exists( 'SiteOrigin_Panels' )
		) {
			add_filter( 'template_include', array( $this, 'get_template' ), 60 );
			add_filter( 'wc_get_template', array( $this, 'get_woocommerce_template' ), 10, 5 );
			add_filter( 'wc_get_template_part', array( $this, 'get_woocommerce_template_part' ), 10, 3 );
			add_filter( 'the_content', array( $this, 'block_editor_content' ), 11 );
		}

		add_filter( 'siteorigin_panels_widget_dialog_tabs', array( $this, 'add_widgets_dialog_tabs' ), 20 );
		add_filter( 'siteorigin_panels_widgets', array( $this, 'wc_template_widgets' ) );
		add_filter( 'siteorigin_panels_local_layouts_directories', array( $this, 'add_template_layouts' ), 11 );
		add_filter( 'siteorigin_panels_prebuilt_layouts', array( $this, 'remove_template_layouts' ), 11 );
		add_filter( 'siteorigin_panels_layout_tabs', array( $this, 'add_wctb_items' ) );

		add_action( 'add_meta_boxes_product', array( $this, 'add_product_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );

		add_action( 'add_inline_data', array( $this, 'add_template_inline_data' ), 10, 2 );
		add_action( 'woocommerce_product_quick_edit_end', array( $this, 'quick_bulk_edit' ) );
		add_action( 'woocommerce_product_bulk_edit_end', array( $this, 'quick_bulk_edit' ) );
		add_action( 'woocommerce_product_quick_edit_save', array( $this, 'quick_bulk_edit_save' ) );
		add_action( 'woocommerce_product_bulk_edit_save', array( $this, 'quick_bulk_edit_save' ) );

		add_action( 'product_cat_add_form_fields', array( $this, 'add_product_archive_template_field' ) );
		add_action( 'product_cat_edit_form_fields', array( $this, 'edit_product_archive_template_field' ), 10, 2 );

		add_action( 'product_tag_add_form_fields', array( $this, 'add_product_archive_template_field' ) );
		add_action( 'product_tag_edit_form_fields', array( $this, 'edit_product_archive_template_field' ), 10, 2 );

		add_action( 'created_term', array( $this, 'save_product_cat_template_field' ), 10, 3 );
		add_action( 'edit_term', array( $this, 'save_product_cat_template_field' ), 10, 3 );

		if ( ! empty( $_GET['siteorigin_premium_template_preview'] ) ) {
			add_filter( 'siteorigin_panels_data', array( $this, 'preview_template' ), 10, 2 );
			add_filter( 'show_admin_bar', '__return_false' );
			add_filter( 'the_content', array( $this, 'create_preview_content' ) );
			add_filter( 'the_content', array( $this, 'remove_preview_content' ), 12 );
		}

		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );

		// WCTB Product Shortcode.
		add_shortcode( 'sowctb', array( $this, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'shortcode_enqueue_frontend_scripts' ) );
		add_filter( 'body_class', array( $this, 'shortcode_add_body_class' ) );

		// Add .woocommerce class wrapper.
		add_action( 'siteorigin_premium_wctb_template_before', array( $this, 'open_wc_wrapper' ), 10, 1 );
		add_action( 'siteorigin_premium_wctb_template_after', array( $this, 'close_wc_wrapper' ), 10, 1 );

		// Add compatibility for the Black Studio TinyMCE plugin.
		if ( class_exists( 'Black_Studio_TinyMCE_Admin' ) ) {
			include __DIR__ . '/compat/black-studio.php';
		}

		// Add compatibility for the WooCommerce PayPal Payments plugin.
		if ( is_plugin_active( 'woocommerce-paypal-payments/woocommerce-paypal-payments.php' ) ) {
			include __DIR__ . '/compat/woocommerce-paypal-payments.php';
		}

		// Add compatibility for the NM Gift Registry and Wishlist plugin.
		if ( function_exists( 'nm_gift_registry' ) ) {
			include __DIR__ . '/compat/nm-gift-registry.php';
		}

		// Add compatibility for the WPC Smart Wishlist for WooCommerce plugin.
		if ( function_exists( 'woosw_init' ) || function_exists( 'woosc_init' ) ) {
			include __DIR__ . '/compat/wpc-smart-wishlist.php';
		}

		if ( wp_get_theme()->get( 'TextDomain' ) == 'virtue' ) {
			include __DIR__ . '/compat/virtue.php';
		}

		// Add compatibility for the TP Product Image Flipper for Woocommerce plugin.
		if ( function_exists( 'tp_create_flipper_images' ) ) {
			include __DIR__ . '/compat/tp-plugins.php';
		}
	}

	public function init_addon() {
		$this->create_templates_option();

		$this->register_templates_type();

		$this->register_template_widgets();
	}

	public static function is_siteorigin_premium_wc_template_builder() {
		return ! ( empty( $_GET['page'] ) || $_GET['page'] != 'so-wc-templates' ) ||
			( wp_doing_ajax() && ! empty( $_GET['builderType'] ) && $_GET['builderType'] == 'so_premium_wc_template' );
	}

	private function register_template_widgets() {
		if ( ! class_exists( 'SiteOrigin_Widgets_Bundle' ) ) {
			return;
		}
		$doing_widget_form_ajax = wp_doing_ajax() &&
		! empty( $_REQUEST['action'] ) &&
		$_REQUEST['action'] == 'so_panels_widget_form';

		if ( is_admin() && ! self::is_siteorigin_premium_wc_template_builder() && ! $doing_widget_form_ajax ) {
			return;
		}

		if ( ! function_exists( 'list_files' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Clear PB widgets cache.
		delete_transient( 'siteorigin_panels_widget_dialog_tabs' );
		delete_transient( 'siteorigin_panels_widgets' );

		// If this is the Product archive tab, we need to remove the Shop Product Loop.
		if (
			self::is_siteorigin_premium_wc_template_builder() &&
			isset( $_GET['tab'] ) &&
			$_GET['tab'] === 'content-product'
		) {
			$remove_shop = true;
		}

		if ( WP_Filesystem() ) {
			// Load widgets for template parts.
			$templates = array(
				'content-single-product',
				'content-product',
				'shop',
				'cart',
				'cart-empty',
				'checkout',
				'thankyou',
				'myaccount',
			);

			$this->template_widget_groups = array();

			foreach ( $templates as $template ) {
				$template_files = list_files( __DIR__ . '/templates/' . $template );

				foreach ( $template_files as $template_file ) {
					$filename_parts = pathinfo( $template_file );

					if (
						isset( $remove_shop ) &&
						$filename_parts['filename'] === 'wc-shop-product-loop'
					) {
						continue;
					}

					if ( $filename_parts['extension'] == 'php' ) {
						require_once $template_file;
						$classname = str_replace(
							'Wc_',
							'SiteOrigin_Premium_WooCommerce_',
							implode( '_', array_map( 'ucfirst', explode( '-', $filename_parts['filename'] ) ) )
						);

						if ( ! isset( $this->template_widget_groups[$template] ) ) {
							$this->template_widget_groups[$template] = array();
						}
						$this->template_widget_groups[$template][] = $classname;
					}
				}
			}
		}
	}

	private function create_templates_option() {
		$this->so_wc_templates = get_option( 'so-wc-templates' );

		if ( empty( $this->so_wc_templates ) ) {
			// Add templates if they don't exist.
			$this->so_wc_templates = array(
				// The product, product archive, and shop templates might not always work as they depend on the current
				// theme implementing archive pages and loading templates using the `wc_get_template_part` function.
				// There might be some need to require a specific theme for this.
				'content-single-product' => array(
					'label' => __( 'Product', 'siteorigin-premium' ),
					'type' => 'product',
				),
				'content-product' => array(
					'label' => __( 'Product Archive', 'siteorigin-premium' ),
					'type' => 'product-archive',
				),
				'shop' => array(
					'label' => __( 'Shop', 'siteorigin-premium' ),
					'type' => 'page',
				),
				'cart' => array(
					'label' => __( 'Cart', 'siteorigin-premium' ),
					'type' => 'page',
				),
				'cart-empty' => array(
					'label' => __( 'Empty Cart', 'siteorigin-premium' ),
					'type' => 'page',
				),
				'checkout' => array(
					'label' => __( 'Checkout', 'siteorigin-premium' ),
					'type' => 'page',
				),
				'thankyou' => array(
					'label' => __( 'Thank You', 'siteorigin-premium' ),
					'type' => 'page',
				),
				'myaccount' => array(
					'label' => __( 'My Account', 'siteorigin-premium' ),
					'type' => 'page',
				),
			);
			add_option( 'so-wc-templates', $this->so_wc_templates );
			add_option( 'so-wctb-templates-migrated', true ); // Skip the template migration.
		} else {
			if ( ! get_option( 'so-wctb-templates-migrated' ) ) {
				$this->update_template_data();
			}

			// Add Thank you template if user updated from an older version of Premium.
			if ( empty( $this->so_wc_templates['thankyou'] ) ) {
				$this->so_wc_templates['thankyou'] = array(
					'label' => __( 'Thank You', 'siteorigin-premium' ),
					'type' => 'page',
				);

				$myaccount = $this->so_wc_templates['myaccount'];
				unset( $this->so_wc_templates['myaccount'] );
				$this->so_wc_templates['myaccount'] = $myaccount;

				update_option( 'so-wc-templates', $this->so_wc_templates );
			}
		}
	}

	/**
	 * Migrate legacy format templates to new format, and fix any disassociation issues.
	 */
	private function update_template_data() {
		// Migrate any product templates using the old format.
		if ( isset( $this->so_wc_templates['content-single-product']['post_ids'] ) ) {
			$this->legacy_template_migrate_archive( 'content-single-product' );
		}

		if ( isset( $this->so_wc_templates['content-product']['post_ids'] ) ) {
			$this->legacy_template_migrate_archive( 'content-product' );
		}

		// Before updating, was the site using version 1.21.0? If so, we need to find all disassociated templates.
		if ( isset( $this->so_wc_templates['thankyou'] ) ) {
			global $wpdb;
			$template_posts = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT ID FROM ' . $wpdb->prefix . 'posts WHERE `post_type` = "so_wc_template" AND `post_title` NOT LIKE %s',
					'%' . esc_sql( 'SiteOrigin WooCommerce Layout', 'siteorigin-premium' )
				)
			);

			if ( ! empty( $template_posts ) ) {
				foreach ( $template_posts as $template ) {
					add_post_meta( $template->ID, 'wctb_template', 'content-single-product' );
					add_post_meta( $template->ID, 'wctb_template', 'content-product' );
				}
			}

			// Restore standard templates.
			$template_posts = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT post_title, ID FROM ' . $wpdb->prefix . 'posts WHERE `post_type` = "so_wc_template" AND `post_title` LIKE %s',
					'%' . esc_sql( 'SiteOrigin WooCommerce Layout', 'siteorigin-premium' )
				),
				OBJECT_K
			);

			foreach ( $this->so_wc_templates as $template_tag => $template ) {
				$item = $template['label'] . ' - ' . __( 'SiteOrigin WooCommerce Layout', 'siteorigin-premium' );

				if ( isset( $template_posts[ $item ] ) ) {
					$this->so_wc_templates[ $template_tag ]['post_id'] = $template_posts[ $item ]->ID;
				}
			}

			// Inform the user about this issue.
			$notices = SiteOrigin_Premium_Admin_Notices::single();
			$notices->activate_notice(
				'wctb_1210_upgrade_notice',
				sprintf(
					__(
						'<strong>SiteOrigin Premium User Action Required:</strong> Please, go to the %sWooCommerce Template Builder%s. Check each template tab and ensure that the "Set as default" and "Enable template" checkboxes are enabled or disabled as required. This is a one-time task, thanks for your support. For assistance, please, email %ssupport@siteorigin.com%s.',
						'siteorigin-premium'
					),
					'<a href="' . esc_url( admin_url( 'admin.php?page=so-wc-templates' ) ) . '" target="_blank" rel="noopener noreferrer">',
					'</a>',
					'<a href="mailto:support@siteorigin.com">',
					'</a>'
				)
			);
		}
		add_option( 'so-wctb-templates-migrated', true );
		update_option( 'so-wc-templates', $this->so_wc_templates );
	}

	private function legacy_template_migrate_archive( $type ) {
		$template_posts = get_posts(
			array(
				'post_type' => self::POST_TYPE,
				'post_status' => 'draft',
				'numberposts' => -1,
				'include'   => implode( ',', $this->so_wc_templates[ $type ]['post_ids'] ),
			)
		);

		foreach ( $template_posts as $post ) {
			update_post_meta( $post->ID, 'wctb_template', $type );
		}

		unset( $this->so_wc_templates[ $type ]['post_ids'] );
	}

	private function register_templates_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name' => __( 'WooCommerce Template', 'siteorigin-premium' ),
				),
				'public' => false,
				'publicly_queryable' => true,
			)
		);
	}

	/**
	 * Add the options page.
	 */
	public function add_admin_page() {
		add_submenu_page(
			'siteorigin',
			__( 'WooCommerce Template Builder', 'siteorigin-premium' ),
			__( 'WooCommerce Template Builder', 'siteorigin-premium' ),
			'manage_options',
			'so-wc-templates',
			array( $this, 'render_template_builder' )
		);
	}

	public function submenu_links( $links ) {
		$links[] = array(
			'section' => 'siteorigin',
			'label'   => esc_attr( 'WooCommerce Template Builder', 'siteorigin-premium' ),
			'link'    => admin_url( 'admin.php?page=so-wc-templates' ),
		);

		return $links;
	}

	public function section_link( $section ) {
		return array(
			'label' => esc_attr( 'Manage Templates', 'siteorigin-premium' ),
			'url'   => admin_url( 'admin.php?page=so-wc-templates' ),
		);
	}

	public function settings_migration( $new_version, $old_version ) {
		// If upgrading from a version of SO Premium prior to 1.31.0,
		// check if before/after archive data was set and migrate it.
		if ( version_compare( $old_version, '1.31.0', '<=' ) ) {
			$so_wc_templates = get_option( 'so-wc-templates' );

			if ( ! empty( $so_wc_templates ) ) {
				$templates = self::get_product_template_posts( 'content-product' );
				$before = get_option( 'so-wc-templates-before' );

				if ( $before ) {
					foreach ( $templates as $template ) {
						update_option( 'so-wc-templates-before-' . $template->ID, $before, false );
					}
					delete_option( 'so-wc-templates-before' );
				}

				$after = get_option( 'so-wc-templates-after' );

				if ( $after ) {
					foreach ( $templates as $template ) {
						update_option( 'so-wc-templates-after-' . $template->ID, $after, false );
					}
					delete_option( 'so-wc-templates-after' );
				}
			}
		}
	}

	public function add_wc_to_installer( $products ) {
		$products['woocommerce'] = array(
			'name' => 'Woocommerce',
			'weight' => 81,
			'screenshot' => 'https://ps.w.org/woocommerce/assets/icon-256x256.png',
			'type' => 'plugins',
		);

		return $products;
	}

	public function render_template_builder() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			?>
			<div class="so-wc-templates-missing-plugin">
				<p>
					<strong>
					<?php
					printf(
						__( 'Please install and activate %sWooCommerce%s before using this addon.', 'siteorigin-premium' ),
						sprintf(
							'<a href="%s" target="_blank" rel="noopener noreferrer">',
							apply_filters( 'siteorigin_add_installer', true ) ? admin_url( 'admin.php?page=siteorigin-installer&highlight=woocommerce' ) : 'https://wordpress.org/plugins/woocommerce/'
						),
						'</a>'
					);
					?>
					</strong>
				</p>
			</div>
			<?php
			return;
		}

		if ( version_compare( wc()->version, '3.4.0', '<' ) ) {
			?>
			<div class="so-wc-templates-missing-plugin"><p><strong><?php esc_html_e( "The SiteOrigin WooCommerce Template Builder addon isn't compatible with this version of WooCommerce. Please update to WooCommerce 3.4.0, or later, before using this addon.", 'siteorigin-premium' ); ?></strong></p></div>
			<?php
			return;
		}

		if ( ! class_exists( 'SiteOrigin_Panels' ) ) {
			?>
			<div class="so-wc-templates-missing-plugin">
				<p>
					<strong>
					<?php
					printf(
						__( 'Please install and activate %sSiteOrigin Page Builder%s before using this addon.', 'siteorigin-premium' ),
						sprintf(
							'<a href="%s" target="_blank" rel="noopener noreferrer">',
							apply_filters( 'siteorigin_add_installer', true ) ? admin_url( 'admin.php?page=siteorigin-installer&highlight=siteorigin-panels' ) : 'https://wordpress.org/plugins/siteorigin-panels/'
						),
						'</a>'
					);
					?>
					</strong>
				</p>
			</div>
			<?php
			return;
		}

		if ( ! class_exists( 'SiteOrigin_Widgets_Bundle' ) ) {
			?>
			<div class="so-wc-templates-missing-plugin">
				<p>
					<strong>
					<?php
					printf(
						__( 'Please install and activate the %sSiteOrigin Widgets Bundle%s plugin before using this addon.', 'siteorigin-premium' ),
						sprintf(
							'<a href="%s" target="_blank" rel="noopener noreferrer">',
							apply_filters( 'siteorigin_add_installer', true ) ? admin_url( 'admin.php?page=siteorigin-installer&highlight=so-widgets-bundle' ) : 'https://wordpress.org/plugins/so-widgets-bundle/'
						),
						'</a>'
					);
					?>
					</strong>
				</p>
			</div>
			<?php
			return;
		}

		$so_wc_templates = get_option( 'so-wc-templates' );

		$current_tab = array_keys( $so_wc_templates )[0];

		if ( ! empty( $_GET['tab'] ) ) {
			$current_tab = $_GET['tab'];
		}
		$current_template = $so_wc_templates[ $current_tab ];
		$multi_template_tabs = array( 'content-single-product', 'content-product' );
		$allow_multiple_templates = in_array( $current_tab, $multi_template_tabs );

		if ( $allow_multiple_templates ) {
			$default_template_post_id = ! empty( $current_template['post_id'] ) ? $current_template['post_id'] : '';

			if ( isset( $_GET['template_post_id'] ) ) {
				$template_post_id = $_GET['template_post_id'];
			} else {
				$template_post_id = $default_template_post_id;
			}

			$template_posts = self::get_product_template_posts( $current_tab );
		} else {
			$template_post_id = ! empty( $current_template['post_id'] ) ? $current_template['post_id'] : '';
		}

		if ( ! empty( $template_post_id ) ) {
			/* @var WP_Post $template_post */
			$template_post = get_post( $template_post_id );
			$panels_data = get_post_meta( $template_post_id, 'panels_data', true );
		} else {
			$template_post_id = '';
			$template_post = array();
			$panels_data = array();
		}

		$builder_supports = array();
		$preview_url = '';

		if ( ! empty( $template_post ) ) {
			if ( $current_template['type'] == 'page' ) {
				$wc_page = $current_tab;

				if ( $current_tab == 'thankyou' ) {
					$preview_url = false;
				} else {
					if ( strpos( $current_tab, 'cart' ) !== false ) {
						$wc_page = 'cart';
					}
					$preview_url = wc_get_page_permalink( $wc_page );
				}
			} elseif ( $current_template['type'] == 'product-archive' ) {
				$preview_url = wc_get_page_permalink( 'shop' );
			} else {
				$products = wc_get_products( array( 'limit' => 1 ) );

				if ( count( $products ) > 0 ) {
					/** @var WC_Product $preview_product */
					$preview_product = $products[0];
					$preview_url = add_query_arg( 'template_post_id', $template_post_id, $preview_product->get_permalink() );
				}
			}

			if ( ! empty( $preview_url ) ) {
				$preview_url = add_query_arg( 'siteorigin_premium_template_preview', 'true', $preview_url );
			}
			$builder_supports = apply_filters( 'siteorigin_panels_builder_supports', $builder_supports, $template_post, $panels_data );
		}
		$delete_url = '';

		if ( ! ( empty( $allow_multiple_templates ) || empty( $template_post_id ) ) ) {
			$delete_url = wp_nonce_url(
				add_query_arg(
					array(
						'delete' => 'true',
						'template_post_id' => $template_post_id,
					)
				),
				'delete',
				'_so_wc_template_nonce'
			);
		}

		if ( $allow_multiple_templates ) {
			$template_enabled = ! empty( $current_template['post_id'] ) && $current_template['post_id'] == $template_post_id;
		} else {
			$template_enabled = ! empty( $template_post_id ) && ! empty( $current_template['active'] );
		}

		$so_wc_templates = array_merge_recursive(
			$so_wc_templates,
			array(
				'content-single-product' => array(
					'info' => __( 'The templates to be used when viewing single product pages. Set one as the default and it will be used for all single product pages. Layouts can also be enabled for individual products by going to Products > Edit product and selecting the template from the Product template meta box dropdown.', 'siteorigin-premium' ),
				),
				'content-product' => array(
					'info' => __( 'The templates to be used for each product when viewing an archive page. Set one as the default and it will be used for the Shop page and any other product archive pages. Layouts can also be enabled for individual product archives by going to Products > Categories > Edit category and selecting the template from the Product archive template dropdown.', 'siteorigin-premium' ),
				),
				'shop' => array(
					'info' => __( 'The template to be used when viewing the Shop page. This layout allows customization of position and size of the product loop, and placing widgets around the product loop.', 'siteorigin-premium' ),
				),
				'cart' => array(
					'info' => __( 'The template to be used when viewing a cart which contains products.', 'siteorigin-premium' ),
				),
				'cart-empty' => array(
					'info' => __( 'The template to be used when viewing an empty cart.', 'siteorigin-premium' ),
				),
				'checkout' => array(
					'info' => __( 'The template to be used for the Checkout page.', 'siteorigin-premium' ),
				),
				'thankyou' => array(
					'info' => __( 'The template to be used for the Thank you page which appears after an order is submitted.', 'siteorigin-premium' ),
				),
				'myaccount' => array(
					'info' => __( 'The template to be used for the My account page.', 'siteorigin-premium' ),
				),
			)
		);

		require_once SiteOrigin_Premium::dir_path( __FILE__ ) . '/inc/admin-wc-template-builder.php';
	}

	/**
	 * Enqueue admin scripts.
	 */
	public function admin_scripts( $prefix ) {
		$current_screen = get_current_screen();

		if ( $current_screen->id == 'edit-product' ) {
			wp_enqueue_script(
				'so-wc-template-builder-quick-edit',
				plugin_dir_url( __FILE__ ) . 'js/so-wc-template-builder-quick-edit.js',
				array(),
				SITEORIGIN_PREMIUM_VERSION
			);
		}

		if ( $prefix != 'siteorigin_page_so-wc-templates' ) {
			return;
		}
		wp_enqueue_style(
			'so-premium-wc-templates',
			plugin_dir_url( __FILE__ ) . 'css/so-premium-wc-templates.css',
			array(),
			SITEORIGIN_PREMIUM_VERSION
		);

		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		wp_enqueue_script(
			'so-wc-template-builder',
			plugin_dir_url( __FILE__ ) . 'js/so-wc-template-builder.js',
			array(),
			SITEORIGIN_PREMIUM_VERSION
		);
		wp_localize_script(
			'so-wc-template-builder',
			'soPremiumWcTemplateBuilder',
			array(
				'confirm_delete_template' => __( 'Permanently delete this template?', 'siteorigin-premium' ),
			)
		);

		if ( ! class_exists( 'SiteOrigin_Panels' ) ) {
			return;
		}

		SiteOrigin_Panels_Admin::single()->enqueue_admin_scripts( '', true );
		SiteOrigin_Panels_Admin::single()->enqueue_admin_styles( '', true );
	}

	public function update_template() {
		// TODO: Refactor the required server calls to use the REST API.
		$update_nonce = isset( $_POST['_so_wc_template_nonce'] ) &&
						wp_verify_nonce( $_POST['_so_wc_template_nonce'], 'update' );
		$delete_nonce = isset( $_GET['_so_wc_template_nonce'] ) &&
						wp_verify_nonce( $_GET['_so_wc_template_nonce'], 'delete' );

		if ( ! ( $update_nonce || $delete_nonce ) ) {
			return;
		}

		if ( ! self::is_siteorigin_premium_wc_template_builder() || empty( $_GET['tab'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$so_wc_templates = get_option( 'so-wc-templates' );

		$tab = $_GET['tab'];
		$current_template = $so_wc_templates[ $tab ];
		$is_new_template = empty( $_REQUEST['template_post_id'] );
		$delete = ! empty( $_GET['delete'] );

		$has_multiple_templates = in_array( $tab, array( 'content-single-product', 'content-product' ) );

		if ( $has_multiple_templates ) {
			if ( ! $is_new_template ) {
				$template_post_id = (int) $_REQUEST['template_post_id'];

				if ( $delete ) {
					wp_delete_post( $template_post_id );

					if ( isset( $current_template['post_id'] ) && $current_template['post_id'] == $template_post_id ) {
						unset( $current_template['post_id'] );
						$so_wc_templates[ $tab ] = $current_template;
					}
					update_option( 'so-wc-templates', $so_wc_templates );

					exit( wp_redirect( add_query_arg( array(
						'page' => 'so-wc-templates',
						'tab' => $tab,
					), admin_url( 'admin.php' ) ) ) );
				}
			}

			if ( ! empty( $_POST['so-wc-template-name'] ) ) {
				$post_title = $_POST['so-wc-template-name'];
			} else {
				$post_title = '';
			}
		} else {
			$template_post_id = empty( $current_template['post_id'] ) ? '' : $current_template['post_id'];
			$post_title = $current_template['label'] . ' - ' . __( 'SiteOrigin WooCommerce Layout', 'siteorigin-premium' );
		}

		$post_content = wp_unslash( $_POST['post_content'] );

		$template_changed = false;

		// Prevent Page Builder from generating fallback content. PB is required so this content will never be used.
		add_filter( 'siteorigin_panels_filter_content_enabled', '__return_false' );

		if ( empty( $template_post_id ) ) {
			$template_post_id = wp_insert_post( array(
				'post_title'   => $post_title,
				'post_type'    => self::POST_TYPE,
				'post_content' => $post_content,
			) );

			if ( ! $has_multiple_templates ) {
				$current_template['post_id'] = $template_post_id;
			}

			$template_changed = true;
		} else {
			// `wp_insert_post` does it's own sanitization, but it seems `wp_update_post` doesn't.
			$post_content = sanitize_post_field( 'post_content', $post_content, $template_post_id, 'db' );

			// Update the post with changed content to save revision if necessary.
			wp_update_post(
				array(
					'ID'           => $template_post_id,
					'post_title'   => $post_title,
					'post_content' => $post_content,
				)
			);
		}

		// Disassociate this template with other tabs.
		if ( $has_multiple_templates ) {
			update_post_meta( $template_post_id, 'wctb_template', $tab );
		}

		if ( isset( $_POST['panels_data'] ) ) {
			$old_panels_data = get_post_meta( $template_post_id, 'panels_data', true );
			$panels_data = json_decode( wp_unslash( $_POST['panels_data'] ), true );
			$panels_data['widgets'] = SiteOrigin_Panels_Admin::single()->process_raw_widgets(
				$panels_data['widgets'],
				! empty( $old_panels_data['widgets'] ) ? $old_panels_data['widgets'] : false,
				false
			);

			if ( siteorigin_panels_setting( 'sidebars-emulator' ) ) {
				$sidebars_emulator = SiteOrigin_Panels_Sidebars_Emulator::single();
				$panels_data['widgets'] = $sidebars_emulator->generate_sidebar_widget_ids( $panels_data['widgets'], $template_post_id );
			}
			$panels_data = SiteOrigin_Panels_Styles_Admin::single()->sanitize_all( $panels_data );
			update_post_meta( $template_post_id, 'panels_data', map_deep( $panels_data, array(
				'SiteOrigin_Panels_Admin',
				'double_slash_string',
			) ) );
		}

		// If the active status of this template has changed, update it.
		if ( $has_multiple_templates ) {
			// This is used to set the default template for Products
			if ( ! empty( $_POST['so-wc-activate'] ) && ( empty( $current_template['post_id'] ) || $current_template['post_id'] != $template_post_id ) ) {
				$current_template['post_id'] = $template_post_id;
				$template_changed = true;
			} elseif ( empty( $_POST['so-wc-activate'] ) && ! empty( $current_template['post_id'] ) && $current_template['post_id'] == $template_post_id ) {
				$current_template['post_id'] = '';
				$template_changed = true;
			}
		} else {
			if ( empty( $current_template['active'] ) != empty( $_POST['so-wc-activate'] ) ) {
				$current_template['active'] = ! empty( $_POST['so-wc-activate'] );
				$template_changed = true;
			}
		}

		if ( $tab == 'content-product' ) {
			$old_panels_data = get_option( "so-wc-templates-before-$template_post_id", true );
			$before_panels_data = json_decode( wp_unslash( $_POST['content-product-before'] ), true );

			if ( ! empty( $before_panels_data ) ) {
				$panels_data['widgets'] = SiteOrigin_Panels_Admin::single()->process_raw_widgets(
					$before_panels_data['widgets'],
					! empty( $old_panels_data['widgets'] ) ? $old_panels_data['widgets'] : false,
					false
				);
				$before_panels_data = SiteOrigin_Panels_Styles_Admin::single()->sanitize_all( $before_panels_data );
				update_option( "so-wc-templates-before-$template_post_id", map_deep( $before_panels_data, array(
					'SiteOrigin_Panels_Admin',
					'double_slash_string',
				) ), false );
				$template_changed = true;
			}

			$old_panels_data = get_option( "so-wc-templates-after-$template_post_id", true );
			$after_panels_data = json_decode( wp_unslash( $_POST['content-product-after'] ), true );

			if ( ! empty( $after_panels_data ) ) {
				$panels_data['widgets'] = SiteOrigin_Panels_Admin::single()->process_raw_widgets(
					$after_panels_data['widgets'],
					! empty( $old_panels_data['widgets'] ) ? $old_panels_data['widgets'] : false,
					false
				);
				$after_panels_data = SiteOrigin_Panels_Styles_Admin::single()->sanitize_all( $after_panels_data );
				update_option( "so-wc-templates-after-$template_post_id", map_deep( $after_panels_data, array(
					'SiteOrigin_Panels_Admin',
					'double_slash_string',
				) ), false );
				$template_changed = true;
			}
		}

		if ( $template_changed ) {
			$so_wc_templates[ $tab ] = $current_template;
			update_option( 'so-wc-templates', $so_wc_templates );
		}

		if ( $has_multiple_templates && $is_new_template ) {
			exit( wp_redirect( add_query_arg( array( 'template_post_id' => $template_post_id ) ) ) );
		}
	}

	public function get_template( $template ) {
		if ( is_product_category() || is_product_tag() ) {
			$override = 'content-product';
		}

		if (
			is_shop() &&
			(
				strpos( $template, 'archive-product.php' ) !== false ||
				(
					apply_filters( 'siteorigin_premium_addon_wc_check_for_woocommerce_file', true ) &&
					strpos( $template, 'woocommerce.php' ) !== false
				)
			)
		) {
			$override = 'shop';
		}

		// Genesis Connect for WooCommerce Compatibility.
		if ( function_exists( 'gencwooc_template_loader' ) && is_product() ) {
			$template = wc_get_template_part( 'single', 'product' );
		}

		if ( ! empty( $override ) ) {
			$so_wc_templates = get_option( 'so-wc-templates' );
			$is_preview = ! empty( $_GET['siteorigin_premium_template_preview'] ) && ! $this->preview_rendered;
			$tab = ! empty( $_POST['tab'] ) ? $_POST['tab'] : '';

			// If this an archive, we need to determine if should be showing the before/after product data loop.
			if ( $override == 'content-product' ) {
				$term = ! empty( get_queried_object() ) ? get_queried_object() : get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );

				$template_active = $this->set_active_template(
					! empty( $term ) && is_object( $term ) ? get_option( "_term_type_{$term->taxonomy}_{$term->term_id}" ) : '',
					$so_wc_templates[ $override ]
				);

				$override = 'archive';
			}

			if (
				! empty( $so_wc_templates[ $override ]['active'] ) ||
				! empty( $template_active ) ||
				( $tab == $override && $is_preview )
			) {
				$overide_template = locate_template( "woocommerce/wctb-$override.php", false, false );
				$template = ! empty( $overide_template ) ? $overide_template : SiteOrigin_Premium::dir_path( __FILE__ ) . 'templates/' . $override . '.php';
			}
		}

		return $template;
	}

	public function get_woocommerce_template( $template, $template_name, $args, $template_path, $default_path ) {
		$so_wc_templates = get_option( 'so-wc-templates' );
		$is_preview = ! empty( $_GET['siteorigin_premium_template_preview'] ) && ! $this->preview_rendered;

		if ( is_cart() ) {
			if ( preg_match( '/cart\/cart\.php/', $template ) ) {
				if ( ! empty( $so_wc_templates['cart']['active'] ) || $is_preview ) {
					$template = SiteOrigin_Premium::dir_path( __FILE__ ) . '/templates/cart.php';
				}
			} elseif ( preg_match( '/cart\/cart\-empty\.php/', $template ) ) {
				if ( ! empty( $so_wc_templates['cart-empty']['active'] ) || $is_preview ) {
					$template = SiteOrigin_Premium::dir_path( __FILE__ ) . '/templates/cart-empty.php';
				}
			}
		} elseif ( is_checkout() ) {
			if ( preg_match( '/checkout\/form\-checkout\.php/', $template ) ) {
				if ( ! empty( $so_wc_templates['checkout']['active'] ) || $is_preview ) {
					$template = SiteOrigin_Premium::dir_path( __FILE__ ) . '/templates/checkout.php';
				}
			}

			if ( preg_match( '/checkout\/thankyou\.php/', $template ) ) {
				if ( ! empty( $so_wc_templates['thankyou']['active'] ) ) {
					$template = SiteOrigin_Premium::dir_path( __FILE__ ) . '/templates/thankyou.php';
				}
			}
		} elseif ( is_account_page() ) {
			if ( preg_match( '/myaccount\/my\-account\.php/', $template ) ) {
				if ( ! empty( $so_wc_templates['myaccount']['active'] ) || $is_preview ) {
					$template = SiteOrigin_Premium::dir_path( __FILE__ ) . '/templates/my-account.php';
					wp_enqueue_style(
						'so-wc-myaccount',
						plugin_dir_url( __FILE__ ) . 'templates/myaccount/so-wc-myaccount.css',
						array(),
						SITEORIGIN_PREMIUM_VERSION
					);
				}
			}
		}

		return $template;
	}

	public function get_woocommerce_template_part( $template, $slug, $name ) {
		$template_name = $slug . '-' . $name;
		$template_path = plugin_dir_path( __FILE__ ) . 'templates/' . $template_name . '.php';
		$so_wc_templates = get_option( 'so-wc-templates' );

		if ( empty( $so_wc_templates[ $template_name ] ) ) {
			return $template;
		}
		$template_data = $so_wc_templates[ $template_name ];

		if ( $template_name == 'content-single-product' ) {
			global $post;

			$template_id = $this->set_active_template(
				get_post_meta( $post->ID, 'so_wc_template_post_id', true ),
				$template_data
			);

			if ( ! empty( $template_id ) ) {
				wp_enqueue_style(
					'so-wc-content-product-single',
					plugin_dir_url( __FILE__ ) . 'templates/content-single-product/so-wc-content-product-single.css',
					array(),
					SITEORIGIN_PREMIUM_VERSION
				);
			}
		} elseif ( $template_name == 'content-product' ) {
			$term = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );
			$template_id = $this->set_active_template(
				! empty( $term ) && is_object( $term ) ? get_option( "_term_type_{$term->taxonomy}_{$term->term_id}" ) : '',
				$template_data
			);
		} else {
			$template_id = ! empty( $so_wc_templates[ $template_name ]['active'] );
		}

		$is_preview = ! empty( $_GET['siteorigin_premium_template_preview'] ) && ! $this->preview_rendered;

		if ( file_exists( $template_path ) && ( $template_id || $is_preview ) ) {
			$panels_data = get_post_meta( $template_id, 'panels_data', true );

			if ( ! empty( $panels_data ) && ! empty( $panels_data['grids'] ) ) {
				set_query_var( 'wctb_template_id', $template_id );
				$template = $template_path;
			}
		}

		return $template;
	}

	public function block_editor_content( $content ) {
		// If there aren't any WCTB templates setup, don't bother.
		$this->so_wc_templates = get_option( 'so-wc-templates' );

		if ( empty( $this->so_wc_templates ) ) {
			return $content;
		}

		$current = get_the_ID();
		$wc_pages = array(
			wc_get_page_id( 'shop' ) => 'archive',
			wc_get_page_id( 'cart' ) => 'cart',
			wc_get_page_id( 'checkout' ) => 'checkout',
		);

		// Not a WooCommerce page.
		if ( ! isset( $wc_pages[ $current ] ) ) {
			return $content;
		}

		$current_page = $wc_pages[ $current ];

		// If we're on the checkout, check if the user has submitted an order.
		if (
			$current_page == 'checkout' &&
			is_wc_endpoint_url( 'order-received' )
		) {
			$current_page = 'thankyou';
		}

		// Ensure the current template is active.
		if ( empty( $this->so_wc_templates[ $current_page ]['active'] ) ) {
			return $content;
		}

		$template = $current_page;

		if (
			$current_page == 'cart' &&
			! empty( WC()->cart ) &&
			WC()->cart->is_empty() &&
			! empty( $this->so_wc_templates['cart-empty']['active'] )
		) {
			$template = 'cart-empty';
		}

		if ( ! empty( $template ) ) {
			if (
				$current_page == 'checkout' ||
				$current_page == 'thankyou'
			) {
				if ( is_wc_endpoint_url( 'order-pay' ) ) {
					// We need to load the order into the cart.
					$order_id = get_query_var( 'order-pay' );
					$order = wc_get_order( $order_id );

					if ( ! empty( $order ) ) {
						WC()->cart->empty_cart();

						foreach ( $order->get_items() as $item ) {
							$product = $item->get_product();

							if ( empty( $product ) ) {
								continue;
							}

							WC()->cart->add_to_cart(
								$product->get_id(),
								$item->get_quantity()
							);
						}
					}
				}
				$checkout = WC()->checkout();
			}
			ob_start();
			include SiteOrigin_Premium::dir_path( __FILE__ ) . '/templates/' . sanitize_file_name( $template ) . '.php';

			return ob_get_clean();
		}

		return $content;
	}

	public function add_widgets_dialog_tabs( $tabs ) {
		if ( ! self::is_siteorigin_premium_wc_template_builder() ) {
			return $tabs;
		}

		$tabs['woocommerce_content_single_product'] = array(
			'title' => __( 'WooCommerce Product', 'siteorigin-premium' ),
			'filter' => array(
				'groups' => array( 'woocommerce_content_single_product' ),
			),
		);

		$tabs['woocommerce_content_product'] = array(
			'title' => __( 'WooCommerce Product Archive', 'siteorigin-premium' ),
			'filter' => array(
				'groups' => array( 'woocommerce_content_product' ),
			),
		);

		$tabs['woocommerce_shop'] = array(
			'title' => __( 'WooCommerce Shop', 'siteorigin-premium' ),
			'filter' => array(
				'groups' => array( 'woocommerce_shop' ),
			),
		);

		$tabs['woocommerce_cart'] = array(
			'title' => __( 'WooCommerce Cart', 'siteorigin-premium' ),
			'filter' => array(
				'groups' => array( 'woocommerce_cart' ),
			),
		);

		$tabs['woocommerce_cart_empty'] = array(
			'title' => __( 'WooCommerce Empty Cart', 'siteorigin-premium' ),
			'filter' => array(
				'groups' => array( 'woocommerce_cart_empty' ),
			),
		);

		$tabs['woocommerce_checkout'] = array(
			'title' => __( 'WooCommerce Checkout', 'siteorigin-premium' ),
			'filter' => array(
				'groups' => array( 'woocommerce_checkout' ),
			),
		);

		$tabs['woocommerce_thankyou'] = array(
			'title' => __( 'WooCommerce Thank You', 'siteorigin-premium' ),
			'filter' => array(
				'groups' => array( 'woocommerce_thankyou' ),
			),
		);

		$tabs['woocommerce_myaccount'] = array(
			'title' => __( 'WooCommerce My Account', 'siteorigin-premium' ),
			'filter' => array(
				'groups' => array( 'woocommerce_myaccount' ),
			),
		);

		return $tabs;
	}

	public function wc_template_widgets( $widgets ) {
		foreach ( $widgets as $class => &$widget ) {
			if ( preg_match( '/SiteOrigin_Premium_WooCommerce_(.*)/i', $class, $matches ) ) {
				if ( self::is_siteorigin_premium_wc_template_builder() ) {
					$widget['groups'] = array();

					foreach ( $this->template_widget_groups as $group => $group_widgets ) {
						if ( in_array( $class, $group_widgets ) ) {
							$widget['icon'] = 'so-wc-widget-icon';
							$widget['groups'][] = 'woocommerce_' . str_replace( '-', '_', $group );
						}
					}
				}
			}
		}

		return $widgets;
	}

	public function add_template_layouts( $layout_directories ) {
		if ( self::is_siteorigin_premium_wc_template_builder() ) {
			if ( apply_filters( 'siteorigin_premium_wctb_panels_remove_layouts', true ) ) {
				$layout_directories = array();
			}
			$layout_directories[] = plugin_dir_path( __FILE__ ) . 'prebuilt-templates';
		}

		return $layout_directories;
	}
	public function remove_template_layouts( $layouts ) {
		if ( self::is_siteorigin_premium_wc_template_builder() && apply_filters( 'siteorigin_premium_wctb_panels_remove_layouts', true ) ) {
			unset( $layouts['default-home'] );
		}

		return $layouts;
	}

	public function add_wctb_items( $tabs ) {
		if ( self::is_siteorigin_premium_wc_template_builder() ) {
			$tabs['clone_so_wc_template'] = __( 'Clone: WooCommerce Templates', 'siteorigin-premium' );
		}

		return $tabs;
	}

	public function add_product_meta_box( $post ) {
		add_meta_box(
			'so-wc-template-post-id',
			__( 'Product template', 'siteorigin-premium' ),
			array( $this, 'render_template_post_meta_box' ),
			'product',
			'side',
			'default'
		);
	}

	public function render_template_post_meta_box( $post, $metabox ) {
		$template_post_id = get_post_meta( $post->ID, 'so_wc_template_post_id', true );
		$template_posts = self::get_product_template_posts( 'content-single-product' );

		?>
		<select id="so_wc_template_post_id" name="so_wc_template_post_id">
			<option value=""><?php esc_html_e( 'Default', 'siteorigin-premium' ); ?></option>
			<?php foreach ( $template_posts as $tmpl_post ) { ?>
				<option
					value="<?php echo esc_attr( $tmpl_post->ID ); ?>"
					<?php selected( $tmpl_post->ID, $template_post_id ); ?>>
					<?php echo esc_html( $tmpl_post->post_title ); ?>
				</option>
			<?php } ?>
		</select>
		<?php

		wp_nonce_field( 'save_post_so_wc_template', '_so_wc_template_nonce' );
	}

	public function save_post( $post_id, $post ) {
		if (
			$post->post_type == 'product' &&
			isset( $_POST['so_wc_template_post_id'] ) &&
			! empty( $_POST['_so_wc_template_nonce'] ) &&
			wp_verify_nonce( $_POST['_so_wc_template_nonce'], 'save_post_so_wc_template' )
		) {
			$template_post_id = intval( $_POST['so_wc_template_post_id'] );
			update_post_meta( $post_id, 'so_wc_template_post_id', $template_post_id );
		}
	}

	public function add_template_inline_data( $post, $post_type_object ) {
		if ( $post->post_type == 'product' ) {
			$template_post_id = get_post_meta( $post->ID, 'so_wc_template_post_id', true );
			echo '<div class="so_wc_template_post_id_current">' . esc_attr( (int) $template_post_id ) . '</div>';
		}
	}

	public function quick_bulk_edit() {
		$so_wc_templates = get_option( 'so-wc-templates' );
		$template_posts = self::get_product_template_posts( 'content-single-product' );
		?>
		<br class="clear">
		<label>
			<span class="title"><?php esc_html_e( 'Template:', 'siteorigin-premium' ); ?></span>
			<span class="input-text-wrap">
				<select class="so_wc_template_post_id" name="so_wc_template_post_id">
					<option value="0"><?php esc_html_e( 'Default', 'siteorigin-premium' ); ?></option>
					<?php foreach ( $template_posts as $tmpl_post ) { ?>
						<option value="<?php echo esc_attr( $tmpl_post->ID ); ?>">
							<?php echo esc_html( $tmpl_post->post_title ); ?>
						</option>
					<?php } ?>
				</select>
			</span>
		</label>
		<?php
	}

	public function quick_bulk_edit_save( $product ) {
		// Nonce check is not required due to WC nonce check being run prior to triggering this.
		if ( isset( $_REQUEST['so_wc_template_post_id'] ) ) {
			$template_post_id = (int) $_REQUEST['so_wc_template_post_id'];

			// Is this a bulk edit, or a quick edit?
			if ( ! empty( $_REQUEST['woocommerce_bulk_edit'] ) && ! empty( $_REQUEST['post'] ) ) {
				foreach ( $_REQUEST['post'] as $post_id ) {
					update_post_meta( $post_id, 'so_wc_template_post_id', wc_clean( $template_post_id ) );
				}
			} else {
				update_post_meta( $product->get_id(), 'so_wc_template_post_id', wc_clean( $template_post_id ) );
			}
		}
	}

	private static function get_product_template_posts( $type ) {
		$template_posts = get_posts(
			array(
				'post_type' => self::POST_TYPE,
				'post_status' => 'draft',
				'numberposts' => -1,
				'meta_key' => 'wctb_template',
				'meta_value' => $type,
			)
		);

		return $template_posts;
	}

	public function add_product_archive_template_field( $taxonomy ) {
		$template_posts = self::get_product_template_posts( 'content-product' );

		if ( ! empty( $template_posts ) ) {
			?>
			<label for="so_wc_template_post_id"><?php esc_html_e( 'Product Archive Template', 'siteorigin-premium' ); ?></label>
			<?php
			$this->product_cat_template_select( $template_posts, '' );
		}
	}

	public function product_cat_template_select( $template_posts, $value ) {
		?>
		<select id="so_wc_template_post_id" name="so_wc_template_post_id">
			<option value=""><?php esc_html_e( 'Default', 'siteorigin-premium' ); ?></option>
			<?php foreach ( $template_posts as $tmpl_post ) { ?>
				<option
					value="<?php echo esc_attr( $tmpl_post->ID ); ?>"
					<?php selected( $tmpl_post->ID, $value ); ?>>
						<?php echo esc_html( $tmpl_post->post_title ); ?>
					</option>
			<?php } ?>
		</select>
		<?php
	}

	public function edit_product_archive_template_field( $tag, $taxonomy ) {
		$selected = get_option( "_term_type_{$taxonomy}_{$tag->term_id}" );
		$template_posts = self::get_product_template_posts( 'content-product' );

		if ( ! empty( $template_posts ) ) {
			?>
			<tr class="form-field form-required">
				<th scope="row" valign="top">
					<label for="so_wc_template_post_id"><?php esc_html_e( 'Product Archive Template', 'siteorigin-premium' ); ?></label>
				</th>
				<td><?php $this->product_cat_template_select( $template_posts, $selected ); ?></td>
			</tr>
			<?php
		}
	}

	public function save_product_cat_template_field( $term_id, $tt_id, $taxonomy ) {
		if ( isset( $_POST['so_wc_template_post_id'] ) ) {
			update_option( "_term_type_{$taxonomy}_{$term_id}", $_POST['so_wc_template_post_id'] );
		}
	}

	public function preview_template( $panels_data, $post_id ) {
		if (
			current_user_can( 'edit_post', $post_id ) &&
			! empty( $_POST['siteorigin_premium_template_preview'] ) &&
			$_POST['preview_template_post_id'] == $post_id
		) {
			$panels_data = json_decode( wp_unslash( $_POST['template_preview_panels_data'] ), true );

			if ( ! empty( $panels_data['widgets'] ) ) {
				$panels_data['widgets'] = SiteOrigin_Panels_Admin::single()->process_raw_widgets( $panels_data['widgets'] );
			}
		}

		return $panels_data;
	}

	private $tmp_cart_contents;

	/**
	 * This temporarily adds/removes items to the cart to allow previewing the cart and cart-empty templates.
	 *
	 * @return mixed
	 *
	 * @throws Exception
	 */
	public function create_preview_content( $content ) {
		if (
			empty( $_GET['siteorigin_premium_template_preview'] ) ||
			! isset( $_POST['tab'] )
		) {
			return $content;
		}

		if ( $_POST['tab'] == 'cart' ) {
			if ( WC()->cart->is_empty() ) {
				$products = wc_get_products( array( 'limit' => 1 ) );

				if ( count( $products ) > 0 ) {
					@WC()->cart->add_to_cart( $products[0]->id );
				}
				$this->tmp_cart_contents = WC()->cart->get_cart_contents();
			}
		} elseif ( $_POST['tab'] == 'cart-empty' ) {
			$this->tmp_cart_contents = WC()->cart->get_cart_contents();

			foreach ( $this->tmp_cart_contents as $tmp_cart_item_key => $tmp_cart_item ) {
				WC()->cart->remove_cart_item( $tmp_cart_item_key );
			}
		}

		return $content;
	}

	/**
	 * This resets the cart to it's original state before previewing cart and cart-empty templates.
	 *
	 * @return mixed
	 */
	public function remove_preview_content( $content ) {
		if (
			empty( $_GET['siteorigin_premium_template_preview'] ) ||
			! isset( $_POST['tab'] )
		) {
			return $content;
		}

		if ( $_POST['tab'] == 'cart' ) {
			if ( ! empty( $this->tmp_cart_contents ) ) {
				foreach ( $this->tmp_cart_contents as $tmp_cart_item_key => $tmp_cart_item ) {
					WC()->cart->remove_cart_item( $tmp_cart_item_key );
				}
			}
		} elseif ( $_POST['tab'] == 'cart-empty' ) {
			if ( ! empty( $this->tmp_cart_contents ) ) {
				foreach ( $this->tmp_cart_contents as $tmp_cart_item_key => $tmp_cart_item ) {
					WC()->cart->restore_cart_item( $tmp_cart_item_key );
				}
			}
		}

		return $content;
	}

	public function before_template_render() {
		add_filter( 'siteorigin_panels_css_cell_margin_bottom', '__return_false' );

		if ( siteorigin_panels_setting( 'add-widget-class' ) ) {
			add_filter( 'siteorigin_panels_widget_classes', array( $this, 'remove_widget_class' ) );
		}
	}

	public function remove_widget_class( $classes ) {
		$widget_class_position = array_search( 'widget', $classes );

		if ( $widget_class_position !== false ) {
			unset( $classes[ $widget_class_position ] );
		}

		return $classes;
	}

	public function after_template_render() {
		remove_filter( 'siteorigin_panels_css_cell_margin_bottom', '__return_false' );

		if ( siteorigin_panels_setting( 'add-widget-class' ) ) {
			add_filter( 'siteorigin_panels_widget_classes', array( $this, 'remove_widget_class' ) );
		}

		SiteOrigin_Panels_Renderer::single()->print_inline_css();
	}

	// Confirm custom template exists, and is active before using it.
	public function set_active_template( $custom_template_id, $template_data ) {
		// Check if the user is previewing the template.
		if ( ! empty( $_GET['siteorigin_premium_template_preview'] ) ) {
			if ( ! $this->preview_rendered ) {
				$this->preview_rendered = true;
				$template_post_id = $_POST['preview_template_post_id'];
			}
		}

		if ( empty( $template_post_id ) ) {
			// Check if there's a valid custom template set.
			if ( ! empty( $custom_template_id ) && get_post( $custom_template_id ) ) {
				$template_post_id = $custom_template_id;
			} elseif ( ! empty( $template_data['post_id'] ) && get_post( $template_data['post_id'] ) ) {
				// Attempt to use the default template.
				$template_post_id = $template_data['post_id'];
			}
		}

		if ( ! empty( $template_post_id ) ) {
			set_query_var( 'wctb_template_id', $template_post_id );
			return $template_post_id;
		}

		return false;
	}

	/**
	 * Detect if the WCTB Product Shortcode is present, and load WooCommerce scripts if necessary.
	 */
	public function shortcode_enqueue_frontend_scripts() {
		global $post;

		if (
			is_null( $post ) ||
			! has_shortcode( $post->post_content, 'sowctb' )
		) {
			return;
		}

		preg_match( '/\[sowctb[^\]]*product="(\d+)"[^\]]*\]/', $post->post_content, $matches );

		if (
			empty( $matches ) ||
			empty( $matches[1] ) ||
			! is_numeric( $matches[1] )
		) {
			return;
		}

		// Ensure the product is valid.
		$product = wc_get_product( $matches[1] );
		if ( empty( $product ) ) {
			return;
		}

		$this->has_shortcode = true;

		// Trick WC into loading scripts.
		$post->post_content .= '[product_page';
		WC_Frontend_Scripts::load_scripts();

		wp_enqueue_style(
			'so-wc-content-product-single',
			plugin_dir_url( __FILE__ ) . 'templates/content-single-product/so-wc-content-product-single.css',
			array(),
			SITEORIGIN_PREMIUM_VERSION
		);

		do_action( 'siteorigin_premium_wctb_shortcode', $product );

		// Remove the shortcode.
		$post->post_content = substr( $post->post_content, 0, -13 );
	}

	/**
	 * Add WooCommerce classes to the body tag to help with styling.
	 *
	 * @param array $classes The current body classes.
	 * @return array The modified body classes.
	 */
	public function shortcode_add_body_class( $classes ) {
		if ( $this->has_shortcode ) {
			$classes[] = 'woocommerce';
			$classes[] = 'woocommerce-page single';
			$classes[] = 'single';
		}

		return $classes;
	}

	/**
	 * Add the WooCommerce content product template builder shortcode.
	 *
	 * @param array $attr The shortcode attributes.
	 * @return string The template output.
	 */
	public function shortcode( $attr ) {
		$attributes = shortcode_atts( array(
			'product' => '',
			'template' => '',
		), $attr );

		if ( is_admin() ) {
			// Return shortcode to help with enqueueing scripts on frontend.
			return sprintf(
				'[sowctb product="%d" template="%d"]',
				(int) $attributes['product'],
				(int) $attributes['template']
			);
		}

		if (
			empty( $attributes['product'] ) ||
			! is_numeric( $attributes['product'] )
		) {
			return __( 'Please provide a product ID.', 'siteorigin-premium' );
		}

		if (
			empty( $attributes['template'] ) ||
			! is_numeric( $attributes['template'] )
		) {
			return __( 'Please provide a WCTB template id.', 'siteorigin-premium' );
		}

		global $wp_query, $product, $post;

		// Prep template.
		$initial_product = $product;
		$initial_post = $post;

		$product = wc_get_product( (int) $attributes['product'] );
		$post = get_post( (int) $attributes['product'] );

		$original_wp_query = $wp_query;
		$wp_query = new WP_Query( array(
			'p' => (int) $attributes['product'],
			'post_type' => 'product'
		) );

		set_query_var( 'wctb_template_id', (int) $attributes['template'] );

		ob_start();
		require SiteOrigin_Premium::dir_path( __FILE__ ) . '/templates/content-single-product.php';

		// Restore previous values.
		$wp_query = $original_wp_query;
		$product = $initial_product;
		$post = $initial_post;

		return ob_get_clean();
	}

	public function open_wc_wrapper() {
		echo '<div class="woocommerce so-wc-wrapper">';
	}

	public function close_wc_wrapper() {
		echo '</div>';
	}
}
