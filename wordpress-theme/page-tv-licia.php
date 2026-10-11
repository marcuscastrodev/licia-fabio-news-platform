<?php
/**
 * Página TV Lícia (/tv-licia/).
 *
 * Carregado automaticamente pela hierarquia de templates (page-{slug}.php)
 * para a página com slug tv-licia, e também pelo filtro lf_tv_template(),
 * que garante o uso mesmo quando a página tem um template do Elementor.
 *
 * Um único player. Ao abrir, mostra só a capa do primeiro vídeo — nada é
 * reproduzido nem carregado do YouTube além das miniaturas. O iframe só é
 * criado quando alguém escolhe assistir.
 *
 * @package licia-fabio
 */

get_header();

while ( have_posts() ) :
	the_post();

	$lf_videos   = function_exists( 'lf_tv_videos' ) ? lf_tv_videos( get_the_ID() ) : array();
	$lf_primeiro = $lf_videos ? $lf_videos[0] : null;
	$lf_texto    = (bool) get_post_meta( get_the_ID(), '_lf_tv_mostrar_texto', true );
	?>
	<div class="lf-wrap lf-arch lf-tv">

		<header class="lf-arch__head">
			<h1 class="lf-arch__title"><?php esc_html_e( 'TV Lícia', 'licia-fabio' ); ?></h1>
		</header>

		<?php if ( $lf_texto && '' !== trim( get_the_content() ) ) : ?>
			<div class="lf-conteudo lf-tv__intro"><?php the_content(); ?></div>
		<?php endif; ?>

		<?php if ( ! $lf_primeiro ) : ?>

			<p class="lf-tv__vazio"><?php esc_html_e( 'Nenhum vídeo disponível no momento.', 'licia-fabio' ); ?></p>

		<?php else : ?>

			<div class="lf-tv__grid" data-lf-tv>

				<div class="lf-tv__player">
					<div class="lf-tv__frame" data-lf-tv-frame>
						<a class="lf-tv__poster" href="<?php echo esc_url( $lf_primeiro['url'] ); ?>" data-lf-tv-poster>
							<img
								src="<?php echo esc_url( 'https://i.ytimg.com/vi/' . $lf_primeiro['id'] . '/hqdefault.jpg' ); ?>"
								alt=""
								width="480"
								height="360"
								fetchpriority="high"
								data-lf-tv-capa>
							<span class="lf-tv__play" aria-hidden="true">
								<svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M8 5.5v13l11-6.5z"/></svg>
							</span>
							<span class="lf-sr" data-lf-tv-rotulo>
								<?php
								/* translators: %s: título do vídeo */
								printf( esc_html__( 'Reproduzir: %s', 'licia-fabio' ), esc_html( $lf_primeiro['titulo'] ) );
								?>
							</span>
						</a>
					</div>

					<div aria-live="polite">
						<h2 class="lf-tv__atual" data-lf-tv-titulo><?php echo esc_html( $lf_primeiro['titulo'] ); ?></h2>
						<p class="lf-tv__atual-dur" data-lf-tv-duracao><?php echo esc_html( $lf_primeiro['duracao'] ); ?></p>
					</div>
				</div>

				<div class="lf-tv__lista-wrap">
					<h2 class="lf-tv__lista-head">
						<?php
						/* translators: %d: quantidade de vídeos */
						printf( esc_html( _n( 'Playlist · %d vídeo', 'Playlist · %d vídeos', count( $lf_videos ), 'licia-fabio' ) ), (int) count( $lf_videos ) );
						?>
					</h2>

					<ol class="lf-tv__lista">
						<?php foreach ( $lf_videos as $lf_n => $lf_v ) : ?>
							<li>
								<a
									class="lf-tv__item"
									href="<?php echo esc_url( $lf_v['url'] ); ?>"
									data-id="<?php echo esc_attr( $lf_v['id'] ); ?>"
									data-titulo="<?php echo esc_attr( $lf_v['titulo'] ); ?>"
									data-duracao="<?php echo esc_attr( $lf_v['duracao'] ); ?>"
									<?php echo 0 === $lf_n ? 'aria-current="true"' : ''; ?>>
									<span class="lf-tv__thumb">
										<img
											src="<?php echo esc_url( 'https://i.ytimg.com/vi/' . $lf_v['id'] . '/mqdefault.jpg' ); ?>"
											alt=""
											width="320"
											height="180"
											loading="lazy"
											decoding="async">
										<?php if ( $lf_v['duracao'] ) : ?>
											<span class="lf-tv__dur"><span class="lf-sr"><?php esc_html_e( 'Duração:', 'licia-fabio' ); ?> </span><?php echo esc_html( $lf_v['duracao'] ); ?></span>
										<?php endif; ?>
									</span>
									<span class="lf-tv__item-titulo"><?php echo esc_html( $lf_v['titulo'] ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ol>
				</div>

			</div>

		<?php endif; ?>

	</div>
	<?php
endwhile;

get_footer();
