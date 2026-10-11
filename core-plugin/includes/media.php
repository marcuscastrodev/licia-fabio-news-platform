<?php
/**
 * Crédito de fotografia, no anexo da Biblioteca de Mídia.
 *
 * O crédito pertence à imagem, não à matéria: a mesma foto usada em duas
 * notícias leva o mesmo crédito. Por isso o campo é registrado no anexo.
 *
 * @package licia-fabio-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra o campo no anexo, com schema e permissão.
 */
function lf_register_attachment_meta() {

	register_post_meta(
		'attachment',
		'_lf_image_credit',
		array(
			'type'              => 'string',
			'description'       => __( 'Crédito da fotografia, exibido abaixo da imagem.', 'licia-fabio-core' ),
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => array(
				'schema' => array(
					'type'    => 'string',
					'context' => array( 'view', 'edit' ),
				),
			),
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
				return current_user_can( 'edit_post', $post_id );
			},
		)
	);
}
add_action( 'init', 'lf_register_attachment_meta' );

/**
 * Acrescenta o campo à tela de edição do anexo.
 *
 * Aparece tanto na Biblioteca de Mídia quanto no painel lateral que abre ao
 * escolher uma imagem dentro do editor.
 *
 * @param array   $campos Campos do formulário.
 * @param WP_Post $anexo  Anexo em edição.
 * @return array
 */
function lf_attachment_credit_field( $campos, $anexo ) {

	$pode = current_user_can( 'edit_post', $anexo->ID );

	$campos['lf_image_credit'] = array(
		'label' => __( 'Crédito da foto', 'licia-fabio-core' ),
		'input' => 'text',
		'value' => (string) get_post_meta( $anexo->ID, '_lf_image_credit', true ),
		'helps' => __( 'Nome do fotógrafo ou da agência. Aparece abaixo da imagem no site. Deixe vazio se não houver crédito.', 'licia-fabio-core' ),
	);

	if ( ! $pode ) {
		$campos['lf_image_credit']['input'] = 'html';
		$campos['lf_image_credit']['html']  = esc_html( (string) get_post_meta( $anexo->ID, '_lf_image_credit', true ) );
	}

	return $campos;
}
add_filter( 'attachment_fields_to_edit', 'lf_attachment_credit_field', 10, 2 );

/**
 * Salva o crédito.
 *
 * @param array $post     Dados do anexo.
 * @param array $anexo    Campos enviados.
 * @return array
 */
function lf_attachment_credit_save( $post, $anexo ) {

	if ( ! isset( $anexo['lf_image_credit'] ) ) {
		return $post;
	}

	$post_id = isset( $post['ID'] ) ? (int) $post['ID'] : 0;

	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		return $post;
	}

	$credito = sanitize_text_field( $anexo['lf_image_credit'] );

	if ( '' === $credito ) {
		delete_post_meta( $post_id, '_lf_image_credit' );
	} else {
		update_post_meta( $post_id, '_lf_image_credit', $credito );
	}

	return $post;
}
add_filter( 'attachment_fields_to_save', 'lf_attachment_credit_save', 10, 2 );

/**
 * Lê o crédito de um anexo.
 *
 * @param int $attachment_id Id do anexo.
 * @return string String vazia quando não houver crédito.
 */
function lf_get_image_credit( $attachment_id ) {

	$attachment_id = (int) $attachment_id;

	if ( ! $attachment_id ) {
		return '';
	}

	$credito = get_post_meta( $attachment_id, '_lf_image_credit', true );

	return is_string( $credito ) ? trim( $credito ) : '';
}

/**
 * Legenda BRUTA de um anexo, sem passar por nenhum filtro.
 *
 * É o post_excerpt do anexo, lido direto. Existe para que o crédito nunca seja
 * acrescentado duas vezes: wp_get_attachment_caption() passa pelo filtro que
 * anexa o crédito, então usá-la como base para montar "legenda + crédito"
 * duplicaria o nome do fotógrafo.
 *
 * Também é a fonte correta de fallback para o atributo alt: o alt jamais deve
 * conter o crédito.
 *
 * @param int $attachment_id Id do anexo.
 * @return string
 */
function lf_get_raw_caption( $attachment_id ) {

	$attachment_id = (int) $attachment_id;

	if ( ! $attachment_id ) {
		return '';
	}

	$legenda = get_post_field( 'post_excerpt', $attachment_id, 'raw' );

	return is_string( $legenda ) ? trim( $legenda ) : '';
}

/**
 * Coluna de crédito na Biblioteca de Mídia, em modo lista.
 *
 * @param array $colunas Colunas.
 * @return array
 */
function lf_media_credit_column( $colunas ) {

	$colunas['lf_credit'] = __( 'Crédito', 'licia-fabio-core' );

	return $colunas;
}
add_filter( 'manage_media_columns', 'lf_media_credit_column' );

/**
 * Conteúdo da coluna de crédito.
 *
 * @param string $coluna  Coluna.
 * @param int    $post_id Id do anexo.
 */
function lf_media_credit_column_content( $coluna, $post_id ) {

	if ( 'lf_credit' !== $coluna ) {
		return;
	}

	$credito = lf_get_image_credit( $post_id );

	echo $credito ? esc_html( $credito ) : '—';
}
add_action( 'manage_media_custom_column', 'lf_media_credit_column_content', 10, 2 );
