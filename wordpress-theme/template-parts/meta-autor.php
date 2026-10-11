<?php
/**
 * Autor, data e tempo de leitura.
 *
 * @package licia-fabio
 */

$lf_views = lf_views();
?>
<div class="lf-autor">
	<?php echo get_avatar( get_the_author_meta( 'ID' ), 88, '', '', array( 'class' => 'lf-autor__foto' ) ); ?>

	<div style="flex:1">
		<div class="lf-autor__nome"><?php the_author(); ?></div>
		<div class="lf-autor__data">
			<?php
			printf(
				/* translators: 1: data, 2: minutos de leitura */
				esc_html__( '%1$s · %2$d min de leitura', 'licia-fabio' ),
				esc_html( lf_the_date_text() ),
				(int) lf_reading_time()
			);
			?>
		</div>
	</div>

	<?php if ( $lf_views > 0 ) : ?>
		<div class="lf-views">
			<?php
			printf(
				/* translators: número de visualizações */
				esc_html( _n( '%s leitura', '%s leituras', $lf_views, 'licia-fabio' ) ),
				esc_html( number_format_i18n( $lf_views ) )
			);
			?>
		</div>
	<?php endif; ?>
</div>
