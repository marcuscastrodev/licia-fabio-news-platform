<?php
/**
 * Aviso de galeria.
 *
 * As galerias do acervo ficam DENTRO do conteúdo, no shortcode nativo
 * [gallery]. Elas são renderizadas pelo próprio WordPress em the_content(),
 * na posição em que o redator as colocou — este tema não as move, não as
 * converte e não altera o conteúdo salvo no banco.
 *
 * O que o tema faz é apenas apresentação:
 *   - grade responsiva em assets/css/main.css (.gallery, .gallery-columns-N);
 *   - visualização ampliada com teclado em assets/js/gallery.js, carregada
 *     somente em matérias que tenham galeria;
 *   - legenda e texto alternativo dos anexos, quando existirem;
 *   - srcset, sizes e carregamento adiado pelos mecanismos nativos.
 *
 * Este arquivo existe para deixar a decisão documentada onde alguém iria
 * procurá-la. Ele não imprime nada.
 *
 * @package licia-fabio
 */
