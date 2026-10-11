<?php
/**
 * Campo de busca.
 *
 * @package licia-fabio
 */

$lf_id = 'lf-s-' . wp_rand( 1000, 9999 );
?>
<form role="search" method="get" class="lf-searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="lf-sr" for="<?php echo esc_attr( $lf_id ); ?>"><?php esc_html_e( 'Buscar no portal', 'licia-fabio' ); ?></label>
	<input
		type="search"
		id="<?php echo esc_attr( $lf_id ); ?>"
		name="s"
		value="<?php echo esc_attr( get_search_query() ); ?>"
		placeholder="<?php esc_attr_e( 'Buscar no portal…', 'licia-fabio' ); ?>">
	<button type="submit"><?php esc_html_e( 'Buscar', 'licia-fabio' ); ?></button>
</form>
