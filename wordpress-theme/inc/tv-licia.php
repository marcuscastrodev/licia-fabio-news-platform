<?php
/**
 * TV Lícia: playlist de vídeos da página /tv-licia/.
 *
 * Os quatro vídeos iniciais estão definidos UMA vez, em lf_tv_videos_padrao().
 * Depois que alguém salvar a caixa "TV Lícia · Vídeos" na edição da página, a
 * lista passa a vir do metadado _lf_tv_videos, e o template deixa de depender
 * do código. Nenhum conteúdo é criado automaticamente.
 *
 * @package licia-fabio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Slug da página. */
define( 'LF_TV_SLUG', 'tv-licia' );

/** Metadado com a lista de vídeos. */
define( 'LF_TV_META', '_lf_tv_videos' );

/** Metadado: exibir o texto da página acima da playlist. */
define( 'LF_TV_META_TEXTO', '_lf_tv_mostrar_texto' );

/**
 * Os quatro vídeos iniciais. Fonte única.
 *
 * @return array
 */
function lf_tv_videos_padrao() {

	return array(
		array(
			'url'     => 'https://youtu.be/Bh5QUBVAgaU',
			'titulo'  => 'Programa Nomes: Entrevista com Lícia Fábio, Bloco I',
			'duracao' => '12:32',
			'ordem'   => 1,
			'visivel' => true,
		),
		array(
			'url'     => 'https://youtu.be/VFgrgzjpahk',
			'titulo'  => 'Programa Nomes: Entrevista com Lícia Fábio, Bloco II',
			'duracao' => '14:30',
			'ordem'   => 2,
			'visivel' => true,
		),
		array(
			'url'     => 'https://youtu.be/EzxqoW9Su9c',
			'titulo'  => 'Vem aí Upgrade com Lícia Fábio!',
			'duracao' => '1:15',
			'ordem'   => 3,
			'visivel' => true,
		),
		array(
			'url'     => 'https://youtu.be/1FU8tjiHgBw',
			'titulo'  => 'TV Contigo: Carnaval Salvador 2008, Lícia Fábio',
			'duracao' => '1:29',
			'ordem'   => 4,
			'visivel' => true,
		),
	);
}

/**
 * Extrai o id de 11 caracteres de um endereço do YouTube.
 *
 * Aceita youtu.be/ID, youtube.com/watch?v=ID, /embed/ID, /shorts/ID, /live/ID
 * e o id puro. Qualquer outra coisa devolve string vazia — o vídeo é ignorado
 * em vez de gerar um player quebrado.
 *
 * @param string $url Endereço.
 * @return string
 */
function lf_tv_youtube_id( $url ) {

	$url = trim( (string) $url );

	if ( preg_match( '~^[A-Za-z0-9_-]{11}$~', $url ) ) {
		return $url;
	}

	$partes = wp_parse_url( $url );

	if ( ! $partes || empty( $partes['host'] ) ) {
		return '';
	}

	$host    = strtolower( preg_replace( '~^(www\.|m\.)~i', '', $partes['host'] ) );
	$caminho = isset( $partes['path'] ) ? $partes['path'] : '';
	$bruto   = '';

	if ( 'youtu.be' === $host ) {
		$bruto = ltrim( $caminho, '/' );
	} elseif ( in_array( $host, array( 'youtube.com', 'youtube-nocookie.com' ), true ) ) {

		if ( '/watch' === $caminho && ! empty( $partes['query'] ) ) {
			parse_str( $partes['query'], $q );
			$bruto = isset( $q['v'] ) ? (string) $q['v'] : '';
		} elseif ( preg_match( '~^/(embed|shorts|live|v)/([^/?#]+)~', $caminho, $m ) ) {
			$bruto = $m[2];
		}
	}

	return preg_match( '~^([A-Za-z0-9_-]{11})~', $bruto, $m ) ? $m[1] : '';
}

/**
 * Duração no formato m:ss, mm:ss ou h:mm:ss. Inválida vira string vazia.
 *
 * @param string $valor Duração.
 * @return string
 */
function lf_tv_duracao_valida( $valor ) {

	$valor = trim( (string) $valor );

	return preg_match( '~^(\d{1,2}:)?[0-5]?\d:[0-5]\d$~', $valor ) ? $valor : '';
}

/**
 * Este post é a página da TV Lícia?
 *
 * @param WP_Post|null $post Post.
 * @return bool
 */
function lf_tv_eh_pagina( $post ) {
	return $post instanceof WP_Post && 'page' === $post->post_type && LF_TV_SLUG === $post->post_name;
}

/**
 * Linhas como estão guardadas (ou as iniciais), sem filtrar.
 *
 * Com o metadado presente — mesmo vazio — ele manda: se o administrador
 * ocultou ou removeu todos os vídeos, a página respeita.
 *
 * @param int $post_id Página.
 * @return array
 */
function lf_tv_linhas( $post_id ) {

	if ( $post_id && metadata_exists( 'post', $post_id, LF_TV_META ) ) {
		$salvas = get_post_meta( $post_id, LF_TV_META, true );
		return is_array( $salvas ) ? $salvas : array();
	}

	return lf_tv_videos_padrao();
}

/**
 * Vídeos prontos para exibir: visíveis, válidos e na ordem definida.
 *
 * @param int $post_id Página.
 * @return array Lista de arrays com id, url, titulo e duracao.
 */
function lf_tv_videos( $post_id ) {

	$saida = array();

	foreach ( lf_tv_linhas( $post_id ) as $n => $v ) {

		if ( ! is_array( $v ) || empty( $v['visivel'] ) ) {
			continue;
		}

		$id = lf_tv_youtube_id( isset( $v['url'] ) ? $v['url'] : '' );

		if ( ! $id ) {
			continue;
		}

		$saida[] = array(
			'id'      => $id,
			'url'     => 'https://www.youtube.com/watch?v=' . $id,
			'titulo'  => isset( $v['titulo'] ) ? (string) $v['titulo'] : '',
			'duracao' => lf_tv_duracao_valida( isset( $v['duracao'] ) ? $v['duracao'] : '' ),
			'ordem'   => isset( $v['ordem'] ) ? (int) $v['ordem'] : 0,
			'_n'      => $n,
		);
	}

	// Ordem estável: empate mantém a sequência cadastrada (usort não é estável no PHP 7.4).
	usort(
		$saida,
		function ( $a, $b ) {
			return ( $a['ordem'] === $b['ordem'] ) ? ( $a['_n'] - $b['_n'] ) : ( $a['ordem'] - $b['ordem'] );
		}
	);

	return $saida;
}

/**
 * Usa page-tv-licia.php mesmo que a página tenha um template do Elementor.
 *
 * A página importada do site antigo usa o template "elementor_header_footer".
 * Com o Elementor ativo no staging, ele assumiria a renderização e o template
 * do tema nunca seria usado. Este filtro roda depois e aponta para o template
 * do tema — só nesta página. O conteúdo salvo não é alterado.
 *
 * @param string $template Template escolhido.
 * @return string
 */
function lf_tv_template( $template ) {

	if ( ! is_page( LF_TV_SLUG ) ) {
		return $template;
	}

	$nosso = locate_template( 'page-tv-licia.php' );

	return $nosso ? $nosso : $template;
}
add_filter( 'template_include', 'lf_tv_template', 999 );

/**
 * Script da playlist: só nesta página.
 */
function lf_tv_assets() {

	if ( ! is_page( LF_TV_SLUG ) ) {
		return;
	}

	wp_enqueue_script(
		'lf-tv-licia',
		get_template_directory_uri() . '/assets/js/tv-licia.js',
		array(),
		LF_THEME_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'lf_tv_assets' );

/* -------------------------------------------------------------------------
 * Administração
 * ---------------------------------------------------------------------- */

/**
 * Caixa de vídeos, só na edição da página tv-licia.
 *
 * @param WP_Post $post Página.
 */
function lf_tv_add_metabox( $post ) {

	if ( ! lf_tv_eh_pagina( $post ) ) {
		return;
	}

	add_meta_box( 'lf-tv-videos', __( 'TV Lícia · Vídeos', 'licia-fabio' ), 'lf_tv_render_metabox', 'page', 'normal', 'high' );
}
add_action( 'add_meta_boxes_page', 'lf_tv_add_metabox' );

/**
 * Formulário da caixa.
 *
 * @param WP_Post $post Página.
 */
function lf_tv_render_metabox( $post ) {

	wp_nonce_field( 'lf_tv_salvar_' . $post->ID, 'lf_tv_nonce' );

	$linhas = array_values( lf_tv_linhas( $post->ID ) );

	// Três linhas vazias para acrescentar vídeos.
	for ( $i = 0; $i < 3; $i++ ) {
		$linhas[] = array( 'url' => '', 'titulo' => '', 'duracao' => '', 'ordem' => '', 'visivel' => true );
	}

	$texto = (bool) get_post_meta( $post->ID, LF_TV_META_TEXTO, true );
	?>
	<p class="description" style="margin-bottom:12px">
		<?php esc_html_e( 'Cole o endereço do YouTube, o título e a duração (ex.: 12:32). A ordem é pelo número; linhas sem endereço são descartadas ao salvar. Desmarque "Visível" para ocultar um vídeo sem apagá-lo.', 'licia-fabio' ); ?>
	</p>
	<table class="widefat striped" role="presentation">
		<thead>
			<tr>
				<th style="width:60px"><?php esc_html_e( 'Ordem', 'licia-fabio' ); ?></th>
				<th><?php esc_html_e( 'Endereço do YouTube', 'licia-fabio' ); ?></th>
				<th><?php esc_html_e( 'Título', 'licia-fabio' ); ?></th>
				<th style="width:90px"><?php esc_html_e( 'Duração', 'licia-fabio' ); ?></th>
				<th style="width:70px"><?php esc_html_e( 'Visível', 'licia-fabio' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $linhas as $i => $v ) : ?>
				<tr>
					<td><input type="number" min="0" step="1" style="width:56px" name="lf_tv[<?php echo (int) $i; ?>][ordem]" value="<?php echo esc_attr( isset( $v['ordem'] ) ? $v['ordem'] : '' ); ?>"></td>
					<td><input type="url" class="widefat" name="lf_tv[<?php echo (int) $i; ?>][url]" value="<?php echo esc_attr( isset( $v['url'] ) ? $v['url'] : '' ); ?>" placeholder="https://youtu.be/…"></td>
					<td><input type="text" class="widefat" maxlength="200" name="lf_tv[<?php echo (int) $i; ?>][titulo]" value="<?php echo esc_attr( isset( $v['titulo'] ) ? $v['titulo'] : '' ); ?>"></td>
					<td><input type="text" style="width:80px" maxlength="8" name="lf_tv[<?php echo (int) $i; ?>][duracao]" value="<?php echo esc_attr( isset( $v['duracao'] ) ? $v['duracao'] : '' ); ?>" placeholder="0:00"></td>
					<td><input type="checkbox" name="lf_tv[<?php echo (int) $i; ?>][visivel]" value="1" <?php checked( ! empty( $v['visivel'] ) ); ?>></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p>
		<label><input type="checkbox" name="lf_tv_mostrar_texto" value="1" <?php checked( $texto ); ?>> <?php esc_html_e( 'Exibir o texto desta página acima da playlist', 'licia-fabio' ); ?></label><br>
		<span class="description"><?php esc_html_e( 'Deixe desmarcado se o texto vier do construtor antigo: ele não é exibido pelo tema novo.', 'licia-fabio' ); ?></span>
	</p>
	<p>
		<label><input type="checkbox" name="lf_tv_restaurar" value="1"> <?php esc_html_e( 'Restaurar os quatro vídeos originais ao salvar', 'licia-fabio' ); ?></label>
	</p>
	<?php
}

/**
 * Salva a caixa: nonce, permissão e sanitização campo a campo.
 *
 * @param int     $post_id Página.
 * @param WP_Post $post    Objeto.
 */
function lf_tv_salvar( $post_id, $post ) {

	if ( ! isset( $_POST['lf_tv_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lf_tv_nonce'] ) ), 'lf_tv_salvar_' . $post_id ) ) {
		return;
	}

	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) || ! lf_tv_eh_pagina( $post ) ) {
		return;
	}

	update_post_meta( $post_id, LF_TV_META_TEXTO, empty( $_POST['lf_tv_mostrar_texto'] ) ? 0 : 1 );

	if ( ! empty( $_POST['lf_tv_restaurar'] ) ) {
		delete_post_meta( $post_id, LF_TV_META );
		return;
	}

	$brutas    = isset( $_POST['lf_tv'] ) && is_array( $_POST['lf_tv'] ) ? wp_unslash( $_POST['lf_tv'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitizado campo a campo abaixo.
	$limpas    = array();
	$ignoradas = 0;

	foreach ( $brutas as $linha ) {

		if ( ! is_array( $linha ) ) {
			continue;
		}

		$url = isset( $linha['url'] ) ? esc_url_raw( trim( (string) $linha['url'] ) ) : '';

		if ( '' === $url ) {
			continue;
		}

		if ( ! lf_tv_youtube_id( $url ) ) {
			$ignoradas++;
			continue;
		}

		$limpas[] = array(
			'url'     => $url,
			'titulo'  => isset( $linha['titulo'] ) ? mb_substr( sanitize_text_field( $linha['titulo'] ), 0, 200 ) : '',
			'duracao' => lf_tv_duracao_valida( isset( $linha['duracao'] ) ? sanitize_text_field( $linha['duracao'] ) : '' ),
			'ordem'   => isset( $linha['ordem'] ) && '' !== $linha['ordem'] ? absint( $linha['ordem'] ) : 999,
			'visivel' => ! empty( $linha['visivel'] ),
		);
	}

	// Ordena e renumera 1, 2, 3… para a próxima edição ficar limpa.
	$n = 0;
	foreach ( $limpas as $k => $v ) {
		$limpas[ $k ]['_n'] = $n++;
	}
	usort(
		$limpas,
		function ( $a, $b ) {
			return ( $a['ordem'] === $b['ordem'] ) ? ( $a['_n'] - $b['_n'] ) : ( $a['ordem'] - $b['ordem'] );
		}
	);
	foreach ( $limpas as $k => $v ) {
		unset( $limpas[ $k ]['_n'] );
		$limpas[ $k ]['ordem'] = $k + 1;
	}

	update_post_meta( $post_id, LF_TV_META, $limpas );

	if ( $ignoradas ) {
		set_transient( 'lf_tv_aviso_' . get_current_user_id(), $ignoradas, 60 );
	}
}
add_action( 'save_post_page', 'lf_tv_salvar', 10, 2 );

/**
 * Avisa quando algum endereço foi ignorado por não ser do YouTube.
 */
function lf_tv_aviso() {

	$chave = 'lf_tv_aviso_' . get_current_user_id();
	$n     = (int) get_transient( $chave );

	if ( ! $n ) {
		return;
	}

	delete_transient( $chave );
	?>
	<div class="notice notice-warning is-dismissible">
		<p>
			<?php
			printf(
				/* translators: %d: quantidade de linhas */
				esc_html( _n( 'TV Lícia: %d endereço não foi reconhecido como vídeo do YouTube e foi descartado.', 'TV Lícia: %d endereços não foram reconhecidos como vídeos do YouTube e foram descartados.', $n, 'licia-fabio' ) ),
				(int) $n
			);
			?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'lf_tv_aviso' );
