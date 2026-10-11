<?php
/**
 * Página estática (institucional).
 *
 * @package licia-fabio
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<div class="lf-wrap lf-arch">
		<header class="lf-arch__head">
			<h1 class="lf-arch__title"><?php the_title(); ?></h1>
		</header>

		<div class="lf-conteudo" style="max-width:44em">
			<?php the_content(); ?>
		</div>
	</div>
	<?php
endwhile;

get_footer();
