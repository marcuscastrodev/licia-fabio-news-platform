<?php
/**
 * Barra e menu do celular.
 *
 * @package licia-fabio
 */

?>
<div class="lf-mobbar">
	<button
		class="lf-mobbar__burger"
		type="button"
		aria-expanded="false"
		aria-controls="lf-mobmenu"
		aria-label="<?php esc_attr_e( 'Abrir menu de editorias', 'licia-fabio' ); ?>"
		data-label-abrir="<?php esc_attr_e( 'Abrir menu de editorias', 'licia-fabio' ); ?>"
		data-label-fechar="<?php esc_attr_e( 'Fechar menu', 'licia-fabio' ); ?>">
		<span></span><span></span><span></span>
	</button>

	<a class="lf-mobbar__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
		<img
			src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/logo-licia-fabio.png' ); ?>"
			alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
			width="158"
			height="40">
	</a>

	<a class="lf-mobbar__search" href="<?php echo esc_url( home_url( '/?s=' ) ); ?>" aria-label="<?php esc_attr_e( 'Buscar', 'licia-fabio' ); ?>">
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
	</a>
</div>

<nav id="lf-mobmenu" class="lf-mobmenu" aria-label="<?php esc_attr_e( 'Editorias', 'licia-fabio' ); ?>">
	<div class="lf-mobmenu__form"><?php get_search_form(); ?></div>
	<?php
	/*
	 * O rótulo só é impresso quando existe navegação para acompanhá-lo — um
	 * "Editorias" sozinho, sem lista abaixo, era o defeito relatado.
	 */
	if ( lf_tem_nav_editorias( 'principal' ) ) :
		?>
		<div class="lf-mobmenu__label"><?php esc_html_e( 'Editorias', 'licia-fabio' ); ?></div>
		<?php
	endif;

	lf_nav_editorias( 'principal' );
	?>
	<?php if ( has_nav_menu( 'social' ) ) : ?>
		<div class="lf-mobmenu__social">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'social',
					'container'      => false,
					'items_wrap'     => '%3$s',
					'depth'          => 1,
					'fallback_cb'    => false,
				)
			);
			?>
		</div>
	<?php endif; ?>
</nav>
