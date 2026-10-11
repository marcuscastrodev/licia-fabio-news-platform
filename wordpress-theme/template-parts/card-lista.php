<?php
/**
 * Item em lista compacta, sem foto.
 *
 * @package licia-fabio
 */

?>
<article class="lf-card lf-card--lista">
	<?php lf_the_category_label(); ?>
	<h3 class="lf-card__title" style="font-size:19px"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
	<div class="lf-card__meta"><?php echo esc_html( lf_the_date_text() ); ?></div>
</article>
