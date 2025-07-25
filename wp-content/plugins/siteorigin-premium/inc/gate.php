<?php

class SiteOrigin_Premium_Central_Gate {
	public $settings;
	public $is_panels;
	public $content;
	public $status_code;

	public function load_pb_front_css() {
		?>
		<link rel='stylesheet' id='siteorigin-panels-css' href='<?php echo esc_url( SiteOrigin_Panels::front_css_url() ); ?>' type='text/css' media='all' />
		<?php
		SiteOrigin_Panels_Styles::register_scripts();
	}

	public function add_gate_layout_builder_css() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo SiteOrigin_Panels::renderer()->generate_css( 'wGateAddon', $this->settings['content_layout'] );
	}

	public function add_gate_content() {
		if ( empty( $this->is_panels ) ) {
			echo wp_kses_post( apply_filters( 'the_content', $this->content ) );
			return;
		}

		if ( ! class_exists( 'SiteOrigin_Panels' ) ) {
			esc_html_e( 'SiteOrigin Page Builder is required for this content to output.', 'siteorigin-premium' );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->content;
	}

	public function form_options( $page = false ) {
		$form_options = array(
			'enabled' => array(
				'type' => 'checkbox',
				'label' => '',
				'default' => false,
			),
			'content_type' => array(
				'type' => 'radio',
				'label' => __( 'Display', 'siteorigin-premium' ),
				'options' => array(
					'text' => __( 'Text', 'siteorigin-premium' ),
					'page' => __( 'Page', 'siteorigin-premium' ),
				),
				'default' => 'text',
				'state_emitter' => array(
					'callback' => 'select',
					'args' => array( 'content_type' ),
				),
			),
			'title' => array(
				'type' => 'text',
				'label' => __( 'Title', 'siteorigin-premium' ),
				'description' => __( 'The text used for the browser window title.', 'siteorigin-premium' ),
				'state_handler' => array(
					'content_type[page]' => array( 'show' ),
					'_else[content_type]' => array( 'hide' ),
				),
			),
		);

		if (
			defined( 'SITEORIGIN_PANELS_VERSION' ) &&
			class_exists( 'SiteOrigin_Widgets_Bundle' )
		) {
			siteorigin_widgets_array_insert(
				$form_options['content_type']['options'],
				'page',
				array(
					'layout' => __( 'Layout Builder', 'siteorigin-premium' ),
				)
			);

			$form_options['content_layout'] = array(
				'type' => 'builder',
				'label' => __( 'Content', 'siteorigin-premium' ),
				'builder_type' => 'central_gate_builder',
				'state_handler' => array(
					'content_type[layout]' => array( 'show' ),
					'_else[content_type]' => array( 'hide' ),
				),
			);
		}

		if ( $page === true ) {
			$form_options['content_page'] = array(
				'type' => 'link',
				'label' => __( 'Page', 'siteorigin-premium' ),
				'readonly' => true, // Only allow selection of pages.
				'single' => true,
				'state_handler' => array(
					'content_type[page]' => array( 'show' ),
					'_else[content_type]' => array( 'hide' ),
				),
			);
		} else {
			// This addon doesn't use the page type, so let's remove it.
			unset( $form_options['content_type']['options']['page'] );
			unset( $form_options['content_page'] );
			$form_options['content_type']['default'] = defined( 'SITEORIGIN_PANELS_VERSION' ) ? 'layout' : 'text';
		}

		$form_options['content_text'] = array(
			'type' => 'tinymce',
			'label' => __( 'Text', 'siteorigin-premium' ),
			'state_handler' => array(
				'content_type[text]' => array( 'show' ),
				'_else[content_type]' => array( 'hide' ),
			),
		);

		$form_options['design'] = array(
			'type' => 'section',
			'label' => __( 'Design', 'siteorigin-premium' ),
			'collapsed' => true,
			'state_handler' => array(
				'content_type[layout,text]' => array( 'show' ),
				'_else[content_type]' => array( 'hide' ),
			),
			'fields' => array(
				'body' => array(
					'type' => 'section',
					'collapsed' => true,
					'label' => __( 'Body', 'siteorigin-premium' ),
					'fields' => array(
						'background_color' => array(
							'type' => 'color',
							'label' => __( 'Background Color', 'siteorigin-premium' ),
							'default' => '#ffffff',
						),
						'background_image' => array(
							'type' => 'media',
							'label' => __( 'Background Image', 'siteorigin-premium' ),
							'state_emitter' => array(
								'callback' => 'conditional',
								'args' => array(
									'body_background[show]: val',
									'body_background[hide]: ! val',
								),
							),
						),
						'background_image_opacity' => array(
							'label' => __( 'Background image opacity', 'siteorigin-premium' ),
							'type' => 'slider',
							'min' => 0,
							'max' => 100,
							'default' => 100,
							'state_handler' => array(
								'body_background[show]' => array( 'show' ),
								'_else[body_background]' => array( 'hide' ),
							),
						),
					),
				),
				'container' => array(
					'type' => 'section',
					'collapsed' => true,
					'label' => __( 'Content Container', 'siteorigin-premium' ),
					'fields' => array(
						'margin' => array(
							'type' => 'multi-measurement',
							'label' => __( 'Margin', 'siteorigin-premium' ),
							'default' => '30px 0px 0px 0px',
							'measurements' => array(
								'top' => __( 'Top', 'siteorigin-premium' ),
								'right' => __( 'Right', 'siteorigin-premium' ),
								'bottom' => __( 'Bottom', 'siteorigin-premium' ),
								'left' => __( 'Left', 'siteorigin-premium' ),
							),
						),
						'border_radius' => array(
							'type' => 'multi-measurement',
							'label' => __( 'Border Radius', 'siteorigin-premium' ),
							'default' => '16px 16px 16px 16px',
							'measurements' => array(
								'top' => __( 'Top', 'siteorigin-premium' ),
								'right' => __( 'Right', 'siteorigin-premium' ),
								'bottom' => __( 'Bottom', 'siteorigin-premium' ),
								'left' => __( 'Left', 'siteorigin-premium' ),
							),
						),
						'background_color' => array(
							'type' => 'color',
							'label' => __( 'Background', 'siteorigin-premium' ),
							'default' => '#ffffff',
						),
						'padding' => array(
							'type' => 'multi-measurement',
							'label' => __( 'Padding', 'siteorigin-premium' ),
							'default' => '35px 40px 20px 40px',
							'measurements' => array(
								'top' => __( 'Top', 'siteorigin-premium' ),
								'right' => __( 'Right', 'siteorigin-premium' ),
								'bottom' => __( 'Bottom', 'siteorigin-premium' ),
								'left' => __( 'Left', 'siteorigin-premium' ),
							),
						),
						'alignment' => array(
							'type' => 'select',
							'label' => __( 'Alignment', 'siteorigin-premium' ),
							'default' => 'center',
							'options' => array(
								'left' => __( 'Left', 'siteorigin-premium' ),
								'center' => __( 'Center', 'siteorigin-premium' ),
								'right' => __( 'Right', 'siteorigin-premium' ),
							),
						),
						'width' => array(
							'type' => 'measurement',
							'label' => __( 'Width', 'siteorigin-premium' ),
							'default' => '820px',
						),
					),
				),
				'heading' => array(
					'type' => 'section',
					'collapsed' => true,
					'label' => __( 'Heading', 'siteorigin-premium' ),
					'fields' => array(
						'font' => array(
							'type' => 'font',
							'label' => __( 'Font', 'siteorigin-premium' ),
							'default' => 'Helvetica Neue',
						),
						'size' => array(
							'type' => 'measurement',
							'label' => __( 'Font Size', 'siteorigin-premium' ),
							'default' => '32px',
						),
						'color' => array(
							'type' => 'color',
							'label' => __( 'Color', 'siteorigin-premium' ),
							'default' => '#131313',
						),
					),
				),
				'text' => array(
					'type' => 'section',
					'collapsed' => true,
					'label' => __( 'Text', 'siteorigin-premium' ),
					'fields' => array(
						'font' => array(
							'type' => 'font',
							'label' => __( 'Font', 'siteorigin-premium' ),
							'default' => 'Helvetica Neue',
						),
						'size' => array(
							'type' => 'measurement',
							'label' => __( 'Font Size', 'siteorigin-premium' ),
							'default' => '16px',
						),
						'color' => array(
							'type' => 'color',
							'label' => __( 'Color', 'siteorigin-premium' ),
							'default' => '#1b1b1b',
						),
						'link' => array(
							'type' => 'color',
							'label' => __( 'Link Color', 'siteorigin-premium' ),
						),
						'link_hover' => array(
							'type' => 'color',
							'label' => __( 'Link Hover Color', 'siteorigin-premium' ),
						),
					),
				),
			),
		);

		// If WB has the Toggle field present, add a Box Shadow content container setting.
		if ( class_exists( 'SiteOrigin_Widget_Field_Toggle' ) ) {
			siteorigin_widgets_array_insert(
				$form_options['design']['fields']['container']['fields'],
				'border_radius',
				array(
					'box_shadow' => array(
						'type' => 'toggle',
						'label' => __( 'Box Shadow', 'siteorigin-premium' ),
						'default' => false,
						'state_handler' => array(
							'content_type[layout,text]' => array( 'show' ),
							'_else[content_type]' => array( 'hide' ),
						),
						'fields' => array(
							'color' => array(
								'type' => 'color',
								'label' => __( 'Color', 'siteorigin-premium' ),
								'default' => 'rgba(0, 0, 0, 0.07)',
								'alpha' => true,
							),
							'offset_horizontal' => array(
								'type' => 'measurement',
								'label' => __( 'Horizontal Offset', 'siteorigin-premium' ),
								'default' => 0,
							),
							'offset_vertical' => array(
								'type' => 'measurement',
								'label' => __( 'Vertical Offset', 'siteorigin-premium' ),
								'default' => '2px',
							),
							'blur' => array(
								'type' => 'measurement',
								'label' => __( 'Blur', 'siteorigin-premium' ),
								'default' => '12px',
							),
							'spread' => array(
								'type' => 'measurement',
								'label' => __( 'Spread', 'siteorigin-premium' ),
								'default' => 0,
							),
						),
					),
				)
			);
		}

		return $form_options;
	}

	public function setup_settings( $id, $force = false ) {
		if ( ! empty( $this->settings ) && ! $force ) {
			return;
		}

		$addon_settings = SiteOrigin_Premium_Options::single()->get_settings( $id );
		$this->settings = $addon_settings;
	}

	public function gate_setting( $setting, $group = null ) {
		if ( empty( $group ) ) {
			return empty( $this->settings[ $setting ] ) ? false : $this->settings[ $setting ];
		}

		return empty( $this->settings['design'][ $group ][ $setting ] ) ? false : $this->settings['design'][ $group ][ $setting ];
	}

	public function render( $force_gate = false ) {
		if (
			empty( $this->settings ) ||
			empty( $this->settings['enabled'] )
		) {
			return false;
		}

		if (
			empty( $force_gate ) &&
			empty( $this->settings['content_type'] )
		) {
			return false;
		}

		if ( ! empty( $this->status_code ) ) {
			status_header( $this->status_code );
		}

		$force_gate = $force_gate ? $force_gate : in_array( $this->settings['content_type'], array( 'layout', 'text' ) );


		if ( $force_gate ) {
			$this->render_gate( $force_gate );
		}

		$this->render_page();
		return true;
	}


	/**
	 * Format font family CSS properly with quotes around each individual font name.
	 *
	 * @param array $font Font settings array.
	 *
	 * @return string Formatted font-family CSS declaration.
	 */
	private function output_font_family( $font ) : string {
		if ( empty( $font['family'] ) ) {
			return '';
		}

		// Split the font family string by commas.
		$font_families = explode( ',', $font['family'] );
		$formatted_fonts = array();

		foreach ( $font_families as $family ) {
			$family = trim( $family );

			// Skip empty values
			if ( empty( $family ) ) {
				continue;
			}

			$family = sanitize_text_field( $family );

			// Only add quotes if they don't already exist.
			if ( strpos( $family, '"' ) === false && strpos( $family, "'" ) === false ) {
				$family = '"' . $family . '"';
			}

			$formatted_fonts[] = $family;
		}

		// Join the formatted font families back with commas.
		$formatted_family = implode( ', ', $formatted_fonts );

		return "font-family: $formatted_family;";
	}

	private function prepare_gate_content() {
		// Layout Builder.
		if ( $this->settings['content_type'] == 'layout' ) {
			if (
				! class_exists( 'SiteOrigin_Panels' ) ||
				! class_exists( 'SiteOrigin_Widgets_Bundle' )
			) {
				return false;
			}

			$sowb = SiteOrigin_Widgets_Bundle::single();
			$sowb->register_general_scripts();
			add_filter( 'siteorigin_widgets_is_preview', '__return_true' );
			SiteOrigin_Panels_Post_Content_Filters::add_filters( true );
			$this->content = SiteOrigin_Panels::renderer()->render(
				'wGateAddon',
				false,
				$this->settings['content_layout']
			);
			SiteOrigin_Panels_Post_Content_Filters::remove_filters( true );
			$this->is_panels = true;
		} elseif ( ! empty( $this->settings['content_text'] ) ) {
			// Text.
			$this->content = $this->settings['content_text'];
		}
	}

	private function default_title() {
		return;
	}

	public function get_page_id() {
		if ( empty( $this->settings['content_page'] ) ) {
			return false;
		}

		if ( preg_match( '/^post: *([0-9]+)/', $this->settings['content_page'], $matches ) ) {
			return (int) $matches[1];
		}

		return false;
	}

	/**
	 * Generate the box shadow CSS.
	 *
	 * @param array $settings The settings array.
	 * @param string $setting The setting name.
	 *
	 * @return string The box shadow CSS.
	 */
	private static function generate_shadow( $settings, $setting = '' ): string {
		$box_shadow_offset_horizontal = ! empty( $settings['offset_horizontal'] ) ? $settings['offset_horizontal'] : '0';
		$box_shadow_offset_vertical = ! empty( $settings['offset_vertical'] ) ? $settings['offset_vertical'] : '2px';
		$box_shadow_blur = ! empty( $settings['blur'] ) ? $settings['blur'] : '12px';
		$box_shadow_spread = ! empty( $settings['spread'] ) ? $settings['spread'] : '';
		$box_shadow_color = isset( $settings['color'] ) ? $settings['color'] : 'rgba(0, 0, 0, 0.07)';

		return "$box_shadow_offset_horizontal $box_shadow_offset_vertical $box_shadow_blur $box_shadow_spread $box_shadow_color";
	}

	private function render_gate( $force_gate = false ) {
		$this->prepare_gate_content();

		if ( ! $force_gate && empty( $this->content ) ) {
			return false;
		}

		// Set the title if needed.
		if ( empty( $this->settings['title'] ) ) {
			$this->settings['title'] = $this->default_title();
		}

		if ( $this->is_panels === true ) {
			add_action( 'siteorigin_premium_gate_head', array( $this, 'load_pb_front_css' ) );
			add_action( 'siteorigin_premium_gate_css', array( $this, 'add_gate_layout_builder_css' ) );
		}

		add_action( 'siteorigin_premium_gate_content', array( $this, 'add_gate_content' ) );

		if (
			class_exists( 'SiteOrigin_Widget_Field_Toggle' ) &&
			! empty( $this->settings['design']['container']['box_shadow']['so_field_container_state'] )
		) {
			$box_shadow = self::generate_shadow( $this->settings['design']['container']['box_shadow'], 'shadow' );
		}

		include SITEORIGIN_PREMIUM_DIR . 'tpl/gate.php';
		die();
	}

	public function render_page() {
		// Regular Page.
		if ( ! preg_match( '/^post: *([0-9]+)/', $this->settings['content_page'], $matches ) ) {
			return false;
		}
		$page_id = (int) $matches[1];

		// Ensure the page exists.
		$page = get_post( $page_id );

		if ( empty( $page ) ) {
			return false;
		}

		// Override the page to show the assigned page.
		global $wp_query;
		$wp_query->queried_object = $page;
		$wp_query->queried_object_id = $page_id;
		$wp_query->is_404 = false;
		$wp_query->is_single = false;
		$wp_query->is_page = true;
		$wp_query->post_count = 1;
		$wp_query->found_posts = 1;
		$wp_query->current_post = -1;
		$wp_query->posts = array( $page );
	}
}
