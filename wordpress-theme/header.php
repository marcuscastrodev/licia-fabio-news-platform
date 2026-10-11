<?php
/**
 * Topo do site.
 *
 * @package licia-fabio
 */

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="lf-sr" href="#lf-conteudo"><?php esc_html_e( 'Ir para o conteúdo', 'licia-fabio' ); ?></a>

<?php if ( ! function_exists( 'lf_render_ad_position' ) && current_user_can( 'activate_plugins' ) ) : ?>
	<div class="lf-core-missing">
		<?php esc_html_e( 'O plugin Lícia Fábio Core não está ativo. Subtítulos, destaques manuais, anúncios e agenda não aparecem enquanto isso. Este aviso é visível apenas para administradores.', 'licia-fabio' ); ?>
	</div>
<?php endif; ?>

<?php
/*
 * BARRA MOBILE FIXA.
 *
 * Fica FORA do <header>, como filha direta do <body>. Um elemento sticky só
 * gruda enquanto o pai está na tela; dentro do .lf-header a barra se soltaria
 * assim que o cabeçalho saísse da tela. Aqui o limite é a página inteira.
 *
 * A marca grande (.lf-brand), logo abaixo dentro do <header>, continua no
 * fluxo normal — sem sticky nem fixed — e sobe com o conteúdo ao rolar.
 * Nenhuma instância nova da marca foi criada.
 */
?>
<div class="lf-mobhead">
	<?php get_template_part( 'template-parts/menu-mobile' ); ?>
</div>

<header class="lf-header">

	<div class="lf-topbar">
		<div class="lf-wrap lf-topbar__inner">
			<div class="lf-topbar__left">
				<span style="color:var(--lf-vermelho)">&#9679;</span>
				<span><?php esc_html_e( 'Salvador · Bahia', 'licia-fabio' ); ?></span>
				<span class="lf-topbar__sep">|</span>
				<span><?php echo esc_html( wp_date( 'l, j \d\e F' ) ); ?></span>
			</div>
			<div class="lf-topbar__right">
				<?php
				/*
				 * O "Anuncie aqui" era texto fixo aqui — não vinha de menu nem
				 * de configuração do WordPress. Removido junto do separador que
				 * o seguia; o gap do flex fecha sozinho, sem margem residual.
				 * Nada mais do cabeçalho foi tocado.
				 */
				?>
				<?php if ( has_nav_menu( 'social' ) ) : ?>
					<div class="lf-topbar__social">
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
			</div>
		</div>
	</div>

	<div class="lf-brand">
		<a class="lf-brand__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<img
				src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/logo-licia-fabio.png' ); ?>"
				alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
				width="300"
				height="76">
		</a>
		<?php $desc = get_bloginfo( 'description', 'display' ); ?>
		<?php if ( $desc ) : ?>
			<div class="lf-brand__tagline"><?php echo esc_html( $desc ); ?></div>
		<?php endif; ?>
	</div>

	<nav class="lf-nav" aria-label="<?php esc_attr_e( 'Editorias', 'licia-fabio' ); ?>">
		<div class="lf-wrap lf-nav__inner">
			<?php
			/*
			 * Menu atribuído pelo administrador tem prioridade. Sem menu, cai
			 * nas editorias configuradas. Sem as duas, nada é impresso.
			 */
			lf_nav_editorias( 'principal' );
			?>
			<a class="lf-nav__search" href="<?php echo esc_url( home_url( '/?s=' ) ); ?>" aria-label="<?php esc_attr_e( 'Buscar', 'licia-fabio' ); ?>">
				<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
			</a>
		</div>
	</nav>

</header>

<main id="lf-conteudo">
