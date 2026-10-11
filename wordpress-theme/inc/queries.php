<?php
/**
 * Consultas do tema.
 *
 * Todas são limitadas, usam WP_Query e restauram o post global. Nenhuma
 * carrega o acervo inteiro para filtrar depois em PHP.
 *
 * @package licia-fabio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Destaques da página inicial.
 *
 * Delega ao plugin, que resolve seleção manual e fallback automático. Sem o
 * plugin, cai no comportamento antigo: as três notícias mais recentes.
 *
 * @return WP_Post[]
 */
function lf_home_featured_posts() {

	if ( function_exists( 'lf_get_home_featured' ) ) {
		return lf_get_home_featured();
	}

	$q = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 3,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);

	return $q->posts;
}

/**
 * Manchetes do plantão.
 *
 * @param int   $quantidade Número de manchetes.
 * @param array $excluir    Ids a excluir.
 * @return WP_Query
 */
function lf_breaking_query( $quantidade = 3, $excluir = array() ) {

	return new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, (int) $quantidade ),
			'post__not_in'        => $excluir ? $excluir : array( 0 ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
}

/**
 * Notícias de uma seção visual, resolvida pela categoria configurada.
 *
 * @param string $secao      Chave da seção.
 * @param int    $quantidade Número de notícias.
 * @param array  $excluir    Ids a excluir, para não repetir na home.
 * @return WP_Query|null
 */
function lf_section_query( $secao, $quantidade = 4, $excluir = array() ) {

	$termo = lf_section_category( $secao );

	if ( ! $termo ) {
		return null;
	}

	return new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'cat'                 => (int) $termo->term_id,
			'posts_per_page'      => max( 1, (int) $quantidade ),
			'post__not_in'        => $excluir ? $excluir : array( 0 ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
}

/**
 * Mais lidas.
 *
 * @param int $quantidade Número de notícias.
 * @return WP_Query
 */
function lf_most_read_query( $quantidade = 5 ) {

	if ( function_exists( 'lf_query_most_read' ) ) {
		return lf_query_most_read( $quantidade );
	}

	return new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, (int) $quantidade ),
			'meta_key'            => 'post_views_count',
			'orderby'             => 'meta_value_num',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
}

/**
 * Próximos eventos da agenda.
 *
 * @param int $quantidade Número de eventos.
 * @return WP_Query|null
 */
function lf_events_query( $quantidade = 4 ) {

	if ( function_exists( 'lf_query_upcoming_events' ) ) {
		return lf_query_upcoming_events( $quantidade );
	}

	return null;
}

/**
 * Notícias relacionadas: mesma editoria, sem repetir a matéria aberta.
 *
 * @param int $post_id    Id da matéria.
 * @param int $quantidade Número de notícias.
 * @return WP_Query
 */
function lf_related_query( $post_id, $quantidade = 3 ) {

	$cats = wp_get_post_categories( (int) $post_id );

	return new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'category__in'        => $cats ? $cats : array(),
			'post__not_in'        => array( (int) $post_id ),
			'posts_per_page'      => max( 1, (int) $quantidade ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
}

/**
 * Posts por página nas listagens.
 *
 * @param WP_Query $query Consulta principal.
 */
function lf_posts_per_page( $query ) {

	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( $query->is_category() || ( $query->is_archive() && ! $query->is_post_type_archive( 'lf_evento' ) ) ) {
		$query->set( 'posts_per_page', 16 );
	}

	if ( $query->is_search() ) {
		$query->set( 'posts_per_page', 12 );
		$query->set( 'post_type', array( 'post' ) );
	}
}
add_action( 'pre_get_posts', 'lf_posts_per_page' );
