<?php
/**
 * Manchete principal.
 *
 * @package licia-fabio
 */

$lf_cat = lf_primary_category();
$lf_sub = lf_subtitle();
?>
<a class="lf-hero__main" href="<?php the_permalink(); ?>">

	<?php if ( has_post_thumbnail() ) : ?>
		<?php
		the_post_thumbnail(
			'lf-destaque',
			array(
				'class'         => 'lf-hero__img',
				'alt'           => the_title_attribute( array( 'echo' => false ) ),
				'fetchpriority' => 'high',
				'loading'       => 'eager',
				'decoding'      => 'sync',
			)
		);
		?>
	<?php endif; ?>

	<span class="lf-hero__veil"></span>

	<div class="lf-hero__body">
		<?php if ( $lf_cat ) : ?>
			<span class="lf-hero__cat"><?php echo esc_html( $lf_cat->name ); ?></span>
		<?php endif; ?>

		<h2 class="lf-hero__title"><?php the_title(); ?></h2>

		<?php if ( $lf_sub ) : ?>
			<p class="lf-hero__lede"><?php echo esc_html( $lf_sub ); ?></p>
		<?php endif; ?>

		<div class="lf-hero__meta">
			<?php
			printf(
				/* translators: 1: autor, 2: tempo desde a publicação */
				esc_html__( 'Por %1$s · há %2$s', 'licia-fabio' ),
				esc_html( get_the_author() ),
				esc_html( lf_time_ago() )
			);
			?>
		</div>
	</div>
</a>
