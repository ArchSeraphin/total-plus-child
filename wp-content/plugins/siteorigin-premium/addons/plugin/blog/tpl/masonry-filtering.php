<?php
if ( empty( $posts ) || ! $posts->have_posts() ) {
	wp_reset_postdata();
	return;
}

if ( ! empty( $instance['title'] ) ) {
	?>
	<div class="sow-blog-title">
		<?php
		echo wp_kses_post( $args['before_title'] . $instance['title'] . $args['after_title'] );
		?>
	</div>
	<?php
}

$this->override_read_more( $settings );
?>
<div
	class="sow-blog sow-blog-layout-masonry sow-masonry-filtering"
	data-template="<?php echo esc_attr( $instance['template'] ); ?>"
	data-settings="<?php echo esc_attr( json_encode( $settings ) ); ?>"
	data-paged="<?php echo esc_attr( $posts->query['paged'] ); ?>"
	data-paging-id="<?php echo esc_attr( $instance['paged_id'] ); ?>"
	data-total-pages="<?php echo esc_attr( $this->total_pages( $posts ) ); ?>"
	data-hash="<?php echo esc_attr( $storage_hash ); ?>"
>
	<?php
	do_action( 'siteorigin_widgets_blog_output_before', $settings );

	if (
		! empty( $template_settings['terms'] ) &&
		! is_wp_error( $template_settings['terms'] )
	) {
		?>
		<div class="sow-masonry-filter-terms" style="margin-bottom: 25px;">
			<button data-filter="*" class="active" style="background: none; margin-right: 34px; padding: 0 0 6px;">
				<?php esc_html_e( 'All', 'siteorigin-premium' ); ?>
			</button>
			<?php
			foreach ( $template_settings['terms'] as $tax_term ) {
				$slug = is_object( $tax_term ) ? $tax_term->slug : $tax_term;
				?>
				<button data-filter=".<?php echo esc_attr( $slug ); ?>" style="background: none; box-shadow: none; margin-right: 34px; padding: 0 0 6px;">
					<?php echo esc_html( $slug ); ?>
				</button>
			<?php } ?>
		</div>
	<?php } ?>

	<?php $template = SiteOrigin_Widget_Blog_Widget::get_template( $instance ); ?>
	<?php if ( ! empty( $template ) ) { ?>
		<div class="sow-blog-posts">
			<?php
			while ( $posts->have_posts() ) {
				$posts->the_post();
				include $template;
			}
			?>
		</div>
		<?php $this->paginate_links( $settings, $posts, $instance ); ?>
	<?php } ?>
	<?php do_action( 'siteorigin_widgets_blog_output_after', $settings ); ?>
</div>
<?php $this->override_read_more( $settings ); ?>

<?php wp_reset_postdata(); ?>
