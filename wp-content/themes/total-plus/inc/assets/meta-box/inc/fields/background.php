<?php
/**
 * The background field.
 *
 * @package Meta Box
 */

/**
 * The Background field.
 */
class RWMB_Background_Field extends RWMB_Field {
	/**
	 * Enqueue scripts and styles.
	 */
	public static function admin_enqueue_scripts() {
		wp_enqueue_style( 'rwmb-background', RWMB_CSS_URL . 'background.css', '', RWMB_VER );

		RWMB_Color_Field::admin_enqueue_scripts();
		RWMB_File_Input_Field::admin_enqueue_scripts();
	}

	/**
	 * Get field HTML.
	 *
	 * @param mixed $meta  Meta value.
	 * @param array $field Field settings.
	 *
	 * @return string
	 */
	public static function html( $meta, $field ) {
		$meta = wp_parse_args(
			$meta,
			array(
				'color'      => '',
				'image'      => '',
				'repeat'     => '',
				'attachment' => '',
				'position'   => '',
				'size'       => '',
			)
		);

		$output = '<div class="rwmb-background-row">';

		// Color.
		$color   = RWMB_Color_Field::normalize(
			array(
				'type'       => 'color',
				'id'         => "{$field['id']}_color",
				'field_name' => "{$field['field_name']}[color]",
			)
		);
		$output .= RWMB_Color_Field::html( $meta['color'], $color );

		$output .= '</div><!-- .rwmb-background-row -->';
		$output .= '<div class="rwmb-background-row">';

		// Image.
		$image   = RWMB_File_Input_Field::normalize(
			array(
				'type'        => 'file_input',
				'id'          => "{$field['id']}_image",
				'field_name'  => "{$field['field_name']}[image]",
				'placeholder' => __( 'Background Image', 'total-plus' ),
			)
		);
		$output .= RWMB_File_Input_Field::html( $meta['image'], $image );

		$output .= '</div><!-- .rwmb-background-row -->';
		$output .= '<div class="rwmb-background-row">';

		// Repeat.
		$repeat  = RWMB_Select_Field::normalize(
			array(
				'type'        => 'select',
				'id'          => "{$field['id']}_repeat",
				'field_name'  => "{$field['field_name']}[repeat]",
				'placeholder' => esc_html__( '-- Background Repeat --', 'total-plus' ),
				'options'     => array(
					'no-repeat' => esc_html__( 'No Repeat', 'total-plus' ),
					'repeat'    => esc_html__( 'Repeat All', 'total-plus' ),
					'repeat-x'  => esc_html__( 'Repeat Horizontally', 'total-plus' ),
					'repeat-y'  => esc_html__( 'Repeat Vertically', 'total-plus' ),
					'inherit'   => esc_html__( 'Inherit', 'total-plus' ),
				),
			)
		);
		$output .= RWMB_Select_Field::html( $meta['repeat'], $repeat );

		// Position.
		$position = RWMB_Select_Field::normalize(
			array(
				'type'        => 'select',
				'id'          => "{$field['id']}_position",
				'field_name'  => "{$field['field_name']}[position]",
				'placeholder' => esc_html__( '-- Background Position --', 'total-plus' ),
				'options'     => array(
					'top left'      => esc_html__( 'Top Left', 'total-plus' ),
					'top center'    => esc_html__( 'Top Center', 'total-plus' ),
					'top right'     => esc_html__( 'Top Right', 'total-plus' ),
					'center left'   => esc_html__( 'Center Left', 'total-plus' ),
					'center center' => esc_html__( 'Center Center', 'total-plus' ),
					'center right'  => esc_html__( 'Center Right', 'total-plus' ),
					'bottom left'   => esc_html__( 'Bottom Left', 'total-plus' ),
					'bottom center' => esc_html__( 'Bottom Center', 'total-plus' ),
					'bottom right'  => esc_html__( 'Bottom Right', 'total-plus' ),
				),
			)
		);
		$output  .= RWMB_Select_Field::html( $meta['position'], $position );

		// Attachment.
		$attachment = RWMB_Select_Field::normalize(
			array(
				'type'        => 'select',
				'id'          => "{$field['id']}_attachment",
				'field_name'  => "{$field['field_name']}[attachment]",
				'placeholder' => esc_html__( '-- Background Attachment --', 'total-plus' ),
				'options'     => array(
					'fixed'   => esc_html__( 'Fixed', 'total-plus' ),
					'scroll'  => esc_html__( 'Scroll', 'total-plus' ),
					'inherit' => esc_html__( 'Inherit', 'total-plus' ),
				),
			)
		);
		$output    .= RWMB_Select_Field::html( $meta['attachment'], $attachment );

		// Size.
		$size    = RWMB_Select_Field::normalize(
			array(
				'type'        => 'select',
				'id'          => "{$field['id']}_size",
				'field_name'  => "{$field['field_name']}[size]",
				'placeholder' => esc_html__( '-- Background Size --', 'total-plus' ),
				'options'     => array(
					'inherit' => esc_html__( 'Inherit', 'total-plus' ),
					'cover'   => esc_html__( 'Cover', 'total-plus' ),
					'contain' => esc_html__( 'Contain', 'total-plus' ),
				),
			)
		);
		$output .= RWMB_Select_Field::html( $meta['size'], $size );
		$output .= '</div><!-- .rwmb-background-row -->';

		return $output;
	}

	/**
	 * Format a single value for the helper functions. Sub-fields should overwrite this method if necessary.
	 *
	 * @param array    $field   Field parameters.
	 * @param array    $value   The value.
	 * @param array    $args    Additional arguments. Rarely used. See specific fields for details.
	 * @param int|null $post_id Post ID. null for current post. Optional.
	 *
	 * @return string
	 */
	public static function format_single_value( $field, $value, $args, $post_id ) {
		if ( empty( $value ) ) {
			return '';
		}
		$output = '';
		$value  = array_filter( $value );
		foreach ( $value as $key => $subvalue ) {
			$subvalue = 'image' === $key ? 'url(' . esc_url( $subvalue ) . ')' : $subvalue;
			$output  .= 'background-' . $key . ': ' . $subvalue . ';';
		}
		return $output;
	}
}
