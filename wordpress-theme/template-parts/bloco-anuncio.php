<?php
/**
 * Espaço publicitário.
 *
 * Só imprime quando há anúncio realmente disponível. A decisão é de
 * lf_ad_disponivel(), no servidor, antes de qualquer HTML — sem rótulo, caixa,
 * borda, altura ou margem sobrando quando a posição está vazia.
 *
 * @package licia-fabio
 *
 * Argumentos:
 *   $args['posicao']  Chave da posição (obrigatório).
 *   $args['variante'] billboard | halfpage | retangulo | intexto.
 */

$lf_pos = isset( $args['posicao'] ) ? $args['posicao'] : '';
$lf_var = isset( $args['variante'] ) ? $args['variante'] : 'billboard';

if ( ! $lf_pos ) {
	return;
}

if ( ! function_exists( 'lf_ad_disponivel' ) || ! lf_ad_disponivel( $lf_pos ) ) {
	return;
}

lf_ad( $lf_pos, $lf_var );
