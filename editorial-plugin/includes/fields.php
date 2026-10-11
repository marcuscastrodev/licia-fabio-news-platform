<?php
/**
 * Campos reutilizados do plugin Core.
 *
 * Os nomes e tipos abaixo foram CONFERIDOS no código do
 * licia-fabio-core 1.0.3-staging, não deduzidos da documentação:
 *
 *   _lf_subtitulo      post        string, register_post_meta, show_in_rest,
 *                                  sanitize_textarea_field, auth por edit_post
 *                                  → includes/post-meta.php, lf_register_post_meta()
 *
 *   _lf_image_credit   attachment  string, register_post_meta, show_in_rest,
 *                                  sanitize_text_field
 *                                  → includes/media.php
 *
 * Nenhum campo é redeclarado aqui: registrar de novo criaria dois donos para o
 * mesmo dado. O painel escreve nesses metadados pelas rotas NATIVAS
 * /wp/v2/posts e /wp/v2/media, que já aplicam o schema, a sanitização e a
 * permissão definidos pelo Core.
 *
 * @package licia-fabio-editorial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Meta do subtítulo, no plugin Core. */
define( 'LF_ED_META_SUBTITULO', '_lf_subtitulo' );

/** Meta do crédito da foto, no plugin Core (no anexo, não no post). */
define( 'LF_ED_META_CREDITO', '_lf_image_credit' );

/**
 * O plugin Core está ativo e com os campos registrados?
 *
 * Sem ele, subtítulo e crédito não têm onde ser gravados. O painel funciona
 * para título, corpo, categoria e imagem — e avisa o que falta, em vez de
 * gravar em um campo inventado.
 *
 * @return bool
 */
function lf_ed_core_ativo() {
	return defined( 'LF_CORE_VERSION' );
}

/**
 * Aviso de ausência do Core.
 */
function lf_ed_core_notice() {

	if ( lf_ed_core_ativo() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	?>
	<div class="notice notice-warning">
		<p>
			<strong><?php esc_html_e( 'Painel Editorial: plugin Core inativo.', 'licia-fabio-editorial' ); ?></strong>
			<?php esc_html_e( 'Os campos de subtítulo e crédito da foto pertencem ao plugin Lícia Fábio Core. Sem ele ativo, esses dois campos ficam indisponíveis no painel — nada é gravado em campo alternativo. Título, corpo, categoria e imagem destacada continuam funcionando.', 'licia-fabio-editorial' ); ?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'lf_ed_core_notice' );

/**
 * Expõe o subtítulo e o crédito nas rotas nativas, se o Core não o fizer.
 *
 * O Core 1.0.3 já registra os dois com show_in_rest. Esta função existe como
 * rede de segurança para instalação em que o Core esteja em versão anterior, e
 * ela NÃO redeclara o campo: só acrescenta um campo de leitura no recurso REST,
 * com o mesmo nome do meta. A escrita continua sendo pelo meta do Core.
 *
 * Usa hooks públicos. O plugin Core não é modificado.
 */
function lf_ed_rest_fields() {

	if ( ! function_exists( 'register_rest_field' ) ) {
		return;
	}

	register_rest_field(
		'post',
		'lf_ed_subtitulo',
		array(
			'get_callback' => function ( $post ) {
				return (string) get_post_meta( $post['id'], LF_ED_META_SUBTITULO, true );
			},
			'schema'       => array(
				'description' => __( 'Subtítulo, lido do campo do plugin Core.', 'licia-fabio-editorial' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
		)
	);

	register_rest_field(
		'attachment',
		'lf_ed_credito',
		array(
			'get_callback' => function ( $anexo ) {
				return (string) get_post_meta( $anexo['id'], LF_ED_META_CREDITO, true );
			},
			'schema'       => array(
				'description' => __( 'Crédito da foto, lido do campo do plugin Core.', 'licia-fabio-editorial' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
		)
	);
}
add_action( 'rest_api_init', 'lf_ed_rest_fields' );
