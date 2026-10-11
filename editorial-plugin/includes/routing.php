<?php
/**
 * Rota do painel.
 *
 * O painel responde em {instalação}/redacao/ — em staging isso significa
 * /staging/redacao/. A URL é montada com home_url(), nunca escrita à mão:
 * nenhum caminho absoluto fixo aparece no plugin.
 *
 * @package licia-fabio-editorial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL do painel.
 *
 * @param string $rota Rota interna opcional (ex.: 'nova', 'editar/123').
 * @return string
 */
function lf_ed_url( $rota = '' ) {

	$base = home_url( '/' . LF_ED_SLUG . '/' );

	return $rota ? $base . ltrim( $rota, '/' ) . '/' : $base;
}

/**
 * Registra a regra de reescrita.
 *
 * Um único padrão captura o painel e tudo abaixo dele: a navegação interna é
 * do JavaScript, e o servidor entrega a mesma casca para qualquer subrota.
 */
function lf_ed_add_rewrite() {

	add_rewrite_rule(
		'^' . LF_ED_SLUG . '/?$',
		'index.php?' . LF_ED_QUERY_VAR . '=1',
		'top'
	);

	add_rewrite_rule(
		'^' . LF_ED_SLUG . '/(.+)/?$',
		'index.php?' . LF_ED_QUERY_VAR . '=1',
		'top'
	);
}
add_action( 'init', 'lf_ed_add_rewrite' );

/**
 * Declara a query var.
 *
 * @param array $vars Vars públicas.
 * @return array
 */
function lf_ed_query_vars( $vars ) {
	$vars[] = LF_ED_QUERY_VAR;
	return $vars;
}
add_filter( 'query_vars', 'lf_ed_query_vars' );

/**
 * Recalcula as regras quando a versão do plugin muda.
 *
 * Deliberadamente NÃO é um flush por requisição — isso reescreveria a opção de
 * rewrite a cada carregamento de página, com custo real em site com tráfego.
 * A comparação de versão custa uma leitura de opção já em cache.
 */
function lf_ed_maybe_flush() {

	if ( get_option( 'lf_ed_version' ) === LF_ED_VERSION ) {
		return;
	}

	update_option( 'lf_ed_version', LF_ED_VERSION );
	flush_rewrite_rules();

	/*
	 * A limpeza é agendada aqui também, não só na ativação.
	 *
	 * Instalar uma versão nova por cima — o caminho normal de atualização — não
	 * dispara o hook de ativação. Na 1.0.1 isso deixava a tarefa sem
	 * agendamento em quem nunca tinha ativado a versão anterior.
	 */
	if ( function_exists( 'lf_ed_agendar_limpeza' ) ) {
		lf_ed_agendar_limpeza();
	}
}
add_action( 'admin_init', 'lf_ed_maybe_flush' );

/**
 * Existe algo publicado ocupando o endereço /redacao/?
 *
 * O plugin NÃO sobrescreve página existente. Se houver conflito, a rota do
 * painel ainda responde (a regra é 'top'), mas o administrador é avisado —
 * porque a página que estava lá deixa de aparecer, e isso não pode acontecer
 * em silêncio.
 *
 * @return WP_Post|null
 */
function lf_ed_route_conflict() {

	$pagina = get_page_by_path( LF_ED_SLUG );

	if ( $pagina ) {
		return $pagina;
	}

	// Categoria, tag ou post com o mesmo slug também disputam o endereço.
	$post = get_posts(
		array(
			'name'           => LF_ED_SLUG,
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => 1,
		)
	);

	return $post ? $post[0] : null;
}

/**
 * Aviso de conflito de rota, no painel administrativo.
 */
function lf_ed_conflict_notice() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$conflito = lf_ed_route_conflict();

	if ( ! $conflito ) {
		return;
	}
	?>
	<div class="notice notice-warning">
		<p>
			<strong><?php esc_html_e( 'Painel Editorial: conflito de endereço.', 'licia-fabio-editorial' ); ?></strong>
			<?php
			printf(
				/* translators: 1: título do conteúdo, 2: endereço */
				esc_html__( 'Existe um conteúdo publicado chamado “%1$s” com o mesmo slug do painel. O painel responde em %2$s e esse conteúdo deixa de aparecer nesse endereço. Nada foi sobrescrito ou excluído: renomeie o slug de um dos dois para resolver.', 'licia-fabio-editorial' ),
				esc_html( get_the_title( $conflito ) ),
				esc_html( lf_ed_url() )
			);
			?>
			<a href="<?php echo esc_url( get_edit_post_link( $conflito->ID ) ); ?>"><?php esc_html_e( 'Ver o conteúdo', 'licia-fabio-editorial' ); ?></a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'lf_ed_conflict_notice' );

/**
 * Estamos no pedido de renovação de nonce?
 *
 * É a rota /redacao/ com ?lf_renovar=1.
 *
 * @return bool
 */
function lf_ed_is_renovacao() {
	return lf_ed_is_panel() && isset( $_GET['lf_renovar'] );
}

/**
 * Estamos na rota do painel?
 *
 * @return bool
 */
function lf_ed_is_panel() {
	return (bool) get_query_var( LF_ED_QUERY_VAR );
}

/**
 * Impede cache compartilhado do painel.
 *
 * Página autenticada, com conteúdo de rascunho: não pode ser guardada por
 * proxy, CDN ou plugin de cache de página.
 */
function lf_ed_no_cache() {

	if ( ! lf_ed_is_panel() ) {
		return;
	}

	nocache_headers();

	header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private' );
	header( 'X-Robots-Tag: noindex, nofollow', true );

	// Constantes que os plugins de cache mais comuns respeitam.
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
		define( 'DONOTCACHEOBJECT', true );
	}

	if ( ! defined( 'DONOTCACHEDB' ) ) {
		define( 'DONOTCACHEDB', true );
	}
}
add_action( 'template_redirect', 'lf_ed_no_cache', 1 );
