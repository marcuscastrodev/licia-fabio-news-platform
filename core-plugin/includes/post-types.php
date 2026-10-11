<?php
/**
 * Tipos de conteúdo: anúncios e agenda de eventos.
 *
 * @package licia-fabio-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra os tipos de conteúdo do portal.
 */
function lf_register_post_types() {

	// ---------- Anúncios ----------
	register_post_type(
		'lf_anuncio',
		array(
			'labels'              => array(
				'name'               => __( 'Anúncios', 'licia-fabio-core' ),
				'singular_name'      => __( 'Anúncio', 'licia-fabio-core' ),
				'add_new'            => __( 'Novo anúncio', 'licia-fabio-core' ),
				'add_new_item'       => __( 'Novo anúncio', 'licia-fabio-core' ),
				'edit_item'          => __( 'Editar anúncio', 'licia-fabio-core' ),
				'new_item'           => __( 'Novo anúncio', 'licia-fabio-core' ),
				'view_item'          => __( 'Ver anúncio', 'licia-fabio-core' ),
				'search_items'       => __( 'Buscar anúncios', 'licia-fabio-core' ),
				'not_found'          => __( 'Nenhum anúncio cadastrado', 'licia-fabio-core' ),
				'menu_name'          => __( 'Anúncios', 'licia-fabio-core' ),
				'all_items'          => __( 'Todos os anúncios', 'licia-fabio-core' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'menu_icon'           => 'dashicons-megaphone',
			'menu_position'       => 26,
			'supports'            => array( 'title' ),
			'show_in_rest'        => true,
			'rest_base'           => 'lf-anuncios',
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		)
	);

	// ---------- Agenda de eventos ----------
	register_post_type(
		'lf_evento',
		array(
			'labels'            => array(
				'name'          => __( 'Agenda', 'licia-fabio-core' ),
				'singular_name' => __( 'Evento', 'licia-fabio-core' ),
				'add_new'       => __( 'Novo evento', 'licia-fabio-core' ),
				'add_new_item'  => __( 'Novo evento', 'licia-fabio-core' ),
				'edit_item'     => __( 'Editar evento', 'licia-fabio-core' ),
				'view_item'     => __( 'Ver evento', 'licia-fabio-core' ),
				'search_items'  => __( 'Buscar eventos', 'licia-fabio-core' ),
				'not_found'     => __( 'Nenhum evento cadastrado', 'licia-fabio-core' ),
				'menu_name'     => __( 'Agenda', 'licia-fabio-core' ),
			),
			'public'            => true,
			'show_in_nav_menus' => true,
			'has_archive'       => true,
			'menu_icon'         => 'dashicons-calendar-alt',
			'menu_position'     => 25,
			'supports'          => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
			'show_in_rest'      => true,
			'rest_base'         => 'lf-eventos',
			'rewrite'           => array( 'slug' => lf_event_slug(), 'with_front' => false ),
			'capability_type'   => 'post',
			'map_meta_cap'      => true,
		)
	);
}
add_action( 'init', 'lf_register_post_types' );

/**
 * Posições publicitárias previstas.
 *
 * A chave é o valor gravado em _lf_ad_position; o rótulo aparece no painel.
 *
 * @return array
 */
function lf_ad_positions() {

	return array(
		'home_billboard_top'       => array(
			'label'   => __( 'Home · Billboard do topo', 'licia-fabio-core' ),
			'formato' => '970 × 130',
		),
		'single_billboard_top'     => array(
			'label'   => __( 'Matéria · Billboard do topo', 'licia-fabio-core' ),
			'formato' => '970 × 130',
		),
		'home_half_page'           => array(
			'label'   => __( 'Home · Half page lateral', 'licia-fabio-core' ),
			'formato' => '300 × 320',
		),
		'home_sidebar_rectangle'   => array(
			'label'   => __( 'Home · Retângulo lateral', 'licia-fabio-core' ),
			'formato' => '300 × 250',
		),
		'single_sidebar_rectangle' => array(
			'label'   => __( 'Matéria · Retângulo lateral', 'licia-fabio-core' ),
			'formato' => '300 × 250',
		),
		'single_middle'            => array(
			'label'   => __( 'Matéria · Meio do texto', 'licia-fabio-core' ),
			'formato' => '728 × 120',
		),
		'single_end'               => array(
			'label'   => __( 'Matéria · Fim da notícia', 'licia-fabio-core' ),
			'formato' => '970 × 130',
		),
		'category_between_posts'   => array(
			'label'   => __( 'Editoria · Entre as manchetes', 'licia-fabio-core' ),
			'formato' => '970 × 110',
		),
		'branded_content'          => array(
			'label'   => __( 'Conteúdo de marca', 'licia-fabio-core' ),
			'formato' => __( 'Cards editoriais sinalizados', 'licia-fabio-core' ),
		),
	);
}

/**
 * Seções visuais da home que se ligam a categorias existentes.
 *
 * @return array
 */
function lf_editorial_sections() {

	return array(
		'agenda'       => __( 'Agenda', 'licia-fabio-core' ),
		'beleza-pura'  => __( 'Beleza Pura', 'licia-fabio-core' ),
		'cultura'      => __( 'Cultura', 'licia-fabio-core' ),
		'concierge'    => __( 'Concierge', 'licia-fabio-core' ),
		'galeria'      => __( 'Galeria', 'licia-fabio-core' ),
		'gastro'       => __( 'Gastrô', 'licia-fabio-core' ),
		'lf-producoes' => __( 'LF Produções', 'licia-fabio-core' ),
		'social'       => __( 'Social', 'licia-fabio-core' ),
		'tv-licia'     => __( 'TV Lícia', 'licia-fabio-core' ),
	);
}
