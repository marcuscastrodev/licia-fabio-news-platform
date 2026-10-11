<?php
/**
 * Entrega do painel: autenticação, casca HTML e carregamento dos assets.
 *
 * O painel NÃO carrega o tema do portal. A página é montada aqui, com apenas o
 * CSS e o JavaScript do painel — nada de header.php, footer.php, estilos ou
 * scripts do tema público.
 *
 * @package licia-fabio-editorial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Intercepta a rota e entrega o painel.
 *
 * Três portas, nesta ordem:
 *
 *   1. não autenticado  → login NATIVO do WordPress, com retorno ao painel;
 *   2. autenticado sem permissão editorial → acesso negado, e nenhuma
 *      permissão é concedida para compensar;
 *   3. autorizado → painel.
 */
function lf_ed_serve_panel() {

	if ( ! lf_ed_is_panel() ) {
		return;
	}

	/*
	 * RENOVAÇÃO DE NONCE.
	 *
	 * Tratada antes de tudo, e propositalmente FORA do pipeline REST.
	 *
	 * O motivo: quando o nonce do WordPress vence, rest_cookie_check_errors()
	 * rejeita a requisição REST inteira. Qualquer endpoint REST que servisse
	 * para buscar um nonce novo seria rejeitado pelo mesmo mecanismo — era o
	 * defeito da 1.0.0, e a recuperação nunca funcionava.
	 *
	 * Esta rota é uma página comum do site, autenticada apenas pelo COOKIE de
	 * sessão, que não vence junto com o nonce. Se o cookie ainda vale, o
	 * usuário recebe um nonce novo; se não vale, recebe 401 e o painel sabe
	 * que precisa de login — é assim que nonce vencido e sessão encerrada
	 * passam a ser distinguíveis.
	 *
	 * Por que não é um vazamento: a resposta é application/json com nosniff,
	 * então não pode ser carregada por <script> de outro site; e a política de
	 * mesma origem impede que um site terceiro LEIA a resposta, mesmo tendo
	 * conseguido disparar a requisição. Nada é cacheável, e Vary: Cookie
	 * impede que um intermediário sirva a resposta de um usuário a outro.
	 */
	if ( lf_ed_is_renovacao() ) {
		lf_ed_render_nonce();
		exit;
	}

	/*
	 * auth_redirect() manda para wp-login.php com redirect_to apontando para a
	 * URL atual, então o usuário volta ao painel depois de entrar. Recuperação
	 * de senha e logout ficam sendo os fluxos nativos, sem reimplementação.
	 */
	if ( ! is_user_logged_in() ) {
		auth_redirect();
		exit;
	}

	if ( ! current_user_can( LF_ED_CAP ) ) {
		lf_ed_render_denied();
		exit;
	}

	lf_ed_render_panel();
	exit;
}
add_action( 'template_redirect', 'lf_ed_serve_panel', 5 );

/**
 * Responde à renovação de nonce.
 *
 * Três respostas possíveis, e o painel age diferente em cada uma:
 *
 *   401 sessao_encerrada  → precisa de login
 *   403 sem_permissao     → a conta perdeu a capability enquanto usava
 *   200 { nonce }         → era só o nonce; segue em frente
 */
function lf_ed_render_nonce() {

	nocache_headers();

	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Cache-Control: no-store, no-cache, must-revalidate, private' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Robots-Tag: noindex, nofollow', true );
	header( 'Vary: Cookie' );

	if ( ! is_user_logged_in() ) {
		status_header( 401 );
		echo wp_json_encode(
			array(
				'erro'     => 'sessao_encerrada',
				'mensagem' => __( 'Sua sessão terminou. Entre novamente para continuar.', 'licia-fabio-editorial' ),
				'loginUrl' => wp_login_url( lf_ed_url() ),
			)
		);
		exit;
	}

	if ( ! current_user_can( LF_ED_CAP ) ) {
		status_header( 403 );
		echo wp_json_encode(
			array(
				'erro'     => 'sem_permissao',
				'mensagem' => __( 'Sua conta não tem mais permissão para escrever notícias.', 'licia-fabio-editorial' ),
			)
		);
		exit;
	}

	status_header( 200 );
	echo wp_json_encode(
		array(
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'usuario' => array(
				'id'   => get_current_user_id(),
				'caps' => array(
					'publicar'       => current_user_can( 'publish_posts' ),
					'editarDeOutros' => current_user_can( 'edit_others_posts' ),
					'enviarArquivos' => current_user_can( 'upload_files' ),
				),
			),
		)
	);
	exit;
}

/**
 * Dados iniciais entregues ao JavaScript.
 *
 * Contém o nonce da REST API — que NÃO é uma credencial: ele só tem valor
 * junto do cookie de sessão do próprio usuário, tem vida curta e não serve
 * fora desta origem. Nada é gravado em localStorage.
 *
 * @return array
 */
function lf_ed_boot_data() {

	$user = wp_get_current_user();

	$categorias = get_categories(
		array(
			'hide_empty' => false,
			'orderby'    => 'name',
			'number'     => 200,
		)
	);

	$lista = array();

	foreach ( $categorias as $cat ) {
		$lista[] = array(
			'id'   => (int) $cat->term_id,
			'nome' => $cat->name,
			'slug' => $cat->slug,
		);
	}

	return array(
		'restUrl'    => esc_url_raw( rest_url() ),
		'assetsUrl'  => esc_url_raw( plugins_url( 'assets/', LF_ED_FILE ) ),
		'renovarUrl' => esc_url_raw( add_query_arg( 'lf_renovar', '1', lf_ed_url() ) ),
		'escopo'     => lf_ed_escopo_local(),
		'categorias' => $lista,
		'posicoesAnuncio' => lf_ed_posicoes_anuncio(),
		'tiposAtivos'     => array(
			'evento'  => post_type_exists( 'lf_evento' ),
			'anuncio' => post_type_exists( 'lf_anuncio' ),
		),
		'nonce'      => wp_create_nonce( 'wp_rest' ),
		'painelUrl'  => esc_url_raw( lf_ed_url() ),
		'siteUrl'    => esc_url_raw( home_url( '/' ) ),
		'logoutUrl'  => esc_url_raw( wp_logout_url( lf_ed_url() ) ),
		'perfilUrl'  => esc_url_raw( admin_url( 'profile.php' ) ),
		'coreAtivo'  => lf_ed_core_ativo(),
		'metaSub'    => LF_ED_META_SUBTITULO,
		'metaCred'   => LF_ED_META_CREDITO,
		'maxUpload'  => (int) wp_max_upload_size(),
		'ambiente'   => 'staging',
		'usuario'    => array(
			'id'    => (int) $user->ID,
			'nome'  => $user->display_name,
			'papel' => lf_ed_role_label( $user ),
			'caps'  => array(
				'publicar'        => current_user_can( 'publish_posts' ),
				'editarDeOutros'  => current_user_can( 'edit_others_posts' ),
				'enviarArquivos'  => current_user_can( 'upload_files' ),
				'gerenciarTermos' => current_user_can( 'manage_categories' ),
			),
		),
	);
}

/**
 * Identificador do escopo do armazenamento local.
 *
 * Combina INSTALAÇÃO e USUÁRIO. É o que separa os rascunhos de recuperação de
 * duas contas no mesmo navegador, e de staging e produção servidos da mesma
 * origem — o defeito da 1.0.0, em que a chave era só o id da notícia.
 *
 * É um hash curto e não reversível: não expõe o endereço do site nem o login
 * de ninguém para quem inspecionar o armazenamento do navegador.
 *
 * @return string
 */
function lf_ed_escopo_local() {

	$base = home_url( '/' ) . '|' . get_current_user_id();

	return substr( hash( 'sha256', $base ), 0, 16 );
}

/**
 * Posições publicitárias, lidas do Core.
 *
 * É a mesma lista que o wp-admin usa. Sem o Core, a lista vem vazia e a aba
 * de publicidade avisa em vez de oferecer posições inventadas.
 *
 * @return array
 */
function lf_ed_posicoes_anuncio() {

	if ( ! function_exists( 'lf_ad_positions' ) ) {
		return array();
	}

	$saida = array();

	foreach ( lf_ad_positions() as $chave => $def ) {
		$saida[] = array(
			'chave'   => (string) $chave,
			'rotulo'  => isset( $def['label'] ) ? (string) $def['label'] : (string) $chave,
			'formato' => isset( $def['formato'] ) ? (string) $def['formato'] : '',
		);
	}

	return $saida;
}

/**
 * Rótulo legível do papel do usuário.
 *
 * @param WP_User $user Usuário.
 * @return string
 */
function lf_ed_role_label( $user ) {

	$nomes = wp_roles()->get_names();

	foreach ( (array) $user->roles as $papel ) {
		if ( isset( $nomes[ $papel ] ) ) {
			return translate_user_role( $nomes[ $papel ] );
		}
	}

	return '';
}

/**
 * Casca HTML do painel.
 */
function lf_ed_render_panel() {

	$assets = plugins_url( 'assets/', LF_ED_FILE );
	$boot   = lf_ed_boot_data();

	status_header( 200 );
	header( 'Content-Type: text/html; charset=utf-8' );
	?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php esc_html_e( 'Painel Editorial · Lícia Fábio', 'licia-fabio-editorial' ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( $assets . 'app.css?ver=' . LF_ED_VERSION ); ?>">
</head>
<body class="lfed">
	<div id="lfed-app" aria-busy="true">
		<div class="lfed-boot"><?php esc_html_e( 'Carregando o painel…', 'licia-fabio-editorial' ); ?></div>
	</div>

	<script id="lfed-boot" type="application/json"><?php echo wp_json_encode( $boot ); ?></script>
	<script src="<?php echo esc_url( $assets . 'app.js?ver=' . LF_ED_VERSION ); ?>" defer></script>
</body>
</html>
	<?php
}

/**
 * Acesso negado, para quem está autenticado sem permissão editorial.
 *
 * Nenhuma permissão é concedida, e o wp-admin não é bloqueado.
 */
function lf_ed_render_denied() {

	$assets = plugins_url( 'assets/', LF_ED_FILE );

	status_header( 403 );
	header( 'Content-Type: text/html; charset=utf-8' );
	?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php esc_html_e( 'Sem acesso · Painel Editorial', 'licia-fabio-editorial' ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( $assets . 'app.css?ver=' . LF_ED_VERSION ); ?>">
</head>
<body class="lfed">
	<div class="lfed-aviso-tela">
		<img class="lfed-aviso-tela__logo" src="<?php echo esc_url( $assets . 'logo-licia-fabio.png' ); ?>" alt="Lícia Fábio">
		<h1><?php esc_html_e( 'Sua conta não tem acesso ao painel', 'licia-fabio-editorial' ); ?></h1>
		<p>
			<?php
			printf(
				/* translators: nome do usuário */
				esc_html__( 'Você entrou como %s, mas essa conta não tem permissão para escrever notícias. Quem administra o site pode ajustar isso.', 'licia-fabio-editorial' ),
				'<strong>' . esc_html( wp_get_current_user()->display_name ) . '</strong>'
			);
			?>
		</p>
		<div class="lfed-aviso-tela__acoes">
			<a class="lfed-btn lfed-btn--principal" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Ir para o site', 'licia-fabio-editorial' ); ?></a>
			<a class="lfed-btn" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Sair da conta', 'licia-fabio-editorial' ); ?></a>
		</div>
	</div>
</body>
</html>
	<?php
}
