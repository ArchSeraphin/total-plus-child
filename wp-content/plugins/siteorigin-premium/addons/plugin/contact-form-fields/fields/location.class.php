<?php

class SiteOrigin_Widget_ContactForm_Field_Location extends SiteOrigin_Widget_ContactForm_Field_Base {
	protected function initialize( $options ) {
		wp_enqueue_style(
			'so-contactform-location',
			plugin_dir_url( __FILE__ ) . 'css/so-contactform-location.css'
		);
		wp_enqueue_script( 'sow-google-map' );

		$maps_widget = new SiteOrigin_Widget_GoogleMap_Widget();
		$global_settings = $maps_widget->get_global_settings();

		wp_localize_script(
			'sow-google-map',
			'soWidgetsGoogleMap',
			array(
				'map_consent'  => ! empty( $global_settings['map_consent'] ),
			)
		);
	}

	/**
	 * Handle custom attributes for fields with version compatibility.
	 *
	 * Checks if add_custom_attrs method exists and supports options
	 * parameter. Uses reflection to determine method signature for
	 * backwards compatibility.
	 *
	 * @param string $type The field type.
	 * @param array $options Optional. Custom attributes to add
	 */
	private function handle_custom_attrs( $type, $options ) {
		if ( ! method_exists( $this, 'add_custom_attrs' ) ) {
			return;
		}

		$ref = new ReflectionMethod( __CLASS__, 'add_custom_attrs' );
		if ( $ref->getNumberOfParameters() > 1 ) {
			echo self::add_custom_attrs( $type, $options );
		} else {
			echo self::add_custom_attrs( $type );
		}
	}

	protected function render_field( $options ) {
		$location_options = $options['field']['location_options'];
		$location = empty( $options['value'] ) ? $location_options['default_location'] : $options['value'];

		if ( is_array( $location ) ) {
			$location = empty( $location['address'] ) ? '' : $location['address'];
		}
		$maps_widget = new SiteOrigin_Widget_GoogleMap_Widget();
		$global_settings = $maps_widget->get_global_settings();
		$gmaps_key = $global_settings['api_key'];
		?>
		<input
			type="text"
			name="<?php echo esc_attr( SiteOrigin_Widgets_ContactForm_Widget::single()->name_from_label( $options['field_name'] ) ); ?>"
			id="<?php echo esc_attr( $options['field_id'] ); ?>" value="<?php echo esc_attr( $location ); ?>"
			class="sow-google-map-autocomplete sow-text-field"
			<?php $this->handle_custom_attrs( 'location', $options ); ?>
		/>
		<?php
		if ( ! empty( $location_options['show_map'] ) ) {
			$map_data = array(
				'address' => $location,
				'apiKey' => $gmaps_key,
				'libraries' => array( 'places' ),
				'zoom' => 10,
				'disableDefaultUI' => true,
				'zoomControl' => true,
				'panControl' => true,
				'markerAtCenter' => true,
				'markersDraggable' => true,
				'keepCentered' => true,
				'center_user_location' => ! empty( $options['field']['location_options']['center_user_location'] ),
				'id' => ! empty( $location_options['map_id'] ) ? $location_options['map_id'] : substr( uniqid(), 0, 6 ),
			);

			if ( ! empty( $global_settings['map_consent'] ) ) {
				if ( ! empty( $global_settings['map_consent_design']['background'] ) ) {
					$consent_background_image = siteorigin_widgets_get_attachment_image_src(
						$global_settings['map_consent_design']['background']['image'],
						'full',
						$global_settings['map_consent_design']['background']['image_fallback']
					);
				}

				if ( empty( $consent_background_image ) ) {
					$consent_background_image = plugin_dir_url( SOW_BUNDLE_BASE_FILE ) . 'widgets/google-map/assets/map-consent-background.jpg';
				} else {
					$consent_background_image = $consent_background_image[0];
				}
			}

			if ( ! empty( $global_settings['map_consent'] ) ) {
				?>
				<div class="sow-google-map-consent" style="<?php echo 'background-image: url(' . sow_esc_url( $consent_background_image ) . ')'; ?>">
					<div class="sow-google-map-consent-prompt">
						<div class="sow-google-map-consent-prompt-inner">
							<?php echo wp_kses_post( $global_settings['map_consent_notice'] ); ?>

							<button class="btn button"><?php echo esc_html( $global_settings['map_consent_btn_text'] ); ?></button>
						</div>
					</div>
				</div>
			<?php } ?>

			<div class="sow-google-map-canvas"
			     style="<?php echo ( $global_settings['map_consent'] ) ? 'display: none;' : ''; ?>"
			     id="map-canvas-<?php echo esc_attr( $options['field_id'] ); ?>"
			     data-options="<?php echo esc_attr( wp_json_encode( $map_data ) ); ?>">
			</div>
			<?php
		}
	}
}
