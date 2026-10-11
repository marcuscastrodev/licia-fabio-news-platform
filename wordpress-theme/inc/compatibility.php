<?php
/**
 * Compatibilidade com o acervo e com os plugins que continuam instalados.
 *
 * Nada aqui converte, reescreve ou apaga conteúdo. As funções são todas
 * defensivas: quando o plugin de estrutura não está ativo, o tema continua
 * funcionando com um comportamento equivalente ao do site antigo.
 *
 * @package licia-fabio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subtítulo da matéria.
 *
 * Lê a meta registrada pelo plugin. Nunca usa o primeiro parágrafo do texto
 * nem o resumo nativo como substituto: matéria sem subtítulo simplesmente não
 * exibe subtítulo.
 *
 * @param int|null $post_id Id do post.
 * @return string
 */
function lf_subtitle( $post_id = null ) {

	$post_id = $post_id ? (int) $post_id : get_the_ID();

	if ( ! $post_id ) {
		return '';
	}

	$sub = get_post_meta( $post_id, '_lf_subtitulo', true );

	if ( ! is_string( $sub ) ) {
		return '';
	}

	return trim( $sub );
}

/**
 * Visualizações, com fallback quando o plugin não está ativo.
 *
 * @param int|null $post_id Id do post.
 * @return int
 */
function lf_views( $post_id = null ) {

	if ( function_exists( 'lf_get_post_views' ) ) {
		return lf_get_post_views( $post_id );
	}

	$post_id = $post_id ? (int) $post_id : get_the_ID();
	$valor   = get_post_meta( $post_id, 'post_views_count', true );

	if ( '' === $valor || false === $valor ) {
		$valor = get_post_meta( $post_id, 'Views', true );
	}

	return ( '' === $valor || false === $valor ) ? 0 : max( 0, (int) $valor );
}

/**
 * Existe anúncio realmente disponível nesta posição?
 *
 * FONTE ÚNICA DA REGRA. Todo template do tema pergunta aqui antes de abrir
 * qualquer contêiner, cabeçalho ou coluna de publicidade — a decisão é feita
 * no servidor, antes de o HTML existir, e não por CSS ou JavaScript depois.
 *
 * Quem responde de fato é lf_has_ad(), do plugin Core: ela já aplica todos os
 * critérios de atividade (status do anúncio, data inicial, data final,
 * presença de imagem) e, na falta de anúncio, ainda considera o fallback do
 * MetaSlider. Não reimplemento nenhuma dessas regras aqui: duplicá-las
 * garantiria divergência na primeira mudança.
 *
 * O resultado é memoizado por posição. Alguns templates precisam perguntar
 * duas vezes — uma para decidir se abrem a coluna, outra ao renderizar — e
 * isso não deve custar duas consultas.
 *
 * @param string $posicao Chave da posição.
 * @return bool
 */
function lf_ad_disponivel( $posicao ) {

	static $cache = array();

	$posicao = (string) $posicao;

	if ( isset( $cache[ $posicao ] ) ) {
		return $cache[ $posicao ];
	}

	if ( ! function_exists( 'lf_has_ad' ) ) {
		$cache[ $posicao ] = false;
		return false;
	}

	$cache[ $posicao ] = (bool) lf_has_ad( $posicao );

	return $cache[ $posicao ];
}

/**
 * Posição publicitária, com degradação segura.
 *
 * Quando não há anúncio disponível, NADA é impresso: nem rótulo
 * "Publicidade", nem caixa, nem borda, nem altura reservada, nem margem.
 *
 * Por que o tema decide antes de chamar o plugin: lf_render_ad_position()
 * imprime um placeholder administrativo ("Espaço disponível", com caixa,
 * borda e altura mínima) para quem tem manage_options. Era um recurso
 * deliberado — mostrar a quem administra onde os espaços ficam —, e é a razão
 * de o box aparecer para você e não para um visitante.
 *
 * Como a orientação desta rodada é não exibir espaço vazio a ninguém, e como
 * o plugin Core não pode ser alterado, o tema para antes de chegar lá. O
 * placeholder continua no Core, intacto, e volta a aparecer se outro tema o
 * chamar diretamente.
 *
 * @param string $posicao  Chave da posição.
 * @param string $variante Variante visual.
 */
function lf_ad( $posicao, $variante = 'billboard' ) {

	if ( ! function_exists( 'lf_render_ad_position' ) ) {
		return;
	}

	if ( ! lf_ad_disponivel( $posicao ) ) {
		return;
	}

	lf_render_ad_position( $posicao, $variante );
}

/**
 * Categoria de uma seção visual, buscada pelo slug configurado.
 *
 * Nunca usa ID fixo: os IDs diferem entre produção e staging.
 *
 * @param string $secao Chave da seção.
 * @return WP_Term|null
 */
function lf_section_category( $secao ) {

	if ( function_exists( 'lf_get_section_category' ) ) {
		return lf_get_section_category( $secao );
	}

	$termo = get_category_by_slug( $secao );

	return $termo ? $termo : null;
}

/**
 * O conteúdo desta matéria tem galeria nativa?
 *
 * Usado para carregar o JavaScript da galeria apenas onde ele é necessário.
 *
 * @param int|null $post_id Id do post.
 * @return bool
 */
function lf_post_has_gallery( $post_id = null ) {

	$post = get_post( $post_id );

	if ( ! $post ) {
		return false;
	}

	// Galeria legada, do shortcode.
	if ( has_shortcode( $post->post_content, 'gallery' ) ) {
		return true;
	}

	if ( ! function_exists( 'has_block' ) ) {
		return false;
	}

	// Bloco Galeria do Gutenberg.
	if ( has_block( 'gallery', $post ) ) {
		return true;
	}

	/*
	 * Padrão sincronizado / bloco reutilizável (core/block): o conteúdo da
	 * galeria não está no post_content, e sim no post wp_block referenciado.
	 * Sem olhar dentro, o script não carrega e a galeria abre fora do site.
	 *
	 * A busca é RECURSIVA nos blocos internos, porque o padrão pode estar
	 * dentro de um Grupo, de Colunas ou de outro contêiner — foi essa a lacuna
	 * da 1.0.3, que só inspecionava o primeiro nível.
	 */
	if ( has_block( 'block', $post ) ) {
		return lf_blocks_have_gallery( parse_blocks( $post->post_content ) );
	}

	return false;
}

/**
 * Procura galeria em uma árvore de blocos, resolvendo padrões sincronizados.
 *
 * Percorre os blocos internos em profundidade, e a cada core/block encontrado
 * abre o post wp_block referenciado e repete a busca lá dentro.
 *
 * @param array $blocos Blocos de parse_blocks().
 * @param int   $nivel  Profundidade de padrões já resolvidos (trava de ciclo).
 * @return bool
 */
function lf_blocks_have_gallery( $blocos, $nivel = 0 ) {

	// Trava contra padrão que referencia a si mesmo, direta ou indiretamente.
	if ( $nivel > 3 ) {
		return false;
	}

	foreach ( (array) $blocos as $bloco ) {

		if ( empty( $bloco['blockName'] ) ) {
			continue;
		}

		if ( 'core/gallery' === $bloco['blockName'] ) {
			return true;
		}

		if ( 'core/block' === $bloco['blockName'] && ! empty( $bloco['attrs']['ref'] ) ) {

			$ref = get_post( (int) $bloco['attrs']['ref'] );

			if ( $ref && 'wp_block' === $ref->post_type ) {

				if ( has_shortcode( $ref->post_content, 'gallery' ) ) {
					return true;
				}

				if ( lf_blocks_have_gallery( parse_blocks( $ref->post_content ), $nivel + 1 ) ) {
					return true;
				}
			}

			continue;
		}

		// Grupo, Colunas, Coluna e qualquer outro contêiner.
		if ( ! empty( $bloco['innerBlocks'] ) && lf_blocks_have_gallery( $bloco['innerBlocks'], $nivel ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Imprime a navegação de editorias, com fallback seguro.
 *
 * Prioridade:
 *
 *   1. o menu que o administrador atribuiu ao local — sempre vence;
 *   2. na ausência dele, as editorias configuradas em Portal › Editorias que
 *      apontem para uma categoria existente;
 *   3. na ausência das duas, NADA é impresso — nem rótulo solto, nem lista
 *      vazia. Quem pode configurar vê um aviso; visitante não vê nada.
 *
 * Nenhum menu é criado ou alterado, nem aqui nem na ativação. O fallback é
 * apenas de exibição.
 *
 * Os links saem de get_category_link(), então respeitam a instalação em
 * subdiretório (/staging/, por exemplo) sem nenhum tratamento especial.
 *
 * @param string $local  Local do menu ('principal').
 * @param string $classe Classe da <ul>.
 * @return bool Imprimiu alguma coisa?
 */
function lf_nav_editorias( $local = 'principal', $classe = '' ) {

	// 1. Menu atribuído pelo administrador.
	if ( has_nav_menu( $local ) ) {

		wp_nav_menu(
			array(
				'theme_location' => $local,
				'container'      => false,
				'menu_class'     => $classe,
				'depth'          => 1,
				'fallback_cb'    => false,
			)
		);

		return true;
	}

	// 2. Editorias configuradas que resolvem numa categoria existente.
	$itens = lf_editorias_configuradas();

	if ( $itens ) {
		?>
		<ul class="<?php echo esc_attr( $classe ); ?>">
			<?php foreach ( $itens as $termo ) : ?>
				<li<?php echo is_category( $termo->term_id ) ? ' class="current-menu-item"' : ''; ?>>
					<a href="<?php echo esc_url( get_category_link( $termo ) ); ?>"><?php echo esc_html( $termo->name ); ?></a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
		return true;
	}

	// 3. Nada a exibir. Aviso apenas para quem pode resolver.
	if ( current_user_can( 'edit_theme_options' ) ) {
		?>
		<p class="lf-nav__aviso">
			<?php esc_html_e( 'Aviso visível apenas para administradores: nenhum menu foi atribuído ao local “Editorias (topo)” e nenhuma editoria está configurada em Portal › Editorias. Configure um dos dois para a navegação aparecer.', 'licia-fabio' ); ?>
		</p>
		<?php
	}

	return false;
}

/**
 * Categorias das editorias configuradas, na ordem do painel.
 *
 * Devolve apenas as que resolvem num termo existente. Nenhuma categoria é
 * criada; slug configurado que não corresponde a nada é simplesmente omitido.
 *
 * @return WP_Term[]
 */
function lf_editorias_configuradas() {

	if ( ! function_exists( 'lf_editorial_sections' ) ) {
		return array();
	}

	$itens = array();

	foreach ( array_keys( lf_editorial_sections() ) as $secao ) {

		$termo = lf_section_category( $secao );

		if ( $termo && ! isset( $itens[ $termo->term_id ] ) ) {
			$itens[ $termo->term_id ] = $termo;
		}
	}

	return array_values( $itens );
}

/**
 * Existe navegação de editorias para exibir?
 *
 * Usado pelos templates para não abrir um contêiner que ficaria vazio.
 *
 * @param string $local Local do menu.
 * @return bool
 */
function lf_tem_nav_editorias( $local = 'principal' ) {
	return has_nav_menu( $local ) || (bool) lf_editorias_configuradas();
}

/**
 * Crédito de fotografia de um anexo.
 *
 * O campo é registrado pelo plugin, no próprio anexo. Sem o plugin, a leitura
 * direta da meta continua funcionando.
 *
 * @param int $attachment_id Id do anexo.
 * @return string String vazia quando não houver crédito.
 */
function lf_credit( $attachment_id ) {

	if ( function_exists( 'lf_get_image_credit' ) ) {
		return lf_get_image_credit( $attachment_id );
	}

	$credito = get_post_meta( (int) $attachment_id, '_lf_image_credit', true );

	return is_string( $credito ) ? trim( $credito ) : '';
}

/**
 * Legenda BRUTA de um anexo, sem passar por filtro nenhum.
 *
 * Lê o post_excerpt direto. É o que impede a duplicação do crédito:
 * wp_get_attachment_caption() passa pelo filtro que anexa o crédito, então
 * usá-la como base para montar "legenda + crédito" escreveria o nome do
 * fotógrafo duas vezes.
 *
 * @param int $attachment_id Id do anexo.
 * @return string
 */
function lf_raw_caption( $attachment_id ) {

	if ( function_exists( 'lf_get_raw_caption' ) ) {
		return lf_get_raw_caption( $attachment_id );
	}

	$attachment_id = (int) $attachment_id;

	if ( ! $attachment_id ) {
		return '';
	}

	$legenda = get_post_field( 'post_excerpt', $attachment_id, 'raw' );

	return is_string( $legenda ) ? trim( $legenda ) : '';
}

/**
 * Legenda de exibição de uma imagem: legenda do anexo mais crédito.
 *
 * Parte sempre da legenda BRUTA, nunca da filtrada, para que o crédito apareça
 * no máximo uma vez: "Legenda · Foto: Nome".
 *
 * Devolve string vazia quando não há nem legenda nem crédito — assim o
 * template não imprime figcaption vazio.
 *
 * @param int $attachment_id Id do anexo.
 * @return string
 */
function lf_caption_with_credit( $attachment_id ) {

	$attachment_id = (int) $attachment_id;

	if ( ! $attachment_id ) {
		return '';
	}

	$legenda = lf_raw_caption( $attachment_id );
	$credito = lf_credit( $attachment_id );

	if ( ! $credito ) {
		return trim( $legenda );
	}

	$credito = sprintf(
		/* translators: nome do fotógrafo ou da agência */
		__( 'Foto: %s', 'licia-fabio' ),
		$credito
	);

	if ( ! trim( $legenda ) ) {
		return $credito;
	}

	return trim( $legenda ) . ' · ' . $credito;
}

/**
 * Acrescenta o crédito às legendas das galerias nativas.
 *
 * O shortcode [gallery] do WordPress monta as legendas a partir de
 * wp_get_attachment_caption(). Filtrar aqui faz o crédito aparecer nas
 * galerias do acervo sem tocar no conteúdo salvo no banco.
 *
 * Quando não há crédito, a legenda sai exatamente como era. Quando não há
 * legenda nem crédito, continua vazia — nada é impresso.
 *
 * @param string $legenda Legenda atual.
 * @param int    $post_id Id do anexo.
 * @return string
 */
function lf_gallery_caption_credit( $legenda, $post_id ) {

	if ( is_admin() || ! is_singular() ) {
		return $legenda;
	}

	$credito = lf_credit( $post_id );

	if ( ! $credito ) {
		return $legenda;
	}

	$texto = sprintf(
		/* translators: nome do fotógrafo ou da agência */
		__( 'Foto: %s', 'licia-fabio' ),
		$credito
	);

	// Evita repetir o crédito se ele já estiver escrito na legenda.
	if ( $legenda && false !== strpos( (string) $legenda, $credito ) ) {
		return $legenda;
	}

	// Evita reentrada: se o filtro já rodou nesta legenda, não acrescenta de novo.
	if ( $legenda && false !== strpos( (string) $legenda, $texto ) ) {
		return $legenda;
	}

	if ( ! trim( (string) $legenda ) ) {
		return $texto;
	}

	return $legenda . ' · ' . $texto;
}
add_filter( 'wp_get_attachment_caption', 'lf_gallery_caption_credit', 10, 2 );

/**
 * Mantém o shortcode [gallery] intacto e apenas melhora os atributos das
 * imagens para acessibilidade e desempenho.
 *
 * A ordem dos ids é preservada: o WordPress já respeita a ordem escrita no
 * shortcode, e este filtro não reordena nada.
 *
 * @param array   $atributos Atributos da imagem.
 * @param WP_Post $anexo     Anexo.
 * @return array
 */
function lf_gallery_image_attributes( $atributos, $anexo ) {

	if ( ! is_singular() ) {
		return $atributos;
	}

	/*
	 * Texto alternativo, nesta ordem:
	 *   1. o texto alternativo cadastrado no anexo;
	 *   2. na ausência dele, a legenda BRUTA;
	 *   3. nunca o crédito do fotógrafo — "Foto: Nome" não descreve a imagem
	 *      para quem usa leitor de tela.
	 *
	 * A legenda bruta é lida direto do post_excerpt, sem passar pelo filtro que
	 * acrescenta o crédito.
	 */
	if ( empty( $atributos['alt'] ) ) {

		$legenda = lf_raw_caption( $anexo->ID );

		if ( $legenda ) {
			$atributos['alt'] = $legenda;
		}
	}

	$atributos['decoding'] = 'async';

	return $atributos;
}
add_filter( 'wp_get_attachment_image_attributes', 'lf_gallery_image_attributes', 10, 2 );

/**
 * Garante que os embeds antigos continuem responsivos.
 *
 * @param string $html HTML do embed.
 * @return string
 */
function lf_responsive_embeds( $html ) {

	if ( ! $html ) {
		return $html;
	}

	return '<div class="lf-embed">' . $html . '</div>';
}
add_filter( 'embed_oembed_html', 'lf_responsive_embeds' );

/*
 * Observações sobre o acervo, registradas em código de propósito:
 *
 * - td_post_theme_settings e classic-editor-remember NÃO são lidos nem
 *   apagados por este tema. Ficam no banco, intactos, e voltam a ser usados se
 *   o Newspaper for reativado.
 * - O conteúdo dos posts não é filtrado nem reescrito: HTML clássico, estilos
 *   inline, shortcodes e embeds continuam sendo renderizados pelo WordPress.
 * - Os metadados de visualizações do plugin atual são apenas LIDOS. Nenhum é
 *   removido ou convertido.
 */
