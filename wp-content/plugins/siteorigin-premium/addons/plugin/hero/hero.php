<?php
/*
Plugin Name: SiteOrigin Hero
Description: Unlock new levels of creativity in your Hero Widget with added functionality and style settings, enabling dynamic animations and personalized control.
Version: 1.0.0
Author: SiteOrigin
Author URI: https://siteorigin.com
License: GPL3
License URI: https://www.gnu.org/licenses/gpl-3.0.txt
Documentation: https://siteorigin.com/premium-documentation/plugin-addons/hero/
Tags: Widgets Bundle
Video: 314963133
Requires: so-widgets-bundle/hero
*/

class SiteOrigin_Premium_Plugin_Hero {
	private $hidden_used = false;

	public function __construct() {
		add_action( 'init', array( $this, 'init_addon' ) );
	}

	public static function single() {
		static $single;

		return empty( $single ) ? $single = new self() : $single;
	}
	public function init_addon() {
		$this->add_filters();
	}

	/**
	 * Adds required filters for this addon.
	 */
	public function add_filters() {
		if ( class_exists( 'SiteOrigin_Widget_Hero_Widget' ) ) {
			add_filter( 'siteorigin_widgets_form_options_sow-hero', array( $this, 'admin_form_options' ), 10, 2 );
			add_filter( 'siteorigin_hero_frame_content', array( $this, 'apply_frame_animation' ), 10, 2 );
			add_action( 'siteorigin_widgets_render_preview_sow-hero', array( $this, 'register_scripts_for_preview' ) );
			add_action( 'siteorigin_widgets_enqueue_frontend_scripts_sow-hero', array( $this, 'enqueue_animation_scripts' ), 10, 2 );

			add_action( 'wp_head', array( $this, 'add_hiding_class' ) );
			add_action( 'wp_footer', array( $this, 'add_hiding_class' ) );
		}
	}

	/**
	 * Add form fields required by this addon.
	 *
	 * @return mixed
	 */
	public function admin_form_options( $form_options, $widget ) {
		if ( empty( $form_options ) ) {
			return $form_options;
		}

		$frames_fields = $form_options['frames']['fields'];

		$position = array_key_exists( 'background', $frames_fields ) ? 'background' : count( $frames_fields );

		siteorigin_widgets_array_insert( $frames_fields, $position, array(
			'content_animation' => array(
				'type' => 'section',
				'label' => __( 'Content animation', 'siteorigin-premium' ),
				'hide' => true,
				'fields' => array(
					'type' => array(
						'type' => 'select',
						'label' => __( 'Animation', 'siteorigin-premium' ),
						'options' => include SiteOrigin_Premium::dir_path() . 'inc/animation-types.php',
					),
					'event' => array(
						'type' => 'select',
						'label' => __( 'Animation event', 'siteorigin-premium' ),
						'options' => array(
							'enter' => __( 'Element enters screen', 'siteorigin-premium' ),
							'in'    => __( 'Element in screen', 'siteorigin-premium' ),
							'load'  => __( 'Page load', 'siteorigin-premium' ),
							'hover' => __( 'On hover', 'siteorigin-premium' ),
							'slide_display' => __( 'Frame display', 'siteorigin-premium' ),
						),
						'default' => 'slide_display',
					),
					'screen_offset' => array(
						'type' => 'number',
						'label' => __( 'Screen offset', 'siteorigin-premium' ),
						'default' => 0,
						'description' => __( 'Distance, in pixels, the content must be above the bottom of the screen before animating in.', 'siteorigin-premium' ),
					),
					'duration' => array(
						'type' => 'number',
						'label' => __( 'Animation speed', 'siteorigin-premium' ),
						'default' => 1,
						'description' => __( 'Time, in seconds, that the incoming animation lasts.', 'siteorigin-premium' ),
					),
					'hide' => array(
						'label' => __( 'Hide before animation', 'siteorigin-premium' ),
						'type' => 'checkbox',
						'default' => true,
						'description' => __( 'Hide the element before animating.', 'siteorigin-premium' ),
					),
					'delay' => array(
						'type' => 'number',
						'label' => __( 'Animation delay', 'siteorigin-premium' ),
						'default' => 0,
						'description' => __( 'Time, in seconds, after the event to start the animation.', 'siteorigin-premium' ),
					),
				),
			),
		) );

		$form_options['frames']['fields'] = $frames_fields;

		return $form_options;
	}

	/**
	 * Filter function to output HTML, JS and CSS required for animating content.
	 *
	 * @return string
	 */
	public function apply_frame_animation( $content, $frame ) {
		$animation = $frame['content_animation'];
		$animation_wrapper = $content;

		if ( ! empty( $animation['type'] ) ) {
			$selector = preg_replace( '/\./', '', uniqid( 'animate-', true ) );
			$classes = array( $selector );

			if ( ! empty( $animation[ 'hide' ] ) ) {
				$this->hidden_used = true;
				$classes[] = 'so-premium-animation-hide';
			}

			$animation_wrapper = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-so-animation="' . esc_attr( json_encode( array(
				'animation' => $animation['type'],
				'selector' => '.' . $selector,
				'duration' => isset( $animation['duration'] ) ? floatval( $animation['duration'] ) : 1,
				'hide' => ! empty( $animation['hide'] ) ? 1 : 0,
				'delay' => isset( $animation['delay'] ) ? floatval( $animation['delay'] ) : 0,
				'event' => isset( $animation['event'] ) ? $animation['event'] : 'enter',
				'offset' => isset( $animation['screen_offset'] ) ? intval( $animation['screen_offset'] ) : 0,
			) ) ) . '"';

			$animation_wrapper .= '>' . $content . '</div>';

			$this->enqueue_animation_scripts();
		}

		return $animation_wrapper;
	}

	public function add_hiding_class() {
		static $once = false;

		if ( ! $once && $this->hidden_used ) {
			$once = true;
			?><script type="text/javascript">document.write('<style type="text/css">.so-premium-animation-hide{opacity:0}</style>');</script><?php
		}
	}

	public function register_scripts_for_preview( $widget ) {
		$so_premium = SiteOrigin_Premium::single();
		$so_premium->register_common_scripts();
	}

	public function enqueue_animation_scripts() {
		$this->register_scripts_for_preview( false );
		wp_enqueue_script( 'siteorigin-premium-animate' );
		wp_enqueue_style( 'siteorigin-premium-animate' );
	}
}
