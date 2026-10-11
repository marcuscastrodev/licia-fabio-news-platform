<?php
/**
 * Listagem de editoria.
 *
 * @package licia-fabio
 */

get_header();
?>

<div class="lf-wrap lf-arch">

	<header class="lf-arch__head">
		<div class="lf-arch__kicker"><?php esc_html_e( 'Editoria', 'licia-fabio' ); ?></div>
		<h1 class="lf-arch__title"><?php single_cat_title(); ?></h1>
		<?php $lf_desc = category_description(); ?>
		<?php if ( $lf_desc ) : ?>
			<div class="lf-arch__desc"><?php echo wp_kses_post( $lf_desc ); ?></div>
		<?php endif; ?>
	</header>

	<?php if ( have_posts() ) : ?>

		<?php
		/*
		 * Na primeira página, as quatro primeiras notícias formam o destaque da
		 * editoria. Nas páginas seguintes, tudo entra na grade.
		 */
		$lf_pagina  = max( 1, (int) get_query_var( 'paged' ) );
		$lf_destaca = ( 1 === $lf_pagina );
		?>

		<?php if ( $lf_destaca ) : ?>
			<div class="lf-hero" style="margin-bottom:40px">
				<?php
				the_post();
				get_template_part( 'template-parts/card-destaque' );
				?>

				<div class="lf-hero__side">
					<?php
					$lf_lado = 0;

					while ( have_posts() && $lf_lado < 3 ) :
						the_post();

						if ( $lf_lado > 0 ) {
							echo '<div class="lf-hero__divider"></div>';
						}

						get_template_part( 'template-parts/card-noticia', null, array( 'variante' => 0 === $lf_lado ? 'padrao' : 'row' ) );
						$lf_lado++;
					endwhile;
					?>
				</div>
			</div>

			<?php get_template_part( 'template-parts/bloco-anuncio', null, array( 'posicao' => 'category_between_posts', 'variante' => 'billboard' ) ); ?>
		<?php endif; ?>

		<div class="lf-grid lf-grid--3" style="margin-top:34px">
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
			<h2 class="lf-vazio__title"><?php esc_html_e( 'Nada publicado nesta editoria ainda', 'licia-fabio' ); ?></h2>
			<p class="lf-vazio__text"><?php esc_html_e( 'Assim que a primeira notícia sair, ela aparece aqui.', 'licia-fabio' ); ?></p>
			<?php get_search_form(); ?>
		</div>

	<?php endif; ?>

</div>

<?php
get_footer();
