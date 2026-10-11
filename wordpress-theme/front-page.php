<?php
/**
 * Página inicial do portal.
 *
 * Cada bloco tem consulta própria e limitada, exclui o que já foi usado nos
 * blocos anteriores e restaura o post global. Nenhuma consulta é ilimitada.
 *
 * @package licia-fabio
 */

get_header();

// Ids já exibidos, para não repetir matéria na mesma página.
$lf_usados = array();
?>

<?php // ---------- plantão ---------- ?>
<?php
/*
 * Os destaques são resolvidos primeiro: eles têm precedência sobre todos os
 * outros blocos. Em seguida o plantão, excluindo o que já está em destaque.
 * Cada bloco acrescenta o que exibiu a $lf_usados, e o próximo exclui a lista
 * inteira — assim nenhuma matéria aparece duas vezes na mesma página.
 */
$lf_destaques = lf_home_featured_posts();
$lf_usados    = wp_list_pluck( $lf_destaques, 'ID' );
$lf_plantao   = lf_breaking_query( 3, $lf_usados );

if ( $lf_plantao->have_posts() ) :
	?>
	<div class="lf-plantao">
		<div class="lf-wrap lf-plantao__inner">
			<span class="lf-plantao__tag"><?php esc_html_e( 'Agora', 'licia-fabio' ); ?></span>
			<div class="lf-plantao__list">
				<?php
				$lf_itens = array();

				while ( $lf_plantao->have_posts() ) :
					$lf_plantao->the_post();
					$lf_usados[] = get_the_ID();
					$lf_itens[]  = '<a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a>';
				endwhile;

				wp_reset_postdata();

				echo wp_kses_post( implode( ' · ', $lf_itens ) );
				?>
			</div>
		</div>
	</div>
	<?php
endif;
?>

<?php // ---------- billboard ---------- ?>
<?php
/*
 * O contêiner só é aberto se houver anúncio. Imprimir a div vazia deixaria o
 * padding do .lf-wrap como faixa em branco — espaço reservado sem conteúdo.
 */
if ( function_exists( 'lf_ad_disponivel' ) && lf_ad_disponivel( 'home_billboard_top' ) ) :
	?>
	<div class="lf-wrap">
		<?php get_template_part( 'template-parts/bloco-anuncio', null, array( 'posicao' => 'home_billboard_top', 'variante' => 'billboard' ) ); ?>
	</div>
	<?php
endif;
?>

<?php // ---------- três destaques ---------- ?>
<?php if ( ! empty( $lf_destaques ) ) : ?>
	<section class="lf-wrap lf-sec">
		<div class="lf-hero">
			<?php
			global $post;
			$lf_guarda = $post;
			$lf_lado   = array();

			foreach ( $lf_destaques as $lf_i => $lf_destaque ) {

				$post = $lf_destaque; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- laço controlado, restaurado abaixo.
				setup_postdata( $post );

				if ( 0 === $lf_i ) {
					get_template_part( 'template-parts/card-destaque' );
				} else {
					ob_start();
					get_template_part( 'template-parts/card-noticia', null, array( 'variante' => 1 === $lf_i ? 'padrao' : 'row' ) );
					$lf_lado[] = ob_get_clean();
				}
			}

			$post = $lf_guarda; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restauração.
			wp_reset_postdata();
			?>

			<?php if ( ! empty( $lf_lado ) ) : ?>
				<div class="lf-hero__side">
					<?php
					foreach ( $lf_lado as $lf_n => $lf_html ) {

						if ( $lf_n > 0 ) {
							echo '<div class="lf-hero__divider"></div>';
						}

						echo $lf_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup do template-part, já escapado.
					}
					?>
				</div>
			<?php endif; ?>
		</div>
	</section>
<?php endif; ?>

<?php // ---------- faixas de editorias ---------- ?>
<?php
$lf_faixas = function_exists( 'lf_get_setting' ) ? (array) lf_get_setting( 'home_stripes', array() ) : array();
$lf_faixas = array_filter( $lf_faixas );

foreach ( $lf_faixas as $lf_secao ) :

	$lf_q = lf_section_query( $lf_secao, 4, $lf_usados );

	if ( ! $lf_q || ! $lf_q->have_posts() ) {
		continue;
	}

	$lf_termo = lf_section_category( $lf_secao );
	?>
	<section class="lf-wrap lf-sec lf-sec--pad">

		<?php lf_section_header( $lf_termo ? $lf_termo->name : '', $lf_termo ); ?>

		<div class="lf-grid lf-grid--4">
			<?php
			while ( $lf_q->have_posts() ) :
				$lf_q->the_post();
				$lf_usados[] = get_the_ID();
				get_template_part( 'template-parts/card-noticia', null, array( 'variante' => 'sm' ) );
			endwhile;

			wp_reset_postdata();
			?>
		</div>
	</section>
	<?php
endforeach;
?>

<?php // ---------- mais lidas + agenda + trilha publicitária lateral ---------- ?>
<?php
/*
 * As duas posições laterais da home — half page (300×320) e retângulo
 * (300×250) — vivem nesta trilha, ao lado de Mais lidas e Agenda. É o único
 * lugar da home com estrutura de coluna lateral.
 *
 * A coluna só é criada quando há algo para colocar nela: anúncio ativo no
 * período, fallback válido, ou sessão de administrador (que vê a marcação da
 * posição). Para o visitante, sem anúncio, a grade volta a duas colunas e
 * nenhum espaço vazio é reservado.
 */
$lf_rail = array( 'home_half_page', 'home_sidebar_rectangle' );
$lf_tem_rail = false;

foreach ( $lf_rail as $lf_pos ) {
	if ( lf_ad_disponivel( $lf_pos ) ) {
		$lf_tem_rail = true;
		break;
	}
}

if ( ! $lf_tem_rail && current_user_can( 'manage_options' ) ) {
	$lf_tem_rail = true;
}
?>
<section class="lf-wrap lf-sec lf-sec--pad">
	<div class="<?php echo $lf_tem_rail ? 'lf-home-cols' : 'lf-grid lf-grid--2'; ?>">

		<?php
		$lf_lidas = lf_most_read_query( 5 );

		if ( $lf_lidas->have_posts() ) :
			?>
			<div>
				<?php lf_section_header( __( 'Mais lidas', 'licia-fabio' ) ); ?>
				<ol class="lf-lidas">
					<?php
					while ( $lf_lidas->have_posts() ) :
						$lf_lidas->the_post();
						?>
						<li>
							<div>
								<h3 class="lf-lidas__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
							</div>
						</li>
						<?php
					endwhile;

					wp_reset_postdata();
					?>
				</ol>
			</div>
			<?php
		endif;
		?>

		<?php
		$lf_eventos = lf_events_query( 4 );

		if ( $lf_eventos && $lf_eventos->have_posts() ) :
			?>
			<div>
				<?php lf_section_header( __( 'Agenda da Bahia', 'licia-fabio' ) ); ?>
				<div style="display:flex;flex-direction:column;gap:14px">
					<?php
					while ( $lf_eventos->have_posts() ) :
						$lf_eventos->the_post();
						$lf_ev = function_exists( 'lf_get_event_data' ) ? lf_get_event_data() : array();
						?>
						<div class="lf-agenda__item">
							<div class="lf-agenda__data">
								<div class="lf-agenda__dia"><?php echo esc_html( ! empty( $lf_ev['dia'] ) ? $lf_ev['dia'] : '—' ); ?></div>
								<div class="lf-agenda__mes"><?php echo esc_html( ! empty( $lf_ev['mes'] ) ? $lf_ev['mes'] : '' ); ?></div>
							</div>
							<div>
								<h3 class="lf-agenda__nome"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
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

					wp_reset_postdata();
					?>
				</div>
			</div>
			<?php
		endif;
		?>

		<?php if ( $lf_tem_rail ) : ?>
			<aside class="lf-home-rail" aria-label="<?php esc_attr_e( 'Publicidade', 'licia-fabio' ); ?>">
				<?php
				get_template_part( 'template-parts/bloco-anuncio', null, array( 'posicao' => 'home_half_page', 'variante' => 'halfpage' ) );
				get_template_part( 'template-parts/bloco-anuncio', null, array( 'posicao' => 'home_sidebar_rectangle', 'variante' => 'retangulo' ) );
				?>
			</aside>
		<?php endif; ?>

	</div>
</section>

<?php // ---------- conteúdo de marca ---------- ?>
<?php
$lf_branded = function_exists( 'lf_get_setting' ) ? lf_get_setting( 'branded_category', '' ) : '';

if ( $lf_branded ) :

	$lf_q = lf_section_query( $lf_branded, 3, $lf_usados );

	if ( $lf_q && $lf_q->have_posts() ) :

		$lf_termo = lf_section_category( $lf_branded );
		?>
		<section class="lf-wrap lf-sec lf-sec--pad">

			<?php lf_section_header( $lf_termo ? $lf_termo->name : __( 'Conteúdo de marca', 'licia-fabio' ), $lf_termo ); ?>

			<div class="lf-grid lf-grid--3">
				<?php
				while ( $lf_q->have_posts() ) :
					$lf_q->the_post();
					$lf_usados[] = get_the_ID();
					get_template_part( 'template-parts/card-noticia', null, array( 'variante' => 'sm' ) );
				endwhile;

				wp_reset_postdata();
				?>
			</div>
		</section>
		<?php
	endif;
endif;
?>

<?php // ---------- coluna ---------- ?>
<?php
$lf_coluna = function_exists( 'lf_get_setting' ) ? lf_get_setting( 'column_category', '' ) : '';

if ( $lf_coluna ) :

	$lf_q = lf_section_query( $lf_coluna, 3, $lf_usados );

	if ( $lf_q && $lf_q->have_posts() ) :

		$lf_termo = lf_section_category( $lf_coluna );
		?>
		<section class="lf-wrap lf-sec lf-sec--pad">

			<?php lf_section_header( $lf_termo ? $lf_termo->name : __( 'Coluna', 'licia-fabio' ), $lf_termo ); ?>

			<div class="lf-grid lf-grid--3">
				<?php
				while ( $lf_q->have_posts() ) :
					$lf_q->the_post();
					$lf_usados[] = get_the_ID();
					get_template_part( 'template-parts/card-noticia', null, array( 'variante' => 'sm' ) );
				endwhile;

				wp_reset_postdata();
				?>
			</div>
		</section>
		<?php
	endif;
endif;
?>

<?php // ---------- TV Lícia ---------- ?>
<?php
$lf_tv = function_exists( 'lf_get_setting' ) ? lf_get_setting( 'tv_category', '' ) : '';

if ( $lf_tv ) :

	$lf_q = lf_section_query( $lf_tv, 4, $lf_usados );

	if ( $lf_q && $lf_q->have_posts() ) :

		$lf_termo = lf_section_category( $lf_tv );
		?>
		<section class="lf-wrap lf-sec lf-sec--pad">

			<?php lf_section_header( $lf_termo ? $lf_termo->name : __( 'TV Lícia', 'licia-fabio' ), $lf_termo ); ?>

			<div class="lf-grid lf-grid--4">
				<?php
				while ( $lf_q->have_posts() ) :
					$lf_q->the_post();
					$lf_usados[] = get_the_ID();
					get_template_part( 'template-parts/card-noticia', null, array( 'variante' => 'sm' ) );
				endwhile;

				wp_reset_postdata();
				?>
			</div>
		</section>
		<?php
	endif;
endif;
?>

<?php // ---------- newsletter ---------- ?>
<?php
/*
 * NEWSLETTER — componente visual, DESATIVADO de propósito.
 *
 * Não há integração definitiva porque o provedor de e-mail e o destino dos
 * dados ainda não foram definidos. Enquanto isso:
 *
 *   - o campo e o botão estão desabilitados (atributo disabled);
 *   - o formulário não tem action e não é submetido;
 *   - nenhum e-mail é gravado no banco;
 *   - nenhum envio é simulado, e nenhuma mensagem de sucesso é exibida.
 *
 * Para ligar a newsletter quando o provedor for escolhido, use o hook abaixo
 * em um plugin (não no tema, para sobreviver a troca de tema):
 *
 *     add_action( 'lf_newsletter_form', function () {
 *         // Imprima aqui o formulário do provedor ou o shortcode do plugin.
 *         echo do_shortcode( '[meu_formulario]' );
 *     } );
 *
 * Havendo algo registrado no hook, o bloco desabilitado é substituído por ele.
 */
?>
<section class="lf-wrap">
	<div class="lf-news">
		<div style="max-width:520px">
			<h2 class="lf-news__title"><?php esc_html_e( 'O melhor da Bahia no seu e-mail', 'licia-fabio' ); ?></h2>
			<p class="lf-news__text"><?php esc_html_e( 'Sociedade, cultura e gastronomia toda semana. Sem spam.', 'licia-fabio' ); ?></p>
		</div>

		<?php if ( has_action( 'lf_newsletter_form' ) ) : ?>
			<div class="lf-news__form">
				<?php do_action( 'lf_newsletter_form' ); ?>
			</div>
		<?php else : ?>
			<div class="lf-news__form" aria-describedby="lf-news-nota">
				<label class="lf-sr" for="lf-news-email"><?php esc_html_e( 'Seu e-mail', 'licia-fabio' ); ?></label>
				<input id="lf-news-email" type="email" placeholder="seu@email.com" disabled aria-disabled="true">
				<button type="button" disabled aria-disabled="true"><?php esc_html_e( 'Assinar', 'licia-fabio' ); ?></button>
			</div>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<p id="lf-news-nota" style="font-family:var(--lf-sans);font-size:12px;line-height:1.5;color:rgba(255,255,255,.85);margin:10px 0 0;flex-basis:100%">
					<?php esc_html_e( 'Aviso visível apenas para administradores: a newsletter está desativada até que o provedor de e-mail seja definido. Nenhum endereço é guardado.', 'licia-fabio' ); ?>
				</p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
