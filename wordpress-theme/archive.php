<?php
/**
 * Listagem genérica: tag, autor, datas e agenda.
 *
 * @package licia-fabio
 */

get_header();
?>

<div class="lf-wrap lf-arch">

	<header class="lf-arch__head">
		<div class="lf-arch__kicker">
			<?php
			if ( is_tag() ) {
				esc_html_e( 'Assunto', 'licia-fabio' );
			} elseif ( is_author() ) {
				esc_html_e( 'Autor', 'licia-fabio' );
			} elseif ( is_post_type_archive( 'lf_evento' ) ) {
				esc_html_e( 'Agenda', 'licia-fabio' );
			} else {
				esc_html_e( 'Arquivo', 'licia-fabio' );
			}
			?>
		</div>
		<h1 class="lf-arch__title"><?php echo esc_html( wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
		<?php $lf_desc = get_the_archive_description(); ?>
		<?php if ( $lf_desc ) : ?>
			<div class="lf-arch__desc"><?php echo wp_kses_post( $lf_desc ); ?></div>
		<?php endif; ?>
	</header>

	<?php if ( have_posts() ) : ?>

		<?php if ( is_post_type_archive( 'lf_evento' ) ) : ?>

			<div class="lf-agenda">
				<?php
				while ( have_posts() ) :
					the_post();
					$lf_ev = function_exists( 'lf_get_event_data' ) ? lf_get_event_data() : array();
					?>
					<div class="lf-agenda__item">
						<div class="lf-agenda__data">
							<div class="lf-agenda__dia"><?php echo esc_html( ! empty( $lf_ev['dia'] ) ? $lf_ev['dia'] : '—' ); ?></div>
							<div class="lf-agenda__mes"><?php echo esc_html( ! empty( $lf_ev['mes'] ) ? $lf_ev['mes'] : '' ); ?></div>
						</div>
						<div>
							<h2 class="lf-agenda__nome"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<?php if ( ! empty( $lf_ev['local'] ) ) : ?>
								<div class="lf-agenda__local">
									<?php
									echo esc_html( $lf_ev['local'] );

									if ( ! empty( $lf_ev['hora'] ) ) {
										echo esc_html( ' · ' . $lf_ev['hora'] );
									}
									?>
								</div>
							<?php endif; ?>
						</div>
					</div>
					<?php
				endwhile;
				?>
			</div>

		<?php else : ?>

			<div class="lf-grid lf-grid--3">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/card-noticia', null, array( 'variante' => 'sm' ) );
				endwhile;
				?>
			</div>

		<?php endif; ?>

		<?php lf_pagination(); ?>

	<?php else : ?>

		<div class="lf-vazio">
			<h2 class="lf-vazio__title">
				<?php
				if ( is_post_type_archive( 'lf_evento' ) ) {
					esc_html_e( 'Nenhum evento na agenda', 'licia-fabio' );
				} else {
					esc_html_e( 'Nada encontrado aqui', 'licia-fabio' );
				}
				?>
			</h2>
			<p class="lf-vazio__text">
				<?php
				if ( is_post_type_archive( 'lf_evento' ) ) {
					esc_html_e( 'Os próximos eventos aparecem aqui quando forem cadastrados.', 'licia-fabio' );
				} else {
					esc_html_e( 'Tente outra editoria ou use a busca.', 'licia-fabio' );
				}
				?>
			</p>
			<?php get_search_form(); ?>
		</div>

	<?php endif; ?>

</div>

<?php
get_footer();
