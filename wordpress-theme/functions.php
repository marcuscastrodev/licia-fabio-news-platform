<?php
/**
 * Configuração do tema Portal Lícia Fábio.
 *
 * VERSÃO PARA VALIDAÇÃO EM STAGING.
 *
 * O tema NÃO registra tipos de conteúdo nem metadados: isso é papel do plugin
 * licia-fabio-core, para que os dados sobrevivam a uma troca de tema.
 *
 * O tema também não desativa, renomeia nem exclui nenhum outro tema ou plugin,
 * e não escreve em .htaccess ou wp-config.php. O tema Newspaper permanece
 * instalado como rota de reversão.
 *
 * @package licia-fabio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LF_THEME_VERSION', '1.0.5-staging' );

require_once get_template_directory() . '/inc/compatibility.php';
require_once get_template_directory() . '/inc/pages.php';
require_once get_template_directory() . '/inc/tv-licia.php';
require_once get_template_directory() . '/inc/queries.php';
require_once get_template_directory() . '/inc/template-functions.php';
require_once get_template_directory() . '/inc/performance.php';

/**
 * Recursos suportados.
 */
function lf_theme_setup() {

	load_theme_textdomain( 'licia-fabio', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );

	// Tamanhos usados pelo layout. Não remove nem altera os tamanhos existentes.
	set_post_thumbnail_size( 760, 475, true );
	add_image_size( 'lf-destaque', 1280, 760, true );
	add_image_size( 'lf-card', 760, 475, true );
	add_image_size( 'lf-quadrado', 400, 400, true );

	register_nav_menus(
		array(
			'principal' => __( 'Editorias (topo)', 'licia-fabio' ),
			'rodape'    => __( 'Rodapé', 'licia-fabio' ),
			'social'    => __( 'Redes sociais', 'licia-fabio' ),
		)
	);
}
add_action( 'after_setup_theme', 'lf_theme_setup' );

/**
 * Área de widget da coluna lateral.
 *
 * As posições publicitárias NÃO são widgets: são gerenciadas pelo plugin, em
 * Anúncios.
 */
function lf_theme_widgets() {

	register_sidebar(
		array(
			'name'          => __( 'Coluna lateral', 'licia-fabio' ),
			'id'            => 'lf-sidebar',
			'description'   => __( 'Aparece ao lado da matéria.', 'licia-fabio' ),
			'before_widget' => '<section id="%1$s" class="lf-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="lf-widget__title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'lf_theme_widgets' );

/**
 * CSS e JavaScript do site público.
 *
 * Nada do painel é carregado aqui, e nada daqui é carregado no painel.
 */
function lf_theme_assets() {

	wp_enqueue_style( 'lf-theme', get_stylesheet_uri(), array(), LF_THEME_VERSION );

	/*
	 * FONTES — nenhuma chamada externa.
	 *
	 * A requisição ao Google Fonts foi removida. O tema não carrega recurso de
	 * terceiro: nada sai do domínio do site.
	 *
	 * Os arquivos oficiais das famílias do layout (Playfair Display, Archivo e
	 * Newsreader) NÃO estão neste pacote, e deliberadamente não foram baixados
	 * nem substituídos por arquivos parecidos — não vou empacotar fonte cuja
	 * origem e licença eu não possa afirmar. Enquanto eles não forem fornecidos,
	 * o tema usa uma pilha segura de fontes do sistema, declarada em
	 * assets/css/main.css.
	 *
	 * Para instalar as fontes reais, ver a seção "Fontes" do
	 * README-IMPLEMENTACAO.md: basta colocar os .woff2 em assets/fonts/ e
	 * descomentar o bloco @font-face no topo do main.css. O preload da fonte
	 * crítica está abaixo, também comentado, e só deve ser ligado junto.
	 */

	wp_enqueue_style(
		'lf-main',
		get_template_directory_uri() . '/assets/css/main.css',
		array( 'lf-theme' ),
		LF_THEME_VERSION
	);

	// Menu mobile: pequeno, sem dependência, no rodapé.
	wp_enqueue_script(
		'lf-menu',
		get_template_directory_uri() . '/assets/js/menu.js',
		array(),
		LF_THEME_VERSION,
		true
	);

	/*
	 * Visualização ampliada da galeria: carregada apenas em matérias que
	 * realmente tenham galeria — shortcode [gallery], bloco Galeria do
	 * Gutenberg, ou bloco reutilizável que contenha uma das duas.
	 */
	if ( is_singular() && lf_post_has_gallery() ) {

		wp_enqueue_script(
			'lf-gallery',
			get_template_directory_uri() . '/assets/js/gallery.js',
			array(),
			LF_THEME_VERSION,
			true
		);

		wp_localize_script(
			'lf-gallery',
			'lfGalleryStrings',
			array(
				'dialog' => __( 'Imagem ampliada', 'licia-fabio' ),
				'close'  => __( 'Fechar', 'licia-fabio' ),
				'prev'   => __( 'Imagem anterior', 'licia-fabio' ),
				'next'   => __( 'Próxima imagem', 'licia-fabio' ),
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'lf_theme_assets' );

/**
 * Aviso no painel quando o plugin de estrutura não está ativo.
 */
function lf_theme_core_notice() {

	if ( function_exists( 'lf_get_home_featured' ) ) {
		return;
	}

	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
		esc_html__( 'Plugin Lícia Fábio Core não encontrado.', 'licia-fabio' ),
		esc_html__( 'O tema funciona sem ele, mas subtítulos, destaques manuais, anúncios e agenda não aparecem. Instale e ative o plugin.', 'licia-fabio' ),
		esc_url( admin_url( 'plugins.php' ) ),
		esc_html__( 'Ver plugins', 'licia-fabio' )
	);
}
add_action( 'admin_notices', 'lf_theme_core_notice' );
