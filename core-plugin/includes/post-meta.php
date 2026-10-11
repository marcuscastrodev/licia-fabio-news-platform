<?php
/**
 * Metadados editoriais: subtítulo, destaque da home, anúncios e eventos.
 *
 * Todos os campos são registrados com register_post_meta (schema, sanitização
 * e permissão) e também expostos em caixas no editor clássico, com nonce e
 * verificação de capability.
 *
 * @package licia-fabio-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Permissão de leitura/escrita dos metadados via REST.
 *
 * @param bool   $allowed  Permitido.
 * @param string $meta_key Chave.
 * @param int    $post_id  Id do post.
 * @return bool
 */
function lf_meta_auth( $allowed, $meta_key, $post_id ) {
	return current_user_can( 'edit_post', $post_id );
}

/**
 * Sanitiza a posição do destaque: inteiro de 1 a 3, ou 0 quando vazio.
 *
 * @param mixed $valor Valor recebido.
 * @return int
 */
function lf_sanitize_featured_position( $valor ) {

	$n = absint( $valor );

	if ( $n < 1 || $n > 3 ) {
		return 0;
	}

	return $n;
}

/**
 * Sanitiza uma data no formato Y-m-d. Devolve string vazia se inválida.
 *
 * @param mixed $valor Valor recebido.
 * @return string
 */
function lf_sanitize_date( $valor ) {

	$valor = sanitize_text_field( (string) $valor );

	if ( '' === $valor ) {
		return '';
	}

	$d = DateTime::createFromFormat( 'Y-m-d', $valor );

	if ( ! $d || $d->format( 'Y-m-d' ) !== $valor ) {
		return '';
	}

	return $valor;
}

/**
 * Sanitiza uma hora no formato H:i. Devolve string vazia se inválida.
 *
 * @param mixed $valor Valor recebido.
 * @return string
 */
function lf_sanitize_time( $valor ) {

	$valor = sanitize_text_field( (string) $valor );

	if ( '' === $valor ) {
		return '';
	}

	$d = DateTime::createFromFormat( 'H:i', $valor );

	if ( ! $d || $d->format( 'H:i' ) !== $valor ) {
		return '';
	}

	return $valor;
}

/**
 * Sanitiza um id de anexo, conferindo que o anexo existe.
 *
 * @param mixed $valor Valor recebido.
 * @return int
 */
function lf_sanitize_attachment_id( $valor ) {

	$id = absint( $valor );

	if ( ! $id || 'attachment' !== get_post_type( $id ) ) {
		return 0;
	}

	return $id;
}

/**
 * Registra os metadados.
 */
function lf_register_post_meta() {

	$auth = 'lf_meta_auth';

	// ---------- Notícias ----------
	register_post_meta(
		'post',
		'_lf_subtitulo',
		array(
			'type'              => 'string',
			'description'       => __( 'Subtítulo da matéria, exibido entre o título e a imagem destacada.', 'licia-fabio-core' ),
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => array(
				'schema' => array(
					'type'    => 'string',
					'context' => array( 'view', 'edit' ),
				),
			),
			'sanitize_callback' => 'sanitize_textarea_field',
			'auth_callback'     => $auth,
		)
	);

	register_post_meta(
		'post',
		'_lf_home_featured',
		array(
			'type'              => 'boolean',
			'description'       => __( 'Marca a matéria como destaque manual da página inicial.', 'licia-fabio-core' ),
			'single'            => true,
			'default'           => false,
			'show_in_rest'      => array(
				'schema' => array(
					'type'    => 'boolean',
					'context' => array( 'view', 'edit' ),
				),
			),
			'sanitize_callback' => 'rest_sanitize_boolean',
			'auth_callback'     => $auth,
		)
	);

	register_post_meta(
		'post',
		'_lf_home_featured_position',
		array(
			'type'              => 'integer',
			'description'       => __( 'Posição do destaque na home, de 1 a 3. Zero significa sem posição definida.', 'licia-fabio-core' ),
			'single'            => true,
			'default'           => 0,
			'show_in_rest'      => array(
				'schema' => array(
					'type'    => 'integer',
					'minimum' => 0,
					'maximum' => 3,
					'context' => array( 'view', 'edit' ),
				),
			),
			'sanitize_callback' => 'lf_sanitize_featured_position',
			'auth_callback'     => $auth,
		)
	);

	// ---------- Anúncios ----------
	$campos_anuncio = array(
		'_lf_ad_position'          => array( 'string', 'sanitize_text_field' ),
		'_lf_ad_desktop_image_id'  => array( 'integer', 'lf_sanitize_attachment_id' ),
		'_lf_ad_mobile_image_id'   => array( 'integer', 'lf_sanitize_attachment_id' ),
		'_lf_ad_url'               => array( 'string', 'esc_url_raw' ),
		'_lf_ad_alt'               => array( 'string', 'sanitize_text_field' ),
		'_lf_ad_start_date'        => array( 'string', 'lf_sanitize_date' ),
		'_lf_ad_end_date'          => array( 'string', 'lf_sanitize_date' ),
		'_lf_ad_active'            => array( 'boolean', 'rest_sanitize_boolean' ),
		'_lf_ad_new_tab'           => array( 'boolean', 'rest_sanitize_boolean' ),
	);

	foreach ( $campos_anuncio as $chave => $def ) {
		register_post_meta(
			'lf_anuncio',
			$chave,
			array(
				'type'              => $def[0],
				'single'            => true,
				'default'           => 'boolean' === $def[0] ? false : ( 'integer' === $def[0] ? 0 : '' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => $def[0],
						'context' => array( 'view', 'edit' ),
					),
				),
				'sanitize_callback' => $def[1],
				'auth_callback'     => $auth,
			)
		);
	}

	// ---------- Eventos ----------
	$campos_evento = array(
		'_lf_event_date'     => array( 'string', 'lf_sanitize_date' ),
		'_lf_event_time'     => array( 'string', 'lf_sanitize_time' ),
		'_lf_event_location' => array( 'string', 'sanitize_text_field' ),
		'_lf_event_address'  => array( 'string', 'sanitize_text_field' ),
		'_lf_event_url'      => array( 'string', 'esc_url_raw' ),
	);

	foreach ( $campos_evento as $chave => $def ) {
		register_post_meta(
			'lf_evento',
			$chave,
			array(
				'type'              => $def[0],
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => $def[0],
						'context' => array( 'view', 'edit' ),
					),
				),
				'sanitize_callback' => $def[1],
				'auth_callback'     => $auth,
			)
		);
	}
}
add_action( 'init', 'lf_register_post_meta' );

/* ==========================================================================
   Caixas no editor (compatíveis com o Editor Clássico)
   ========================================================================== */

/**
 * Adiciona as caixas de edição.
 */
function lf_add_meta_boxes() {

	add_meta_box(
		'lf_box_noticia',
		__( 'Portal · Subtítulo e destaque', 'licia-fabio-core' ),
		'lf_render_box_noticia',
		'post',
		'normal',
		'high'
	);

	add_meta_box(
		'lf_box_anuncio',
		__( 'Configuração do anúncio', 'licia-fabio-core' ),
		'lf_render_box_anuncio',
		'lf_anuncio',
		'normal',
		'high'
	);

	add_meta_box(
		'lf_box_evento',
		__( 'Dados do evento', 'licia-fabio-core' ),
		'lf_render_box_evento',
		'lf_evento',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'lf_add_meta_boxes' );

/**
 * Caixa da notícia: subtítulo e destaque.
 *
 * @param WP_Post $post Post em edição.
 */
function lf_render_box_noticia( $post ) {

	wp_nonce_field( 'lf_save_noticia_' . $post->ID, 'lf_noticia_nonce' );

	$sub      = (string) get_post_meta( $post->ID, '_lf_subtitulo', true );
	$featured = (bool) get_post_meta( $post->ID, '_lf_home_featured', true );
	$posicao  = (int) get_post_meta( $post->ID, '_lf_home_featured_position', true );
	$pode     = current_user_can( 'edit_post', $post->ID );
	$conflito = $featured && $posicao ? lf_featured_conflicts( $posicao, $post->ID ) : array();
	?>
	<p>
		<label for="lf_subtitulo" style="display:block;font-weight:600;margin-bottom:6px">
			<?php esc_html_e( 'Subtítulo', 'licia-fabio-core' ); ?>
		</label>
		<textarea
			id="lf_subtitulo"
			name="lf_subtitulo"
			rows="2"
			class="widefat"
			<?php disabled( ! $pode ); ?>
			placeholder="<?php esc_attr_e( 'Resuma a notícia em uma frase. Deixe vazio se não houver.', 'licia-fabio-core' ); ?>"><?php echo esc_textarea( $sub ); ?></textarea>
		<span class="description"><?php esc_html_e( 'Aparece entre o título e a foto. Quando vazio, nada é exibido no site.', 'licia-fabio-core' ); ?></span>
	</p>

	<hr>

	<p>
		<label for="lf_home_featured">
			<input type="checkbox" id="lf_home_featured" name="lf_home_featured" value="1" <?php checked( $featured ); ?> <?php disabled( ! $pode ); ?>>
			<strong><?php esc_html_e( 'Destacar na página inicial', 'licia-fabio-core' ); ?></strong>
		</label>
	</p>

	<p>
		<label for="lf_home_featured_position" style="margin-right:8px"><?php esc_html_e( 'Posição do destaque:', 'licia-fabio-core' ); ?></label>
		<select id="lf_home_featured_position" name="lf_home_featured_position" <?php disabled( ! $pode ); ?>>
			<option value="0" <?php selected( 0, $posicao ); ?>><?php esc_html_e( 'Sem posição definida', 'licia-fabio-core' ); ?></option>
			<option value="1" <?php selected( 1, $posicao ); ?>><?php esc_html_e( '1 — manchete principal', 'licia-fabio-core' ); ?></option>
			<option value="2" <?php selected( 2, $posicao ); ?>><?php esc_html_e( '2 — segunda chamada', 'licia-fabio-core' ); ?></option>
			<option value="3" <?php selected( 3, $posicao ); ?>><?php esc_html_e( '3 — terceira chamada', 'licia-fabio-core' ); ?></option>
		</select>
	</p>

	<?php if ( ! empty( $conflito ) ) : ?>
		<div class="notice notice-warning inline" style="margin:10px 0 0">
			<p>
				<strong><?php esc_html_e( 'Conflito de posição.', 'licia-fabio-core' ); ?></strong>
				<?php
				printf(
					/* translators: 1: posição, 2: lista de matérias */
					esc_html__( 'Outra matéria também está na posição %1$d: %2$s. A home mostra a atualizada mais recentemente; a outra cai para a primeira posição livre.', 'licia-fabio-core' ),
					(int) $posicao,
					esc_html( implode( ', ', $conflito ) )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<p class="description">
		<?php esc_html_e( 'As posições que ficarem sem marcação manual são preenchidas pelas notícias publicadas mais recentes, como acontecia antes.', 'licia-fabio-core' ); ?>
	</p>
	<?php
}

/**
 * Caixa do anúncio.
 *
 * @param WP_Post $post Post em edição.
 */
function lf_render_box_anuncio( $post ) {

	wp_nonce_field( 'lf_save_anuncio_' . $post->ID, 'lf_anuncio_nonce' );

	$pode      = current_user_can( 'edit_post', $post->ID );
	$posicao   = (string) get_post_meta( $post->ID, '_lf_ad_position', true );
	$desktop   = (int) get_post_meta( $post->ID, '_lf_ad_desktop_image_id', true );
	$mobile    = (int) get_post_meta( $post->ID, '_lf_ad_mobile_image_id', true );
	$url       = (string) get_post_meta( $post->ID, '_lf_ad_url', true );
	$alt       = (string) get_post_meta( $post->ID, '_lf_ad_alt', true );
	$inicio    = (string) get_post_meta( $post->ID, '_lf_ad_start_date', true );
	$fim       = (string) get_post_meta( $post->ID, '_lf_ad_end_date', true );
	$ativo     = (bool) get_post_meta( $post->ID, '_lf_ad_active', true );
	$nova_aba  = (bool) get_post_meta( $post->ID, '_lf_ad_new_tab', true );
	$posicoes  = lf_ad_positions();
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="lf_ad_position"><?php esc_html_e( 'Posição no site', 'licia-fabio-core' ); ?></label></th>
			<td>
				<select id="lf_ad_position" name="lf_ad_position" <?php disabled( ! $pode ); ?>>
					<option value=""><?php esc_html_e( '— escolha —', 'licia-fabio-core' ); ?></option>
					<?php foreach ( $posicoes as $chave => $info ) : ?>
						<option value="<?php echo esc_attr( $chave ); ?>" <?php selected( $chave, $posicao ); ?>>
							<?php echo esc_html( $info['label'] . ' · ' . $info['formato'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="lf_ad_desktop_image_id"><?php esc_html_e( 'Imagem (computador)', 'licia-fabio-core' ); ?></label></th>
			<td>
				<input type="number" id="lf_ad_desktop_image_id" name="lf_ad_desktop_image_id" value="<?php echo esc_attr( $desktop ); ?>" class="small-text" min="0" <?php disabled( ! $pode ); ?>>
				<span class="description"><?php esc_html_e( 'Id do anexo na Biblioteca de Mídia.', 'licia-fabio-core' ); ?></span>
				<?php if ( $desktop ) : ?>
					<div style="margin-top:8px"><?php echo wp_get_attachment_image( $desktop, 'medium' ); ?></div>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="lf_ad_mobile_image_id"><?php esc_html_e( 'Imagem (celular)', 'licia-fabio-core' ); ?></label></th>
			<td>
				<input type="number" id="lf_ad_mobile_image_id" name="lf_ad_mobile_image_id" value="<?php echo esc_attr( $mobile ); ?>" class="small-text" min="0" <?php disabled( ! $pode ); ?>>
				<span class="description"><?php esc_html_e( 'Opcional. Sem ela, a imagem de computador é usada também no celular.', 'licia-fabio-core' ); ?></span>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="lf_ad_url"><?php esc_html_e( 'Link de destino', 'licia-fabio-core' ); ?></label></th>
			<td><input type="url" id="lf_ad_url" name="lf_ad_url" value="<?php echo esc_attr( $url ); ?>" class="large-text" placeholder="https://" <?php disabled( ! $pode ); ?>></td>
		</tr>
		<tr>
			<th scope="row"><label for="lf_ad_alt"><?php esc_html_e( 'Texto alternativo', 'licia-fabio-core' ); ?></label></th>
			<td>
				<input type="text" id="lf_ad_alt" name="lf_ad_alt" value="<?php echo esc_attr( $alt ); ?>" class="large-text" <?php disabled( ! $pode ); ?>>
				<span class="description"><?php esc_html_e( 'Descreve o anúncio para leitores de tela.', 'licia-fabio-core' ); ?></span>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Período de exibição', 'licia-fabio-core' ); ?></th>
			<td>
				<label for="lf_ad_start_date"><?php esc_html_e( 'De', 'licia-fabio-core' ); ?></label>
				<input type="date" id="lf_ad_start_date" name="lf_ad_start_date" value="<?php echo esc_attr( $inicio ); ?>" <?php disabled( ! $pode ); ?>>
				<label for="lf_ad_end_date" style="margin-left:12px"><?php esc_html_e( 'até', 'licia-fabio-core' ); ?></label>
				<input type="date" id="lf_ad_end_date" name="lf_ad_end_date" value="<?php echo esc_attr( $fim ); ?>" <?php disabled( ! $pode ); ?>>
				<p class="description"><?php esc_html_e( 'As duas datas são opcionais. Sem data final, o anúncio fica no ar até ser desativado. Fuso do site.', 'licia-fabio-core' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Situação', 'licia-fabio-core' ); ?></th>
			<td>
				<label><input type="checkbox" name="lf_ad_active" value="1" <?php checked( $ativo ); ?> <?php disabled( ! $pode ); ?>> <?php esc_html_e( 'Ativo', 'licia-fabio-core' ); ?></label><br>
				<label style="margin-top:6px;display:inline-block"><input type="checkbox" name="lf_ad_new_tab" value="1" <?php checked( $nova_aba ); ?> <?php disabled( ! $pode ); ?>> <?php esc_html_e( 'Abrir em nova aba', 'licia-fabio-core' ); ?></label>
				<p class="description"><?php esc_html_e( 'Por padrão o link abre na mesma aba.', 'licia-fabio-core' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Caixa do evento.
 *
 * @param WP_Post $post Post em edição.
 */
function lf_render_box_evento( $post ) {

	wp_nonce_field( 'lf_save_evento_' . $post->ID, 'lf_evento_nonce' );

	$pode     = current_user_can( 'edit_post', $post->ID );
	$data     = (string) get_post_meta( $post->ID, '_lf_event_date', true );
	$hora     = (string) get_post_meta( $post->ID, '_lf_event_time', true );
	$local    = (string) get_post_meta( $post->ID, '_lf_event_location', true );
	$endereco = (string) get_post_meta( $post->ID, '_lf_event_address', true );
	$url      = (string) get_post_meta( $post->ID, '_lf_event_url', true );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="lf_event_date"><?php esc_html_e( 'Data do evento', 'licia-fabio-core' ); ?></label></th>
			<td>
				<input type="date" id="lf_event_date" name="lf_event_date" value="<?php echo esc_attr( $data ); ?>" <?php disabled( ! $pode ); ?>>
				<span class="description"><?php esc_html_e( 'A agenda ordena por esta data, não pela data de publicação.', 'licia-fabio-core' ); ?></span>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="lf_event_time"><?php esc_html_e( 'Horário', 'licia-fabio-core' ); ?></label></th>
			<td><input type="time" id="lf_event_time" name="lf_event_time" value="<?php echo esc_attr( $hora ); ?>" <?php disabled( ! $pode ); ?>></td>
		</tr>
		<tr>
			<th scope="row"><label for="lf_event_location"><?php esc_html_e( 'Local', 'licia-fabio-core' ); ?></label></th>
			<td><input type="text" id="lf_event_location" name="lf_event_location" value="<?php echo esc_attr( $local ); ?>" class="large-text" <?php disabled( ! $pode ); ?>></td>
		</tr>
		<tr>
			<th scope="row"><label for="lf_event_address"><?php esc_html_e( 'Endereço', 'licia-fabio-core' ); ?></label></th>
			<td><input type="text" id="lf_event_address" name="lf_event_address" value="<?php echo esc_attr( $endereco ); ?>" class="large-text" <?php disabled( ! $pode ); ?>></td>
		</tr>
		<tr>
			<th scope="row"><label for="lf_event_url"><?php esc_html_e( 'Link de ingressos ou informações', 'licia-fabio-core' ); ?></label></th>
			<td><input type="url" id="lf_event_url" name="lf_event_url" value="<?php echo esc_attr( $url ); ?>" class="large-text" placeholder="https://" <?php disabled( ! $pode ); ?>></td>
		</tr>
	</table>
	<?php
}

/**
 * Salva os metadados das caixas.
 *
 * @param int     $post_id Id do post.
 * @param WP_Post $post    Objeto do post.
 */
function lf_save_meta_boxes( $post_id, $post ) {

	// Autosave e revisões não salvam metadados.
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	// Permissão real do usuário sobre este post.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// ---------- Notícia ----------
	if ( 'post' === $post->post_type && isset( $_POST['lf_noticia_nonce'] ) ) {

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lf_noticia_nonce'] ) ), 'lf_save_noticia_' . $post_id ) ) {
			return;
		}

		$sub = isset( $_POST['lf_subtitulo'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lf_subtitulo'] ) ) : '';

		if ( '' === $sub ) {
			delete_post_meta( $post_id, '_lf_subtitulo' );
		} else {
			update_post_meta( $post_id, '_lf_subtitulo', $sub );
		}

		$featured = ! empty( $_POST['lf_home_featured'] );
		$posicao  = isset( $_POST['lf_home_featured_position'] ) ? lf_sanitize_featured_position( wp_unslash( $_POST['lf_home_featured_position'] ) ) : 0;

		if ( $featured ) {
			update_post_meta( $post_id, '_lf_home_featured', true );
			update_post_meta( $post_id, '_lf_home_featured_position', $posicao );
		} else {
			delete_post_meta( $post_id, '_lf_home_featured' );
			delete_post_meta( $post_id, '_lf_home_featured_position' );
		}

		lf_flush_home_cache();
	}

	// ---------- Anúncio ----------
	if ( 'lf_anuncio' === $post->post_type && isset( $_POST['lf_anuncio_nonce'] ) ) {

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lf_anuncio_nonce'] ) ), 'lf_save_anuncio_' . $post_id ) ) {
			return;
		}

		$posicoes = array_keys( lf_ad_positions() );
		$posicao  = isset( $_POST['lf_ad_position'] ) ? sanitize_text_field( wp_unslash( $_POST['lf_ad_position'] ) ) : '';

		update_post_meta( $post_id, '_lf_ad_position', in_array( $posicao, $posicoes, true ) ? $posicao : '' );
		update_post_meta( $post_id, '_lf_ad_desktop_image_id', isset( $_POST['lf_ad_desktop_image_id'] ) ? lf_sanitize_attachment_id( wp_unslash( $_POST['lf_ad_desktop_image_id'] ) ) : 0 );
		update_post_meta( $post_id, '_lf_ad_mobile_image_id', isset( $_POST['lf_ad_mobile_image_id'] ) ? lf_sanitize_attachment_id( wp_unslash( $_POST['lf_ad_mobile_image_id'] ) ) : 0 );
		update_post_meta( $post_id, '_lf_ad_url', isset( $_POST['lf_ad_url'] ) ? esc_url_raw( wp_unslash( $_POST['lf_ad_url'] ) ) : '' );
		update_post_meta( $post_id, '_lf_ad_alt', isset( $_POST['lf_ad_alt'] ) ? sanitize_text_field( wp_unslash( $_POST['lf_ad_alt'] ) ) : '' );
		update_post_meta( $post_id, '_lf_ad_start_date', isset( $_POST['lf_ad_start_date'] ) ? lf_sanitize_date( wp_unslash( $_POST['lf_ad_start_date'] ) ) : '' );
		update_post_meta( $post_id, '_lf_ad_end_date', isset( $_POST['lf_ad_end_date'] ) ? lf_sanitize_date( wp_unslash( $_POST['lf_ad_end_date'] ) ) : '' );
		update_post_meta( $post_id, '_lf_ad_active', ! empty( $_POST['lf_ad_active'] ) );
		update_post_meta( $post_id, '_lf_ad_new_tab', ! empty( $_POST['lf_ad_new_tab'] ) );

		delete_transient( 'lf_ads_by_position' );
	}

	// ---------- Evento ----------
	if ( 'lf_evento' === $post->post_type && isset( $_POST['lf_evento_nonce'] ) ) {

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lf_evento_nonce'] ) ), 'lf_save_evento_' . $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, '_lf_event_date', isset( $_POST['lf_event_date'] ) ? lf_sanitize_date( wp_unslash( $_POST['lf_event_date'] ) ) : '' );
		update_post_meta( $post_id, '_lf_event_time', isset( $_POST['lf_event_time'] ) ? lf_sanitize_time( wp_unslash( $_POST['lf_event_time'] ) ) : '' );
		update_post_meta( $post_id, '_lf_event_location', isset( $_POST['lf_event_location'] ) ? sanitize_text_field( wp_unslash( $_POST['lf_event_location'] ) ) : '' );
		update_post_meta( $post_id, '_lf_event_address', isset( $_POST['lf_event_address'] ) ? sanitize_text_field( wp_unslash( $_POST['lf_event_address'] ) ) : '' );
		update_post_meta( $post_id, '_lf_event_url', isset( $_POST['lf_event_url'] ) ? esc_url_raw( wp_unslash( $_POST['lf_event_url'] ) ) : '' );

		lf_flush_home_cache();
	}
}
add_action( 'save_post', 'lf_save_meta_boxes', 10, 2 );

/**
 * Coluna de destaque na lista de notícias, para ver rapidamente o que está na home.
 *
 * @param array $colunas Colunas.
 * @return array
 */
function lf_posts_columns( $colunas ) {

	$colunas['lf_destaque'] = __( 'Destaque', 'licia-fabio-core' );

	return $colunas;
}
add_filter( 'manage_post_posts_columns', 'lf_posts_columns' );

/**
 * Conteúdo da coluna de destaque.
 *
 * @param string $coluna  Coluna.
 * @param int    $post_id Id do post.
 */
function lf_posts_column_content( $coluna, $post_id ) {

	if ( 'lf_destaque' !== $coluna ) {
		return;
	}

	if ( ! get_post_meta( $post_id, '_lf_home_featured', true ) ) {
		echo '—';
		return;
	}

	$pos = (int) get_post_meta( $post_id, '_lf_home_featured_position', true );

	echo esc_html( $pos ? sprintf( __( 'Home · posição %d', 'licia-fabio-core' ), $pos ) : __( 'Home · sem posição', 'licia-fabio-core' ) );
}
add_action( 'manage_post_posts_custom_column', 'lf_posts_column_content', 10, 2 );

/**
 * Coluna de posição na lista de anúncios.
 *
 * @param array $colunas Colunas.
 * @return array
 */
function lf_ads_columns( $colunas ) {

	$novas = array();

	foreach ( $colunas as $chave => $rotulo ) {
		$novas[ $chave ] = $rotulo;

		if ( 'title' === $chave ) {
			$novas['lf_ad_position'] = __( 'Posição', 'licia-fabio-core' );
			$novas['lf_ad_status']   = __( 'Situação', 'licia-fabio-core' );
		}
	}

	return $novas;
}
add_filter( 'manage_lf_anuncio_posts_columns', 'lf_ads_columns' );

/**
 * Conteúdo das colunas da lista de anúncios.
 *
 * @param string $coluna  Coluna.
 * @param int    $post_id Id do post.
 */
function lf_ads_column_content( $coluna, $post_id ) {

	$posicoes = lf_ad_positions();

	if ( 'lf_ad_position' === $coluna ) {
		$pos = (string) get_post_meta( $post_id, '_lf_ad_position', true );
		echo esc_html( isset( $posicoes[ $pos ] ) ? $posicoes[ $pos ]['label'] : '—' );
	}

	if ( 'lf_ad_status' === $coluna ) {
		echo esc_html( lf_ad_status_label( $post_id ) );
	}
}
add_action( 'manage_lf_anuncio_posts_custom_column', 'lf_ads_column_content', 10, 2 );

/**
 * Coluna de data do evento.
 *
 * @param array $colunas Colunas.
 * @return array
 */
function lf_events_columns( $colunas ) {

	$novas = array();

	foreach ( $colunas as $chave => $rotulo ) {
		$novas[ $chave ] = $rotulo;

		if ( 'title' === $chave ) {
			$novas['lf_event_date'] = __( 'Data do evento', 'licia-fabio-core' );
		}
	}

	return $novas;
}
add_filter( 'manage_lf_evento_posts_columns', 'lf_events_columns' );

/**
 * Conteúdo da coluna de data do evento.
 *
 * @param string $coluna  Coluna.
 * @param int    $post_id Id do post.
 */
function lf_events_column_content( $coluna, $post_id ) {

	if ( 'lf_event_date' !== $coluna ) {
		return;
	}

	$data = (string) get_post_meta( $post_id, '_lf_event_date', true );

	if ( ! $data ) {
		echo '—';
		return;
	}

	$ts = strtotime( $data );

	echo esc_html( wp_date( 'd/m/Y', $ts ) );

	if ( $ts < strtotime( current_time( 'Y-m-d' ) ) ) {
		echo ' <em>' . esc_html__( '(passado)', 'licia-fabio-core' ) . '</em>';
	}
}
add_action( 'manage_lf_evento_posts_custom_column', 'lf_events_column_content', 10, 2 );
