<?php
/**
 * Template part for displaying posts.
 *
 * @package Total Plus
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <?php if ('post' == get_post_type()) : ?>
        <div class="entry-meta ht-post-info">
            <?php total_plus_posted_on(); ?>
        </div><!-- .entry-meta -->
    <?php endif; ?>


    <div class="ht-post-wrapper">
        <?php if (has_post_thumbnail()): ?>
            <figure class="entry-figure">
                <?php
                $total_plus_image = wp_get_attachment_image_src(get_post_thumbnail_id(), 'total-840x420');
                ?>
                <a href="<?php the_permalink(); ?>"><img src="<?php echo esc_url($total_plus_image[0]); ?>" alt="<?php echo esc_attr(get_the_title()) ?>"></a>
            </figure>
        <?php endif; ?>

        <header class="entry-header">
            <?php the_title(sprintf('<h1 class="entry-title"><a href="%s" rel="bookmark">', esc_url(get_permalink())), '</a></h1>'); ?>
        </header><!-- .entry-header -->

        <div class="entry-categories">
            <?php echo total_plus_entry_category(); ?>
        </div>

        <div class="entry-content">
            <?php
            /* translators: %s: Name of current post */
            the_content(sprintf(wp_kses(__('Continue reading %s <span class="meta-nav">&rarr;</span>', 'total-plus'), array('span' => array('class' => array()))), the_title('<span class="screen-reader-text">"', '"</span>', false)
            ));
            ?>

            <?php
            wp_link_pages(array(
                'before' => '<div class="page-links">' . esc_html__('Pages:', 'total-plus'),
                'after' => '</div>',
            ));
            ?>
        </div><!-- .entry-content -->
    </div>
</article><!-- #post-## -->
