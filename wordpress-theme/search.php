<?php
/**
 * Resultados de busca.
 *
 * @package licia-fabio
 */

get_header();

global $wp_query;
$lf_total = (int) $wp_query->found_posts;
?>

<div class="lf-wrap lf-arch">

	<header class="lf-arch__head">
		<div class="lf-arch__kicker"><?php esc_html_e( 'Busca', 'licia-fabio' ); ?></div>
		<h1 class="lf-arch__title">
			<?php
			printf(
				/* translators: termo buscado */
				esc_html__( 'Resultados para “%s”', 'licia-fabio' ),
				esc_html( get_search_query() )
			);
			?>
		</h1>
		<div class="lf-arch__desc">
			<?php
			printf(
				/* translators: número de resultados */
				esc_html( _n( '%s notícia encontrada.', '%s notícias encontradas.', $lf_total, 'licia-fabio' ) ),
				esc_html( number_format_i18n( $lf_total ) )
			);
			?>
		</div>
		<div style="margin-top:20px;max-width:520px"><?php get_search_form(); ?></div>
	</header>

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
			<h2 class="lf-vazio__title"><?php esc_html_e( 'Nenhum resultado', 'licia-fabio' ); ?></h2>
			<p class="lf-vazio__text"><?php esc_html_e( 'Tente outras palavras, ou escolha uma editoria abaixo.', 'licia-fabio' ); ?></p>

			<div class="lf-vazio__cats">
				<?php
				foreach ( get_categories( array( 'number' => 6, 'orderby' => 'count', 'order' => 'DESC' ) ) as $lf_c ) :
					?>
					<a href="<?php echo esc_url( get_category_link( $lf_c->term_id ) ); ?>"><?php echo esc_html( $lf_c->name ); ?></a>
					<?php
				endforeach;
				?>
			</div>
		</div>

	<?php endif; ?>

</div>

<?php
get_footer();
