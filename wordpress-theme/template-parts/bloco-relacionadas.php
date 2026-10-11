<?php
/**
 * Notícias da mesma editoria.
 *
 * @package licia-fabio
 */

$lf_rel = lf_related_query( get_the_ID(), 3 );

if ( ! $lf_rel->have_posts() ) {
	return;
}

$lf_cat  = lf_primary_category();
$lf_nome = $lf_cat ? sprintf( __( 'Mais de %s', 'licia-fabio' ), $lf_cat->name ) : __( 'Leia também', 'licia-fabio' );
?>
<section class="lf-relacionadas">

	<?php lf_section_header( $lf_nome, $lf_cat ); ?>

	<div class="lf-grid lf-grid--3">
		<?php
		while ( $lf_rel->have_posts() ) :
			$lf_rel->the_post();
			get_template_part( 'template-parts/card-noticia', null, array( 'variante' => 'sm' ) );
		endwhile;

		wp_reset_postdata();
		?>
	</div>
</section>
