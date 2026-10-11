<?php
/**
 * Desempenho: carregamento de imagens, scripts e limpeza do que não é usado.
 *
 * @package licia-fabio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contador de imagens já renderizadas na página, usado para decidir o
 * carregamento adiado.
 *
 * @var int
 */
$GLOBALS['lf_image_count'] = 0;

/**
 * A imagem principal (LCP) nunca recebe lazy loading; as demais recebem.
 *
 * A primeira imagem de conteúdo da página é considerada a principal.
 *
 * @param string|bool $valor   Valor atual do atributo.
 * @param string      $imagem  Tag da imagem.
 * @param string      $contexto Contexto.
 * @return string|bool
 */
function lf_loading_attr( $valor, $imagem, $contexto ) {

	if ( is_admin() || is_feed() ) {
		return $valor;
	}

	$GLOBALS['lf_image_count']++;

	// Primeira imagem: carrega imediatamente, com prioridade alta.
	if ( 1 === $GLOBALS['lf_image_count'] ) {
		return false;
	}

	return 'lazy';
}
add_filter( 'wp_img_tag_add_loading_attr', 'lf_loading_attr', 10, 3 );

/**
 * Remove o que o site público não usa.
 */
function lf_trim_head() {

	// Emojis: o portal não depende deles.
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );

	// Links de manifesto e de shortlink que o tema não utiliza.
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
}
add_action( 'init', 'lf_trim_head' );

/**
 * Não carrega o CSS de blocos do editor no site público quando não há blocos.
 *
 * O acervo usa o Editor Clássico, então na maioria das páginas esse CSS é peso
 * morto. A verificação é por página: quando houver blocos, o CSS é mantido.
 */
function lf_conditional_block_styles() {

	if ( is_admin() ) {
		return;
	}

	$post = get_post();

	if ( $post && function_exists( 'has_blocks' ) && has_blocks( $post ) ) {
		return;
	}

	if ( is_home() || is_archive() || is_search() ) {
		return;
	}

	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'classic-theme-styles' );
	wp_dequeue_style( 'global-styles' );
}
add_action( 'wp_enqueue_scripts', 'lf_conditional_block_styles', 100 );

/*
 * Não há resource hints: o tema não carrega nenhum recurso de terceiro. Se as
 * fontes oficiais forem instaladas em assets/fonts/, o preload da fonte
 * crítica deve ser feito ali mesmo, no @font-face, e não por preconnect — os
 * arquivos passam a vir do próprio domínio.
 */

/**
 * Adiciona defer aos scripts do tema.
 *
 * @param string $tag    Tag do script.
 * @param string $handle Identificador.
 * @return string
 */
function lf_defer_scripts( $tag, $handle ) {

	$nossos = array( 'lf-menu', 'lf-gallery' );

	if ( ! in_array( $handle, $nossos, true ) ) {
		return $tag;
	}

	if ( false !== strpos( $tag, ' defer' ) ) {
		return $tag;
	}

	return str_replace( ' src=', ' defer src=', $tag );
}
add_filter( 'script_loader_tag', 'lf_defer_scripts', 10, 2 );
