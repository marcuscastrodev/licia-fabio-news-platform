<?php
/**
 * Tela de configuração do portal.
 *
 * @package licia-fabio-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adiciona a página de configuração.
 */
function lf_admin_menu() {

	add_menu_page(
		__( 'Portal Lícia Fábio', 'licia-fabio-core' ),
		__( 'Portal', 'licia-fabio-core' ),
		'manage_options',
		'lf-core',
		'lf_render_settings_page',
		'dashicons-admin-site-alt3',
		24
	);
}
add_action( 'admin_menu', 'lf_admin_menu' );

/**
 * Registra a opção com sanitização.
 */
function lf_register_settings() {

	register_setting(
		'lf_core_group',
		LF_CORE_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'lf_sanitize_settings',
			'default'           => lf_core_default_settings(),
		)
	);
}
add_action( 'admin_init', 'lf_register_settings' );

/**
 * Sanitiza todas as configurações.
 *
 * @param mixed $entrada Valores recebidos do formulário.
 * @return array
 */
function lf_sanitize_settings( $entrada ) {

	$padroes = lf_core_default_settings();
	$saida   = $padroes;

	if ( ! is_array( $entrada ) ) {
		return $saida;
	}

	$saida['views_counter_enabled']   = empty( $entrada['views_counter_enabled'] ) ? 0 : 1;
	$saida['metaslider_fallback']     = empty( $entrada['metaslider_fallback'] ) ? 0 : 1;
	$saida['views_cookie_hours']      = min( 72, max( 1, absint( $entrada['views_cookie_hours'] ?? 6 ) ) );
	$saida['most_read_days']          = min( 365, max( 1, absint( $entrada['most_read_days'] ?? 7 ) ) );
	$saida['transient_cache_minutes'] = min( 1440, absint( $entrada['transient_cache_minutes'] ?? 10 ) );

	// Slug da agenda: sanitizado, com recusa de slug em conflito com categoria.
	$slug_evento = isset( $entrada['event_slug'] ) ? sanitize_title( $entrada['event_slug'] ) : '';

	if ( ! $slug_evento ) {
		$slug_evento = 'agenda-eventos';
	}

	if ( get_category_by_slug( $slug_evento ) ) {

		add_settings_error(
			LF_CORE_OPTION,
			'lf_event_slug',
			sprintf(
				/* translators: 1: slug recusado, 2: slug aplicado */
				esc_html__( 'O slug “%1$s” já pertence a uma categoria existente e faria as duas estruturas competirem pelo mesmo endereço. A agenda ficou com “%2$s”. A categoria não foi alterada.', 'licia-fabio-core' ),
				esc_html( $slug_evento ),
				'agenda-eventos'
			),
			'warning'
		);

		$slug_evento = 'agenda-eventos';
	}

	$saida['event_slug'] = $slug_evento;

	$saida['metaslider_home_id']   = preg_replace( '/[^0-9]/', '', (string) ( $entrada['metaslider_home_id'] ?? '' ) );
	$saida['metaslider_single_id'] = preg_replace( '/[^0-9]/', '', (string) ( $entrada['metaslider_single_id'] ?? '' ) );

	// Mapa de categorias: apenas slugs de categorias que já existem.
	$mapa = array();

	foreach ( array_keys( lf_editorial_sections() ) as $secao ) {

		$slug = isset( $entrada['category_map'][ $secao ] ) ? sanitize_title( $entrada['category_map'][ $secao ] ) : '';

		// Nunca cria categoria: slug inexistente é descartado.
		if ( $slug && ! get_category_by_slug( $slug ) ) {
			$slug = '';

			add_settings_error(
				LF_CORE_OPTION,
				'lf_cat_' . $secao,
				sprintf(
					/* translators: nome da seção */
					esc_html__( 'A categoria escolhida para “%s” não existe. O campo foi deixado vazio — nenhuma categoria é criada automaticamente.', 'licia-fabio-core' ),
					esc_html( lf_editorial_sections()[ $secao ] )
				),
				'warning'
			);
		}

		$mapa[ $secao ] = $slug;
	}

	$saida['category_map'] = $mapa;

	// Faixas da home: quatro seções, na ordem.
	$faixas = array();

	for ( $i = 0; $i < 4; $i++ ) {
		$faixas[ $i ] = isset( $entrada['home_stripes'][ $i ] ) ? sanitize_key( $entrada['home_stripes'][ $i ] ) : '';
	}

	$saida['home_stripes'] = $faixas;

	$saida['branded_category'] = isset( $entrada['branded_category'] ) ? sanitize_key( $entrada['branded_category'] ) : '';
	$saida['column_category']  = isset( $entrada['column_category'] ) ? sanitize_key( $entrada['column_category'] ) : '';
	$saida['tv_category']      = isset( $entrada['tv_category'] ) ? sanitize_key( $entrada['tv_category'] ) : '';

	lf_flush_home_cache();

	// Se o slug da agenda mudou, as regras de reescrita precisam ser refeitas.
	$anterior = (string) lf_get_setting( 'event_slug', 'agenda-eventos' );

	if ( $anterior !== $saida['event_slug'] ) {
		update_option( 'lf_core_flush_needed', 1 );
	}

	return $saida;
}

/**
 * Refaz as regras de reescrita depois de mudar o slug da agenda.
 *
 * Só roda quando o slug realmente mudou, e uma única vez.
 */
function lf_maybe_flush_rewrites() {

	if ( ! get_option( 'lf_core_flush_needed' ) ) {
		return;
	}

	delete_option( 'lf_core_flush_needed' );
	flush_rewrite_rules();
}
add_action( 'admin_init', 'lf_maybe_flush_rewrites', 99 );

/**
 * Seletor de categoria existente.
 *
 * @param string $nome  Nome do campo.
 * @param string $atual Slug selecionado.
 */
function lf_category_select( $nome, $atual ) {

	$cats = get_categories(
		array(
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);
	?>
	<select name="<?php echo esc_attr( $nome ); ?>">
		<option value=""><?php esc_html_e( '— não configurada —', 'licia-fabio-core' ); ?></option>
		<?php foreach ( $cats as $cat ) : ?>
			<option value="<?php echo esc_attr( $cat->slug ); ?>" <?php selected( $cat->slug, $atual ); ?>>
				<?php echo esc_html( $cat->name . ' (' . $cat->slug . ')' ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<?php
}

/**
 * Seletor de seção visual.
 *
 * @param string $nome  Nome do campo.
 * @param string $atual Chave selecionada.
 */
function lf_section_select( $nome, $atual ) {
	?>
	<select name="<?php echo esc_attr( $nome ); ?>">
		<option value=""><?php esc_html_e( '— vazia —', 'licia-fabio-core' ); ?></option>
		<?php foreach ( lf_editorial_sections() as $chave => $rotulo ) : ?>
			<option value="<?php echo esc_attr( $chave ); ?>" <?php selected( $chave, $atual ); ?>>
				<?php echo esc_html( $rotulo ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<?php
}

/**
 * Diagnóstico do fallback do MetaSlider.
 *
 * Só leitura. Não instala o MetaSlider, não importa slider e não altera os
 * sliders existentes.
 */
function lf_render_metaslider_diagnostic() {

	if ( ! function_exists( 'lf_metaslider_status' ) ) {
		return;
	}

	$estado = lf_metaslider_status();
	$nomes  = lf_ad_positions();
	?>
	<h3><?php esc_html_e( 'Diagnóstico do fallback', 'licia-fabio-core' ); ?></h3>

	<?php if ( ! $estado['ligado'] ) : ?>
		<div class="notice notice-info inline" style="margin:0 0 12px">
			<p><?php esc_html_e( 'O fallback está desligado. Nenhum slider é exibido — o que é o estado final desejado depois que todos os anúncios forem cadastrados.', 'licia-fabio-core' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! $estado['plugin'] ) : ?>
		<div class="notice notice-warning inline" style="margin:0 0 12px">
			<p>
				<strong><?php esc_html_e( 'MetaSlider não disponível.', 'licia-fabio-core' ); ?></strong>
				<?php esc_html_e( 'O shortcode [metaslider] não está registrado neste site: o plugin não está instalado ou está desativado. Com o fallback marcado, nenhum slider será exibido — e nenhum shortcode cru aparece no site, nem mensagem de erro para o visitante.', 'licia-fabio-core' ); ?>
			</p>
			<p><?php esc_html_e( 'Este plugin não instala o MetaSlider nem importa sliders. Se os banners antigos precisam continuar no ar, reative o MetaSlider; se não, desmarque o fallback.', 'licia-fabio-core' ); ?></p>
		</div>
	<?php endif; ?>

	<table class="widefat striped" style="max-width:900px">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Posição atendida', 'licia-fabio-core' ); ?></th>
				<th><?php esc_html_e( 'Id configurado', 'licia-fabio-core' ); ?></th>
				<th><?php esc_html_e( 'Situação', 'licia-fabio-core' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $estado['posicoes'] as $posicao => $dados ) : ?>
				<?php
				$situacao = '';
				$cor      = '#6C7285';

				if ( ! $dados['id'] ) {

					$situacao = __( 'Nenhum id configurado.', 'licia-fabio-core' );

				} elseif ( ! $estado['plugin'] ) {

					$situacao = __( 'MetaSlider indisponível — não é possível verificar nem exibir.', 'licia-fabio-core' );
					$cor      = '#9A6700';

				} elseif ( false === $dados['existe'] ) {

					$situacao = sprintf(
						/* translators: id do slider */
						__( 'Não existe slider com o id %s neste site. Confira o id em MetaSlider; nada foi alterado automaticamente.', 'licia-fabio-core' ),
						$dados['id']
					);
					$cor = '#B31414';

				} elseif ( true === $dados['existe'] ) {

					$situacao = $estado['ligado']
						? __( 'Slider encontrado. Aparece quando a posição não tiver anúncio ativo.', 'licia-fabio-core' )
						: __( 'Slider encontrado, mas o fallback está desligado.', 'licia-fabio-core' );

					$cor = $estado['ligado'] ? '#1E7B34' : '#6C7285';
				}
				?>
				<tr>
					<td><strong><?php echo esc_html( isset( $nomes[ $posicao ] ) ? $nomes[ $posicao ]['label'] : $posicao ); ?></strong></td>
					<td><code><?php echo esc_html( $dados['id'] ? $dados['id'] : '—' ); ?></code></td>
					<td style="color:<?php echo esc_attr( $cor ); ?>"><?php echo esc_html( $situacao ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Diagnóstico das faixas da home.
 *
 * Existe porque a ausência de saída de uma faixa tem três causas possíveis, e
 * de fora do código elas são indistinguíveis:
 *
 *   1. a faixa não foi configurada;
 *   2. o slug configurado não corresponde a nenhuma categoria existente;
 *   3. a faixa está configurada e a categoria existe, mas TODAS as matérias
 *      elegíveis já apareceram em blocos anteriores da home.
 *
 * A causa 3 é o comportamento correto — a home não repete matéria —, mas em
 * site com pouco conteúdo ela parece defeito. Este quadro mostra, para cada
 * faixa, quantas matérias a categoria tem e quantas sobram depois dos blocos
 * anteriores.
 *
 * Só leitura. Nada é criado, alterado ou apagado.
 */
function lf_render_stripes_diagnostic() {

	$faixas = (array) lf_get_setting( 'home_stripes', array() );
	$extras = array(
		'branded_category' => __( 'Conteúdo de marca', 'licia-fabio-core' ),
		'column_category'  => __( 'Coluna', 'licia-fabio-core' ),
		'tv_category'      => __( 'TV Lícia', 'licia-fabio-core' ),
	);

	$linhas = array();

	foreach ( $faixas as $i => $secao ) {
		$linhas[] = array(
			/* translators: número da faixa */
			sprintf( __( 'Faixa %d', 'licia-fabio-core' ), (int) $i + 1 ),
			$secao,
		);
	}

	foreach ( $extras as $chave => $rotulo ) {
		$linhas[] = array( $rotulo, (string) lf_get_setting( $chave, '' ) );
	}

	/*
	 * Reproduz a ordem de precedência da home: destaques primeiro, depois o
	 * plantão, depois cada faixa na ordem. Assim a contagem de "sobram"
	 * corresponde ao que a home realmente vai encontrar.
	 */
	$usados = array();

	if ( function_exists( 'lf_get_home_featured' ) ) {
		$usados = wp_list_pluck( lf_get_home_featured(), 'ID' );
	}

	$plantao = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 3,
			'post__not_in'        => $usados ? $usados : array( 0 ),
			'fields'              => 'ids',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);

	$usados = array_merge( $usados, $plantao->posts );
	$secoes = lf_editorial_sections();
	?>
	<h3><?php esc_html_e( 'Diagnóstico das faixas', 'licia-fabio-core' ); ?></h3>

	<p class="description" style="max-width:80ch">
		<?php esc_html_e( 'A home não repete matéria: os três destaques têm precedência, o plantão vem depois, e cada faixa exclui tudo o que já apareceu acima dela. Uma faixa configurada corretamente pode ficar invisível simplesmente porque não sobrou matéria elegível na categoria. O quadro abaixo mostra em qual dos casos cada faixa está.', 'licia-fabio-core' ); ?>
	</p>

	<table class="widefat striped" style="max-width:900px">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Bloco', 'licia-fabio-core' ); ?></th>
				<th><?php esc_html_e( 'Editoria', 'licia-fabio-core' ); ?></th>
				<th><?php esc_html_e( 'Categoria', 'licia-fabio-core' ); ?></th>
				<th><?php esc_html_e( 'Publicadas', 'licia-fabio-core' ); ?></th>
				<th><?php esc_html_e( 'Elegíveis agora', 'licia-fabio-core' ); ?></th>
				<th><?php esc_html_e( 'Situação', 'licia-fabio-core' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $linhas as $linha ) : ?>
				<?php
				list( $rotulo, $secao ) = $linha;

				$nome_secao = ( $secao && isset( $secoes[ $secao ] ) ) ? $secoes[ $secao ] : '';
				$termo      = $secao ? lf_get_section_category( $secao ) : null;

				$publicadas = '—';
				$elegiveis  = '—';
				$situacao   = '';
				$cor        = '#6C7285';

				if ( ! $secao ) {

					$situacao = __( 'Faixa vazia. Escolha uma editoria acima.', 'licia-fabio-core' );

				} elseif ( ! $termo ) {

					$situacao = __( 'A editoria não aponta para nenhuma categoria existente. Configure o slug em Editorias.', 'licia-fabio-core' );
					$cor      = '#B31414';

				} else {

					$publicadas = (int) $termo->count;

					$q = new WP_Query(
						array(
							'post_type'           => 'post',
							'post_status'         => 'publish',
							'cat'                 => (int) $termo->term_id,
							'post__not_in'        => $usados ? $usados : array( 0 ),
							'posts_per_page'      => 20,
							'fields'              => 'ids',
							'ignore_sticky_posts' => true,
							'no_found_rows'       => true,
						)
					);

					$elegiveis = count( $q->posts );

					if ( $elegiveis > 0 ) {
						$situacao = __( 'A faixa aparece na home.', 'licia-fabio-core' );
						$cor      = '#1E7B34';

						// Consome o que a faixa vai exibir, para a linha seguinte.
						$usados = array_merge( $usados, array_slice( $q->posts, 0, 4 ) );

					} elseif ( $publicadas > 0 ) {
						$situacao = __( 'Configurada, mas todas as matérias desta categoria já aparecem nos destaques, no plantão ou em uma faixa acima. Publique mais matérias na categoria, ou troque as matérias em destaque.', 'licia-fabio-core' );
						$cor      = '#9A6700';

					} else {
						$situacao = __( 'A categoria existe, mas não tem nenhuma matéria publicada.', 'licia-fabio-core' );
						$cor      = '#9A6700';
					}
				}
				?>
				<tr>
					<td><strong><?php echo esc_html( $rotulo ); ?></strong></td>
					<td><?php echo esc_html( $nome_secao ? $nome_secao : '—' ); ?></td>
					<td><?php echo esc_html( $termo ? $termo->slug : '—' ); ?></td>
					<td><?php echo esc_html( (string) $publicadas ); ?></td>
					<td><?php echo esc_html( (string) $elegiveis ); ?></td>
					<td style="color:<?php echo esc_attr( $cor ); ?>"><?php echo esc_html( $situacao ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Desenha a página de configuração.
 */
function lf_render_settings_page() {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'licia-fabio-core' ) );
	}

	$mapa    = lf_get_setting( 'category_map', array() );
	$faixas  = (array) lf_get_setting( 'home_stripes', array( '', '', '', '' ) );
	$secoes  = lf_editorial_sections();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Portal Lícia Fábio', 'licia-fabio-core' ); ?></h1>

		<?php settings_errors( LF_CORE_OPTION ); ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'lf_core_group' ); ?>

			<h2><?php esc_html_e( 'Editorias', 'licia-fabio-core' ); ?></h2>
			<p class="description" style="max-width:70ch">
				<?php esc_html_e( 'Relacione cada seção do layout a uma categoria que já existe no site. Nada é criado automaticamente: se a categoria não existir, crie-a primeiro em Posts › Categorias. O tema sempre busca pelo slug, então o site continua funcionando entre produção e staging.', 'licia-fabio-core' ); ?>
			</p>
			<p class="description" style="max-width:70ch">
				<?php esc_html_e( 'As categorias que já existem no site — inclusive blog, coluna, dicas-de-licia, featured e geral — não são alteradas, renomeadas nem excluídas por este plugin. Elas continuam disponíveis mesmo que não apareçam em nenhuma seção do novo layout.', 'licia-fabio-core' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<?php
				foreach ( $secoes as $chave => $rotulo ) :
					$lf_atual     = isset( $mapa[ $chave ] ) ? $mapa[ $chave ] : '';
					$lf_sugestao  = lf_suggested_slug( $chave );
					$lf_pendente  = ( ! $lf_atual );
					?>
					<tr>
						<th scope="row">
							<?php echo esc_html( $rotulo ); ?>
							<?php if ( $lf_pendente ) : ?>
								<span style="display:block;font-weight:400;color:#8a6d1a;font-size:12px"><?php esc_html_e( 'pendente', 'licia-fabio-core' ); ?></span>
							<?php endif; ?>
						</th>
						<td>
							<?php lf_category_select( LF_CORE_OPTION . '[category_map][' . $chave . ']', $lf_atual ); ?>

							<?php if ( $lf_pendente && $lf_sugestao ) : ?>
								<p class="description" style="max-width:70ch">
									<?php
									printf(
										/* translators: slug sugerido */
										esc_html__( 'Esta editoria ainda não existe no site. Sugestão de slug quando você decidir criá-la: %s. Nada é criado automaticamente — crie a categoria em Posts › Categorias e volte para relacioná-la aqui.', 'licia-fabio-core' ),
										'<code>' . esc_html( $lf_sugestao ) . '</code>'
									);
									?>
								</p>
							<?php elseif ( $lf_pendente ) : ?>
								<p class="description"><?php esc_html_e( 'Sem categoria relacionada: esta seção não aparece no site.', 'licia-fabio-core' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<?php
				endforeach;
				?>
			</table>

			<hr>

			<h2><?php esc_html_e( 'Faixas da página inicial', 'licia-fabio-core' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Quais editorias aparecem em faixa na home, e em que ordem. Deixe vazio para ocupar menos espaço.', 'licia-fabio-core' ); ?></p>

			<table class="form-table" role="presentation">
				<?php for ( $i = 0; $i < 4; $i++ ) : ?>
					<tr>
						<th scope="row"><?php printf( esc_html__( 'Faixa %d', 'licia-fabio-core' ), $i + 1 ); ?></th>
						<td><?php lf_section_select( LF_CORE_OPTION . '[home_stripes][' . $i . ']', isset( $faixas[ $i ] ) ? $faixas[ $i ] : '' ); ?></td>
					</tr>
				<?php endfor; ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Conteúdo de marca', 'licia-fabio-core' ); ?></th>
					<td><?php lf_section_select( LF_CORE_OPTION . '[branded_category]', lf_get_setting( 'branded_category', '' ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Coluna', 'licia-fabio-core' ); ?></th>
					<td><?php lf_section_select( LF_CORE_OPTION . '[column_category]', lf_get_setting( 'column_category', '' ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'TV Lícia', 'licia-fabio-core' ); ?></th>
					<td><?php lf_section_select( LF_CORE_OPTION . '[tv_category]', lf_get_setting( 'tv_category', '' ) ); ?></td>
				</tr>
			</table>

			<?php lf_render_stripes_diagnostic(); ?>

			<hr>

			<h2><?php esc_html_e( 'Visualizações', 'licia-fabio-core' ); ?></h2>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Contador próprio', 'licia-fabio-core' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( LF_CORE_OPTION ); ?>[views_counter_enabled]" value="1" <?php checked( lf_views_counter_enabled() ); ?>>
							<?php esc_html_e( 'Contar visualizações por conta própria', 'licia-fabio-core' ); ?>
						</label>
						<p class="description" style="max-width:70ch">
							<strong><?php esc_html_e( 'Deixe desligado enquanto o plugin antigo de visualizações estiver ativo', 'licia-fabio-core' ); ?></strong>
							<?php esc_html_e( '— com os dois ligados, cada visita é contada duas vezes. O bloco “Mais lidas” funciona de qualquer forma: ele lê o campo post_views_count, que já existe no acervo.', 'licia-fabio-core' ); ?>
						</p>
						<p class="description" style="max-width:70ch">
							<?php esc_html_e( 'Precisão: a contagem roda em PHP. Com cache de página ou CDN, boa parte das visitas não passa pelo PHP e não é contada. Trate o número como referência relativa, não como audiência real.', 'licia-fabio-core' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_most_read_days"><?php esc_html_e( 'Recorte do bloco “Mais lidas”', 'licia-fabio-core' ); ?></label></th>
					<td>
						<input type="number" id="lf_most_read_days" name="<?php echo esc_attr( LF_CORE_OPTION ); ?>[most_read_days]" value="<?php echo esc_attr( lf_get_setting( 'most_read_days', 7 ) ); ?>" class="small-text" min="1" max="365">
						<?php esc_html_e( 'dias', 'licia-fabio-core' ); ?>
						<p class="description" style="max-width:70ch">
							<strong><?php esc_html_e( 'O bloco mostra as mais lidas entre as matérias publicadas nos últimos X dias.', 'licia-fabio-core' ); ?></strong>
							<?php esc_html_e( 'O recorte é pela data de publicação, não pela data das leituras: o campo de visualizações guarda um total acumulado, sem registro de quando cada leitura aconteceu. Uma matéria antiga muito lida não entra no bloco; uma matéria recente com poucas leituras pode entrar.', 'licia-fabio-core' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_cookie_hours"><?php esc_html_e( 'Janela antirrecontagem', 'licia-fabio-core' ); ?></label></th>
					<td>
						<input type="number" id="lf_cookie_hours" name="<?php echo esc_attr( LF_CORE_OPTION ); ?>[views_cookie_hours]" value="<?php echo esc_attr( lf_get_setting( 'views_cookie_hours', 6 ) ); ?>" class="small-text" min="1" max="72">
						<?php esc_html_e( 'horas', 'licia-fabio-core' ); ?>
					</td>
				</tr>
			</table>

			<hr>

			<h2><?php esc_html_e( 'Agenda', 'licia-fabio-core' ); ?></h2>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lf_event_slug"><?php esc_html_e( 'Endereço do arquivo da agenda', 'licia-fabio-core' ); ?></label></th>
					<td>
						<code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code>
						<input type="text" id="lf_event_slug" name="<?php echo esc_attr( LF_CORE_OPTION ); ?>[event_slug]" value="<?php echo esc_attr( lf_get_setting( 'event_slug', 'agenda-eventos' ) ); ?>" class="regular-text">

						<p class="description" style="max-width:70ch">
							<?php esc_html_e( 'O padrão é agenda-eventos, e não agenda, porque já existe uma categoria com o slug “agenda”. Usar o mesmo slug nos dois faz as duas estruturas competirem pelo mesmo endereço, e qual delas responde depende da estrutura de links permanentes do site.', 'licia-fabio-core' ); ?>
						</p>

						<?php if ( lf_event_slug_conflicts() ) : ?>
							<div class="notice notice-warning inline" style="margin:10px 0 0">
								<p>
									<strong><?php esc_html_e( 'Conflito de endereço.', 'licia-fabio-core' ); ?></strong>
									<?php
									printf(
										/* translators: slug em conflito */
										esc_html__( 'Existe uma categoria com o slug “%s”, igual ao escolhido para a agenda. Escolha um slug diferente, como agenda-eventos. A categoria existente não é alterada.', 'licia-fabio-core' ),
										esc_html( lf_event_slug() )
									);
									?>
								</p>
							</div>
						<?php endif; ?>

						<p class="description" style="max-width:70ch">
							<?php esc_html_e( 'Depois de alterar este campo, vá em Configurações › Links permanentes e clique em Salvar, para que o novo endereço passe a valer.', 'licia-fabio-core' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<hr>

			<h2><?php esc_html_e( 'Transição do MetaSlider', 'licia-fabio-core' ); ?></h2>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Fallback temporário', 'licia-fabio-core' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( LF_CORE_OPTION ); ?>[metaslider_fallback]" value="1" <?php checked( lf_get_setting( 'metaslider_fallback', 0 ) ); ?>>
							<?php esc_html_e( 'Exibir os sliders atuais quando a posição não tiver anúncio cadastrado', 'licia-fabio-core' ); ?>
						</label>
						<p class="description" style="max-width:70ch">
							<?php esc_html_e( 'Mantém os banners no ar durante a transição. Desligue depois de cadastrar os anúncios em Anúncios. Os sliders não são alterados nem excluídos por este plugin.', 'licia-fabio-core' ); ?>
						</p>
						<p class="description" style="max-width:70ch">
							<strong><?php esc_html_e( 'O fallback atende apenas duas posições:', 'licia-fabio-core' ); ?></strong>
							<?php esc_html_e( 'Home · Billboard do topo e Matéria · Billboard do topo. As outras sete posições nunca exibem slider, por desenho — elas não têm equivalente no MetaSlider atual. Nelas, sem anúncio cadastrado, o visitante não vê nada e o administrador vê a marcação da posição.', 'licia-fabio-core' ); ?>
						</p>
						<p class="description" style="max-width:70ch">
							<?php esc_html_e( 'Anúncio cadastrado tem precedência: quando a posição tem anúncio ativo no período, ele aparece e o slider não.', 'licia-fabio-core' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_ms_home"><?php esc_html_e( 'Slider do billboard da home', 'licia-fabio-core' ); ?></label></th>
					<td><input type="text" id="lf_ms_home" name="<?php echo esc_attr( LF_CORE_OPTION ); ?>[metaslider_home_id]" value="<?php echo esc_attr( lf_get_setting( 'metaslider_home_id', '' ) ); ?>" class="small-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_ms_single"><?php esc_html_e( 'Slider do billboard da matéria', 'licia-fabio-core' ); ?></label></th>
					<td><input type="text" id="lf_ms_single" name="<?php echo esc_attr( LF_CORE_OPTION ); ?>[metaslider_single_id]" value="<?php echo esc_attr( lf_get_setting( 'metaslider_single_id', '' ) ); ?>" class="small-text"></td>
				</tr>
			</table>

			<?php lf_render_metaslider_diagnostic(); ?>

			<hr>

			<h2><?php esc_html_e( 'Desempenho', 'licia-fabio-core' ); ?></h2>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lf_cache_min"><?php esc_html_e( 'Cache dos blocos da home', 'licia-fabio-core' ); ?></label></th>
					<td>
						<input type="number" id="lf_cache_min" name="<?php echo esc_attr( LF_CORE_OPTION ); ?>[transient_cache_minutes]" value="<?php echo esc_attr( lf_get_setting( 'transient_cache_minutes', 10 ) ); ?>" class="small-text" min="0" max="1440">
						<?php esc_html_e( 'minutos', 'licia-fabio-core' ); ?>
						<p class="description"><?php esc_html_e( 'Zero desliga o cache. O cache é limpo automaticamente quando uma notícia ou evento é publicado ou atualizado.', 'licia-fabio-core' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Aviso quando nenhuma editoria foi configurada ainda.
 */
function lf_settings_missing_notice() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$mapa = array_filter( (array) lf_get_setting( 'category_map', array() ) );

	if ( ! empty( $mapa ) ) {
		return;
	}

	printf(
		'<div class="notice notice-info"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
		esc_html__( 'Portal Lícia Fábio:', 'licia-fabio-core' ),
		esc_html__( 'as editorias ainda não foram relacionadas às categorias do site. Até isso ser feito, as faixas da home ficam vazias e o restante do portal funciona normalmente.', 'licia-fabio-core' ),
		esc_url( admin_url( 'admin.php?page=lf-core' ) ),
		esc_html__( 'Configurar agora', 'licia-fabio-core' )
	);
}
add_action( 'admin_notices', 'lf_settings_missing_notice' );
