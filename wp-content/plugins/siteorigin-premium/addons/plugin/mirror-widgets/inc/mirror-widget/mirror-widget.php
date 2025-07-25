<?php
/*
Widget Name: Mirror
Description: Outputs the Mirror Widget of your choosing.
Author: SiteOrigin
Author URI: https://siteorigin.com
Documentation: https://siteorigin.com/premium-documentation/plugin-addons/mirror-widgets/
*/

if ( ! class_exists( 'SiteOrigin_Widget' ) || ! class_exists( 'SiteOrigin_Panels' ) ) {
	return;
}

class SiteOrigin_Premium_Widget_Mirror_Widget extends SiteOrigin_Widget {
	public function __construct() {
		parent::__construct(
			'so-premium-mirror-widget',
			__( 'SiteOrigin Mirror Widget', 'siteorigin-premium' ),
			array(
				'description' => __( 'An instance of a mirror widget.', 'siteorigin-premium' ),
				'help' => 'https://siteorigin.com/premium-documentation/plugin-addons/mirror-widgets/',
			),
			array(),
			false,
			plugin_dir_path( __FILE__ )
		);
	}

	public function get_widget_form() {
		$mirror_widgets = SiteOrigin_Premium_Plugin_Mirror_Widgets::get_mirror_widget_names();

		return array(
			'mirror_widget' => array(
				'type' => 'select',
				'select2' => true,
				'options' => $mirror_widgets,
			),
		);
	}

	public function get_template_variables( $instance, $args ) {
		$rendered = SiteOrigin_Premium_Plugin_Mirror_Widgets::render_mirror_widget(
			$instance['mirror_widget'],
			! empty( $instance['is_preview'] )
		);

		return array(
			'rendered_widget' => $rendered,
		);
	}
}

siteorigin_widget_register( 'so-premium-mirror-widget', __FILE__, 'SiteOrigin_Premium_Widget_Mirror_Widget' );
