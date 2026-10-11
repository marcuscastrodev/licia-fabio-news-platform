<?php
/**
 * Agenda de eventos.
 *
 * @package licia-fabio-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Próximos eventos, ordenados pela data real do evento.
 *
 * Eventos passados saem da listagem pública sozinhos; nada é excluído do
 * banco — eles continuam acessíveis no painel e por URL direta.
 *
 * @param int $quantidade Número de eventos.
 * @return WP_Query
 */
function lf_query_upcoming_events( $quantidade = 4 ) {

	return new WP_Query(
		array(
			'post_type'      => 'lf_evento',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $quantidade ),
			'meta_key'       => '_lf_event_date',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'     => '_lf_event_date',
					// Data civil de hoje no fuso do site, para o corte não
					// escorregar um dia perto da meia-noite.
					'value'   => wp_date( 'Y-m-d' ),
					'compare' => '>=',
					'type'    => 'DATE',
				),
			),
		)
	);
}

/**
 * Converte a data do evento em um objeto de data no fuso do site.
 *
 * O CAMPO É UMA DATA CIVIL, não um instante. "2026-09-30" quer dizer o dia 30
 * em Salvador, e não meia-noite UTC.
 *
 * O código anterior fazia strtotime( '2026-09-30' ), que produz meia-noite no
 * fuso do PHP (UTC, na configuração do WordPress), e depois wp_date() convertia
 * para UTC-3 — voltando para as 21h do dia 29. Daí o evento do dia 30 aparecer
 * como 29.
 *
 * A correção é construir a data explicitamente em wp_timezone(), ancorada ao
 * meio-dia. O meio-dia não é enfeite: é o que mantém o dia correto mesmo em
 * fusos com horário de verão, onde a meia-noite de certos dias não existe.
 *
 * @param string $valor Valor bruto do campo, formato Y-m-d.
 * @return DateTimeImmutable|null Nulo quando vazio ou inválido.
 */
function lf_event_date_object( $valor ) {

	$valor = trim( (string) $valor );

	if ( ! $valor ) {
		return null;
	}

	$data = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $valor . ' 12:00:00', wp_timezone() );

	if ( ! $data ) {
		return null;
	}

	// Recusa data impossível: createFromFormat aceita 2026-02-30 e desliza
	// para 02/03. Comparar de volta pega isso.
	if ( $data->format( 'Y-m-d' ) !== $valor ) {
		return null;
	}

	return $data;
}

/**
 * Formata a data de um evento no fuso do site.
 *
 * @param string $valor  Valor bruto do campo.
 * @param string $formato Formato aceito por wp_date().
 * @return string String vazia quando a data é vazia ou inválida.
 */
function lf_event_date_format( $valor, $formato ) {

	$data = lf_event_date_object( $valor );

	if ( ! $data ) {
		return '';
	}

	return (string) wp_date( $formato, $data->getTimestamp() );
}

/**
 * Dados de exibição de um evento.
 *
 * Rotina única, usada pela home, pelo arquivo e pela página individual — para
 * que os três nunca divirjam de um dia.
 *
 * @param int|null $post_id Id do evento.
 * @return array
 */
function lf_get_event_data( $post_id = null ) {

	$post_id = $post_id ? (int) $post_id : get_the_ID();

	$data = (string) get_post_meta( $post_id, '_lf_event_date', true );

	return array(
		'data'     => $data,
		'dia'      => lf_event_date_format( $data, 'd' ),
		'mes'      => lf_event_date_format( $data, 'M' ),
		'completa' => lf_event_date_format( $data, 'j \d\e F \d\e Y' ),
		'semana'   => lf_event_date_format( $data, 'l' ),
		'hora'     => (string) get_post_meta( $post_id, '_lf_event_time', true ),
		'local'    => (string) get_post_meta( $post_id, '_lf_event_location', true ),
		'endereco' => (string) get_post_meta( $post_id, '_lf_event_address', true ),
		'url'      => (string) get_post_meta( $post_id, '_lf_event_url', true ),
	);
}

/**
 * Na listagem pública da agenda, mostra apenas eventos futuros, em ordem de data.
 *
 * @param WP_Query $query Consulta principal.
 */
function lf_events_archive_query( $query ) {

	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( ! $query->is_post_type_archive( 'lf_evento' ) ) {
		return;
	}

	$query->set( 'meta_key', '_lf_event_date' );
	$query->set( 'orderby', 'meta_value' );
	$query->set( 'order', 'ASC' );
	$query->set(
		'meta_query',
		array(
			array(
				'key'     => '_lf_event_date',
				'value'   => wp_date( 'Y-m-d' ),
				'compare' => '>=',
				'type'    => 'DATE',
			),
		)
	);
}
add_action( 'pre_get_posts', 'lf_events_archive_query' );
