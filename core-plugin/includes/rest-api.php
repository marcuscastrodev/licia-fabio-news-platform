<?php
/**
 * Integração REST para a plataforma dos redatores.
 *
 * Usa a REST API do WordPress e seus mecanismos de autenticação. Nenhum
 * endpoint próprio de ação administrativa é criado: os campos são expostos nos
 * recursos existentes (posts, lf_anuncio, lf_evento) via register_post_meta,
 * e os endpoints adicionais abaixo são apenas de leitura, exigindo permissão.
 *
 * @package licia-fabio-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Namespace da API do portal.
 */
define( 'LF_REST_NS', 'licia-fabio/v1' );

/**
 * Permissão de leitura: precisa poder editar posts.
 *
 * @return true|WP_Error
 */
function lf_rest_can_read() {

	if ( ! is_user_logged_in() ) {
		return new WP_Error(
			'lf_rest_nao_autenticado',
			__( 'Você precisa entrar com sua conta para acessar estes dados.', 'licia-fabio-core' ),
			array( 'status' => 401 )
		);
	}

	if ( ! current_user_can( 'edit_posts' ) ) {
		return new WP_Error(
			'lf_rest_sem_permissao',
			__( 'Sua conta não tem permissão para acessar estes dados.', 'licia-fabio-core' ),
			array( 'status' => 403 )
		);
	}

	return true;
}

/**
 * Registra os endpoints de apoio.
 */
function lf_register_rest_routes() {

	// Posições publicitárias disponíveis, para montar o seletor na plataforma.
	register_rest_route(
		LF_REST_NS,
		'/ad-positions',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'lf_rest_ad_positions',
			'permission_callback' => 'lf_rest_can_read',
		)
	);

	// Mapa de seções visuais e categorias configuradas.
	register_rest_route(
		LF_REST_NS,
		'/sections',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'lf_rest_sections',
			'permission_callback' => 'lf_rest_can_read',
		)
	);

	// Destaques atuais da home, já resolvidos pela regra de negócio.
	register_rest_route(
		LF_REST_NS,
		'/home-featured',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'lf_rest_home_featured',
			'permission_callback' => 'lf_rest_can_read',
		)
	);
}
add_action( 'rest_api_init', 'lf_register_rest_routes' );

/**
 * Posições publicitárias.
 *
 * @return WP_REST_Response
 */
function lf_rest_ad_positions() {

	$saida = array();

	foreach ( lf_ad_positions() as $chave => $info ) {
		$saida[] = array(
			'key'    => $chave,
			'label'  => $info['label'],
			'format' => $info['formato'],
			'filled' => lf_has_ad( $chave ),
		);
	}

	return rest_ensure_response( $saida );
}

/**
 * Seções visuais e categorias.
 *
 * @return WP_REST_Response
 */
function lf_rest_sections() {

	$saida = array();

	foreach ( lf_editorial_sections() as $chave => $rotulo ) {

		$termo = lf_get_section_category( $chave );

		$saida[] = array(
			'key'         => $chave,
			'label'       => $rotulo,
			'category_id' => $termo ? (int) $termo->term_id : 0,
			'slug'        => $termo ? $termo->slug : '',
			'mapped'      => (bool) $termo,
		);
	}

	return rest_ensure_response( $saida );
}

/**
 * Destaques da home.
 *
 * @return WP_REST_Response
 */
function lf_rest_home_featured() {

	$saida = array();

	foreach ( lf_get_home_featured() as $i => $post ) {
		$saida[] = array(
			'position'  => $i + 1,
			'id'        => (int) $post->ID,
			'title'     => get_the_title( $post ),
			'subtitle'  => (string) get_post_meta( $post->ID, '_lf_subtitulo', true ),
			'manual'    => (bool) get_post_meta( $post->ID, '_lf_home_featured', true ),
			'link'      => get_permalink( $post ),
			'thumbnail' => get_the_post_thumbnail_url( $post, 'medium_large' ),
		);
	}

	return rest_ensure_response( $saida );
}

/**
 * Expõe o subtítulo no recurso de posts também como campo de leitura direta,
 * para facilitar a listagem na plataforma dos redatores.
 */
function lf_register_rest_fields() {

	register_rest_field(
		'post',
		'lf_subtitulo',
		array(
			'get_callback' => function ( $post ) {
				return (string) get_post_meta( $post['id'], '_lf_subtitulo', true );
			},
			'schema'       => array(
				'description' => __( 'Subtítulo da matéria.', 'licia-fabio-core' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
		)
	);

	register_rest_field(
		'post',
		'lf_views',
		array(
			'get_callback' => function ( $post ) {
				return lf_get_post_views( $post['id'] );
			},
			'schema'       => array(
				'description' => __( 'Visualizações registradas.', 'licia-fabio-core' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
		)
	);
}
add_action( 'rest_api_init', 'lf_register_rest_fields' );
