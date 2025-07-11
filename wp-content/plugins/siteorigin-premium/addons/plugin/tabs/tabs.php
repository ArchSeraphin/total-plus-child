<?php
/*
Plugin Name: SiteOrigin Tabs
Description: Enhance site navigation and engagement by enriching Tabs Widgets with new styles and functionalities for intuitive content organization.
Version: 1.0.0
Author: SiteOrigin
Author URI: https://siteorigin.com
License: GPL3
License URI: https://www.gnu.org/licenses/gpl-3.0.txt
Documentation: https://siteorigin.com/premium-documentation/plugin-addons/tabs/
Tags: Widgets Bundle
Video: 314963299
Requires: so-widgets-bundle/tabs
*/

class SiteOrigin_Premium_Plugin_Tabs {
	public function __construct() {
		add_action( 'init', array( $this, 'init_addon' ) );
	}

	public static function single() {
		static $single;

		return empty( $single ) ? $single = new self() : $single;
	}

	/**
	 * Do any required intialization methods.
	 */
	public function init_addon() {
		if ( class_exists( 'SiteOrigin_Widget_Tabs_Widget' ) ) {
			add_filter( 'siteorigin_widgets_form_options_sow-tabs', array( $this, 'admin_form_options' ), 10, 2 );
			add_filter( 'siteorigin_widgets_less_variables_sow-tabs', array( $this, 'add_less_variables' ), 10, 3 );
			add_filter( 'siteorigin_widgets_template_variables_sow-tabs', array( $this, 'add_template_variables' ), 10, 4 );
			add_filter( 'siteorigin_widgets_google_font_fields_sow-tabs', array( $this, 'add_google_font_fields' ), 10, 3 );
			add_filter( 'siteorigin_widgets_tabs_render_panel_content', array( $this, 'render_panel_content' ), 10, 3 );
			add_filter( 'siteorigin_widgets_settings_form_sow-tabs', array( $this, 'add_global_settings' ), 10, 2 );

			add_action( 'siteorigin_premium_version_update', array( $this, 'update_settings_migration' ), 20, 3 );
		}
	}

	public function add_global_settings( $form_options, $widget ) {
		$form_options['responsive_breakpoint'] = array(
			'type'        => 'measurement',
			'label'       => __( 'Responsive Breakpoint', 'siteorigin-premium' ),
			'default'     => '780px',
			'description' => __( 'The pixel resolution when the mobile widget settings will be applied.', 'siteorigin-premium' ),
		);

		return $form_options;
	}

	public function update_settings_migration( $new_version, $old_version ) {
		$addons = SiteOrigin_Premium::single()->get_active_addons();

		// If upgrading from version <= 1.31.2, activate the Anchor ID addon.
		if ( version_compare( $old_version, '1.31.2', '<=' ) && ! empty( $addons['plugin/anchor-id'] ) ) {
			SiteOrigin_Premium::single()->set_addon_active( 'plugin/anchor-id', true );
		}
	}

	/**
	 * Filters the admin form for the tabs widget to add Premium fields.
	 *
	 * @param $form_options array The Tabs Widget's form options.
	 * @param $widget SiteOrigin_Widget_Tabs_Widget The widget object.
	 *
	 * @return mixed The updated form options array containing the new and modified fields.
	 */
	public function admin_form_options( $form_options, $widget ) {
		if ( empty( $form_options ) ) {
			return $form_options;
		}

		$presets = json_decode( file_get_contents( plugin_dir_path( __FILE__ ) . 'data/presets.json' ), true );

		siteorigin_widgets_array_insert( $form_options, 'title', array(
			'presets' => array(
				'type' => 'presets',
				'label' => __( 'Presets', 'siteorigin-premium' ),
				'options' => $presets,
			),
		) );

		$tabs_fields = $form_options['tabs']['fields'];

		if ( array_key_exists( 'content_text', $tabs_fields ) ) {
			$position = 'content_text';
			$tabs_fields['content_text']['state_handler'] = array(
				'content_type_{$repeater}[text]' => array( 'show' ),
				'_else[content_type_{$repeater}]' => array( 'hide' ),
			);
		} else {
			$position = count( $tabs_fields );
		}

		$add_fields = array(
			'icon' => array(
				'type' => 'icon',
				'label' => __( 'Title icon', 'siteorigin-premium' ),
			),
			'icon_image' => array(
				'type' => 'media',
				'label' => __( 'Title icon image', 'siteorigin-premium' ),
				'description' => __( 'Use your own title icon image', 'siteorigin-premium' ),
				'library' => 'image',
				'fallback' => true,
			),
		);

		// The Builder field currently only works in some contexts so we only output it in those contexts for now.
		if ( is_admin() ||
			 ( defined( 'REST_REQUEST' ) && function_exists( 'register_block_type' ) ) ||
			 ! empty( $GLOBALS['SITEORIGIN_WIDGET_BLOCK_RENDER'] )
		) {
			$add_fields = array_merge( $add_fields, array(
				'content_type' => array(
					'type'          => 'radio',
					'label'         => __( 'Content type', 'siteorigin-premium' ),
					'options'       => array(
						'text'   => __( 'Text', 'siteorigin-premium' ),
						'layout' => __( 'Layout builder', 'siteorigin-premium' ),
					),
					'default'       => 'text',
					'state_emitter' => array(
						'callback' => 'select',
						'args'     => array( 'content_type_{$repeater}' ),
					),
				),
				'content_layout' => array(
					'type'  => 'builder',
					'label' => __( 'Content', 'siteorigin-premium' ),
					'builder_type' => 'tabs_panel_builder',
					'state_handler' => array(
						'content_type_{$repeater}[layout]' => array( 'show' ),
						'_else[content_type_{$repeater}]' => array( 'hide' ),
					),
				),
			) );
		}

		siteorigin_widgets_array_insert( $tabs_fields, $position, $add_fields );

		$form_options['tabs']['fields'] = $tabs_fields;

		$border_width_units = array();

		foreach ( siteorigin_widgets_get_measurements_list() as $unit ) {
			if ( $unit != '%' ) {
				$border_width_units[] = $unit;
			}
		}

		$tabs_container_fields = $form_options['design']['fields']['tabs_container']['fields'];

		if ( array_key_exists( 'border_width', $tabs_container_fields ) ) {
			$tabs_container_fields['border_width']['type'] = 'multi-measurement';
			$tabs_container_fields['border_width']['autofill'] = true;

			$tabs_container_fields['border_width']['measurements'] = array(
				'top' => array(
					'label' => __( 'Top', 'siteorigin-premium' ),
					'units' => $border_width_units,
				),
				'right' => array(
					'label' => __( 'Right', 'siteorigin-premium' ),
					'units' => $border_width_units,
				),
				'bottom' => array(
					'label' => __( 'Bottom', 'siteorigin-premium' ),
					'units' => $border_width_units,
				),
				'left' => array(
					'label' => __( 'Left', 'siteorigin-premium' ),
					'units' => $border_width_units,
				),
			);
		}

		$tabs_container_fields = array_merge( $tabs_container_fields, array(
			'border_radius' => array(
				'type' => 'multi-measurement',
				'label' => __( 'Border radius', 'siteorigin-premium' ),
				'autofill' => true,
				'measurements' => array(
					'top_left' => __( 'Top left', 'siteorigin-premium' ),
					'top_right' => __( 'Top right', 'siteorigin-premium' ),
					'bottom_right' => __( 'Bottom right', 'siteorigin-premium' ),
					'bottom_left' => __( 'Bottom left', 'siteorigin-premium' ),
				),
			),
			'padding' => array(
				'type' => 'multi-measurement',
				'label' => __( 'Padding', 'siteorigin-premium' ),
				'autofill' => true,
				'default' => '12px 10px 0px 10px',
				'measurements' => array(
					'top' => __( 'Top', 'siteorigin-premium' ),
					'right' => __( 'Right', 'siteorigin-premium' ),
					'bottom' => __( 'Bottom', 'siteorigin-premium' ),
					'left' => __( 'Left', 'siteorigin-premium' ),
				),
			),
			'tabs_align' => array(
				'type' => 'select',
				'label' => __( 'Tabs alignment', 'siteorigin-premium' ),
				'default' => 'left',
				'options' => array(
					'left' => __( 'Left', 'siteorigin-premium' ),
					'right' => __( 'Right', 'siteorigin-premium' ),
					'center' => __( 'Center', 'siteorigin-premium' ),
				),
				'state_handler' => array(
					'tabs_position[top,bottom]' => array( 'show' ),
					'_else[tabs_position]'      => array( 'hide' ),
				),
			),
			'tabs_align_horziontal' => array(
				'type' => 'select',
				'label' => __( 'Tabs alignment', 'siteorigin-premium' ),
				'default' => 'top',
				'options' => array(
					'top' => __( 'Top', 'siteorigin-premium' ),
					'bottom' => __( 'Bottom', 'siteorigin-premium' ),
					'middle' => __( 'Middle', 'siteorigin-premium' ),
				),
				'state_handler' => array(
					'tabs_position[left,right]' => array( 'show' ),
					'_else[tabs_position]'      => array( 'hide' ),
				),
			),
			'tabs_position' => array(
				'type' => 'radio',
				'label' => __( 'Tabs position', 'siteorigin-premium' ),
				'options' => array(
					'top' => __( 'Top', 'siteorigin-premium' ),
					'right' => __( 'Right', 'siteorigin-premium' ),
					'bottom' => __( 'Bottom', 'siteorigin-premium' ),
					'left' => __( 'Left', 'siteorigin-premium' ),
				),
				'default' => 'top',
				'state_emitter' => array(
					'callback' => 'select',
					'args'     => array( 'tabs_position' ),
				),
			),
			'tabs_position_mobile' => array(
				'type' => 'radio',
				'label' => __( 'Tabs mobile position', 'siteorigin-premium' ),
				'options' => array(
					'' => __( 'Default', 'siteorigin-premium' ),
					'left' => __( 'Above', 'siteorigin-premium' ),
					'right' => __( 'Below', 'siteorigin-premium' ),
				),
				'state_handler' => array(
					'tabs_position[left,right]' => array( 'show' ),
					'_else[tabs_position]'      => array( 'hide' ),
				),
			),
		) );
		$form_options['design']['fields']['tabs_container']['fields'] = $tabs_container_fields;

		$tabs_fields = $form_options['design']['fields']['tabs']['fields'];

		if ( array_key_exists( 'border_width', $tabs_fields ) ) {
			$tabs_fields['border_width']['type'] = 'multi-measurement';
			$tabs_fields['border_width']['autofill'] = true;
			$tabs_fields['border_width']['measurements'] = array(
				'top' => array(
					'label' => __( 'Top', 'siteorigin-premium' ),
					'units' => $border_width_units,
				),
				'right' => array(
					'label' => __( 'Right', 'siteorigin-premium' ),
					'units' => $border_width_units,
				),
				'bottom' => array(
					'label' => __( 'Bottom', 'siteorigin-premium' ),
					'units' => $border_width_units,
				),
				'left' => array(
					'label' => __( 'Left', 'siteorigin-premium' ),
					'units' => $border_width_units,
				),
			);
		}

		if ( array_key_exists( 'border_hover_width', $tabs_fields ) ) {
			$tabs_fields['border_hover_width']['type'] = 'multi-measurement';
			$tabs_fields['border_hover_width']['autofill'] = true;
			$tabs_fields['border_hover_width']['measurements'] = array(
				'top' => array(
					'label' => __( 'Top', 'siteorigin-premium' ),
					'units' => $border_width_units,
				),
				'right' => array(
					'label' => __( 'Right', 'siteorigin-premium' ),
					'units' => $border_width_units,
				),
				'bottom' => array(
					'label' => __( 'Bottom', 'siteorigin-premium' ),
					'units' => $border_width_units,
				),
				'left' => array(
					'label' => __( 'Left', 'siteorigin-premium' ),
					'units' => $border_width_units,
				),
			);
		}

		$position = array_key_exists( 'border_color', $tabs_fields ) ? 'border_color' : count( $tabs_fields );
		siteorigin_widgets_array_insert( $tabs_fields, $position, array(
			'icon_location' => array(
				'type' => 'radio',
				'label' => __( 'Icon location', 'siteorigin-premium' ),
				'options' => array(
					'left' => __( 'Left', 'siteorigin-premium' ),
					'right' => __( 'Right', 'siteorigin-premium' ),
				),
				'default' => 'left',
			),
			'font_family' => array(
				'type' => 'font',
				'label' => __( 'Font', 'siteorigin-premium' ),
			),
			'font_size' => array(
				'type' => 'measurement',
				'label' => __( 'Font size', 'siteorigin-premium' ),
				'default' => '16px',
			),
		) );

		$tabs_fields = array_merge( $tabs_fields, array(
			'border_radius' => array(
				'type' => 'multi-measurement',
				'label' => __( 'Border radius', 'siteorigin-premium' ),
				'autofill' => true,
				'measurements' => array(
					'top_left' => __( 'Top left', 'siteorigin-premium' ),
					'top_right' => __( 'Top right', 'siteorigin-premium' ),
					'bottom_right' => __( 'Bottom right', 'siteorigin-premium' ),
					'bottom_left' => __( 'Bottom left', 'siteorigin-premium' ),
				),
			),
			'padding' => array(
				'type' => 'multi-measurement',
				'label' => __( 'Padding', 'siteorigin-premium' ),
				'autofill' => true,
				'default' => '15px 15px 15px 15px',
				'measurements' => array(
					'top' => __( 'Top', 'siteorigin-premium' ),
					'right' => __( 'Right', 'siteorigin-premium' ),
					'bottom' => __( 'Bottom', 'siteorigin-premium' ),
					'left' => __( 'Left', 'siteorigin-premium' ),
				),
			),
			'margin' => array(
				'type' => 'multi-measurement',
				'label' => __( 'Margin', 'siteorigin-premium' ),
				'autofill' => true,
				'default' => '0px 5px 0px 5px',
				'measurements' => array(
					'top' => __( 'Top', 'siteorigin-premium' ),
					'right' => __( 'Right', 'siteorigin-premium' ),
					'bottom' => __( 'Bottom', 'siteorigin-premium' ),
					'left' => __( 'Left', 'siteorigin-premium' ),
				),
			),
		) );

		$form_options['design']['fields']['tabs']['fields'] = $tabs_fields;

		$panels_fields = $form_options['design']['fields']['panels']['fields'];

		if ( array_key_exists( 'border_width', $panels_fields ) ) {
			$panels_fields['border_width']['type'] = 'multi-measurement';
			$panels_fields['border_width']['autofill'] = true;
			$panels_fields['border_width']['measurements'] = array(
				'top' => array(
					'label' => __( 'Top', 'siteorigin-premium' ),
					'units' => $border_width_units,
				),
				'right' => array(
					'label' => __( 'Right', 'siteorigin-premium' ),
					'units' => $border_width_units,
				),
				'bottom' => array(
					'label' => __( 'Bottom', 'siteorigin-premium' ),
					'units' => $border_width_units,
				),
				'left' => array(
					'label' => __( 'Left', 'siteorigin-premium' ),
					'units' => $border_width_units,
				),
			);
		}

		$position = array_key_exists( 'font_color', $panels_fields ) ? 'font_color' : count( $panels_fields );
		siteorigin_widgets_array_insert( $panels_fields, $position, array(
			'font_family' => array(
				'type' => 'font',
				'label' => __( 'Font', 'siteorigin-premium' ),
			),
			'font_size' => array(
				'type' => 'measurement',
				'label' => __( 'Font size', 'siteorigin-premium' ),
			),
		) );

		$panels_fields = array_merge( $panels_fields, array(
			'border_radius' => array(
				'type' => 'multi-measurement',
				'label' => __( 'Border radius', 'siteorigin-premium' ),
				'autofill' => true,
				'measurements' => array(
					'top_left' => __( 'Top left', 'siteorigin-premium' ),
					'top_right' => __( 'Top right', 'siteorigin-premium' ),
					'bottom_right' => __( 'Bottom right', 'siteorigin-premium' ),
					'bottom_left' => __( 'Bottom left', 'siteorigin-premium' ),
				),
			),
			'padding' => array(
				'type' => 'multi-measurement',
				'label' => __( 'Padding', 'siteorigin-premium' ),
				'autofill' => true,
				'default' => '30px 30px 30px 35px',
				'measurements' => array(
					'top' => __( 'Top', 'siteorigin-premium' ),
					'right' => __( 'Right', 'siteorigin-premium' ),
					'bottom' => __( 'Bottom', 'siteorigin-premium' ),
					'left' => __( 'Left', 'siteorigin-premium' ),
				),
			),
		) );

		$form_options['design']['fields']['panels']['fields'] = $panels_fields;

		return $form_options;
	}

	/**
	 * Filters the new design related fields into the LESS variables used for the LESS stylesheet.
	 *
	 * @param $less_variables array LESS variable values to be used in the LESS stylesheet.
	 * @param $instance array The widget instance containing possible values to be used in the LESS stylesheet.
	 * @param $widget SiteOrigin_Widget_Tabs_Widget The widget object.
	 *
	 * @return mixed The updated LESS variables containing the new and modified variables.
	 */
	public function add_less_variables( $less_variables, $instance, $widget ) {
		if ( empty( $instance ) || empty( $instance['design'] ) ) {
			return $less_variables;
		}

		$tabs_container = $instance['design']['tabs_container'];

		if ( ! empty( $tabs_container['border_radius'] ) ) {
			$less_variables['tabs_container_border_radius'] = $tabs_container['border_radius'];
		}

		if ( ! empty( $tabs_container['padding'] ) ) {
			$less_variables['tabs_container_padding'] = $tabs_container['padding'];
		}

		if ( ! empty( $tabs_container['tabs_align'] ) ) {
			if ( ! empty( $tabs_container['tabs_align_horziontal'] ) && ! empty( $tabs_container['tabs_position'] ) &&
				( $tabs_container['tabs_position'] == 'left' || $tabs_container['tabs_position'] == 'right' ) ) {
				$less_variables['tabs_container_tabs_align'] = $tabs_container['tabs_align_horziontal'];
			} else {
				$less_variables['tabs_container_tabs_align'] = $tabs_container['tabs_align'];
			}
		}

		if ( ! empty( $tabs_container['tabs_position'] ) ) {
			$less_variables['tabs_container_tabs_position'] = $tabs_container['tabs_position'];

			if (
				! empty( $tabs_container['tabs_position_mobile'] ) &&
				(
					$tabs_container['tabs_position'] == 'left' ||
					$tabs_container['tabs_position'] == 'right'
				)
			) {
				$less_variables['tabs_container_tabs_position_mobile'] = $tabs_container['tabs_position_mobile'];
			}
		}

		$tabs = $instance['design']['tabs'];

		if ( ! empty( $tabs['font_family'] ) ) {
			$less_variables['tabs_font_family'] = $tabs['font_family'];

			$font = siteorigin_widget_get_font( $tabs['font_family'] );
			$less_variables['tabs_font_family'] = $font['family'];

			if ( ! empty( $font['weight'] ) ) {
				$less_variables['tabs_font_weight'] = $font['weight'];
			}
		}

		if ( ! empty( $tabs['font_size'] ) ) {
			$less_variables['tabs_font_size'] = $tabs['font_size'];
		}

		if ( ! empty( $tabs['border_radius'] ) ) {
			$less_variables['tabs_border_radius'] = $tabs['border_radius'];
		}

		if ( ! empty( $tabs['padding'] ) ) {
			$less_variables['tabs_padding'] = $tabs['padding'];
		}

		if ( ! empty( $tabs['margin'] ) ) {
			$less_variables['tabs_margin'] = $tabs['margin'];
		}

		$panels = $instance['design']['panels'];

		if ( ! empty( $panels['font_family'] ) ) {
			$less_variables['panels_font_family'] = $panels['font_family'];

			$font = siteorigin_widget_get_font( $panels['font_family'] );
			$less_variables['panels_font_family'] = $font['family'];

			if ( ! empty( $font['weight'] ) ) {
				$less_variables['panels_font_weight'] = $font['weight'];
			}
		}

		if ( ! empty( $panels['font_size'] ) ) {
			$less_variables['panels_font_size'] = $panels['font_size'];
		}

		if ( ! empty( $panels['border_radius'] ) ) {
			$less_variables['panels_border_radius'] = $panels['border_radius'];
		}

		if ( ! empty( $panels['padding'] ) ) {
			$less_variables['panels_padding'] = $panels['padding'];
		}

		if (
			! empty( $tabs_container['tabs_position_mobile'] ) &&
			(
				$tabs_container['tabs_position'] == 'left' ||
				$tabs_container['tabs_position'] == 'right'
			)
		) {
			$less_variables['tabs_position_mobile'] = $tabs_container['tabs_position_mobile'];
		}

		$less_variables['responsive_breakpoint'] = $widget->get_global_settings( 'responsive_breakpoint' );

		return $less_variables;
	}

	/**
	 * Filters the new fields into the template variables used when rendering the template.
	 *
	 * @param $template_variables array The template variables array.
	 * @param $instance array The widget instance.
	 * @param $args array The args used when rendering the template.
	 * @param $widget SiteOrigin_Widget_Tabs_Widget The widget object.
	 *
	 * @return mixed The updated template variables.
	 */
	public function add_template_variables( $template_variables, $instance, $args, $widget ) {
		$tabs_design = $instance['design']['tabs'];
		$position_key = null;

		if ( ! empty( $tabs_design['icon_location'] ) ) {
			$position_key = 'before_title';

			if ( $tabs_design['icon_location'] == 'right' ) {
				$position_key = 'after_title';
			}
		}

		foreach ( $template_variables['tabs'] as &$tab ) {
			if ( ! empty( $position_key ) ) {
				// Does this tab have an icon image?
				if (
					! empty( $tab['icon_image'] ) ||
					! empty( $tab['icon_image_fallback'] )
				) {
					$src = siteorigin_widgets_get_attachment_image_src(
						$tab['icon_image'],
						'thumbnail',
						$tab['icon_image_fallback']
					);

					if ( ! empty( $src ) ) {
						$tab[$position_key] = '<img src="' . esc_url( $src[0] ) . '" class="sow-tab-icon-image">';
					}
				}

				// Check if an image icon was successfully set. If it wasn't, set an icon.
				if ( empty( $tab[$position_key] ) && ! empty( $tab['icon'] ) ) {
					$tab[$position_key] = siteorigin_widget_get_icon( $tab['icon'] );
				}
			}
		}

		return $template_variables;
	}

	/**
	 * Filters the additional fields used for google fonts.
	 *
	 * @param $fields array Fields containing google font values.
	 * @param $instance array The widget instance.
	 * @param $widget SiteOrigin_Widget_Tabs_Widget The widget object.
	 *
	 * @return array The modified google font fields array.
	 */
	public function add_google_font_fields( $fields, $instance, $widget ) {
		if ( ! empty( $instance['design']['tabs']['font_family'] ) ) {
			$fields[] = $instance['design']['tabs']['font_family'];
		}

		if ( ! empty( $instance['design']['panels']['font_family'] ) ) {
			$fields[] = $instance['design']['panels']['font_family'];
		}

		return $fields;
	}

	/**
	 * Filters each tab panel's content. This is used to render Page Builder content when the user has opted to use the
	 * layout builder field for content. SiteOrigin Page Builder must be installed and active for this to work.
	 *
	 * @param $content string The content value to be filtered.
	 * @param $panel array The tab panel values.
	 * @param $instance array The widget instance.
	 *
	 * @return string The rendered Page Builder content.
	 */
	public function render_panel_content( $content, $panel, $instance ) {
		if ( ! empty( $panel['content_type'] ) && $panel['content_type'] === 'layout' ) {
			if ( function_exists( 'siteorigin_panels_render' ) ) {
				$content_builder_id = substr( md5( json_encode( $panel['content_layout'] ) ), 0, 8 );
				$content = siteorigin_panels_render( 'w' . $content_builder_id, true, $panel['content_layout'] );
			} else {
				$content = __( 'This field requires Page Builder.', 'siteorigin-premium' );
			}
		}

		return $content;
	}
}
