/* Visualização ampliada das galerias do WordPress.
   Sem dependência de biblioteca. Só é carregado em páginas que têm galeria.

   Cobre as duas marcações que o WordPress produz:

     - legada, do shortcode [gallery]:
         .gallery .gallery-icon a
     - blocos do Gutenberg:
         .wp-block-gallery figure a   (inclusive dentro de blocos reutilizáveis)

   Duas cautelas deliberadas:

     1. Só intercepta links que estão DENTRO de uma galeria e que apontam para
        um arquivo de imagem. Link solto no texto, imagem avulsa e link para
        página de anexo continuam funcionando normalmente.
     2. Se a opção nativa "Ampliar ao clicar" estiver ligada no bloco, o
        WordPress já entrega o próprio lightbox. Nesse caso este script sai de
        cena para aquela galeria — dois overlays disputando o mesmo clique é
        pior que nenhum.

   Cada galeria da página é um grupo próprio: anterior/próxima circulam dentro
   da galeria clicada, e o contador conta só as imagens dela. */
(function () {
  'use strict';

  var RE_IMG = /\.(jpe?g|png|gif|webp|avif)(\?|$)/i;

/* Galeria com "Ampliar ao clicar" ligado no editor: o WordPress entrega o
     próprio lightbox e este script sai de cena para ela.

     A marcação mudou entre versões do WordPress, então testamos todas as
     formas conhecidas:

       - 6.4:  <button class="lightbox-trigger"> dentro da figure
       - 6.5+: <figure class="wp-lightbox-container"> + data-wp-interactive
       - qualquer versão: o overlay .wp-lightbox-overlay já no HTML

     O lightbox nativo não tem anterior/próxima nem contador — e não vamos
     exigir isso dele. Ele abre, fecha com Esc e devolve o foco; é o que
     oferece, e é o comportamento esperado quando a opção está ligada. */
  function temLightboxNativo(galeria) {
    return !!(
      galeria.querySelector('.wp-lightbox-container') ||
      galeria.querySelector('.lightbox-trigger') ||
      galeria.querySelector('[data-wp-interactive]') ||
      galeria.querySelector('.wp-lightbox-overlay') ||
      galeria.closest('.wp-lightbox-container')
    );
  }

  function linksDe(galeria) {
    var sel = galeria.classList.contains('gallery')
      ? '.gallery-icon a'
      : 'figure a';

    return Array.prototype.slice.call(galeria.querySelectorAll(sel)).filter(function (a) {
      // Só links diretos para arquivo de imagem.
      if (!RE_IMG.test(a.getAttribute('href') || '')) {
        return false;
      }
      // Precisa envolver uma imagem — não um link de texto.
      if (!a.querySelector('img')) {
        return false;
      }

      /* Item individual com lightbox nativo, numa galeria em que os outros não
         têm: deixa esse item para o WordPress. Evita dois handlers no mesmo
         clique em galeria mista. */
      var fig = a.closest('figure');

      if (fig && (fig.classList.contains('wp-lightbox-container') || fig.querySelector('.lightbox-trigger'))) {
        return false;
      }

      return true;
    });
  }

  // Cada galeria da página vira um grupo.
  var grupos = [];

  Array.prototype.slice
    .call(document.querySelectorAll('.lf-conteudo .gallery, .lf-conteudo .wp-block-gallery'))
    .forEach(function (galeria) {
      // Galeria dentro de galeria (bloco aninhado): conta só a de fora.
      if (galeria.parentElement && galeria.parentElement.closest('.wp-block-gallery')) {
        return;
      }

      if (temLightboxNativo(galeria)) {
        return;
      }

      var links = linksDe(galeria);

      if (links.length) {
        grupos.push(links);
      }
    });

  if (!grupos.length) {
    return;
  }

  var strings = window.lfGalleryStrings || {};
  var grupo = grupos[0];
  var atual = 0;
  var origem = null;

  // ---- monta o overlay uma única vez ----
  var box = document.createElement('div');
  box.className = 'lf-lightbox';
  box.setAttribute('role', 'dialog');
  box.setAttribute('aria-modal', 'true');
  box.setAttribute('aria-label', strings.dialog || 'Imagem ampliada');
  box.innerHTML =
    '<button type="button" class="lf-lightbox__btn lf-lightbox__btn--close" aria-label="' +
    (strings.close || 'Fechar') +
    '">&times;</button>' +
    '<button type="button" class="lf-lightbox__btn lf-lightbox__btn--prev" aria-label="' +
    (strings.prev || 'Imagem anterior') +
    '">&#8249;</button>' +
    '<button type="button" class="lf-lightbox__btn lf-lightbox__btn--next" aria-label="' +
    (strings.next || 'Próxima imagem') +
    '">&#8250;</button>' +
    '<figure class="lf-lightbox__fig">' +
    '<img class="lf-lightbox__img" alt="">' +
    '<figcaption class="lf-lightbox__cap"></figcaption>' +
    '<div class="lf-lightbox__count" aria-live="polite"></div>' +
    '</figure>';

  document.body.appendChild(box);

  var img = box.querySelector('.lf-lightbox__img');
  var cap = box.querySelector('.lf-lightbox__cap');
  var cont = box.querySelector('.lf-lightbox__count');
  var btnClose = box.querySelector('.lf-lightbox__btn--close');
  var btnPrev = box.querySelector('.lf-lightbox__btn--prev');
  var btnNext = box.querySelector('.lf-lightbox__btn--next');

  /* Legenda: a do item da galeria, nas duas marcações. Sem legenda, cai no
     texto alternativo da imagem. */
  function legendaDe(link) {
    var item = link.closest('.gallery-item') || link.closest('figure');
    var legenda = item
      ? item.querySelector('.gallery-caption, figcaption')
      : null;
    var imagem = link.querySelector('img');

    if (legenda && legenda.textContent.trim()) {
      return legenda.textContent.trim();
    }

    return imagem ? imagem.getAttribute('alt') || '' : '';
  }

  function mostrar(i) {
    atual = (i + grupo.length) % grupo.length;

    var link = grupo[atual];
    var interna = link.querySelector('img');

    img.setAttribute('src', link.getAttribute('href'));
    img.setAttribute('alt', interna ? interna.getAttribute('alt') || '' : '');

    var texto = legendaDe(link);
    cap.textContent = texto;
    cap.style.display = texto ? '' : 'none';

    cont.textContent = atual + 1 + ' / ' + grupo.length;

    // Uma só imagem: esconde as setas.
    var varias = grupo.length > 1;
    btnPrev.style.display = varias ? '' : 'none';
    btnNext.style.display = varias ? '' : 'none';
  }

  function abrir(lista, i, disparador) {
    grupo = lista;
    origem = disparador || null;
    mostrar(i);
    box.classList.add('is-open');
    document.documentElement.style.overflow = 'hidden';
    btnClose.focus();
    document.addEventListener('keydown', teclado);
  }

  function fechar() {
    box.classList.remove('is-open');
    document.documentElement.style.overflow = '';
    document.removeEventListener('keydown', teclado);

    if (origem && typeof origem.focus === 'function') {
      origem.focus();
    }
  }

  function teclado(e) {
    if (e.key === 'Escape') {
      e.preventDefault();
      fechar();
      return;
    }

    if (e.key === 'ArrowRight') {
      e.preventDefault();
      mostrar(atual + 1);
      return;
    }

    if (e.key === 'ArrowLeft') {
      e.preventDefault();
      mostrar(atual - 1);
      return;
    }

    // Mantém o foco dentro do overlay.
    if (e.key === 'Tab') {
      var foco = [btnClose, btnPrev, btnNext].filter(function (b) {
        return b.style.display !== 'none';
      });
      var pos = foco.indexOf(document.activeElement);

      e.preventDefault();

      if (e.shiftKey) {
        foco[(pos - 1 + foco.length) % foco.length].focus();
      } else {
        foco[(pos + 1) % foco.length].focus();
      }
    }
  }

  grupos.forEach(function (lista) {
    lista.forEach(function (a, i) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        abrir(lista, i, a);
      });
    });
  });

  btnClose.addEventListener('click', fechar);
  btnPrev.addEventListener('click', function () {
    mostrar(atual - 1);
  });
  btnNext.addEventListener('click', function () {
    mostrar(atual + 1);
  });

  box.addEventListener('click', function (e) {
    if (e.target === box) {
      fechar();
    }
  });
})();
