<?php
/**
 * Plugin Name: Lícia Fábio Core
 * Plugin URI:  https://liciafabio.com.br/
 * Description: VERSÃO PARA VALIDAÇÃO EM STAGING — não instalar em produção. Estrutura editorial do portal Lícia Fábio: subtítulo, destaques da home, crédito de foto, anúncios, agenda de eventos, visualizações e integração REST. Os dados ficam aqui, não no tema — trocar de tema não apaga nem esconde nada. Desativar este plugin não remove nenhum dado.
 * Version:     1.0.3-staging
 * Author:      Lícia Fábio Produções
 * License:     GPL-2.0-or-later
 * Text Domain: licia-fabio-core
 * Requires at least: 5.9
 * Requires PHP: 7.4
 *
 * @package licia-fabio-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LF_CORE_VERSION', '1.0.3-staging' );
define( 'LF_CORE_FILE', __FILE__ );
define( 'LF_CORE_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Nome da opção que guarda as configurações do plugin.
 */
define( 'LF_CORE_OPTION', 'lf_core_settings' );

require_once LF_CORE_PATH . 'includes/settings.php';
require_once LF_CORE_PATH . 'includes/post-types.php';
require_once LF_CORE_PATH . 'includes/post-meta.php';
require_once LF_CORE_PATH . 'includes/media.php';
require_once LF_CORE_PATH . 'includes/featured.php';
require_once LF_CORE_PATH . 'includes/views.php';
require_once LF_CORE_PATH . 'includes/ads.php';
require_once LF_CORE_PATH . 'includes/events.php';
require_once LF_CORE_PATH . 'includes/rest-api.php';
require_once LF_CORE_PATH . 'includes/admin-settings.php';

/**
 * Carrega as traduções.
 */
function lf_core_load_textdomain() {
	load_plugin_textdomain( 'licia-fabio-core', false, dirname( plugin_basename( LF_CORE_FILE ) ) . '/languages' );
}
add_action( 'init', 'lf_core_load_textdomain', 1 );

/**
 * Ativação do plugin.
 *
 * Deliberadamente NÃO executa migração de dados: nenhum campo antigo é lido,
 * convertido ou apagado na ativação. As permalinks são atualizadas porque o
 * plugin registra novos tipos de conteúdo.
 */
function lf_core_activate() {

	// Garante que as opções existam com os padrões conservadores.
	$atual = get_option( LF_CORE_OPTION );

	if ( false === $atual ) {
		add_option( LF_CORE_OPTION, lf_core_default_settings() );
	}

	/*
	 * Registra os tipos de conteúdo ANTES de recalcular as regras de reescrita.
	 * Na ativação, o hook 'init' deste carregamento já passou, então sem esta
	 * chamada explícita o flush gravaria as regras sem lf_evento e sem
	 * lf_anuncio — e o arquivo da agenda responderia 404 até que as permalinks
	 * fossem salvas manualmente.
	 */
	lf_register_post_types();

	flush_rewrite_rules();

	/*
	 * Nada além disso. A ativação não cria, altera, converte nem exclui posts,
	 * categorias, metadados ou configurações antigas.
	 */
}
register_activation_hook( __FILE__, 'lf_core_activate' );

/**
 * Desativação: apenas limpa as regras de reescrita e os caches temporários.
 *
 * Nenhum dado é removido. Notícias, anúncios, eventos, metadados e
 * configurações permanecem no banco. Reativar o plugin devolve tudo.
 */
function lf_core_deactivate() {

	if ( function_exists( 'lf_flush_home_cache' ) ) {
		lf_flush_home_cache();
	}

	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'lf_core_deactivate' );

/*
 * Desinstalação: ver uninstall.php. Por padrão, desinstalar o plugin NÃO apaga
 * nada. A remoção de dados só acontece se um administrador criar
 * explicitamente a opção lf_core_allow_data_removal — uma trava deliberada.
 */
