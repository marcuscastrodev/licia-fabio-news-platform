<?php
/**
 * Página individual de um evento da agenda.
 *
 * A hierarquia de templates do WordPress escolhe este arquivo automaticamente
 * para o tipo de conteúdo lf_evento, antes de cair em single.php. Antes dele, o
 * evento era renderizado como post genérico — daí os campos "Dados do evento"
 * não aparecerem em nenhum lugar da página.
 *
 * Nada aqui altera o slug nem os permalinks: o endereço do evento continua o
 * mesmo, definido pela configuração do arquivo da agenda.
 *
 * Cada campo vazio é simplesmente omitido — nenhuma linha, rótulo ou separador
 * solto é impresso.
 *
 * @package licia-fabio
 */

get_header();

while ( have_posts() ) :
	the_post();

	$lf_ev     = function_exists( 'lf_get_event_data' ) ? lf_get_event_data() : array();
	$lf_thumb  = get_post_thumbnail_id();
	$lf_ev_id  = get_the_ID();

	// Rótulo do arquivo da agenda, para a trilha.
	$lf_obj      = get_post_type_object( 'lf_evento' );
	$lf_arquivo  = get_post_type_archive_link( 'lf_evento' );
	$lf_passado  = false;

	if ( ! empty( $lf_ev['data'] ) ) {
		$lf_passado = $lf_ev['data'] < wp_date( 'Y-m-d' );
	}
	?>

	<div class="lf-wrap lf-single">
		<article <?php post_class(); ?>>

			<div class="lf-crumbs">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'licia-fabio' ); ?></a>
				<?php if ( $lf_arquivo ) : ?>
					· <a href="<?php echo esc_url( $lf_arquivo ); ?>"><?php echo esc_html( $lf_obj ? $lf_obj->labels->name : __( 'Agenda', 'licia-fabio' ) ); ?></a>
				<?php endif; ?>
			</div>

			<h1 class="lf-single__title"><?php the_title(); ?></h1>

			<?php if ( $lf_passado ) : ?>
				<p class="lf-evento__passado"><?php esc_html_e( 'Este evento já aconteceu.', 'licia-fabio' ); ?></p>
			<?php endif; ?>

			<?php if ( $lf_thumb ) : ?>
				<figure class="lf-figura">
					<?php
					echo wp_get_attachment_image(
						$lf_thumb,
						'lf-destaque',
						false,
						array(
							'alt'           => esc_attr( get_post_meta( $lf_thumb, '_wp_attachment_image_alt', true ) ),
							'fetchpriority' => 'high',
							'loading'       => 'eager',
							'decoding'      => 'async',
						)
					);

					$lf_legenda = function_exists( 'lf_caption_with_credit' ) ? lf_caption_with_credit( $lf_thumb ) : '';

					if ( $lf_legenda ) :
						?>
						<figcaption class="lf-figura__cap"><?php echo esc_html( $lf_legenda ); ?></figcaption>
						<?php
					endif;
					?>
				</figure>
			<?php endif; ?>

			<?php
			// Ficha do evento. Só é impressa se houver ao menos um campo.
			$lf_linhas = array();

			if ( ! empty( $lf_ev['completa'] ) ) {
				$lf_texto = $lf_ev['completa'];

				if ( ! empty( $lf_ev['semana'] ) ) {
					$lf_texto = $lf_ev['semana'] . ', ' . $lf_texto;
				}

				$lf_linhas[] = array( __( 'Data', 'licia-fabio' ), $lf_texto );
			}

			if ( ! empty( $lf_ev['hora'] ) ) {
				$lf_linhas[] = array( __( 'Horário', 'licia-fabio' ), $lf_ev['hora'] );
			}

			if ( ! empty( $lf_ev['local'] ) ) {
				$lf_linhas[] = array( __( 'Local', 'licia-fabio' ), $lf_ev['local'] );
			}

			if ( ! empty( $lf_ev['endereco'] ) ) {
				$lf_linhas[] = array( __( 'Endereço', 'licia-fabio' ), $lf_ev['endereco'] );
			}

			if ( $lf_linhas || ! empty( $lf_ev['url'] ) ) :
				?>
				<div class="lf-evento__ficha">
					<?php if ( $lf_linhas ) : ?>
						<dl class="lf-evento__dados">
							<?php foreach ( $lf_linhas as $lf_linha ) : ?>
								<div class="lf-evento__linha">
									<dt><?php echo esc_html( $lf_linha[0] ); ?></dt>
									<dd><?php echo esc_html( $lf_linha[1] ); ?></dd>
								</div>
							<?php endforeach; ?>
						</dl>
					<?php endif; ?>

					<?php if ( ! empty( $lf_ev['url'] ) ) : ?>
						<a class="lf-evento__cta" href="<?php echo esc_url( $lf_ev['url'] ); ?>" rel="noopener">
							<?php esc_html_e( 'Ingressos e informações', 'licia-fabio' ); ?>
						</a>
					<?php endif; ?>
				</div>
				<?php
			endif;
			?>

			<div class="lf-conteudo">
				<?php the_content(); ?>
			</div>

		</article>
	</div>

	<?php
	// Próximos eventos, sem repetir o que está aberto.
	$lf_outros = function_exists( 'lf_query_upcoming_events' ) ? lf_query_upcoming_events( 5 ) : null;

	if ( $lf_outros && $lf_outros->have_posts() ) :
		?>
		<section class="lf-wrap lf-sec lf-sec--pad">
			<?php lf_section_header( __( 'Próximos eventos', 'licia-fabio' ) ); ?>
			<div class="lf-agenda__lista">
				<?php
				$lf_n = 0;

				while ( $lf_outros->have_posts() ) :
					$lf_outros->the_post();

					// Não repete o evento que está aberto.
					if ( get_the_ID() === $lf_ev_id ) {
						continue;
					}

					if ( $lf_n >= 4 ) {
						break;
					}

					$lf_n++;
					$lf_item = lf_get_event_data();
					?>
					<div class="lf-agenda__item">
						<div class="lf-agenda__data">
							<div class="lf-agenda__dia"><?php echo esc_html( ! empty( $lf_item['dia'] ) ? $lf_item['dia'] : '—' ); ?></div>
							<div class="lf-agenda__mes"><?php echo esc_html( ! empty( $lf_item['mes'] ) ? $lf_item['mes'] : '' ); ?></div>
						</div>
						<div>
							<h3 class="lf-agenda__nome"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
							<?php if ( ! empty( $lf_item['local'] ) ) : ?>
								<div class="lf-agenda__local">
									<?php
									echo esc_html( $lf_item['local'] );

									if ( ! empty( $lf_item['hora'] ) ) {
										echo esc_html( ' · ' . $lf_item['hora'] );
									}
									?>
								</div>
							<?php endif; ?>
						</div>
					</div>
					<?php
				endwhile;

				wp_reset_postdata();
				?>
			</div>
		</section>
		<?php
	endif;

endwhile;

get_footer();
