<?php
/**
 * Visualizações e bloco "Mais lidas".
 *
 * ----------------------------------------------------------------------------
 * LEITURA E ESCRITA, com precisão:
 *
 * - Com o contador próprio DESLIGADO (o padrão), o plugin apenas LÊ
 *   post_views_count e, na ausência dele, Views. Nada é escrito.
 * - Quando o administrador LIGA o contador próprio em Portal › Visualizações,
 *   o plugin passa a INCREMENTAR post_views_count a cada visita contabilizada.
 *   Essa é a única escrita que existe aqui.
 * - O campo legado Views é somente lido, em qualquer cenário.
 * - Nenhuma migração automática acontece: nenhum valor é convertido, copiado
 *   entre campos ou apagado, nem na ativação nem depois.
 * - O contador deve permanecer desligado enquanto o plugin antigo de
 *   visualizações estiver ativo, ou cada visita é contada duas vezes.
 * ----------------------------------------------------------------------------
 *
 * ----------------------------------------------------------------------------
 * LIMITAÇÕES DE PRECISÃO, registradas de propósito:
 *
 * - A contagem é feita em PHP, no carregamento da página. Com cache de página
 *   (plugin de cache, CDN ou cache do servidor), a maioria das visitas é
 *   servida sem executar PHP, e essas visitas NÃO são contadas. O número
 *   resultante é uma referência relativa, não uma métrica de audiência.
 * - A proteção contra recontagem usa cookie, válido por algumas horas. Quem
 *   apaga cookies, usa aba anônima ou outro aparelho é contado de novo.
 * - Robôs e pré-carregamentos de navegador podem ser contados.
 * - Para audiência real, use uma ferramenta de analytics.
 *
 * O contador próprio nasce DESLIGADO. Ligue-o apenas depois de desativar o
 * plugin antigo de visualizações, para não contar em dobro.
 *
 * SOBRE O PERÍODO DO BLOCO "MAIS LIDAS": o recorte é pela DATA DE PUBLICAÇÃO,
 * não pela data das visualizações. post_views_count é um total acumulado, sem
 * registro de quando cada leitura aconteceu — não existe no banco a informação
 * necessária para "mais lidas nesta semana". O que a consulta devolve é
 * "as mais lidas ENTRE AS MATÉRIAS PUBLICADAS nos últimos X dias". O rótulo no
 * painel e na documentação diz exatamente isso. Nenhuma contagem periódica foi
 * inventada, e nenhum campo antigo foi migrado.
 * ----------------------------------------------------------------------------
 *
 * @package licia-fabio-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Chave principal de visualizações, usada pelo acervo.
 */
define( 'LF_VIEWS_KEY', 'post_views_count' );

/**
 * Chave legada, tratada apenas como fallback.
 */
define( 'LF_VIEWS_LEGACY_KEY', 'Views' );

/**
 * Lê o número de visualizações de uma matéria.
 *
 * Ordem de leitura: post_views_count, depois Views (legado), depois zero.
 * Nunca inventa número.
 *
 * @param int|null $post_id Id do post.
 * @return int
 */
function lf_get_post_views( $post_id = null ) {

	$post_id = $post_id ? (int) $post_id : get_the_ID();

	if ( ! $post_id ) {
		return 0;
	}

	$valor = get_post_meta( $post_id, LF_VIEWS_KEY, true );

	if ( '' === $valor || null === $valor || false === $valor ) {
		$valor = get_post_meta( $post_id, LF_VIEWS_LEGACY_KEY, true );
	}

	if ( '' === $valor || null === $valor || false === $valor ) {
		return 0;
	}

	return max( 0, (int) $valor );
}

/**
 * Visualizações formatadas para exibição.
 *
 * @param int|null $post_id Id do post.
 * @return string Já escapado para uso direto no HTML.
 */
function lf_get_post_views_display( $post_id = null ) {

	$n = lf_get_post_views( $post_id );

	if ( ! $n ) {
		return '';
	}

	return esc_html( number_format_i18n( $n ) );
}

/**
 * Consulta do bloco "Mais lidas".
 *
 * Ordena numericamente por post_views_count (meta_value_num), nunca
 * alfabeticamente.
 *
 * O período configurável filtra pela DATA DE PUBLICAÇÃO, não pela data das
 * visualizações — ver a observação no topo deste arquivo.
 *
 * @param int      $quantidade Número de notícias.
 * @param int|null $dias       Janela em dias. Null usa a configuração do painel. Zero ignora a janela.
 * @return WP_Query
 */
function lf_query_most_read( $quantidade = 5, $dias = null ) {

	$dias = null === $dias ? (int) lf_get_setting( 'most_read_days', 7 ) : (int) $dias;

	$args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => max( 1, (int) $quantidade ),
		'meta_key'            => LF_VIEWS_KEY,
		'orderby'             => 'meta_value_num',
		'order'               => 'DESC',
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);

	if ( $dias > 0 ) {
		$args['date_query'] = array(
			array( 'after' => $dias . ' days ago' ),
		);
	}

	$q = new WP_Query( $args );

	/*
	 * Fallback: se nenhuma matéria do período tiver o campo de visualizações
	 * preenchido, mostra as mais recentes em vez de deixar o bloco vazio.
	 */
	if ( ! $q->have_posts() ) {

		$fallback = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, (int) $quantidade ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);

		$q = new WP_Query( $fallback );
	}

	return $q;
}

/**
 * O contador próprio está ligado?
 *
 * @return bool
 */
function lf_views_counter_enabled() {
	return (bool) lf_get_setting( 'views_counter_enabled', 0 );
}

/**
 * Contabiliza uma visualização.
 *
 * Só roda em página de matéria, para visitante desconectado, fora de
 * pré-visualização, REST, AJAX e cron. Atualiza post_views_count, preservando
 * o valor que já existir.
 */
function lf_count_view() {

	if ( ! lf_views_counter_enabled() ) {
		return;
	}

	if ( is_admin() ) {
		return;
	}

	if ( wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}

	if ( is_preview() || is_feed() || is_robots() || is_trackback() ) {
		return;
	}

	// Não conta quem está logado — administradores, editores e redatores.
	if ( is_user_logged_in() ) {
		return;
	}

	if ( ! is_singular( 'post' ) ) {
		return;
	}

	$post_id = get_queried_object_id();

	if ( ! $post_id ) {
		return;
	}

	// Evita recontagem imediata do mesmo visitante.
	$cookie = 'lf_v_' . $post_id;

	if ( isset( $_COOKIE[ $cookie ] ) ) {
		return;
	}

	$horas = max( 1, (int) lf_get_setting( 'views_cookie_hours', 6 ) );

	if ( ! headers_sent() ) {
		setcookie( $cookie, '1', time() + ( $horas * HOUR_IN_SECONDS ), COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
	}

	$atual = lf_get_post_views( $post_id );

	update_post_meta( $post_id, LF_VIEWS_KEY, $atual + 1 );
}
add_action( 'template_redirect', 'lf_count_view' );
