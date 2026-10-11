<?php
/**
 * Template de reserva, exigido pelo WordPress.
 *
 * O portal usa front-page.php, category.php, archive.php, single.php e
 * search.php. Este arquivo atende os casos que nenhum dos outros cobre.
 *
 * @package licia-fabio
 */

get_header();
?>

<div class="lf-wrap lf-arch">

	<?php if ( have_posts() ) : ?>

		<div class="lf-grid lf-grid--3">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/card-noticia', null, array( 'variante' => 'sm' ) );
			endwhile;
			?>
		</div>

		<?php lf_pagination(); ?>

	<?php else : ?>

		<div class="lf-vazio">
			<h2 class="lf-vazio__title"><?php esc_html_e( 'Nada publicado ainda', 'licia-fabio' ); ?></h2>
			<?php get_search_form(); ?>
		</div>

	<?php endif; ?>

</div>

<?php
get_footer();
