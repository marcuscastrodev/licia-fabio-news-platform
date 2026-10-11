<?php
/**
 * Página de matéria.
 *
 * O conteúdo é renderizado por the_content(), sem filtro que altere o HTML
 * clássico, os shortcodes ou os embeds do acervo.
 *
 * @package licia-fabio
 */

get_header();

while ( have_posts() ) :
	the_post();

	$lf_cat   = lf_primary_category();
	$lf_sub   = lf_subtitle();
	$lf_thumb = get_post_thumbnail_id();
	?>

	<?php if ( function_exists( 'lf_ad_disponivel' ) && lf_ad_disponivel( 'single_billboard_top' ) ) : ?>
		<div class="lf-wrap">
			<?php get_template_part( 'template-parts/bloco-anuncio', null, array( 'posicao' => 'single_billboard_top', 'variante' => 'billboard' ) ); ?>
		</div>
	<?php endif; ?>

	<div class="lf-wrap lf-single">
		<article <?php post_class(); ?>>

			<div class="lf-crumbs">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'licia-fabio' ); ?></a>
				<?php if ( $lf_cat ) : ?>
					· <a href="<?php echo esc_url( get_category_link( $lf_cat->term_id ) ); ?>"><?php echo esc_html( $lf_cat->name ); ?></a>
				<?php endif; ?>
			</div>

			<?php if ( lf_is_branded() ) : ?>
				<span class="lf-branded"><?php esc_html_e( 'Conteúdo de marca', 'licia-fabio' ); ?></span>
			<?php endif; ?>

			<h1 class="lf-single__title"><?php the_title(); ?></h1>

			<?php // Sem subtítulo, nenhuma marcação é impressa. ?>
			<?php if ( $lf_sub ) : ?>
				<p class="lf-single__sub"><?php echo esc_html( $lf_sub ); ?></p>
			<?php endif; ?>

			<?php get_template_part( 'template-parts/meta-autor' ); ?>

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
							'decoding'      => 'sync',
						)
					);

					// Legenda do anexo + crédito. Vazio não imprime figcaption.
					$lf_legenda = lf_caption_with_credit( $lf_thumb );

					if ( $lf_legenda ) :
						?>
						<figcaption><?php echo esc_html( $lf_legenda ); ?></figcaption>
						<?php
					endif;
					?>
				</figure>
			<?php endif; ?>

			<div class="lf-conteudo">
				<?php the_content(); ?>
			</div>

			<?php get_template_part( 'template-parts/bloco-anuncio', null, array( 'posicao' => 'single_middle', 'variante' => 'intexto' ) ); ?>

			<?php
			// Etiquetas da matéria.
			$lf_tags = get_the_tags();

			if ( ! empty( $lf_tags ) ) :
				?>
				<div class="lf-vazio__cats" style="justify-content:flex-start;margin:34px 0 0">
					<?php foreach ( $lf_tags as $lf_tag ) : ?>
						<a href="<?php echo esc_url( get_tag_link( $lf_tag->term_id ) ); ?>"><?php echo esc_html( $lf_tag->name ); ?></a>
					<?php endforeach; ?>
				</div>
				<?php
			endif;
			?>

			<?php get_template_part( 'template-parts/bloco-relacionadas' ); ?>

			<?php get_template_part( 'template-parts/bloco-anuncio', null, array( 'posicao' => 'single_end', 'variante' => 'billboard' ) ); ?>

		</article>

		<?php get_sidebar(); ?>
	</div>

	<?php
endwhile;

get_footer();
