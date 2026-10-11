<?php
/**
 * Páginas institucionais.
 *
 * O tema garante que Institucional, Fale conosco e Política de Privacidade
 * EXISTAM como páginas nativas do WordPress, editáveis no wp-admin. Os textos
 * não vivem no código: quem escreve é a redação, no editor.
 *
 * Duas regras que valem nomear:
 *
 *   1. NADA é recriado ou duplicado. A busca é por slug, e uma página
 *      existente — em qualquer status, inclusive rascunho ou lixeira — é
 *      reutilizada, nunca substituída.
 *
 *   2. Nenhum dado é inventado. As páginas nascem com um lembrete curto do que
 *      precisa ser preenchido, e nada de endereço, telefone, e-mail, CNPJ ou
 *      texto jurídico. Um texto genérico apresentado como política de
 *      privacidade definitiva seria pior que uma página vazia.
 *
 * @package licia-fabio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * As páginas institucionais previstas.
 *
 * O slug é a chave: é o que define a URL e o que a busca usa.
 *
 * @return array
 */
function lf_paginas_institucionais() {

	return array(
		'institucional'          => array(
			'titulo'   => __( 'Institucional', 'licia-fabio' ),
			'lembrete' => __( 'Esta página aguarda o texto institucional do portal. Edite-a em Páginas, no painel do WordPress.', 'licia-fabio' ),
		),
		'fale-conosco'           => array(
			'titulo'   => __( 'Fale conosco', 'licia-fabio' ),
			'lembrete' => __( 'Esta página aguarda os canais oficiais de contato. Edite-a em Páginas, no painel do WordPress.', 'licia-fabio' ),
		),
		'politica-de-privacidade' => array(
			'titulo'   => __( 'Política de Privacidade', 'licia-fabio' ),
			'lembrete' => __( 'Esta página aguarda a política de privacidade do portal, que precisa de validação jurídica antes de ir ao ar. Edite-a em Páginas, no painel do WordPress.', 'licia-fabio' ),
		),
	);
}

/**
 * Localiza uma página institucional pelo slug.
 *
 * Procura em todos os status, não só nos publicados: se a página existe como
 * rascunho, ela É a página, e criar outra com o mesmo slug produziria
 * `institucional-2`.
 *
 * @param string $slug Slug.
 * @return WP_Post|null
 */
function lf_pagina_institucional( $slug ) {

	$encontrada = get_posts(
		array(
			'name'           => sanitize_title( $slug ),
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);

	if ( ! $encontrada ) {
		return null;
	}

	$pagina = get_post( $encontrada[0] );

	return $pagina ? $pagina : null;
}

/**
 * Cria as páginas que ainda não existem.
 *
 * Roda uma única vez, na ativação do tema, e nunca em toda requisição. A
 * criação é conservadora: página existente é deixada exatamente como está,
 * inclusive o conteúdo, o status e o slug.
 */
function lf_criar_paginas_institucionais() {

	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}

	$criadas = array();

	foreach ( lf_paginas_institucionais() as $slug => $dados ) {

		// Já existe? Não toca em nada.
		if ( lf_pagina_institucional( $slug ) ) {
			continue;
		}

		$id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_name'      => $slug,
				'post_title'     => $dados['titulo'],
				'post_status'    => 'draft',
				'post_content'   => '<!-- wp:paragraph --><p>' . esc_html( $dados['lembrete'] ) . '</p><!-- /wp:paragraph -->',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
			true
		);

		if ( ! is_wp_error( $id ) ) {
			$criadas[] = $slug;
		}
	}

	if ( $criadas ) {
		update_option( 'lf_paginas_criadas', $criadas );
	}
}
add_action( 'after_switch_theme', 'lf_criar_paginas_institucionais' );

/**
 * Links institucionais para o rodapé.
 *
 * Prioridade para o menu "Institucional" atribuído pelo administrador. Sem
 * menu, usa as páginas encontradas pelos slugs — e apenas as PUBLICADAS, para
 * não linkar rascunho.
 *
 * Devolve array vazio quando não há nada a mostrar, e o rodapé então não abre
 * a coluna.
 *
 * @return array Lista de arrays com 'titulo' e 'url'.
 */
function lf_links_institucionais() {

	$links = array();

	foreach ( lf_paginas_institucionais() as $slug => $dados ) {

		$pagina = lf_pagina_institucional( $slug );

		if ( ! $pagina || 'publish' !== $pagina->post_status ) {
			continue;
		}

		$links[] = array(
			'titulo' => get_the_title( $pagina ),
			'url'    => get_permalink( $pagina ),
		);
	}

	return $links;
}

/**
 * Aviso sobre páginas institucionais pendentes.
 *
 * Só para quem administra, e só enquanto houver pendência. Uma página em
 * rascunho não aparece no site nem é linkada no rodapé — sem este aviso, o
 * esquecimento passaria calado.
 */
function lf_aviso_paginas_institucionais() {

	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}

	$pendentes = array();

	foreach ( lf_paginas_institucionais() as $slug => $dados ) {

		$pagina = lf_pagina_institucional( $slug );

		if ( ! $pagina ) {
			$pendentes[] = $dados['titulo'] . ' ' . __( '(não existe)', 'licia-fabio' );
			continue;
		}

		if ( 'publish' !== $pagina->post_status ) {
			$pendentes[] = $dados['titulo'] . ' ' . __( '(ainda em rascunho)', 'licia-fabio' );
		}
	}

	if ( ! $pendentes ) {
		return;
	}
	?>
	<div class="notice notice-info is-dismissible">
		<p>
			<strong><?php esc_html_e( 'Páginas institucionais pendentes.', 'licia-fabio' ); ?></strong>
			<?php esc_html_e( 'Elas foram criadas como rascunho, sem texto: nenhum conteúdo institucional ou jurídico foi escrito automaticamente. Preencha e publique para que apareçam no rodapé do site.', 'licia-fabio' ); ?>
		</p>
		<ul style="margin:6px 0 6px 22px;list-style:disc">
			<?php foreach ( $pendentes as $item ) : ?>
				<li><?php echo esc_html( $item ); ?></li>
			<?php endforeach; ?>
		</ul>
		<p>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>"><?php esc_html_e( 'Ir para Páginas', 'licia-fabio' ); ?></a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'lf_aviso_paginas_institucionais' );
