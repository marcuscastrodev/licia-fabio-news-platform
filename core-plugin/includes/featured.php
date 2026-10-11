<?php
/**
 * Destaques da página inicial: seleção manual com fallback automático.
 *
 * @package licia-fabio-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Número de destaques da home.
 */
define( 'LF_FEATURED_SLOTS', 3 );

/**
 * Devolve os posts marcados manualmente como destaque.
 *
 * Ordena por data de modificação decrescente, para que o critério de
 * desempate seja sempre "a matéria atualizada mais recentemente ganha".
 *
 * @return WP_Post[]
 */
function lf_get_manual_featured() {

	$q = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 20,
			'orderby'             => 'modified',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'meta_query'          => array(
				array(
					'key'     => '_lf_home_featured',
					'value'   => '1',
					'compare' => '=',
				),
			),
		)
	);

	return $q->posts;
}

/**
 * Lista as matérias que disputam a mesma posição de destaque.
 *
 * Usada pelo aviso de conflito no editor.
 *
 * @param int $posicao  Posição de 1 a 3.
 * @param int $excluir  Id a ignorar (normalmente o post em edição).
 * @return string[] Títulos das outras matérias na mesma posição.
 */
function lf_featured_conflicts( $posicao, $excluir = 0 ) {

	$posicao = lf_sanitize_featured_position( $posicao );

	if ( ! $posicao ) {
		return array();
	}

	$titulos = array();

	foreach ( lf_get_manual_featured() as $post ) {

		if ( (int) $post->ID === (int) $excluir ) {
			continue;
		}

		if ( (int) get_post_meta( $post->ID, '_lf_home_featured_position', true ) === $posicao ) {
			$titulos[] = get_the_title( $post );
		}
	}

	return $titulos;
}

/**
 * Monta os três destaques da página inicial.
 *
 * Regras, nesta ordem:
 *
 * 1. As matérias marcadas manualmente ocupam a posição escolhida (1 a 3).
 * 2. Quando duas matérias escolhem a mesma posição, fica com ela a que foi
 *    atualizada mais recentemente; a outra vai para a primeira posição livre.
 * 3. Marcadas sem posição definida entram nas posições livres restantes.
 * 4. As posições que sobrarem são preenchidas pelas notícias publicadas mais
 *    recentes — o comportamento que o site tinha antes.
 * 5. Nenhuma matéria aparece duas vezes. Somente status publish.
 *
 * @return WP_Post[] Array de no máximo três posts, na ordem de exibição.
 */
function lf_get_home_featured() {

	$cache_min = lf_cache_minutes();

	if ( $cache_min > 0 ) {
		$ids = get_transient( 'lf_home_featured' );

		if ( is_array( $ids ) ) {
			$posts = array_filter( array_map( 'get_post', $ids ) );

			if ( count( $posts ) === count( $ids ) ) {
				return $posts;
			}
		}
	}

	$slots     = array_fill( 1, LF_FEATURED_SLOTS, null );
	$sem_slot  = array();
	$usados    = array();

	// 1 e 2 — marcadas com posição.
	foreach ( lf_get_manual_featured() as $post ) {

		$pos = lf_sanitize_featured_position( get_post_meta( $post->ID, '_lf_home_featured_position', true ) );

		if ( ! $pos ) {
			$sem_slot[] = $post;
			continue;
		}

		if ( null === $slots[ $pos ] ) {
			$slots[ $pos ] = $post;
			$usados[]      = $post->ID;
		} else {
			// Posição já tomada pela matéria mais recentemente atualizada.
			$sem_slot[] = $post;
		}
	}

	// 3 — marcadas sem posição entram nos vãos.
	foreach ( $sem_slot as $post ) {

		if ( in_array( $post->ID, $usados, true ) ) {
			continue;
		}

		for ( $i = 1; $i <= LF_FEATURED_SLOTS; $i++ ) {
			if ( null === $slots[ $i ] ) {
				$slots[ $i ] = $post;
				$usados[]    = $post->ID;
				break;
			}
		}
	}

	// 4 — fallback automático com as notícias mais recentes.
	$vazios = 0;

	foreach ( $slots as $post ) {
		if ( null === $post ) {
			$vazios++;
		}
	}

	if ( $vazios > 0 ) {

		$recentes = new WP_Query(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => $vazios,
				'post__not_in'        => $usados ? $usados : array( 0 ),
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);

		$fila = $recentes->posts;

		for ( $i = 1; $i <= LF_FEATURED_SLOTS; $i++ ) {

			if ( null !== $slots[ $i ] ) {
				continue;
			}

			$proximo = array_shift( $fila );

			if ( ! $proximo ) {
				break;
			}

			$slots[ $i ] = $proximo;
			$usados[]    = $proximo->ID;
		}
	}

	// 5 — compacta, mantendo a ordem das posições.
	$final = array();

	for ( $i = 1; $i <= LF_FEATURED_SLOTS; $i++ ) {
		if ( $slots[ $i ] instanceof WP_Post ) {
			$final[] = $slots[ $i ];
		}
	}

	if ( $cache_min > 0 ) {
		set_transient( 'lf_home_featured', wp_list_pluck( $final, 'ID' ), $cache_min * MINUTE_IN_SECONDS );
	}

	return $final;
}

/**
 * Aviso no painel quando há conflito de posição entre destaques.
 */
function lf_featured_conflict_notice() {

	if ( ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}

	$tela = get_current_screen();

	if ( ! $tela || ! in_array( $tela->id, array( 'dashboard', 'edit-post' ), true ) ) {
		return;
	}

	$ocupadas = array();
	$conflito = array();

	foreach ( lf_get_manual_featured() as $post ) {

		$pos = lf_sanitize_featured_position( get_post_meta( $post->ID, '_lf_home_featured_position', true ) );

		if ( ! $pos ) {
			continue;
		}

		if ( isset( $ocupadas[ $pos ] ) ) {
			$conflito[ $pos ] = true;
		}

		$ocupadas[ $pos ] = true;
	}

	if ( empty( $conflito ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
		esc_html__( 'Destaques da home em conflito.', 'licia-fabio-core' ),
		esc_html(
			sprintf(
				/* translators: lista de posições */
				__( 'Mais de uma matéria está marcada para a posição %s. A home mostra a atualizada mais recentemente e desloca as outras para as posições livres.', 'licia-fabio-core' ),
				implode( ', ', array_keys( $conflito ) )
			)
		),
		esc_url( admin_url( 'edit.php?post_type=post' ) ),
		esc_html__( 'Rever as notícias', 'licia-fabio-core' )
	);
}
add_action( 'admin_notices', 'lf_featured_conflict_notice' );
