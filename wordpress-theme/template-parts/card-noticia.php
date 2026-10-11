<?php
/**
 * Card de notícia.
 *
 * @package licia-fabio
 *
 * Argumentos: $args['variante'] = 'padrao' | 'sm' | 'row'
 */

$lf_var    = isset( $args['variante'] ) ? $args['variante'] : 'padrao';
$lf_classe = 'lf-card';

if ( 'sm' === $lf_var ) {
	$lf_classe .= ' lf-card--sm';
} elseif ( 'row' === $lf_var ) {
	$lf_classe .= ' lf-card--row';
}

$lf_size = 'row' === $lf_var ? 'lf-quadrado' : 'lf-card';
?>
<article class="<?php echo esc_attr( $lf_classe ); ?>">

	<?php if ( has_post_thumbnail() ) : ?>
		<a class="lf-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
			<?php
			the_post_thumbnail(
				$lf_size,
				array(
					'alt'      => the_title_attribute( array( 'echo' => false ) ),
					'decoding' => 'async',
				)
			);
			?>
		</a>
	<?php endif; ?>

	<div>
		<?php if ( lf_is_branded() ) : ?>
			<span class="lf-branded"><?php esc_html_e( 'Conteúdo de marca', 'licia-fabio' ); ?></span>
		<?php endif; ?>

		<?php lf_the_category_label(); ?>

		<h2 class="lf-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>

		<div class="lf-card__meta">
			<?php
			printf(
				/* translators: tempo desde a publicação */
				esc_html__( 'há %s', 'licia-fabio' ),
				esc_html( lf_time_ago() )
			);
			?>
		</div>
	</div>
</article>
