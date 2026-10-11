<?php
/**
 * Gerenciador de publicidade.
 *
 * @package licia-fabio-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Situação legível de um anúncio, usada na lista do painel.
 *
 * @param int $post_id Id do anúncio.
 * @return string
 */
function lf_ad_status_label( $post_id ) {

	$ativo  = (bool) get_post_meta( $post_id, '_lf_ad_active', true );
	$inicio = (string) get_post_meta( $post_id, '_lf_ad_start_date', true );
	$fim    = (string) get_post_meta( $post_id, '_lf_ad_end_date', true );
	$hoje   = current_time( 'Y-m-d' );

	if ( ! $ativo ) {
		return __( 'Pausado', 'licia-fabio-core' );
	}

	if ( $inicio && $inicio > $hoje ) {
		return __( 'Agendado', 'licia-fabio-core' );
	}

	if ( $fim && $fim < $hoje ) {
		return __( 'Encerrado', 'licia-fabio-core' );
	}

	if ( $fim ) {
		$dias = (int) floor( ( strtotime( $fim ) - strtotime( $hoje ) ) / DAY_IN_SECONDS );

		if ( $dias <= 7 ) {
			return __( 'Termina em breve', 'licia-fabio-core' );
		}
	}

	return __( 'No ar', 'licia-fabio-core' );
}

/**
 * Busca o anúncio ativo de uma posição.
 *
 * Considera ativo apenas o anúncio com _lf_ad_active verdadeiro e dentro do
 * período. As duas datas são opcionais: sem data inicial, vale desde já; sem
 * data final, vale até ser desativado.
 *
 * Quando há mais de um anúncio válido na mesma posição, usa o mais recente.
 *
 * @param string $posicao Chave da posição.
 * @return WP_Post|null
 */
function lf_get_ad_for_position( $posicao ) {

	$posicao = sanitize_text_field( $posicao );

	if ( ! $posicao || ! array_key_exists( $posicao, lf_ad_positions() ) ) {
		return null;
	}

	$hoje = current_time( 'Y-m-d' );

	$q = new WP_Query(
		array(
			'post_type'      => 'lf_anuncio',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_lf_ad_position',
					'value'   => $posicao,
					'compare' => '=',
				),
				array(
					'key'     => '_lf_ad_active',
					'value'   => '1',
					'compare' => '=',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => '_lf_ad_start_date',
						'value'   => '',
						'compare' => '=',
					),
					array(
						'key'     => '_lf_ad_start_date',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_lf_ad_start_date',
						'value'   => $hoje,
						'compare' => '<=',
						'type'    => 'DATE',
					),
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => '_lf_ad_end_date',
						'value'   => '',
						'compare' => '=',
					),
					array(
						'key'     => '_lf_ad_end_date',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_lf_ad_end_date',
						'value'   => $hoje,
						'compare' => '>=',
						'type'    => 'DATE',
					),
				),
			),
		)
	);

	return $q->have_posts() ? $q->posts[0] : null;
}

/**
 * Posições que ficam acima da dobra.
 *
 * Nelas a imagem carrega imediatamente, com prioridade alta: são candidatas a
 * LCP, e adiar o carregamento pioraria a métrica. Todas as outras posições
 * ficam abaixo da dobra e mantêm o carregamento adiado.
 *
 * @return string[]
 */
function lf_above_fold_ad_positions() {

	return array(
		'home_billboard_top',
		'single_billboard_top',
	);
}

/**
 * Monta o HTML de um anúncio.
 *
 * @param WP_Post $ad      Anúncio.
 * @param string  $posicao Posição, para decidir o carregamento.
 * @return string HTML já escapado, ou string vazia.
 */
function lf_get_ad_html( $ad, $posicao = '' ) {

	if ( ! $ad instanceof WP_Post ) {
		return '';
	}

	$desktop = (int) get_post_meta( $ad->ID, '_lf_ad_desktop_image_id', true );
	$mobile  = (int) get_post_meta( $ad->ID, '_lf_ad_mobile_image_id', true );
	$url     = (string) get_post_meta( $ad->ID, '_lf_ad_url', true );
	$alt     = (string) get_post_meta( $ad->ID, '_lf_ad_alt', true );
	$nova    = (bool) get_post_meta( $ad->ID, '_lf_ad_new_tab', true );

	if ( ! $desktop ) {
		return '';
	}

	if ( ! $alt ) {
		$alt = get_post_meta( $desktop, '_wp_attachment_image_alt', true );
	}

	/*
	 * Acima da dobra: carrega já, com prioridade alta — são candidatos a LCP.
	 * Abaixo da dobra: carregamento adiado, sem prioridade.
	 *
	 * decoding="async" nos dois casos: a decodificação fora da thread principal
	 * não atrasa o LCP e evita travar a rolagem.
	 */
	$acima = in_array( $posicao, lf_above_fold_ad_positions(), true );

	$figura = lf_get_ad_picture( $desktop, $mobile, $alt, $acima );

	if ( ! $figura ) {
		return '';
	}

	if ( ! $url ) {
		return $figura;
	}

	return sprintf(
		'<a class="lf-ad__link" href="%1$s" rel="sponsored noopener"%2$s>%3$s</a>',
		esc_url( $url ),
		$nova ? ' target="_blank"' : '',
		$figura
	);
}

/**
 * Monta o elemento <picture> de um anúncio.
 *
 * Um único <picture> com um <source> para o celular e um <img> de fallback
 * para o computador. O navegador baixa APENAS o recurso que escolher — a
 * estrutura anterior, com duas tags <img>, fazia o navegador baixar as duas
 * imagens quando ambas tinham carregamento prioritário.
 *
 * width e height vêm dos metadados do anexo, para reservar o espaço e evitar
 * salto de layout (CLS).
 *
 * @param int    $desktop_id Id do anexo da imagem de computador.
 * @param int    $mobile_id  Id do anexo da imagem de celular. Zero quando não houver.
 * @param string $alt        Texto alternativo.
 * @param bool   $acima      Está acima da dobra?
 * @return string HTML escapado, ou string vazia.
 */
function lf_get_ad_picture( $desktop_id, $mobile_id, $alt, $acima = false ) {

	$desktop_id = (int) $desktop_id;
	$mobile_id  = (int) $mobile_id;

	if ( ! $desktop_id ) {
		return '';
	}

	$src_desktop = wp_get_attachment_image_url( $desktop_id, 'full' );

	if ( ! $src_desktop ) {
		return '';
	}

	$meta_desktop = wp_get_attachment_metadata( $desktop_id );
	$w_desktop    = ! empty( $meta_desktop['width'] ) ? (int) $meta_desktop['width'] : 0;
	$h_desktop    = ! empty( $meta_desktop['height'] ) ? (int) $meta_desktop['height'] : 0;

	$srcset_desktop = wp_get_attachment_image_srcset( $desktop_id, 'full' );
	$sizes_desktop  = wp_get_attachment_image_sizes( $desktop_id, 'full' );

	$fonte_mobile = '';

	if ( $mobile_id ) {

		$src_mobile = wp_get_attachment_image_url( $mobile_id, 'full' );

		if ( $src_mobile ) {

			$srcset_mobile = wp_get_attachment_image_srcset( $mobile_id, 'full' );
			$meta_mobile   = wp_get_attachment_metadata( $mobile_id );
			$w_mobile      = ! empty( $meta_mobile['width'] ) ? (int) $meta_mobile['width'] : 0;
			$h_mobile      = ! empty( $meta_mobile['height'] ) ? (int) $meta_mobile['height'] : 0;

			$fonte_mobile = sprintf(
				'<source media="(max-width: 767px)" srcset="%1$s" sizes="100vw"%2$s%3$s>',
				esc_attr( $srcset_mobile ? $srcset_mobile : $src_mobile ),
				$w_mobile ? ' width="' . esc_attr( (string) $w_mobile ) . '"' : '',
				$h_mobile ? ' height="' . esc_attr( (string) $h_mobile ) . '"' : ''
			);
		}
	}

	$attrs = array(
		'src'      => esc_url( $src_desktop ),
		'alt'      => esc_attr( $alt ),
		'class'    => 'lf-ad__img',
		'decoding' => 'async',
	);

	if ( $w_desktop ) {
		$attrs['width'] = (string) $w_desktop;
	}

	if ( $h_desktop ) {
		$attrs['height'] = (string) $h_desktop;
	}

	if ( $srcset_desktop ) {
		$attrs['srcset'] = esc_attr( $srcset_desktop );
		$attrs['sizes']  = esc_attr( $sizes_desktop ? $sizes_desktop : '100vw' );
	}

	if ( $acima ) {
		$attrs['loading']       = 'eager';
		$attrs['fetchpriority'] = 'high';
	} else {
		$attrs['loading'] = 'lazy';
	}

	$pares = array();

	foreach ( $attrs as $chave => $valor ) {
		$pares[] = $chave . '="' . $valor . '"';
	}

	return sprintf(
		'<picture class="lf-ad__media">%1$s<img %2$s></picture>',
		$fonte_mobile,
		implode( ' ', $pares )
	);
}

/**
 * Estado do fallback do MetaSlider, para o painel.
 *
 * O fallback tem quatro dependências, e quando ele não aparece a causa é
 * sempre uma delas. Sem este diagnóstico, "o slider não apareceu" é
 * indistinguível de defeito de código.
 *
 * Nada é instalado, importado ou criado. Só leitura.
 *
 * @return array {
 *     @type bool   $ligado      A opção está marcada?
 *     @type bool   $plugin      O shortcode do MetaSlider existe?
 *     @type array  $posicoes    Estado por posição atendida.
 * }
 */
function lf_metaslider_status() {

	$ligado = (bool) lf_get_setting( 'metaslider_fallback', 0 );
	$plugin = shortcode_exists( 'metaslider' );

	$mapa = array(
		'home_billboard_top'   => (string) lf_get_setting( 'metaslider_home_id', '' ),
		'single_billboard_top' => (string) lf_get_setting( 'metaslider_single_id', '' ),
	);

	$posicoes = array();

	foreach ( $mapa as $posicao => $id_bruto ) {

		$id = preg_replace( '/[^0-9]/', '', $id_bruto );

		$existe = null;

		if ( $plugin && $id ) {
			// O MetaSlider guarda cada slider como um post do tipo ml-slider.
			$slider = get_post( (int) $id );
			$existe = ( $slider && 'ml-slider' === $slider->post_type );
		}

		$posicoes[ $posicao ] = array(
			'id'     => $id,
			'bruto'  => $id_bruto,
			'existe' => $existe,
		);
	}

	return array(
		'ligado'   => $ligado,
		'plugin'   => $plugin,
		'posicoes' => $posicoes,
	);
}

/**
 * Fallback temporário do MetaSlider.
 *
 * Mantém os banners atuais no ar durante a transição. Pode ser desligado no
 * painel (Anúncios > Configurações) quando os anúncios forem migrados.
 *
 * @param string $posicao Chave da posição.
 * @return string HTML do slider, ou string vazia.
 */
function lf_get_metaslider_fallback( $posicao ) {

	if ( ! lf_get_setting( 'metaslider_fallback', 0 ) ) {
		return '';
	}

	if ( ! shortcode_exists( 'metaslider' ) ) {
		return '';
	}

	$mapa = array(
		'home_billboard_top'   => (string) lf_get_setting( 'metaslider_home_id', '' ),
		'single_billboard_top' => (string) lf_get_setting( 'metaslider_single_id', '' ),
	);

	if ( empty( $mapa[ $posicao ] ) ) {
		return '';
	}

	$id = preg_replace( '/[^0-9]/', '', $mapa[ $posicao ] );

	if ( ! $id ) {
		return '';
	}

	return do_shortcode( '[metaslider id="' . $id . '"]' );
}

/**
 * Exibe a posição publicitária.
 *
 * Quando não há anúncio ativo nem fallback, nada é impresso — o espaço não
 * fica vazio no layout. Administradores logados veem uma marcação discreta da
 * posição, para saber onde ela fica.
 *
 * @param string $posicao Chave da posição.
 * @param string $variante Variante visual: billboard | halfpage | retangulo | intexto.
 */
function lf_render_ad_position( $posicao, $variante = 'billboard' ) {

	$posicoes = lf_ad_positions();

	if ( ! isset( $posicoes[ $posicao ] ) ) {
		return;
	}

	$ad   = lf_get_ad_for_position( $posicao );
	$html = lf_get_ad_html( $ad, $posicao );

	if ( ! $html ) {
		$html = lf_get_metaslider_fallback( $posicao );
	}

	// Nada a exibir.
	if ( ! $html ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		printf(
			'<div class="lf-ad lf-ad--%1$s lf-ad--vazio"><div class="lf-ad__label">%2$s</div><div class="lf-ad__box"><div class="lf-ad__vazio">%3$s</div><div class="lf-ad__formato">%4$s</div></div></div>',
			esc_attr( $variante ),
			esc_html__( 'Publicidade', 'licia-fabio-core' ),
			esc_html__( 'Espaço disponível', 'licia-fabio-core' ),
			esc_html( $posicoes[ $posicao ]['formato'] )
		);

		return;
	}

	printf(
		'<div class="lf-ad lf-ad--%1$s"><div class="lf-ad__label">%2$s</div>%3$s</div>',
		esc_attr( $variante ),
		esc_html__( 'Publicidade', 'licia-fabio-core' ),
		$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- montado e escapado em lf_get_ad_html().
	);
}

/**
 * Existe algo para exibir nesta posição?
 *
 * Útil quando o layout precisa decidir antes de abrir um contêiner.
 *
 * @param string $posicao Chave da posição.
 * @return bool
 */
function lf_has_ad( $posicao ) {

	if ( lf_get_ad_html( lf_get_ad_for_position( $posicao ), $posicao ) ) {
		return true;
	}

	return (bool) lf_get_metaslider_fallback( $posicao );
}
