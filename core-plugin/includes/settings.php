<?php
/**
 * Leitura das configurações do plugin.
 *
 * @package licia-fabio-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Valores padrão.
 *
 * Os padrões são conservadores de propósito: o contador próprio nasce
 * DESLIGADO, para não contar em dobro enquanto o plugin antigo de
 * visualizações estiver ativo; e o fallback do MetaSlider nasce LIGADO,
 * para que os banners atuais continuem aparecendo durante a transição.
 *
 * @return array
 */
function lf_core_default_settings() {

	return array(
		'views_counter_enabled'   => 0,
		'views_cookie_hours'      => 6,
		'most_read_days'          => 7,
		'metaslider_fallback'     => 1,
		'metaslider_home_id'      => '17385',
		'metaslider_single_id'    => '38426',
		/*
		 * Slugs confirmados no site atual. A única pendência é Concierge: essa
		 * editoria AINDA NÃO EXISTE e não é criada automaticamente — o campo
		 * fica vazio no painel, com "concierge" oferecido apenas como sugestão
		 * de slug para quando o administrador decidir criá-la.
		 */
		'category_map'            => array(
			'agenda'       => 'agenda',
			'beleza-pura'  => 'beleza-pura',
			'cultura'      => 'cultura',
			'concierge'    => '',
			'galeria'      => 'galeria-de-eventos',
			'gastro'       => 'gastronomia',
			'lf-producoes' => 'lf-producoes',
			'social'       => 'social',
			'tv-licia'     => 'tv-licia',
		),
		'home_stripes'            => array( '', '', '', '' ),
		'branded_category'        => '',
		'column_category'         => '',
		'tv_category'             => '',
		'transient_cache_minutes' => 10,

		/*
		 * Slug do arquivo da agenda.
		 *
		 * CONFLITO CONHECIDO: já existe uma CATEGORIA com o slug "agenda". Se o
		 * tipo de conteúdo usar o mesmo slug, as duas estruturas competem pelo
		 * mesmo endereço, e qual delas responde depende da estrutura de
		 * permalinks do site (em especial de haver ou não a base /category/).
		 *
		 * Por isso o padrão é "agenda-eventos", que é exclusivo. A troca não é
		 * silenciosa: está documentada no README e pode ser alterada aqui no
		 * painel por quem administra o site, depois de conferir o risco.
		 *
		 * A categoria "agenda" existente NÃO é alterada de forma alguma.
		 */
		'event_slug'              => 'agenda-eventos',
	);
}

/**
 * Lê uma configuração.
 *
 * @param string $chave  Chave da configuração.
 * @param mixed  $padrao Valor de retorno quando a chave não existe.
 * @return mixed
 */
function lf_get_setting( $chave, $padrao = null ) {

	$defaults = lf_core_default_settings();
	$opcoes   = get_option( LF_CORE_OPTION, array() );

	if ( ! is_array( $opcoes ) ) {
		$opcoes = array();
	}

	if ( isset( $opcoes[ $chave ] ) ) {
		return $opcoes[ $chave ];
	}

	if ( null !== $padrao ) {
		return $padrao;
	}

	return isset( $defaults[ $chave ] ) ? $defaults[ $chave ] : null;
}

/**
 * Slug do arquivo público da agenda.
 *
 * @return string
 */
function lf_event_slug() {

	$slug = sanitize_title( (string) lf_get_setting( 'event_slug', 'agenda-eventos' ) );

	return $slug ? $slug : 'agenda-eventos';
}

/**
 * Existe uma categoria com o mesmo slug do arquivo da agenda?
 *
 * Usado pelo aviso no painel. Não altera nada.
 *
 * @return bool
 */
function lf_event_slug_conflicts() {
	return (bool) get_category_by_slug( lf_event_slug() );
}

/**
 * Slug sugerido para uma seção que ainda não tem categoria.
 *
 * É apenas sugestão exibida no painel. Nenhuma categoria é criada a partir
 * daqui, em nenhuma circunstância.
 *
 * @param string $secao Chave da seção.
 * @return string
 */
function lf_suggested_slug( $secao ) {

	$sugestoes = array(
		'concierge' => 'concierge',
	);

	return isset( $sugestoes[ $secao ] ) ? $sugestoes[ $secao ] : '';
}

/**
 * Categoria configurada para uma seção visual.
 *
 * O tema NUNCA usa IDs fixos de categoria: pede pelo nome da seção, e esta
 * função devolve o termo correspondente pelo slug configurado. Isso mantém o
 * site funcionando entre produção e staging, onde os IDs diferem.
 *
 * @param string $secao Chave da seção (ex.: 'galeria', 'cultura').
 * @return WP_Term|null
 */
function lf_get_section_category( $secao ) {

	$mapa = lf_get_setting( 'category_map', array() );
	$slug = isset( $mapa[ $secao ] ) ? $mapa[ $secao ] : '';

	/*
	 * Sem configuração explícita, devolve null. Deliberadamente NÃO adivinha o
	 * slug a partir do nome da seção: uma seção não configurada simplesmente
	 * não aparece no site, e nada é criado.
	 */
	if ( ! $slug ) {
		return null;
	}

	$termo = get_category_by_slug( $slug );

	return $termo ? $termo : null;
}

/**
 * Minutos de cache para blocos pesados da home.
 *
 * @return int
 */
function lf_cache_minutes() {
	return max( 0, (int) lf_get_setting( 'transient_cache_minutes', 10 ) );
}

/**
 * Limpa os transients dos blocos da home.
 *
 * Chamado quando qualquer notícia ou evento é publicado ou atualizado.
 */
function lf_flush_home_cache() {

	delete_transient( 'lf_home_featured' );
	delete_transient( 'lf_home_most_read' );
	delete_transient( 'lf_home_events' );

	$mapa = lf_get_setting( 'category_map', array() );

	foreach ( array_keys( (array) $mapa ) as $secao ) {
		delete_transient( 'lf_stripe_' . $secao );
	}
}

/**
 * Dispara a limpeza do cache ao salvar conteúdo relevante.
 *
 * @param int     $post_id Id do post.
 * @param WP_Post $post    Objeto do post.
 */
function lf_flush_home_cache_on_save( $post_id, $post ) {

	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	if ( ! in_array( $post->post_type, array( 'post', 'lf_evento', 'lf_anuncio' ), true ) ) {
		return;
	}

	lf_flush_home_cache();
}
add_action( 'save_post', 'lf_flush_home_cache_on_save', 10, 2 );
add_action( 'deleted_post', 'lf_flush_home_cache' );
add_action( 'trashed_post', 'lf_flush_home_cache' );
