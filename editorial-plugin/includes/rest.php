<?php
/**
 * Endpoints de apoio.
 *
 * Só leitura, e só o que as rotas nativas não entregam pronto. Criar, editar,
 * publicar e subir imagem usam /wp/v2/posts e /wp/v2/media — é lá que o
 * WordPress verifica edit_post, publish_posts e upload_files no objeto
 * concreto, e não há como driblar isso chamando a API direto.
 *
 * Nenhum endpoint aqui concede privilégio ou executa ação administrativa.
 *
 * @package licia-fabio-editorial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * NOTA SOBRE A RENOVAÇÃO DE NONCE.
 *
 * A 1.0.0 tinha aqui um endpoint /contexto que serviria para buscar um nonce
 * novo. Ele foi REMOVIDO porque não podia funcionar: quando o nonce vence,
 * rest_cookie_check_errors() rejeita a requisição REST inteira, então um
 * endpoint REST para obter nonce é rejeitado pelo mesmo mecanismo que rejeitou
 * a chamada original.
 *
 * A renovação vive fora do pipeline REST, em lf_ed_render_nonce()
 * (includes/render.php), servida por template_redirect e autenticada apenas
 * pelo cookie de sessão — que não vence junto com o nonce.
 */

/** Namespace próprio. */
define( 'LF_ED_NS', 'licia-fabio-editorial/v1' );

/**
 * Permissão comum: sessão nativa com capability editorial.
 *
 * Verificada no servidor, a cada requisição.
 *
 * @return bool|WP_Error
 */
function lf_ed_rest_permitir() {

	if ( ! is_user_logged_in() ) {
		return new WP_Error(
			'lf_ed_nao_autenticado',
			__( 'Sua sessão expirou. Entre novamente.', 'licia-fabio-editorial' ),
			array( 'status' => 401 )
		);
	}

	if ( ! current_user_can( LF_ED_CAP ) ) {
		return new WP_Error(
			'lf_ed_sem_permissao',
			__( 'Sua conta não tem permissão para escrever notícias.', 'licia-fabio-editorial' ),
			array( 'status' => 403 )
		);
	}

	return true;
}

/**
 * Registra as rotas.
 */
function lf_ed_register_routes() {

	// Resumo para a tela inicial: contagens por status que o usuário pode ver.
	register_rest_route(
		LF_ED_NS,
		'/resumo',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'lf_ed_route_resumo',
			'permission_callback' => 'lf_ed_rest_permitir',
		)
	);

	/*
	 * Criação idempotente.
	 *
	 * Este é o único endpoint de ESCRITA próprio, e existe por um motivo que
	 * as rotas nativas não resolvem: garantir que uma criação repetida — por
	 * resposta perdida ou requisição simultânea — devolva o MESMO post em vez
	 * de criar outro.
	 *
	 * A gravação em si é delegada à rota nativa por rest_do_request(), então
	 * permissão, schema, sanitização e os campos do Core continuam sendo
	 * aplicados pelo WordPress. Nada aqui reimplementa ou enfraquece isso.
	 */
	register_rest_route(
		LF_ED_NS,
		'/noticias',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'lf_ed_route_criar',
			'permission_callback' => 'lf_ed_rest_permitir',
			'args'                => array(
				'tipo'           => array(
					'required'          => false,
					'type'              => 'string',
					'default'           => 'post',
					'enum'              => lf_ed_tipos_editaveis(),
					'sanitize_callback' => 'sanitize_key',
				),
				'chave_operacao' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'lf_ed_validar_chave',
				),
			),
		)
	);

	/*
	 * Leitura de uma notícia para edição: conteúdo E versão na MESMA resposta.
	 *
	 * Existe porque a versão que o servidor exige é {contador}:{data}, e o
	 * contador é um meta interno que a rota nativa não expõe. Na 1.0.2 o
	 * painel guardava só o modified_gmt vindo da rota nativa e montava uma
	 * versão inválida — todo salvamento de notícia existente morria em 400.
	 *
	 * A alternativa seria o navegador montar o contador, e isso não existe:
	 * inventar a versão no cliente é o mesmo que não ter versão.
	 */
	register_rest_route(
		LF_ED_NS,
		'/noticias/(?P<id>\\d+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'lf_ed_route_abrir',
			'permission_callback' => 'lf_ed_rest_permitir',
			'args'                => array(
				'id' => array(
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);

	/*
	 * Consulta de uma operação de criação. Somente leitura: não cria nada.
	 * O painel usa ao recuperar um rascunho depois de recarregar a página.
	 */
	register_rest_route(
		LF_ED_NS,
		'/operacoes/(?P<chave_operacao>[a-z0-9-]{32,64})',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'lf_ed_route_operacao',
			'permission_callback' => 'lf_ed_rest_permitir',
			'args'                => array(
				'chave_operacao' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'lf_ed_validar_chave',
				),
			),
		)
	);

	/*
	 * Atualização com verificação de versão NO SERVIDOR.
	 *
	 * A comparação acontece na mesma requisição que grava, imediatamente antes
	 * da gravação — e não numa consulta prévia feita pelo navegador, que
	 * deixava uma janela larga entre consultar e gravar.
	 */
	register_rest_route(
		LF_ED_NS,
		'/noticias/(?P<id>\\d+)',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'lf_ed_route_atualizar',
			'permission_callback' => 'lf_ed_rest_permitir',
			'args'                => array(
				'id'      => array(
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
				'versao'  => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'lf_ed_versao_valida',
				),
			),
		)
	);

	// Crédito de uma imagem. Leitura; a escrita é por /wp/v2/media.
	register_rest_route(
		LF_ED_NS,
		'/credito/(?P<id>\\d+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'lf_ed_route_credito',
			'permission_callback' => 'lf_ed_rest_permitir',
			'args'                => array(
				'id' => array(
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'lf_ed_register_routes' );

/**
 * Contagem de notícias por status.
 *
 * Respeita a permissão: quem não pode editar de outros só conta as próprias.
 * Rascunho de outra pessoa nunca entra na contagem de quem não pode vê-lo.
 *
 * @return WP_REST_Response
 */
function lf_ed_route_resumo() {

	$apenas_minhas = ! current_user_can( 'edit_others_posts' );
	$autor         = $apenas_minhas ? get_current_user_id() : 0;

	$status = array( 'publish', 'draft', 'pending', 'future' );
	$saida  = array();

	foreach ( $status as $st ) {

		$args = array(
			'post_type'           => 'post',
			'post_status'         => $st,
			'posts_per_page'      => 1,
			'fields'              => 'ids',
			'ignore_sticky_posts' => true,
		);

		if ( $autor ) {
			$args['author'] = $autor;
		}

		$q = new WP_Query( $args );

		$saida[ $st ] = (int) $q->found_posts;
	}

	return new WP_REST_Response(
		array(
			'contagens'     => $saida,
			'apenasMinhas'  => $apenas_minhas,
		),
		200
	);
}

/**
 * Valida o formato da chave de operação.
 *
 * Formato exigido: 32 a 64 caracteres de [a-z0-9-]. Recusa qualquer coisa
 * fora disso antes de tocar o banco.
 *
 * @param mixed $valor Valor recebido.
 * @return bool
 */
function lf_ed_validar_chave( $valor ) {
	return is_string( $valor ) && (bool) preg_match( '/^[a-z0-9-]{32,64}$/', $valor );
}

/**
 * Tipos de conteúdo que o painel edita.
 *
 * Lista fechada. Os três são registrados pelo WordPress (post) e pelo plugin
 * Core (lf_evento, lf_anuncio), com show_in_rest e capability_type 'post' —
 * então as permissões aplicadas são as mesmas das notícias, verificadas pela
 * rota nativa de cada tipo.
 *
 * @return string[]
 */
function lf_ed_tipos_editaveis() {
	return array( 'post', 'lf_evento', 'lf_anuncio' );
}

/**
 * O tipo é editável pelo painel e está registrado nesta instalação?
 *
 * @param string $tipo Tipo de conteúdo.
 * @return bool
 */
function lf_ed_tipo_editavel( $tipo ) {
	return in_array( $tipo, lf_ed_tipos_editaveis(), true ) && post_type_exists( $tipo );
}

/**
 * Normaliza o tipo recebido. Qualquer valor fora da lista vira 'post'.
 *
 * @param mixed $tipo Valor recebido.
 * @return string
 */
function lf_ed_tipo_valido( $tipo ) {

	$tipo = is_string( $tipo ) ? sanitize_key( $tipo ) : '';

	return lf_ed_tipo_editavel( $tipo ) ? $tipo : 'post';
}

/**
 * Rota nativa de um tipo, lida do próprio registro do tipo.
 *
 * Nada escrito à mão: o rest_base vem de get_post_type_object(), então se o
 * Core mudar a base, o painel acompanha.
 *
 * @param string $tipo Tipo.
 * @param int    $id   Id opcional.
 * @return string
 */
function lf_ed_rota_tipo( $tipo, $id = 0 ) {

	$obj  = get_post_type_object( $tipo );
	$base = ( $obj && ! empty( $obj->rest_base ) ) ? $obj->rest_base : ( 'post' === $tipo ? 'posts' : $tipo );
	$ns   = ( $obj && ! empty( $obj->rest_namespace ) ) ? $obj->rest_namespace : 'wp/v2';

	return '/' . trim( $ns, '/' ) . '/' . $base . ( $id ? '/' . (int) $id : '' );
}

/**
 * Metadados que o painel pode gravar, por tipo.
 *
 * Lista fechada e conferida no código do Core (includes/post-meta.php). Os
 * valores continuam sendo sanitizados pelos sanitize_callback do Core; esta
 * lista só impede o painel de gravar campos que não são dele — como o
 * destaque da home, que fica no wp-admin.
 *
 * @param string $tipo Tipo.
 * @return string[]
 */
function lf_ed_meta_permitidos( $tipo ) {

	$mapa = array(
		'post'       => array( '_lf_subtitulo' ),
		'lf_evento'  => array( '_lf_event_date', '_lf_event_time', '_lf_event_location', '_lf_event_address', '_lf_event_url' ),
		'lf_anuncio' => array(
			'_lf_ad_position',
			'_lf_ad_desktop_image_id',
			'_lf_ad_mobile_image_id',
			'_lf_ad_url',
			'_lf_ad_alt',
			'_lf_ad_start_date',
			'_lf_ad_end_date',
			'_lf_ad_active',
			'_lf_ad_new_tab',
		),
	);

	return isset( $mapa[ $tipo ] ) ? $mapa[ $tipo ] : array();
}

/**
 * Campos que o painel envia e que são repassados à rota nativa.
 *
 * Lista fechada por tipo. O que passa ainda é validado e sanitizado pelo
 * WordPress e pelo Core na rota nativa.
 *
 * @param WP_REST_Request $req  Requisição.
 * @param string          $tipo Tipo de conteúdo.
 * @return array
 */
function lf_ed_campos_permitidos( $req, $tipo = 'post' ) {

	$por_tipo = array(
		'post'       => array( 'title', 'content', 'status', 'categories', 'featured_media', 'meta', 'excerpt' ),
		'lf_evento'  => array( 'title', 'content', 'status', 'featured_media', 'meta' ),
		'lf_anuncio' => array( 'title', 'status', 'meta' ),
	);

	$permitidos = isset( $por_tipo[ $tipo ] ) ? $por_tipo[ $tipo ] : $por_tipo['post'];
	$carga      = array();

	foreach ( $permitidos as $campo ) {

		$valor = $req->get_param( $campo );

		if ( null !== $valor ) {
			$carga[ $campo ] = $valor;
		}
	}

	// Metadados: só as chaves deste tipo.
	if ( isset( $carga['meta'] ) ) {

		$meta = is_array( $carga['meta'] ) ? $carga['meta'] : array();

		$carga['meta'] = array_intersect_key( $meta, array_flip( lf_ed_meta_permitidos( $tipo ) ) );

		if ( ! $carga['meta'] ) {
			unset( $carga['meta'] );
		}
	}

	return $carga;
}

/**
 * Validação do painel antes de gravar.
 *
 * Só o que o Core NÃO valida sozinho. O sanitize_callback de _lf_ad_position é
 * sanitize_text_field, que aceita qualquer texto; um anúncio com posição
 * inexistente seria gravado e nunca apareceria, em silêncio. Aqui a posição é
 * conferida contra lf_ad_positions(), a mesma lista que o wp-admin usa.
 *
 * @param string $tipo  Tipo.
 * @param array  $carga Campos.
 * @return true|WP_Error
 */
function lf_ed_validar_carga( $tipo, $carga ) {

	if ( 'lf_anuncio' !== $tipo || empty( $carga['meta'] ) || ! array_key_exists( '_lf_ad_position', $carga['meta'] ) ) {
		return true;
	}

	$pos = (string) $carga['meta']['_lf_ad_position'];

	if ( '' === $pos || ! function_exists( 'lf_ad_positions' ) ) {
		return true;
	}

	if ( ! array_key_exists( $pos, lf_ad_positions() ) ) {
		return new WP_Error(
			'lf_ed_posicao_invalida',
			__( 'A posição escolhida para o anúncio não existe no site. Nada foi gravado.', 'licia-fabio-editorial' ),
			array( 'status' => 400 )
		);
	}

	return true;
}

/**
 * Executa a gravação pela rota NATIVA, internamente.
 *
 * rest_do_request() roda a requisição no mesmo processo, passando por todo o
 * pipeline do WordPress: permission_callback da rota nativa, schema,
 * sanitize_callback de cada meta registrado pelo Core, e os hooks de
 * gravação. É o que mantém a promessa de não reimplementar permissão nem
 * sanitização.
 *
 * @param string $rota  Rota nativa ('/wp/v2/posts' ou com id).
 * @param array  $carga Corpo.
 * @return array {
 *     @type bool  $ok
 *     @type mixed $dados
 *     @type int   $status
 * }
 */
function lf_ed_gravar_nativo( $rota, $carga ) {

	$interna = new WP_REST_Request( 'POST', $rota );

	$interna->set_header( 'content-type', 'application/json' );
	$interna->set_body_params( $carga );

	$resposta = rest_do_request( $interna );

	if ( $resposta->is_error() ) {

		$erro = $resposta->as_error();

		return array(
			'ok'     => false,
			'dados'  => $erro,
			'status' => $resposta->get_status(),
		);
	}

	return array(
		'ok'     => true,
		'dados'  => $resposta->get_data(),
		'status' => $resposta->get_status(),
	);
}

/**
 * Cria uma notícia, de forma idempotente.
 *
 * @param WP_REST_Request $req Requisição.
 * @return WP_REST_Response|WP_Error
 */
function lf_ed_route_criar( $req ) {

	$chave   = (string) $req->get_param( 'chave_operacao' );
	$user_id = get_current_user_id();
	$tipo    = lf_ed_tipo_valido( $req->get_param( 'tipo' ) );
	$carga   = lf_ed_campos_permitidos( $req, $tipo );

	$valida = lf_ed_validar_carga( $tipo, $carga );

	if ( is_wp_error( $valida ) ) {
		return $valida;
	}

	/*
	 * O tipo entra na impressão: a mesma chave não pode virar, numa
	 * repetição, a criação de um tipo diferente.
	 */
	$carga_impressao         = $carga;
	$carga_impressao['tipo'] = $tipo;

	/*
	 * A operação é identificada pela chave MAIS a impressão do payload.
	 *
	 * Foi o defeito da 1.0.1: a chave sozinha bastava, então se o redator
	 * alterasse o texto antes de tentar de novo, o servidor devolvia o post
	 * antigo e o painel marcava o texto NOVO como salvo. Falso sucesso — o
	 * WordPress tinha A e a tela dizia que B estava gravado.
	 */
	$impressao = lf_ed_impressao( $carga_impressao );

	/*
	 * A recusa acontece AQUI, no servidor, antes de qualquer gravação.
	 *
	 * Um aviso no navegador não bastaria: bastaria alguém repetir a requisição
	 * para criar a duplicata. Se esta chave tem lápide, ela já foi usada — e
	 * criar de novo com ela é proibido, com ou sem interface.
	 */
	$lapide = lf_ed_tomb_ler( $chave, $user_id );

	if ( $lapide ) {

		$antigo = isset( $lapide['post_id'] ) ? (int) $lapide['post_id'] : 0;

		if ( $antigo && get_post( $antigo ) ) {

			$leitura = lf_ed_ler_noticia( $antigo );

			return new WP_Error(
				'lf_ed_operacao_expirada',
				__( 'Este pedido já havia criado uma notícia antes, e o registro dele venceu. Nada foi criado agora. Suas alterações continuam aqui — vou gravá-las naquela notícia.', 'licia-fabio-editorial' ),
				array(
					'status'      => 409,
					'post_id'     => $antigo,
					'versao'      => is_wp_error( $leitura ) ? '' : $leitura['versao'],
					'status_post' => is_wp_error( $leitura ) ? '' : $leitura['status'],
					'link'        => is_wp_error( $leitura ) ? '' : $leitura['link'],
					'categorias'  => is_wp_error( $leitura ) ? array() : $leitura['categorias'],
				)
			);
		}

		return new WP_Error(
			'lf_ed_operacao_desconhecida',
			__( 'Este pedido já foi usado antes e não sei qual foi o resultado dele, então não vou criar uma notícia que pode ser cópia. Seu texto continua aqui. Confira a lista de notícias: se ela não existir, use “Criar como notícia nova”.', 'licia-fabio-editorial' ),
			array(
				'status'   => 409,
				'expirada' => true,
			)
		);
	}

	$reserva = lf_ed_idem_reservar( $chave, $user_id, $impressao );

	if ( ! $reserva['reservada'] ) {

		$post_id = $reserva['post_id'];

		if ( ! $post_id ) {
			// Outra requisição está no meio da criação. Espera pelo id dela.
			$post_id = lf_ed_idem_aguardar( $chave, $user_id );
		}

		if ( ! $post_id ) {
			return new WP_Error(
				'lf_ed_operacao_em_curso',
				__( 'Esta mesma operação ainda está sendo processada. Aguarde um instante e tente de novo — nada foi perdido, e nenhuma cópia será criada.', 'licia-fabio-editorial' ),
				array( 'status' => 409 )
			);
		}

		$leitura = lf_ed_ler_noticia( $post_id );

		if ( is_wp_error( $leitura ) ) {
			return $leitura;
		}

		if ( $reserva['mesmo_payload'] ) {

			/*
			 * Repetição verdadeira: mesmo payload, mesma chave. O post já
			 * existe e o que está no servidor É o que o painel quer gravar.
			 * Pode marcar como salvo.
			 */
			$leitura['repetida']      = true;
			$leitura['conteudo_igual'] = true;

			return new WP_REST_Response( $leitura, 200 );
		}

		/*
		 * Mesma chave, payload DIFERENTE: a notícia foi criada, mas com o
		 * texto anterior. Não é repetição, é uma alteração posterior.
		 *
		 * Devolvemos 409 com o id e a versão, e NÃO gravamos nada. O painel
		 * converte a operação em atualização daquele post, com o texto atual,
		 * e só então marca a tela como salva. Nenhuma alteração se perde e
		 * nenhum segundo post nasce.
		 */
		return new WP_Error(
			'lf_ed_criacao_recuperada',
			__( 'A notícia já havia sido criada pelo pedido anterior, mas com o texto de antes. Nada foi sobrescrito — vou gravar suas alterações nela agora.', 'licia-fabio-editorial' ),
			array(
				'status'     => 409,
				'post_id'    => (int) $leitura['id'],
				'versao'     => $leitura['versao'],
				'status_post'=> $leitura['status'],
				'link'       => $leitura['link'],
				'categorias' => $leitura['categorias'],
			)
		);
	}

	$r = lf_ed_gravar_nativo( lf_ed_rota_tipo( $tipo ), $carga );

	if ( ! $r['ok'] ) {
		// Libera a chave: a tentativa legítima seguinte precisa poder usá-la.
		lf_ed_idem_liberar( $chave, $user_id );
		return $r['dados'];
	}

	$post_id = (int) $r['dados']['id'];

	lf_ed_idem_concluir( $chave, $user_id, $post_id );

	$leitura = lf_ed_ler_noticia( $post_id );

	if ( is_wp_error( $leitura ) ) {
		return new WP_REST_Response( array( 'id' => $post_id ), 201 );
	}

	$leitura['conteudo_igual'] = true;

	return new WP_REST_Response( $leitura, 201 );
}

/**
 * Abre uma notícia para edição: conteúdo e versão juntos.
 *
 * A permissão é verificada NO SERVIDOR duas vezes: `edit_post` no post
 * concreto aqui, e de novo pela rota nativa, que faz a leitura de fato em
 * contexto de edição. A versão devolvida é a mesma string que o endpoint de
 * atualização vai exigir, byte a byte — o painel a guarda e reenvia sem tocar.
 *
 * @param WP_REST_Request $req Requisição.
 * @return WP_REST_Response|WP_Error
 */
function lf_ed_route_abrir( $req ) {

	$post_id = absint( $req->get_param( 'id' ) );
	$post    = get_post( $post_id );

	if ( ! $post || ! lf_ed_tipo_editavel( $post->post_type ) ) {
		return new WP_Error(
			'lf_ed_nao_encontrada',
			__( 'Notícia não encontrada.', 'licia-fabio-editorial' ),
			array( 'status' => 404 )
		);
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return new WP_Error(
			'lf_ed_sem_permissao_post',
			__( 'Você não tem permissão para abrir esta notícia.', 'licia-fabio-editorial' ),
			array( 'status' => 403 )
		);
	}

	// Leitura pela rota nativa, em contexto de edição.
	$interna = new WP_REST_Request( 'GET', lf_ed_rota_tipo( $post->post_type, $post_id ) );
	$interna->set_param( 'context', 'edit' );

	$resposta = rest_do_request( $interna );

	if ( $resposta->is_error() ) {
		return $resposta->as_error();
	}

	$d = $resposta->get_data();

	return new WP_REST_Response(
		array(
			'id'             => (int) $d['id'],
			'titulo'         => isset( $d['title']['raw'] ) ? $d['title']['raw'] : '',
			'conteudo'       => isset( $d['content']['raw'] ) ? $d['content']['raw'] : '',
			'subtitulo'      => isset( $d['meta'][ LF_ED_META_SUBTITULO ] ) ? (string) $d['meta'][ LF_ED_META_SUBTITULO ] : '',
			'status'         => $d['status'],
			'link'           => isset( $d['link'] ) ? $d['link'] : '',
			'autor'          => isset( $d['author'] ) ? (int) $d['author'] : 0,
			'categorias'     => isset( $d['categories'] ) ? array_map( 'intval', (array) $d['categories'] ) : array(),
			'imagemDestaque' => isset( $d['featured_media'] ) ? (int) $d['featured_media'] : 0,

			'tipo'           => $post->post_type,
			'meta'           => isset( $d['meta'] ) && is_array( $d['meta'] )
				? array_intersect_key( $d['meta'], array_flip( lf_ed_meta_permitidos( $post->post_type ) ) )
				: array(),

			// A versão canônica, no formato que a atualização exige.
			'versao'         => lf_ed_versao( $post_id ),
		),
		200
	);
}

/**
 * Consulta uma operação de criação, sem criar nada.
 *
 * O painel chama isto ao recuperar um rascunho depois de recarregar a página,
 * quando havia uma criação cujo resultado ele não conhece. Três respostas:
 *
 *   - a operação chegou e criou um post → devolve id e versão, e o painel
 *     segue como ATUALIZAÇÃO daquele post;
 *   - a operação está reservada mas sem post → "em andamento", e o painel
 *     espera em vez de criar;
 *   - não existe registro → o painel pode criar com aquela chave.
 *
 * Quando o registro EXPIROU, a resposta é "desconhecida", e o painel
 * deliberadamente NÃO cria sozinho: pede ao redator que confira a lista antes,
 * porque criar às cegas um post cujo resultado anterior é desconhecido é
 * justamente como se duplica conteúdo.
 *
 * @param WP_REST_Request $req Requisição.
 * @return WP_REST_Response
 */
function lf_ed_route_operacao( $req ) {

	$chave   = (string) $req->get_param( 'chave_operacao' );
	$user_id = get_current_user_id();

	$dados = lf_ed_idem_consultar( $chave, $user_id );

	if ( ! $dados ) {

		/*
		 * Sem registro ativo: pode ser que a operação nunca tenha sido
		 * enviada, ou que o registro tenha vencido. A LÁPIDE distingue os dois
		 * casos. Na 1.0.2 a ausência era sempre lida como "nunca aconteceu", e
		 * recuperar um rascunho antigo criava uma segunda notícia.
		 */
		$lapide = lf_ed_tomb_ler( $chave, $user_id );

		if ( $lapide ) {

			$post_id = isset( $lapide['post_id'] ) ? (int) $lapide['post_id'] : 0;

			// O post ainda existe? Então a recuperação é possível.
			if ( $post_id && get_post( $post_id ) ) {

				$leitura = lf_ed_ler_noticia( $post_id );

				if ( ! is_wp_error( $leitura ) ) {
					$leitura['situacao'] = 'criada';
					$leitura['expirada'] = true;

					return new WP_REST_Response( $leitura, 200 );
				}
			}

			return new WP_REST_Response(
				array(
					'situacao' => 'desconhecida',
					'expirada' => true,
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'situacao' => 'inexistente',
				'expirada' => false,
			),
			200
		);
	}

	$post_id = isset( $dados['post_id'] ) ? (int) $dados['post_id'] : 0;

	if ( ! $post_id ) {
		return new WP_REST_Response(
			array(
				'situacao' => 'em_andamento',
				'expirada' => false,
			),
			200
		);
	}

	// O post pode ter sido apagado no intervalo.
	if ( ! get_post( $post_id ) ) {
		return new WP_REST_Response(
			array(
				'situacao' => 'desconhecida',
				'expirada' => true,
			),
			200
		);
	}

	$leitura = lf_ed_ler_noticia( $post_id );

	if ( is_wp_error( $leitura ) ) {
		return new WP_REST_Response(
			array(
				'situacao' => 'desconhecida',
				'expirada' => false,
			),
			200
		);
	}

	$leitura['situacao'] = 'criada';
	$leitura['expirada'] = false;

	return new WP_REST_Response( $leitura, 200 );
}

/**
 * Atualiza uma notícia, recusando sobrescrita de versão mais recente.
 *
 * @param WP_REST_Request $req Requisição.
 * @return WP_REST_Response|WP_Error
 */
function lf_ed_route_atualizar( $req ) {

	$post_id = absint( $req->get_param( 'id' ) );
	$versao  = $req->get_param( 'versao' );

	/*
	 * Versão ausente, vazia ou malformada é RECUSADA.
	 *
	 * A 1.0.1 tinha `if ( $versao && ... )`, o que transformava a guarda em
	 * decoração: bastava não enviar o campo para sobrescrever qualquer coisa.
	 */
	if ( ! lf_ed_versao_valida( $versao ) ) {
		return new WP_Error(
			'lf_ed_versao_invalida',
			__( 'O pedido não informou de qual versão da notícia você partiu, então nada foi gravado. Recarregue a notícia e tente de novo.', 'licia-fabio-editorial' ),
			array( 'status' => 400 )
		);
	}

	$post = get_post( $post_id );

	if ( ! $post || ! lf_ed_tipo_editavel( $post->post_type ) ) {
		return new WP_Error(
			'lf_ed_nao_encontrada',
			__( 'Notícia não encontrada.', 'licia-fabio-editorial' ),
			array( 'status' => 404 )
		);
	}

	/*
	 * Capability no post CONCRETO. A rota nativa verifica de novo lá dentro;
	 * verificar aqui evita adquirir a trava para uma requisição que já sabemos
	 * que será recusada.
	 */
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return new WP_Error(
			'lf_ed_sem_permissao_post',
			__( 'Você não tem permissão para editar esta notícia.', 'licia-fabio-editorial' ),
			array( 'status' => 403 )
		);
	}

	/*
	 * TRAVA POR POST.
	 *
	 * Comparar e gravar viram uma seção crítica: outra requisição DO PAINEL
	 * não entra aqui enquanto esta não terminar. É o que faltava na 1.0.1,
	 * onde a comparação acontecia no navegador e sobrava uma ida e volta de
	 * rede entre consultar e gravar.
	 *
	 * O alcance disso está documentado no README e não é total — o wp-admin
	 * não passa por este endpoint.
	 */
	if ( ! lf_ed_trava_adquirir( $post_id ) ) {
		return new WP_Error(
			'lf_ed_gravacao_em_curso',
			__( 'Outra pessoa está salvando esta notícia neste instante. Nada foi gravado. Aguarde alguns segundos e tente de novo — seu texto continua na tela.', 'licia-fabio-editorial' ),
			array( 'status' => 409 )
		);
	}

	// A partir daqui, toda saída precisa liberar a trava.
	$atual = lf_ed_versao( $post_id );

	if ( $versao !== $atual ) {

		lf_ed_trava_liberar( $post_id );

		return new WP_Error(
			'lf_ed_conflito',
			__( 'Esta notícia foi alterada depois que você a abriu. Para não apagar o trabalho de outra pessoa, nada foi salvo.', 'licia-fabio-editorial' ),
			array(
				'status'          => 409,
				'versao_servidor' => $atual,
				'versao_enviada'  => (string) $versao,
				'rev_servidor'    => lf_ed_versao_rev( $atual ),
				'rev_enviada'     => lf_ed_versao_rev( $versao ),
				'modificada_por'  => lf_ed_quem_modificou( $post_id ),
			)
		);
	}

	$carga = lf_ed_campos_permitidos( $req, $post->post_type );

	$valida = lf_ed_validar_carga( $post->post_type, $carga );

	if ( is_wp_error( $valida ) ) {
		lf_ed_trava_liberar( $post_id );
		return $valida;
	}

	$r = lf_ed_gravar_nativo( lf_ed_rota_tipo( $post->post_type, $post_id ), $carga );

	lf_ed_trava_liberar( $post_id );

	if ( ! $r['ok'] ) {
		return $r['dados'];
	}

	$leitura = lf_ed_ler_noticia( $post_id );

	if ( is_wp_error( $leitura ) ) {
		return new WP_REST_Response( array( 'id' => $post_id ), 200 );
	}

	$leitura['conteudo_igual'] = true;

	return new WP_REST_Response( $leitura, 200 );
}

/**
 * Quem gravou a revisão mais recente, quando dá para saber.
 *
 * Só para a mensagem de conflito ficar útil. Devolve string vazia quando as
 * revisões estão desativadas.
 *
 * @param int $post_id Post.
 * @return string
 */
function lf_ed_quem_modificou( $post_id ) {

	$revisoes = wp_get_post_revisions( $post_id, array( 'posts_per_page' => 1 ) );

	if ( ! $revisoes ) {
		return '';
	}

	$revisao = array_shift( $revisoes );
	$autor   = get_userdata( $revisao->post_author );

	return $autor ? $autor->display_name : '';
}

/**
 * Lê uma notícia no formato que o painel usa.
 *
 * Passa pela rota nativa em contexto de edição, então respeita permissão e
 * devolve exatamente os mesmos campos de uma leitura normal.
 *
 * @param int $post_id Post.
 * @return array|WP_Error
 */
function lf_ed_ler_noticia( $post_id ) {

	$tipo_post = get_post_type( (int) $post_id );

	$interna = new WP_REST_Request(
		'GET',
		lf_ed_rota_tipo( lf_ed_tipo_editavel( $tipo_post ) ? $tipo_post : 'post', (int) $post_id )
	);
	$interna->set_param( 'context', 'edit' );

	$resposta = rest_do_request( $interna );

	if ( $resposta->is_error() ) {
		return $resposta->as_error();
	}

	$dados = $resposta->get_data();

	return array(
		'id'          => (int) $dados['id'],
		'status'      => $dados['status'],
		'link'        => isset( $dados['link'] ) ? $dados['link'] : '',
		'versao'      => lf_ed_versao( $post_id ),
		'categorias'  => isset( $dados['categories'] ) ? array_map( 'intval', (array) $dados['categories'] ) : array(),
	);
}

/**
 * Crédito de uma imagem.
 *
 * @param WP_REST_Request $req Requisição.
 * @return WP_REST_Response|WP_Error
 */
function lf_ed_route_credito( $req ) {

	$id = absint( $req->get_param( 'id' ) );

	if ( ! $id || 'attachment' !== get_post_type( $id ) ) {
		return new WP_Error(
			'lf_ed_anexo_invalido',
			__( 'Imagem não encontrada.', 'licia-fabio-editorial' ),
			array( 'status' => 404 )
		);
	}

	return new WP_REST_Response(
		array(
			'id'       => $id,
			'credito'  => (string) get_post_meta( $id, LF_ED_META_CREDITO, true ),
			'legenda'  => (string) get_post_field( 'post_excerpt', $id, 'raw' ),
			'alt'      => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
		),
		200
	);
}
