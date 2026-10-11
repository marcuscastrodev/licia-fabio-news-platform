<?php
/**
 * Plugin Name: Lícia Fábio — Painel Editorial
 * Description: Painel de redação próprio, servido em /redacao/ da mesma instalação do WordPress. Usa os usuários, sessões, cookies e permissões nativas — sem token no navegador e sem backend externo. Reutiliza os campos do plugin Core. Desativar o plugin remove apenas o painel: nenhuma notícia, imagem ou configuração é apagada.
 * Version:     1.0.5-staging
 * Author:      Portal Lícia Fábio
 * License:     GPL-2.0-or-later
 * Requires PHP: 7.4
 * Requires at least: 5.9
 *
 * VERSÃO PARA VALIDAÇÃO EM STAGING. NÃO INSTALAR EM PRODUÇÃO.
 *
 * @package licia-fabio-editorial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LF_ED_VERSION', '1.0.5-staging' );
define( 'LF_ED_FILE', __FILE__ );
define( 'LF_ED_DIR', plugin_dir_path( __FILE__ ) );

/** Slug da rota do painel, relativo à instalação. */
define( 'LF_ED_SLUG', 'redacao' );

/** Query var interna que identifica a rota. */
define( 'LF_ED_QUERY_VAR', 'lf_editorial' );

/** Capability mínima para entrar no painel. */
define( 'LF_ED_CAP', 'edit_posts' );

require_once LF_ED_DIR . 'includes/fields.php';
require_once LF_ED_DIR . 'includes/idempotency.php';
require_once LF_ED_DIR . 'includes/routing.php';
require_once LF_ED_DIR . 'includes/render.php';
require_once LF_ED_DIR . 'includes/rest.php';

/**
 * Ativação.
 *
 * Registra a rota e recalcula as regras UMA vez. Não cria usuário, não altera
 * permissão, não toca em post, categoria, campo ou configuração.
 */
function lf_ed_activate() {

	add_option( 'lf_ed_version', LF_ED_VERSION );

	lf_ed_add_rewrite();
	flush_rewrite_rules();

	lf_ed_agendar_limpeza();
}
register_activation_hook( __FILE__, 'lf_ed_activate' );

/**
 * Desativação: limpa as regras de URL.
 *
 * O painel deixa de responder em /redacao/. Nada mais acontece — nenhuma
 * notícia, imagem, campo do Core ou configuração é removida.
 */
function lf_ed_deactivate() {

	flush_rewrite_rules();

	wp_clear_scheduled_hook( 'lf_ed_limpeza_diaria' );
}
register_deactivation_hook( __FILE__, 'lf_ed_deactivate' );
