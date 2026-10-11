<?php
/**
 * Página não encontrada.
 *
 * Mantém o visitante no site em vez de deixá-lo sem caminho. As URLs antigas
 * não são alteradas por este tema: um endereço que funcionava continua
 * funcionando.
 *
 * @package licia-fabio
 */

get_header();
?>

<div class="lf-wrap">
	<div class="lf-vazio">
		<div class="lf-arch__kicker" style="text-align:center"><?php esc_html_e( 'Erro 404', 'licia-fabio' ); ?></div>
		<h1 class="lf-vazio__title"><?php esc_html_e( 'Esta página não foi encontrada', 'licia-fabio' ); ?></h1>
		<p class="lf-vazio__text"><?php esc_html_e( 'O endereço pode ter mudado. Use a busca ou escolha uma editoria.', 'licia-fabio' ); ?></p>

		<?php get_search_form(); ?>

		<div class="lf-vazio__cats">
			<?php
			foreach ( get_categories( array( 'number' => 8, 'orderby' => 'count', 'order' => 'DESC' ) ) as $lf_c ) :
				?>
				<a href="<?php echo esc_url( get_category_link( $lf_c->term_id ) ); ?>"><?php echo esc_html( $lf_c->name ); ?></a>
				<?php
			endforeach;
			?>
		</div>
	</div>
</div>

<?php
get_footer();
