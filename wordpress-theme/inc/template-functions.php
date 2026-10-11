<?php
/**
 * Funções de exibição.
 *
 * @package licia-fabio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nome da editoria principal da matéria.
 *
 * @param int|null $post_id Id do post.
 * @return WP_Term|null
 */
function lf_primary_category( $post_id = null ) {

	$cats = get_the_category( $post_id );

	return ! empty( $cats ) ? $cats[0] : null;
}

/**
 * Imprime a etiqueta da editoria.
 *
 * @param string $classe Classe CSS.
 */
function lf_the_category_label( $classe = 'lf-card__cat' ) {

	$cat = lf_primary_category();

	if ( ! $cat ) {
		return;
	}

	printf(
		'<div class="%1$s"><a href="%2$s">%3$s</a></div>',
		esc_attr( $classe ),
		esc_url( get_category_link( $cat->term_id ) ),
		esc_html( $cat->name )
	);
}

/**
 * Data no formato do layout.
 *
 * @param int|null $post_id Id do post.
 * @return string
 */
function lf_the_date_text( $post_id = null ) {
	return get_the_date( 'j \d\e F, Y', $post_id );
}

/**
 * Tempo estimado de leitura, em minutos.
 *
 * @param int|null $post_id Id do post.
 * @return int
 */
function lf_reading_time( $post_id = null ) {

	$post = get_post( $post_id );

	if ( ! $post ) {
		return 1;
	}

	$palavras = str_word_count( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );

	return max( 1, (int) ceil( $palavras / 200 ) );
}

/**
 * "há 2 horas", no formato do layout.
 *
 * @param int|null $post_id Id do post.
 * @return string
 */
function lf_time_ago( $post_id = null ) {
	return human_time_diff( get_post_time( 'U', false, $post_id ), current_time( 'timestamp' ) );
}

/**
 * Paginação no desenho do tema.
 */
function lf_pagination() {

	the_posts_pagination(
		array(
			'mid_size'           => 1,
			'prev_text'          => __( 'Anterior', 'licia-fabio' ),
			'next_text'          => __( 'Próxima', 'licia-fabio' ),
			'class'              => 'lf-pag',
			'screen_reader_text' => __( 'Navegação entre páginas', 'licia-fabio' ),
		)
	);
}

/**
 * Esta matéria é conteúdo de marca?
 *
 * @param int|null $post_id Id do post.
 * @return bool
 */
function lf_is_branded( $post_id = null ) {

	$secao = function_exists( 'lf_get_setting' ) ? lf_get_setting( 'branded_category', '' ) : '';

	if ( ! $secao ) {
		return false;
	}

	$termo = lf_section_category( $secao );

	if ( ! $termo ) {
		return false;
	}

	return has_category( $termo->term_id, $post_id );
}

/**
 * Título de uma seção, com a régua e o link "ver tudo".
 *
 * @param string  $titulo Título exibido.
 * @param WP_Term $termo  Categoria, para montar o link. Opcional.
 */
function lf_section_header( $titulo, $termo = null ) {
	?>
	<div class="lf-sec__head">
		<h2 class="lf-sec__title"><?php echo esc_html( $titulo ); ?></h2>
		<div class="lf-sec__rule"></div>
		<?php if ( $termo instanceof WP_Term ) : ?>
			<a class="lf-sec__link" href="<?php echo esc_url( get_category_link( $termo->term_id ) ); ?>">
				<?php esc_html_e( 'Ver tudo', 'licia-fabio' ); ?>
			</a>
		<?php endif; ?>
	</div>
	<?php
}
