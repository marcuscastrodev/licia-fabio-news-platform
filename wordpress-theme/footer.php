<?php
/**
 * Rodapé do site.
 *
 * @package licia-fabio
 */

?>
</main>

<footer class="lf-footer">
	<div class="lf-wrap lf-footer__cols">

		<div>
			<?php
			/*
			 * MARCA NO RODAPÉ.
			 *
			 * A duplicação no mobile vinha daqui: com o rodapé em coluna única,
			 * este logotipo cai logo abaixo do logotipo do cabeçalho, e a marca
			 * aparece duas vezes na mesma rolagem curta.
			 *
			 * A correção é na origem, não uma ocultação genérica: a imagem é
			 * marcada com uma classe própria e some apenas nas larguras
			 * estreitas, dando lugar ao nome do portal em texto. Assim a coluna
			 * não fica órfã e nada mais do rodapé é afetado.
			 */
			?>
			<img
				class="lf-footer__brand"
				src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/logo-licia-fabio.png' ); ?>"
				alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
				width="245"
				height="62"
				loading="lazy">

			<p class="lf-footer__brand-txt"><?php bloginfo( 'name' ); ?></p>

			<?php $desc = get_bloginfo( 'description', 'display' ); ?>
			<?php if ( $desc ) : ?>
				<p style="font-family:var(--lf-leitura);font-size:14px;line-height:1.5;color:var(--lf-texto);margin:0;max-width:30em"><?php echo esc_html( $desc ); ?></p>
			<?php endif; ?>

			<?php if ( has_nav_menu( 'social' ) ) : ?>
				<div class="lf-footer__social">
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

		<div>
			<h2 class="lf-footer__title"><?php esc_html_e( 'Editorias', 'licia-fabio' ); ?></h2>
			<div class="lf-footer__list">
				<?php
				wp_list_categories(
					array(
						'title_li'   => '',
						'number'     => 6,
						'orderby'    => 'count',
						'order'      => 'DESC',
						'show_count' => false,
						'style'      => '',
						'separator'  => '',
					)
				);
				?>
			</div>
		</div>

		<?php
		/*
		 * Coluna institucional: menu atribuído pelo administrador tem
		 * prioridade. Sem menu, entram as páginas institucionais publicadas,
		 * localizadas pelos slugs. Sem as duas coisas, a coluna não é aberta —
		 * cabeçalho sem conteúdo é pior que coluna a menos.
		 */
		$lf_tem_menu_rodape = has_nav_menu( 'rodape' );
		$lf_inst            = function_exists( 'lf_links_institucionais' ) ? lf_links_institucionais() : array();
		?>
		<?php if ( $lf_tem_menu_rodape || $lf_inst ) : ?>
			<div>
				<h2 class="lf-footer__title"><?php esc_html_e( 'Institucional', 'licia-fabio' ); ?></h2>
				<div class="lf-footer__list">
					<?php if ( $lf_tem_menu_rodape ) : ?>
						<?php
						wp_nav_menu(
							array(
								'theme_location' => 'rodape',
								'container'      => false,
								'items_wrap'     => '%3$s',
								'depth'          => 1,
								'fallback_cb'    => false,
							)
						);
						?>
					<?php else : ?>
						<?php foreach ( $lf_inst as $lf_link ) : ?>
							<a href="<?php echo esc_url( $lf_link['url'] ); ?>"><?php echo esc_html( $lf_link['titulo'] ); ?></a>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<div>
			<h2 class="lf-footer__title"><?php esc_html_e( 'Busca', 'licia-fabio' ); ?></h2>
			<?php get_search_form(); ?>
		</div>

	</div>

	<div class="lf-footer__legal">
		<div class="lf-wrap">
			<span>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?> · <?php esc_html_e( 'Salvador, Bahia', 'licia-fabio' ); ?></span>

			<?php
			/*
			 * "Política de Privacidade · Termos de Uso" era TEXTO SOLTO aqui,
			 * sem link — parecia navegação e não levava a lugar nenhum. Agora a
			 * política vira link de verdade quando a página existe publicada.
			 * O rótulo de termos de uso foi retirado: não há página
			 * correspondente prevista nesta rodada, e manter um rótulo morto é
			 * pior que não tê-lo. Registrado no relatório.
			 */
			$lf_priv = function_exists( 'lf_pagina_institucional' ) ? lf_pagina_institucional( 'politica-de-privacidade' ) : null;
			?>
			<?php if ( $lf_priv && 'publish' === $lf_priv->post_status ) : ?>
				<span><a href="<?php echo esc_url( get_permalink( $lf_priv ) ); ?>"><?php echo esc_html( get_the_title( $lf_priv ) ); ?></a></span>
			<?php endif; ?>
		</div>
	</div>

	<div class="lf-footer__credito">
		<div class="lf-wrap">
			<?php
			printf(
				/* translators: %s: link para a agência */
				esc_html__( 'Feito por %s.', 'licia-fabio' ),
				'<a href="https://agenciaemi.com/" target="_blank" rel="noopener noreferrer">agenciaemi.com</a>'
			);
			?>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
