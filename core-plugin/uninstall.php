<?php
/**
 * Desinstalação do plugin.
 *
 * ESTE ARQUIVO NÃO APAGA NADA por padrão. É proposital.
 *
 * Notícias, subtítulos, destaques, anúncios, eventos, créditos de foto,
 * visualizações e configurações continuam no banco depois de desinstalar o
 * plugin. Reinstalar devolve tudo exatamente como estava.
 *
 * A remoção de dados exige uma AÇÃO ADMINISTRATIVA EXPLÍCITA: criar a opção
 * lf_core_allow_data_removal com valor verdadeiro antes de desinstalar. Ela
 * não existe por padrão, não tem interface no painel e precisa ser criada de
 * propósito por quem administra o site, por exemplo via WP-CLI:
 *
 *     wp option add lf_core_allow_data_removal 1
 *
 * Mesmo nesse caso, o que é removido é apenas a OPÇÃO DE CONFIGURAÇÃO do
 * plugin e os caches temporários. Conteúdo — posts, anúncios, eventos,
 * metadados — nunca é apagado por código. Se algum dia for preciso remover
 * conteúdo, isso deve ser feito manualmente no painel, com backup, por uma
 * pessoa que saiba o que está fazendo.
 *
 * @package licia-fabio-core
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Sem a trava explícita, nada acontece.
if ( ! get_option( 'lf_core_allow_data_removal' ) ) {
	return;
}

// Apenas configuração e caches. Nenhum conteúdo.
delete_option( 'lf_core_settings' );
delete_option( 'lf_core_allow_data_removal' );

delete_transient( 'lf_home_featured' );
delete_transient( 'lf_home_most_read' );
delete_transient( 'lf_home_events' );
delete_transient( 'lf_ads_by_position' );
