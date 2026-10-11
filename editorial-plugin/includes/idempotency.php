<?php
/**
 * Idempotência na criação e guarda de versão na atualização.
 *
 * Duas coisas distintas vivem aqui.
 *
 * IDEMPOTÊNCIA. O caso que a trava de botão não cobre: o servidor CRIA a
 * notícia e a resposta se perde. O redator vê falha e tenta de novo. Uma chave
 * de operação enviada pelo navegador resolve isso — a repetição da mesma chave
 * devolve o mesmo post em vez de criar outro.
 *
 * A chave sozinha, porém, não basta, e foi o defeito da 1.0.1: se o redator
 * ALTERA o texto antes de tentar de novo, devolver o post antigo e marcar a
 * tela como salva é falso sucesso — o WordPress ficou com o texto A e o painel
 * diz que B está salvo. Por isso a chave carrega também a IMPRESSÃO do payload
 * enviado. Payload diferente na mesma chave não é repetição: é uma alteração
 * posterior, e a resposta diz isso ao painel, que converte a operação em
 * atualização do mesmo post.
 *
 * GUARDA DE VERSÃO. post_modified_gmt tem resolução de um segundo, e duas
 * gravações no mesmo segundo são indistinguíveis por ele. Então mantemos um
 * CONTADOR por post, incrementado em toda gravação por qualquer caminho —
 * painel, wp-admin, REST nativa, outro plugin. É esse contador que a
 * atualização compara, dentro de uma trava por post.
 *
 * @package licia-fabio-editorial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Quanto tempo uma chave de operação é lembrada. */
define( 'LF_ED_IDEM_TTL', 24 * HOUR_IN_SECONDS );

/** Prefixo das opções de idempotência. */
define( 'LF_ED_IDEM_PREFIXO', 'lf_ed_idem_' );

/**
 * Prefixo das LÁPIDES de operação.
 *
 * Quando o registro de uma operação vence e é removido, fica uma lápide no
 * lugar: um registro mínimo dizendo "esta chave já foi usada, e o resultado
 * foi este". Sem ela — o defeito da 1.0.2 — a ausência de registro era lida
 * como "nunca aconteceu", e recuperar um rascunho antigo criava uma segunda
 * notícia.
 *
 * A lápide guarda apenas o id do post (quando conhecido) e o momento. É muito
 * menor que o registro completo, e por isso pode viver bem mais tempo.
 */
define( 'LF_ED_TOMB_PREFIXO', 'lf_ed_idemx_' );

/** Quanto tempo uma lápide é guardada. */
define( 'LF_ED_TOMB_TTL', 90 * DAY_IN_SECONDS );

/** Prefixo das travas de gravação por post. */
define( 'LF_ED_TRAVA_PREFIXO', 'lf_ed_trava_' );

/** Meta do contador de revisão. */
define( 'LF_ED_META_REV', '_lf_ed_rev' );

/* -------------------------------------------------------------------------
 * Contador de revisão
 * ---------------------------------------------------------------------- */

/**
 * Incrementa o contador de revisão a cada gravação de post.
 *
 * Enganchado em `save_post`, que dispara em TODOS os caminhos de gravação:
 * wp-admin, REST nativa, o endpoint deste painel, WP-CLI e outros plugins. É
 * o que faz o contador detectar alteração feita fora do painel, e o que
 * permite distinguir duas gravações ocorridas no MESMO segundo — coisa que
 * post_modified_gmt, com resolução de um segundo, não consegue.
 *
 * Revisões e autosaves não contam: não alteram o conteúdo publicado.
 *
 * @param int     $post_id Post gravado.
 * @param WP_Post $post    Objeto.
 */
function lf_ed_bump_rev( $post_id, $post = null ) {

	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	// Notícias, eventos e anúncios: os três tipos que o painel edita.
	if ( $post && ! in_array( $post->post_type, array( 'post', 'lf_evento', 'lf_anuncio' ), true ) ) {
		return;
	}

	$atual = (int) get_post_meta( $post_id, LF_ED_META_REV, true );

	update_post_meta( $post_id, LF_ED_META_REV, $atual + 1 );
}
add_action( 'save_post', 'lf_ed_bump_rev', 99, 2 );

/**
 * Versão atual de um post, para comparação de concorrência.
 *
 * Formato: `{contador}:{post_modified_gmt}`.
 *
 * O contador é a parte que importa — ele muda a cada gravação, mesmo que duas
 * aconteçam no mesmo segundo. O timestamp vai junto só para a mensagem de
 * conflito ficar legível para quem for depurar.
 *
 * Posts do acervo que nunca passaram por uma gravação com este plugin ativo
 * não têm o meta ainda; nesse caso o contador vale 0, e a primeira gravação o
 * cria. Isso não quebra a comparação: o painel leu 0 e o servidor vê 0.
 *
 * @param int $post_id Post.
 * @return string
 */
function lf_ed_versao( $post_id ) {

	$post_id = (int) $post_id;

	$rev      = (int) get_post_meta( $post_id, LF_ED_META_REV, true );
	$momento  = (string) get_post_field( 'post_modified_gmt', $post_id, 'raw' );

	return $rev . ':' . $momento;
}

/**
 * A versão recebida tem formato válido?
 *
 * Versão ausente, vazia ou malformada é RECUSADA — não tratada como "pode
 * gravar". Aceitá-la vazia, como a 1.0.1 fazia, transformava a guarda em
 * decoração: bastava não enviar o campo para sobrescrever qualquer coisa.
 *
 * @param mixed $valor Valor recebido.
 * @return bool
 */
function lf_ed_versao_valida( $valor ) {
	return is_string( $valor ) && (bool) preg_match( '/^\d+:\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $valor );
}

/**
 * Apenas o contador de uma string de versão.
 *
 * @param string $versao Versão.
 * @return int
 */
function lf_ed_versao_rev( $versao ) {

	$partes = explode( ':', (string) $versao, 2 );

	return (int) $partes[0];
}

/* -------------------------------------------------------------------------
 * Trava por post
 * ---------------------------------------------------------------------- */

/**
 * Tenta adquirir a trava de gravação de um post.
 *
 * `add_option()` é a primitiva atômica: grava somente se a opção ainda não
 * existe e devolve false caso exista. Duas requisições do painel disputando o
 * mesmo post não conseguem entrar as duas.
 *
 * Dentro da trava, o endpoint releia o contador e grave — comparação e
 * gravação passam a ser uma seção crítica em relação a outras gravações DO
 * PAINEL. O limite disso está documentado no README, na seção de concorrência,
 * e não é pequeno: o wp-admin não passa por aqui.
 *
 * @param int $post_id Post.
 * @return bool Conseguiu a trava?
 */
function lf_ed_trava_adquirir( $post_id ) {

	$opcao = LF_ED_TRAVA_PREFIXO . (int) $post_id;

	// Trava órfã de um processo que morreu: expira em 30 s.
	$existente = get_option( $opcao );

	if ( is_array( $existente ) && ! empty( $existente['quando'] ) && ( time() - (int) $existente['quando'] ) > 30 ) {
		delete_option( $opcao );
	}

	return (bool) add_option(
		$opcao,
		array(
			'quando'  => time(),
			'usuario' => get_current_user_id(),
		),
		'',
		'no'
	);
}

/**
 * Libera a trava de um post.
 *
 * @param int $post_id Post.
 */
function lf_ed_trava_liberar( $post_id ) {
	delete_option( LF_ED_TRAVA_PREFIXO . (int) $post_id );
}

/* -------------------------------------------------------------------------
 * Chaves de operação
 * ---------------------------------------------------------------------- */

/**
 * Nome da opção que guarda a relação chave → post.
 *
 * A chave é hasheada junto do id do usuário: a chave de operação de uma pessoa
 * nunca resolve para o post de outra, mesmo em colisão improvável.
 *
 * @param string $chave   Chave enviada pelo cliente.
 * @param int    $user_id Usuário.
 * @return string
 */
function lf_ed_idem_option( $chave, $user_id ) {
	return LF_ED_IDEM_PREFIXO . substr( hash( 'sha256', $user_id . '|' . $chave ), 0, 32 );
}

/**
 * Impressão de um payload de criação.
 *
 * Identifica o CONTEÚDO da operação, não só a intenção. É o que distingue
 * "esta é a mesma requisição, repetida" de "o texto mudou depois que eu
 * mandei" — e é o que elimina o falso sucesso da 1.0.1.
 *
 * Os campos são ordenados antes do hash, então uma diferença de ordem de
 * chaves no JSON não produz impressões diferentes.
 *
 * @param array $carga Campos enviados.
 * @return string
 */
function lf_ed_impressao( $carga ) {

	$normalizada = array();

	foreach ( array( 'title', 'content', 'status', 'categories', 'featured_media', 'meta', 'excerpt' ) as $campo ) {

		if ( ! isset( $carga[ $campo ] ) ) {
			continue;
		}

		$valor = $carga[ $campo ];

		if ( is_array( $valor ) ) {
			// Ordena para que a ordem dos elementos não mude a impressão.
			if ( wp_is_numeric_array( $valor ) ) {
				sort( $valor );
			} else {
				ksort( $valor );
			}
		}

		$normalizada[ $campo ] = $valor;
	}

	return hash( 'sha256', (string) wp_json_encode( $normalizada ) );
}

/**
 * Tenta reservar uma chave de operação.
 *
 * @param string $chave     Chave enviada pelo cliente.
 * @param int    $user_id   Usuário.
 * @param string $impressao Impressão do payload desta tentativa.
 * @return array {
 *     @type bool   $reservada     Conseguimos a reserva?
 *     @type int    $post_id       Id já associado, quando houver.
 *     @type string $impressao     Impressão registrada na primeira tentativa.
 *     @type bool   $mesmo_payload A tentativa atual tem o mesmo payload?
 * }
 */
function lf_ed_idem_reservar( $chave, $user_id, $impressao ) {

	$opcao = lf_ed_idem_option( $chave, $user_id );

	wp_cache_delete( $opcao, 'options' );

	$existente = get_option( $opcao );

	if ( false !== $existente ) {

		$registrada = is_array( $existente ) && isset( $existente['impressao'] ) ? (string) $existente['impressao'] : '';

		return array(
			'reservada'     => false,
			'post_id'       => is_array( $existente ) ? (int) $existente['post_id'] : 0,
			'impressao'     => $registrada,
			'mesmo_payload' => ( '' !== $registrada && $registrada === $impressao ),
		);
	}

	/*
	 * autoload 'no': são muitas opções, de vida curta, e não devem entrar no
	 * carregamento de toda requisição do site.
	 */
	$ganhou = add_option(
		$opcao,
		array(
			'post_id'   => 0,
			'impressao' => $impressao,
			'criada'    => time(),
		),
		'',
		'no'
	);

	if ( ! $ganhou ) {

		// Outra requisição reservou entre a leitura e a escrita.
		wp_cache_delete( $opcao, 'options' );

		$agora      = get_option( $opcao );
		$registrada = is_array( $agora ) && isset( $agora['impressao'] ) ? (string) $agora['impressao'] : '';

		return array(
			'reservada'     => false,
			'post_id'       => is_array( $agora ) ? (int) $agora['post_id'] : 0,
			'impressao'     => $registrada,
			'mesmo_payload' => ( '' !== $registrada && $registrada === $impressao ),
		);
	}

	return array(
		'reservada'     => true,
		'post_id'       => 0,
		'impressao'     => $impressao,
		'mesmo_payload' => true,
	);
}

/**
 * Registra o post criado para uma chave de operação.
 *
 * Preserva a impressão registrada na reserva — ela é a identidade do payload
 * imutável daquela operação.
 *
 * @param string $chave   Chave.
 * @param int    $user_id Usuário.
 * @param int    $post_id Post criado.
 */
function lf_ed_idem_concluir( $chave, $user_id, $post_id ) {

	$opcao     = lf_ed_idem_option( $chave, $user_id );
	$existente = get_option( $opcao );

	update_option(
		$opcao,
		array(
			'post_id'   => (int) $post_id,
			'impressao' => is_array( $existente ) && isset( $existente['impressao'] ) ? $existente['impressao'] : '',
			'criada'    => is_array( $existente ) && ! empty( $existente['criada'] ) ? (int) $existente['criada'] : time(),
		),
		'no'
	);
}

/**
 * Libera uma reserva que não resultou em post.
 *
 * Sem isto, uma criação recusada por validação deixaria a chave travada, e a
 * segunda tentativa legítima do redator seria rejeitada.
 *
 * @param string $chave   Chave.
 * @param int    $user_id Usuário.
 */
function lf_ed_idem_liberar( $chave, $user_id ) {
	delete_option( lf_ed_idem_option( $chave, $user_id ) );
}

/**
 * Espera curta pelo id de uma operação que outra requisição está executando.
 *
 * Cenário: duas requisições simultâneas com a mesma chave. A que perdeu o
 * add_option espera pelo id da outra em vez de devolver erro — assim o duplo
 * clique real termina com as duas respostas apontando para o mesmo post.
 *
 * Espera no máximo cerca de 3 segundos. Não é fila nem bloqueio: se o id não
 * aparecer, devolvemos 409 e o painel manda recarregar, em vez de arriscar
 * criar um segundo post.
 *
 * @param string $chave   Chave.
 * @param int    $user_id Usuário.
 * @return int Id do post, ou 0.
 */
function lf_ed_idem_aguardar( $chave, $user_id ) {

	$opcao = lf_ed_idem_option( $chave, $user_id );

	for ( $i = 0; $i < 10; $i++ ) {

		wp_cache_delete( $opcao, 'options' );

		$dados = get_option( $opcao );

		if ( is_array( $dados ) && ! empty( $dados['post_id'] ) ) {
			return (int) $dados['post_id'];
		}

		usleep( 300000 ); // 0,3 s
	}

	return 0;
}

/**
 * Consulta o registro de uma chave, sem reservar.
 *
 * Usado pelo endpoint de consulta: o painel pergunta "esta operação chegou?"
 * antes de decidir se cria ou se atualiza. É o que evita criar uma segunda
 * notícia quando o resultado da primeira é desconhecido.
 *
 * @param string $chave   Chave.
 * @param int    $user_id Usuário.
 * @return array|null
 */
function lf_ed_idem_consultar( $chave, $user_id ) {

	$opcao = lf_ed_idem_option( $chave, $user_id );

	wp_cache_delete( $opcao, 'options' );

	$dados = get_option( $opcao );

	return is_array( $dados ) ? $dados : null;
}

/* -------------------------------------------------------------------------
 * Lápides
 * ---------------------------------------------------------------------- */

/**
 * Nome da opção de lápide.
 *
 * @param string $chave   Chave.
 * @param int    $user_id Usuário.
 * @return string
 */
function lf_ed_tomb_option( $chave, $user_id ) {
	return LF_ED_TOMB_PREFIXO . substr( hash( 'sha256', $user_id . '|' . $chave ), 0, 32 );
}

/**
 * Grava a lápide de uma operação que está sendo expirada.
 *
 * @param string $chave   Chave.
 * @param int    $user_id Usuário.
 * @param int    $post_id Post criado, ou 0 se o resultado é desconhecido.
 */
function lf_ed_tomb_gravar( $chave, $user_id, $post_id ) {

	update_option(
		lf_ed_tomb_option( $chave, $user_id ),
		array(
			'post_id'  => (int) $post_id,
			'expirada' => time(),
		),
		'no'
	);
}

/**
 * Lê a lápide de uma chave, se existir.
 *
 * @param string $chave   Chave.
 * @param int    $user_id Usuário.
 * @return array|null
 */
function lf_ed_tomb_ler( $chave, $user_id ) {

	$opcao = lf_ed_tomb_option( $chave, $user_id );

	wp_cache_delete( $opcao, 'options' );

	$dados = get_option( $opcao );

	return is_array( $dados ) ? $dados : null;
}

/* -------------------------------------------------------------------------
 * Limpeza
 * ---------------------------------------------------------------------- */

/**
 * Limpa chaves de operação e travas vencidas.
 *
 * A 1.0.1 usava wp_load_alloptions() aqui, o que estava errado: aquela função
 * devolve apenas as opções com autoload ativo, e estas são gravadas com
 * autoload 'no'. A limpeza praticamente nunca encontrava nada, e os registros
 * acumulavam.
 *
 * Agora a busca é uma consulta preparada pelo nome da opção, que encontra os
 * registros independentemente de autoload. A REMOÇÃO continua sendo por
 * delete_option(), então os hooks e a invalidação de cache do WordPress
 * acontecem normalmente — nada de DELETE direto na tabela.
 *
 * Processa em lotes limitados, para não pesar em instalação com histórico
 * grande, e remove SOMENTE registros deste plugin, SOMENTE vencidos.
 *
 * @param int $lote Quantos registros examinar por execução.
 * @return int Quantos foram removidos.
 */
function lf_ed_idem_limpar( $lote = 200 ) {

	global $wpdb;

	$lote  = max( 1, min( 1000, (int) $lote ) );
	$corte = time() - LF_ED_IDEM_TTL;

	$nomes = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id ASC LIMIT %d",
			$wpdb->esc_like( LF_ED_IDEM_PREFIXO ) . '%',
			$lote
		)
	);

	$removidos = 0;

	/*
	 * Atenção ao prefixo: 'lf_ed_idemx_' também casa com 'lf_ed_idem_%'. As
	 * lápides são filtradas aqui para não serem tratadas como registros de
	 * operação — e são limpas na passagem própria, mais abaixo.
	 */
	$nomes = array_filter(
		(array) $nomes,
		function ( $nome ) {
			return 0 !== strpos( $nome, LF_ED_TOMB_PREFIXO );
		}
	);

	foreach ( (array) $nomes as $nome ) {

		// Cinto de segurança: nunca toca em opção que não seja deste prefixo.
		if ( 0 !== strpos( $nome, LF_ED_IDEM_PREFIXO ) ) {
			continue;
		}

		$dados = get_option( $nome );

		if ( ! is_array( $dados ) || empty( $dados['criada'] ) ) {
			continue;
		}

		// Ainda dentro da validade: pode ser recuperável, fica.
		if ( (int) $dados['criada'] >= $corte ) {
			continue;
		}

		/*
		 * Operação EM ANDAMENTO (reservada, sem post ainda) e recente é
		 * preservada mesmo passando do corte: apagá-la faria a próxima
		 * tentativa do redator criar uma segunda notícia — exatamente a
		 * duplicação que a chave existe para impedir.
		 */
		if ( empty( $dados['post_id'] ) && ( time() - (int) $dados['criada'] ) < ( LF_ED_IDEM_TTL + HOUR_IN_SECONDS ) ) {
			continue;
		}

		/*
		 * LÁPIDE antes de remover.
		 *
		 * O registro completo sai, mas fica a marca de que esta chave já foi
		 * usada e qual foi o desfecho. É o que impede a consulta de concluir
		 * "nunca aconteceu" e o endpoint de criar uma segunda notícia.
		 *
		 * O nome da opção já é o hash de chave + usuário, então a lápide é
		 * derivável do mesmo par sem guardar a chave em claro.
		 */
		$opcao_tomb = LF_ED_TOMB_PREFIXO . substr( $nome, strlen( LF_ED_IDEM_PREFIXO ) );

		update_option(
			$opcao_tomb,
			array(
				'post_id'  => isset( $dados['post_id'] ) ? (int) $dados['post_id'] : 0,
				'expirada' => time(),
			),
			'no'
		);

		delete_option( $nome );
		$removidos++;
	}

	/*
	 * Lápides muito antigas.
	 *
	 * Passados 90 dias, a chance de alguém recuperar um rascunho local daquela
	 * época é nula: o rascunho no navegador vence em 7 dias. Remover a lápide
	 * então não reintroduz duplicação.
	 */
	$corte_tomb = time() - LF_ED_TOMB_TTL;

	$lapides = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id ASC LIMIT %d",
			$wpdb->esc_like( LF_ED_TOMB_PREFIXO ) . '%',
			$lote
		)
	);

	foreach ( (array) $lapides as $nome ) {

		if ( 0 !== strpos( $nome, LF_ED_TOMB_PREFIXO ) ) {
			continue;
		}

		$dados = get_option( $nome );

		if ( is_array( $dados ) && ! empty( $dados['expirada'] ) && (int) $dados['expirada'] < $corte_tomb ) {
			delete_option( $nome );
			$removidos++;
		}
	}

	// Travas órfãs: vida curta, 30 s já bastam.
	$travas = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id ASC LIMIT %d",
			$wpdb->esc_like( LF_ED_TRAVA_PREFIXO ) . '%',
			$lote
		)
	);

	foreach ( (array) $travas as $nome ) {

		if ( 0 !== strpos( $nome, LF_ED_TRAVA_PREFIXO ) ) {
			continue;
		}

		$dados = get_option( $nome );

		if ( is_array( $dados ) && ! empty( $dados['quando'] ) && ( time() - (int) $dados['quando'] ) > 60 ) {
			delete_option( $nome );
			$removidos++;
		}
	}

	return $removidos;
}
add_action( 'lf_ed_limpeza_diaria', 'lf_ed_idem_limpar' );

/**
 * Garante o agendamento da limpeza.
 *
 * Chamado na ativação E na troca de versão do plugin. A 1.0.1 só agendava na
 * ativação, então uma atualização por cima — o caminho normal de instalar uma
 * versão nova — não criava o agendamento em quem nunca tinha ativado a versão
 * anterior.
 */
function lf_ed_agendar_limpeza() {

	if ( ! wp_next_scheduled( 'lf_ed_limpeza_diaria' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'lf_ed_limpeza_diaria' );
	}
}
