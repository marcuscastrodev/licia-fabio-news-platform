<?php
/**
 * Coluna lateral.
 *
 * @package licia-fabio
 */

$lf_ret = is_singular() ? 'single_sidebar_rectangle' : 'home_sidebar_rectangle';
?>
<aside class="lf-sidebar">

	<?php // Half page é posição exclusiva da home. ?>
	<?php if ( ! is_singular() ) : ?>
		<?php lf_ad( 'home_half_page', 'halfpage' ); ?>
	<?php endif; ?>

	<?php
	$lf_lidas = lf_most_read_query( 5 );

	if ( $lf_lidas->have_posts() ) :
		?>
		<section class="lf-widget">
			<h2 class="lf-widget__title"><?php esc_html_e( 'Mais lidas', 'licia-fabio' ); ?></h2>
			<ol class="lf-lidas">
				<?php
				while ( $lf_lidas->have_posts() ) :
					$lf_lidas->the_post();
					?>
					<li>
						<div>
							<h3 class="lf-lidas__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						</div>
					</li>
					<?php
				endwhile;
				wp_reset_postdata();
				?>
			</ol>
		</section>
		<?php
	endif;
	?>

	<?php if ( is_active_sidebar( 'lf-sidebar' ) ) : ?>
		<?php dynamic_sidebar( 'lf-sidebar' ); ?>
	<?php endif; ?>

	<?php lf_ad( $lf_ret, 'retangulo' ); ?>

</aside>
