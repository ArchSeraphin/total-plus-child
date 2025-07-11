<?php

class SiteOrigin_Widget_Field_TinyMCE extends SiteOrigin_Widget_Field_Text_Input_Base {
	/**
	 * The number of visible rows in the textarea.
	 *
	 * @var int
	 */
	protected $rows = 10;

	/**
	 * The editor initial height. Overrides rows if it is set.
	 *
	 * @var int
	 */
	protected $editor_height;

	/**
	 * The editor to be displayed initially.
	 *
	 * @var string
	 */
	protected $default_editor = 'tinymce';

	/**
	 * The last editor selected by the user.
	 *
	 * @var string
	 */
	protected $selected_editor;

	/**
	 * An array of the buttons which will be rendered for the first toolbar of the TinyMCE editor.
	 *
	 * @var array
	 */
	protected $mce_buttons;

	/**
	 * An array of the buttons which will be rendered for the second toolbar of the TinyMCE editor.
	 *
	 * @var array
	 */
	protected $mce_buttons_2;

	/**
	 * An array of the buttons which will be rendered for the third toolbar of the TinyMCE editor.
	 *
	 * @var array
	 */
	protected $mce_buttons_3;

	/**
	 * An array of the buttons which will be rendered for the fourth toolbar of the TinyMCE editor.
	 *
	 * @var array
	 */
	protected $mce_buttons_4;

	/**
	 * An array of the buttons which will be rendered for the QuickTags editor.
	 *
	 * @var array
	 */
	protected $quicktags_buttons;

	/**
	 * Whether to apply `wpautop` processing. (Adds paragraphs for double linebreaks) Default is true.
	 *
	 * @var array
	 */
	protected $wpautop;

	/**
	 * An array of filter callbacks to apply to the set of buttons which will be rendered for the editor.
	 *
	 * @var array
	 */
	protected $button_filters;

	/**
	 * An array of the included plugins to enable for the TinyMCE editor.
	 *
	 * @var array
	 */
	protected $mce_plugins;

	/**
	 * An array of external plugins for the TinyMCE editor.
	 *
	 * @var array
	 */
	protected $mce_external_plugins;

	/**
	 * Updated Editor JS API was introduced in WP 4.8 so need to check for compatibility with older versions.
	 *
	 * @var bool
	 */
	private $wp_version_lt_4_8;

	/**
	 * Whether to add the Add Media button.
	 *
	 * @var bool
	 */
	protected $media_buttons = true;

	protected function get_default_options() {
		return array(
			'wpautop' => true,
			'mce_buttons' => array(
				'formatselect',
				'bold',
				'italic',
				'bullist',
				'numlist',
				'blockquote',
				'alignleft',
				'aligncenter',
				'alignright',
				'link',
				'unlink',
				'wp_more',
				'wp_adv',
			),
			'mce_buttons_2' => array(
				'strikethrough',
				'hr',
				'forecolor',
				'pastetext',
				'removeformat',
				'charmap',
				'outdent',
				'indent',
				'undo',
				'redo',
				'wp_help',
			),
			'mce_buttons_3' => array(),
			'mce_buttons_4' => array(),
			'quicktags_buttons' => array(
				'strong',
				'em',
				'link',
				'block',
				'del',
				'ins',
				'img',
				'ul',
				'ol',
				'li',
				'code',
				'more',
				'close',
			),
			'mce_plugins' => array(
				'charmap',
				'colorpicker',
				'hr',
				'lists',
				'media',
				'paste',
				'tabfocus',
				'textcolor',
				'fullscreen',
				'wordpress',
				'wpautoresize',
				'wpeditimage',
				'wpemoji',
				'wpgallery',
				'wplink',
				'wpdialogs',
				'wptextpattern',
				'wpview',
			),
			'mce_external_plugins' => array(),
		);
	}

	protected function initialize() {
		if ( ! is_admin() ) {
			return;
		}

		global $wp_version;
		$this->wp_version_lt_4_8 = version_compare( $wp_version, '4.8', '<' );

		if ( ! empty( $this->wp_version_lt_4_8 ) ) {
			add_filter( 'mce_buttons', array( $this, 'mce_buttons_filter' ), 10, 2 );
			add_filter( 'quicktags_settings', array( $this, 'quicktags_settings' ), 10, 2 );
		}

		if ( ! empty( $this->button_filters ) ) {
			foreach ( $this->button_filters as $filter_name => $filter ) {
				$is_valid_filter = preg_match(
					'/mce_buttons(?:_[1-4])?|quicktags_settings/',
					$filter_name
				) && ! empty( $filter ) && is_callable( $filter );

				if ( $is_valid_filter ) {
					add_filter( $filter_name, array( $this, $filter_name ), 10, 2 );
				}
			}
		}

		if ( class_exists( 'WC_Shortcodes_TinyMCE_Buttons' ) ) {
			if ( ! empty( $this->wp_version_lt_4_8 ) ) {
				$screen = get_current_screen();

				if ( ! is_null( $screen ) && $screen->id != 'widgets' ) {
					add_filter( 'mce_external_plugins', array( $this, 'add_wpc_shortcodes_plugin' ), 15 );
					add_filter( 'mce_buttons', array( $this, 'register_wpc_shortcodes_button' ), 15 );
				}
			} else {
				$this->mce_external_plugins = $this->add_wpc_shortcodes_plugin( $this->mce_external_plugins );
				$this->mce_buttons = $this->register_wpc_shortcodes_button( $this->mce_buttons );
			}
		}

		if ( class_exists( 'WC_Shortcodes_Admin' ) ) {
			if ( ! empty( $this->wp_version_lt_4_8 ) ) {
				$screen = get_current_screen();

				if ( ! is_null( $screen ) && $screen->id != 'widgets' ) {
					add_filter( 'mce_external_plugins', array( $this, 'add_wc_shortcodes_plugin' ), 15 );
					add_filter( 'mce_buttons', array( $this, 'register_wc_shortcodes_button' ), 15 );
				}
			} else {
				$this->mce_external_plugins = $this->add_wc_shortcodes_plugin( $this->mce_external_plugins );
				$this->mce_buttons = $this->register_wc_shortcodes_button( $this->mce_buttons );
			}
		}
	}

	public function add_wc_shortcodes_plugin( $plugins ) {
		if ( isset( $plugins['woocommerce_shortcodes'] ) ) {
			return $plugins;
		}
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$editor_path = 'woocommerce-shortcodes/assets/js/editor' . $suffix . '.js';

		if ( file_exists( WP_PLUGIN_DIR . '/' . $editor_path ) ) {
			$plugins['woocommerce_shortcodes'] = plugins_url( $editor_path );
		}

		return $plugins;
	}

	public function register_wc_shortcodes_button( $buttons ) {
		if ( ! in_array( 'woocommerce_shortcodes', $buttons ) ) {
			array_push( $buttons, '|', 'woocommerce_shortcodes' );
		}

		return $buttons;
	}

	public function add_wpc_shortcodes_plugin( $plugins ) {
		if ( ! isset( $plugins['wpc_shortcodes'] ) ) {
			$shortcodes_path = 'wc-shortcodes/includes/mce/js/shortcodes-tinymce-4.js';

			if ( file_exists( WP_PLUGIN_DIR . '/' . $shortcodes_path ) ) {
				$plugins['wpc_shortcodes'] = plugins_url( $shortcodes_path . '?ver=' . WC_SHORTCODES_VERSION );
			}
		}

		if ( ! isset( $plugins['wpc_font_awesome'] ) ) {
			$fontawesome_path = 'wc-shortcodes/includes/mce/js/font-awesome-tinymce-4.js';

			if ( file_exists( WP_PLUGIN_DIR . '/' . $fontawesome_path ) ) {
				$plugins['wpc_font_awesome'] = plugins_url( $fontawesome_path . '?ver=' . WC_SHORTCODES_VERSION );
			}
		}

		return $plugins;
	}

	public function register_wpc_shortcodes_button( $buttons ) {
		if ( ! in_array( 'wpc_shortcodes_button', $buttons ) ) {
			array_push( $buttons, 'wpc_shortcodes_button' );
		}

		if ( ! in_array( 'wpcfontAwesomeGlyphSelect', $buttons ) ) {
			array_push( $buttons, 'wpcfontAwesomeGlyphSelect' );
		}

		return $buttons;
	}

	/**
	 * @return array|mixed
	 */
	public function __call( $name, $arguments ) {
		if ( preg_match( '/mce_buttons(?:_[1-4])?|quicktags_settings/', $name ) && ! empty( $this->button_filters[$name] ) ) {
			$filter = $this->button_filters[$name];

			if ( ! empty( $filter[0] ) && is_a( $filter[0], 'SiteOrigin_Widget' ) ) {
				$widget = $filter[0];
				$settings = ! empty( $arguments[0] ) ? $arguments[0] : array();
				$editor_id = ! empty( $arguments[1] ) ? $arguments[1] : '';

				if ( preg_match( '/widget-' . $widget->id_base . '-.*-' . $this->base_name . '/', $editor_id ) ) {
					return call_user_func( $filter, $settings, $editor_id );
				} else {
					return $settings;
				}
			}
		}
	}

	public function mce_buttons_filter( $buttons, $editor_id ) {
		if ( ( $key = array_search( 'fullscreen', $buttons ) ) !== false ) {
			unset( $buttons[$key] );
		}

		return $buttons;
	}

	public function quicktags_settings( $settings, $editor_id ) {
		$settings['buttons'] = preg_replace( '/,fullscreen/', '', $settings['buttons'] );
		$settings['buttons'] = preg_replace( '/,dfw/', '', $settings['buttons'] );

		return $settings;
	}

	protected function get_input_classes() {
		$classes = parent::get_input_classes();
		$classes[] = 'wp-editor-area';
		$classes[] = 'wp-exclude-emoji';

		return $classes;
	}

	protected function render_before_field( $value, $instance ) {
		$selected_editor = $this->get_selected_editor( $instance );

		if ( ! empty( $selected_editor ) ) {
			$this->selected_editor = $selected_editor;
		} else {
			$this->selected_editor = $this->default_editor;
		}
		parent::render_before_field( $value, $instance );
	}

	protected function render_field( $value, $instance ) {
		if ( $this->wp_version_lt_4_8 ) {
			$this->render_field_pre48( $value, $instance );

			return;
		}

		$user_can_richedit = user_can_richedit();
		$selected_editor = $user_can_richedit && in_array( $this->selected_editor, array( 'tinymce', 'tmce' ) ) ? 'tmce' : 'html';

		$settings = array(
			'selectedEditor' => $selected_editor,
			'media_buttons' => $this->media_buttons,
		);

		if ( $user_can_richedit ) {
			$tiny_mce_plugins = apply_filters( 'tiny_mce_plugins', $this->mce_plugins );

			$tmce_settings = array(
				'toolbar1' => apply_filters( 'mce_buttons', $this->mce_buttons, $this->element_id ),
				'toolbar2' => apply_filters( 'mce_buttons_2', $this->mce_buttons_2, $this->element_id ),
				'toolbar3' => apply_filters( 'mce_buttons_3', $this->mce_buttons_3, $this->element_id ),
				'toolbar4' => apply_filters( 'mce_buttons_4', $this->mce_buttons_4, $this->element_id ),
				'plugins' => ! empty( $tiny_mce_plugins ) && is_array( $tiny_mce_plugins ) ? array_unique( $tiny_mce_plugins ) : array(),
			);

			foreach ( $tmce_settings as $name => $setting ) {
				$tmce_settings[ $name ] = is_array( $setting ) ? implode( ',', $setting ) : '';
			}

			$mce_external_plugins = apply_filters( 'mce_external_plugins', $this->mce_external_plugins, $this->element_id );
			$tmce_settings['external_plugins'] = ! empty( $mce_external_plugins ) && is_array( $mce_external_plugins ) ? array_unique( $mce_external_plugins ) : array();

			$suffix = SCRIPT_DEBUG ? '' : '.min';
			$version = 'ver=' . get_bloginfo( 'version' );
			// Default stylesheets
			$mce_css = includes_url( "css/dashicons$suffix.css?$version" ) . ',' .
											includes_url( "js/tinymce/skins/wordpress/wp-content.css?$version" );

			$editor_styles = get_editor_stylesheets();

			if ( ! empty( $editor_styles ) ) {
				// Force urlencoding of commas.
				foreach ( $editor_styles as $key => $url ) {
					if ( strpos( $url, ',' ) !== false ) {
						$editor_styles[ $key ] = str_replace( ',', '%2C', $url );
					}
				}

				$mce_css .= ',' . implode( ',', $editor_styles );
			}
			$mce_css = trim( apply_filters( 'mce_css', $mce_css ), ' ,' );
			$tmce_settings['content_css'] = $mce_css;

			$settings['tinymce'] = array(
				'wp_skip_init' => strpos( $this->element_id, '__i__' ) != false ||
					strpos( $this->element_id, '_id_' ) != false,
				'wpautop' => ! empty( $this->wpautop ),
			);

			$tmce_settings = apply_filters( 'tiny_mce_before_init', $tmce_settings, $this->element_id );

			foreach ( $tmce_settings as $name => $setting ) {
				unset( $jdec );

				if ( ! empty( $tmce_settings[ $name ] ) ) {
					// Attempt to decode setting as JSON. For back compat with filters used by WP editor.
					if ( is_string( $setting ) ) {
						$jdec = json_decode( $setting, true );
					}
					$settings['tinymce'][ $name ] = empty( $jdec ) ? $setting : $jdec;
				}
			}

			if ( $this->media_buttons ) {
				$media_buttons_html = $this->render_media_buttons( $this->element_id );
			}
		}

		$qt_settings = apply_filters(
			'quicktags_settings',
			array( 'buttons' => $this->quicktags_buttons ),
			$this->element_id
		);

		$qt_settings['buttons'] = ! empty( $qt_settings['buttons'] ) ? $qt_settings['buttons'] : array();
		$qt_settings['buttons'] = is_array( $qt_settings['buttons'] ) ? implode( ',', $qt_settings['buttons'] ) : '';

		$settings['quicktags'] = array(
			'buttons' => $qt_settings['buttons'],
		);

		$value = apply_filters( 'the_editor_content', $value, $this->selected_editor );

		if ( ! empty( $value ) && stripos( $value, 'textarea' ) !== false ) {
			$value = preg_replace( '%</textarea%i', '&lt;/textarea', $value );
		}

		if ( $this->media_buttons ) {
			$media_buttons_html = $this->render_media_buttons( $this->element_id );
		}

		$settings['baseURL'] = includes_url( 'js/tinymce' );
		$settings['suffix'] = SCRIPT_DEBUG ? '' : '.min';

		?>
		<div
			class="siteorigin-widget-tinymce-container"
			<?php if ( $this->media_buttons && ! empty( $media_buttons_html ) ) { ?>
				data-media-buttons="<?php echo esc_attr( json_encode( array( 'html' => $media_buttons_html ) ) ); ?>"
			<?php } ?>
			data-editor-settings="<?php echo esc_attr( json_encode( $settings ) ); ?>"
		>
		<textarea
			id="<?php echo esc_attr( $this->element_id ); ?>"
			name="<?php echo esc_attr( $this->element_name ); ?>"
			<?php if ( isset( $this->editor_height ) ) { ?>
				style="height: <?php echo (int) $this->editor_height; ?>px"
			<?php } else { ?>
				rows="<?php echo esc_attr( $this->rows ); ?>"
			<?php } ?>
			<?php $this->render_data_attributes( $this->get_input_data_attributes() ); ?>
			<?php $this->render_CSS_classes( $this->get_input_classes() ); ?>
			<?php if ( ! empty( $this->placeholder ) ) {
				echo 'placeholder="' . esc_attr( $this->placeholder ) . '"';
			} ?>
			<?php if ( ! empty( $this->readonly ) ) {
				echo 'readonly';
			} ?>><?php echo ! empty( $value ) ? esc_textarea( $value ) : ''; ?></textarea>
		</div>
		<input
			type="hidden"
			name="<?php echo esc_attr( $this->for_widget->so_get_field_name( $this->base_name . '_selected_editor', $this->parent_container ) ); ?>"
			class="siteorigin-widget-input siteorigin-widget-tinymce-selected-editor"
			value="<?php echo esc_attr( $this->selected_editor ); ?>"
		/>
		<?php

	}

	private function render_field_pre48( $value, $instance ) {
		$settings = array(
			'textarea_name' => esc_attr( $this->element_name ),
			'default_editor' => $this->selected_editor,
			'textarea_rows' => $this->rows,
			'editor_class' => 'siteorigin-widget-input',
			'media_buttons' => $this->media_buttons,
			'tinymce' => array(
				'wp_skip_init' => strpos( $this->element_id, '__i__' ) != false || strpos( $this->element_id, '_id_' ) != false,
			),
		);

		if ( isset( $this->editor_height ) ) {
			$settings['editor_height'] = $this->editor_height;
		}
		preg_match( '/widget-(.+?)\[/', $this->element_name, $id_base_matches );
		$widget_id_base = empty( $id_base_matches ) || count( $id_base_matches ) < 2 ? '' : $id_base_matches[1];
		?>
		<div
			class="siteorigin-widget-tinymce-container"
			data-mce-settings="<?php echo esc_attr( json_encode( $settings['tinymce'] ) ); ?>"
			data-qt-settings="<?php echo esc_attr( json_encode( array() ) ); ?>"
			data-widget-id-base="<?php echo esc_attr( $widget_id_base ); ?>"
		>
			<?php
			wp_editor( $value, esc_attr( $this->element_id ), $settings );
		?>
		</div>
		<input
			type="hidden"
			name="<?php echo esc_attr( $this->for_widget->so_get_field_name( $this->base_name . '_selected_editor', $this->parent_container ) ); ?>"
			class="siteorigin-widget-input siteorigin-widget-tinymce-selected-editor"
			value="<?php echo esc_attr( $this->selected_editor ); ?>"
		/>
		<?php

		if ( $this->selected_editor == 'html' ) {
			remove_filter( 'the_editor_content', 'wp_htmledit_pre' );
		}

		if ( $this->selected_editor == 'tinymce' ) {
			remove_filter( 'the_editor_content', 'wp_richedit_pre' );
		}
	}

	public function enqueue_scripts() {
		if ( $this->wp_version_lt_4_8 ) {
			$src = plugin_dir_url( __FILE__ ) . 'js/tinymce-field-pre48' . SOW_BUNDLE_JS_SUFFIX . '.js';
			$deps = array( 'jquery', 'editor', 'quicktags' );
		} else {
			wp_enqueue_editor();
			$src = plugin_dir_url( __FILE__ ) . 'js/tinymce-field' . SOW_BUNDLE_JS_SUFFIX . '.js';
			$deps = array( 'jquery' );

			// Ensure the editor buttons CSS has loaded.
			// This is required for the new widget area, but in general, we want to ensure it's loaded.
			wp_enqueue_style( 'editor-buttons' );
		}

		wp_enqueue_script( 'so-tinymce-field', $src, $deps, SOW_BUNDLE_VERSION );
		wp_enqueue_style(
			'so-tinymce-field',
			plugin_dir_url( __FILE__ ) . 'css/tinymce-field.css',
			array(),
			SOW_BUNDLE_VERSION
		);
	}

	protected function sanitize_field_input( $value, $instance ) {
		$selected_editor = $this->get_selected_editor( $instance );

		if ( in_array( $selected_editor, array( 'tinymce', 'tmce' ) ) && ! empty( $this->wpautop ) ) {
			$value = wpautop( $value );
		}

		if ( current_user_can( 'unfiltered_html' ) ) {
			$sanitized_value = $value;
		} else {
			$sanitized_value = wp_kses_post( $value );
		}
		$sanitized_value = balanceTags( $sanitized_value, true );

		return $sanitized_value;
	}

	public function sanitize_instance( $instance ) {
		$selected_editor_name = $this->get_selected_editor_field_name( $this->base_name );

		if ( ! empty( $instance[ $selected_editor_name ] ) ) {
			$selected_editor = $instance[ $selected_editor_name ];
			$instance[ $selected_editor_name ] = in_array( $selected_editor, array( 'tinymce', 'tmce', 'quicktags', 'html' ) ) ? $selected_editor : $this->default_editor;
		}

		return $instance;
	}

	public function get_selected_editor( $instance ) {
		$selected_editor = null;
		$selected_editor_name = $this->get_selected_editor_field_name( $this->base_name );

		if ( ! empty( $instance[ $selected_editor_name ] ) ) {
			$selected_editor = $instance[ $selected_editor_name ];
		}

		return $selected_editor;
	}

	public function get_selected_editor_field_name( $base_name ) {
		$v_name = $base_name;

		if ( strpos( $v_name, '][' ) !== false ) {
			// Remove this splitter
			$v_name = substr( $v_name, strrpos( $v_name, '][' ) + 2 );
		}

		return $v_name . '_selected_editor';
	}

	private function render_media_buttons( $editor_id ) {
		ob_start();

		if ( ! function_exists( 'media_buttons' ) ) {
			include ABSPATH . 'wp-admin/includes/media.php';
		}

		echo '<div id="wp-' . esc_attr( $editor_id ) . '-media-buttons" class="wp-media-buttons">';

		do_action( 'media_buttons', $editor_id );

		echo "</div>\n";

		return ob_get_clean();
	}
}
