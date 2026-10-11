/* TV Lícia: playlist com um único player.
 *
 * Sem JavaScript, capa e itens são links para o YouTube — tudo continua
 * funcionando. Com JavaScript:
 *   - ao abrir, nada toca: só a capa do vídeo selecionado;
 *   - clicar na capa cria o player daquele vídeo;
 *   - escolher um item troca o vídeo principal e o reproduz, sem recarregar;
 *   - existe no máximo um iframe na página.
 */
(function () {
  'use strict';

  var raiz = document.querySelector('[data-lf-tv]');

  if (!raiz) {
    return;
  }

  var frame = raiz.querySelector('[data-lf-tv-frame]');
  var titulo = raiz.querySelector('[data-lf-tv-titulo]');
  var duracao = raiz.querySelector('[data-lf-tv-duracao]');
  var itens = Array.prototype.slice.call(raiz.querySelectorAll('.lf-tv__item'));

  if (!frame || !itens.length) {
    return;
  }

  function atual() {
    return raiz.querySelector('.lf-tv__item[aria-current="true"]') || itens[0];
  }

  function tocar(item) {
    var f = document.createElement('iframe');

    // O autoplay aqui só acontece depois de um clique da pessoa.
    f.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(item.getAttribute('data-id')) + '?autoplay=1&rel=0&playsinline=1';
    f.title = item.getAttribute('data-titulo') || 'Vídeo';
    f.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
    f.setAttribute('allowfullscreen', '');
    f.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');

    frame.innerHTML = '';
    frame.appendChild(f);
  }

  function selecionar(item) {
    itens.forEach(function (i) {
      i.removeAttribute('aria-current');
    });

    item.setAttribute('aria-current', 'true');

    if (titulo) {
      titulo.textContent = item.getAttribute('data-titulo') || '';
    }

    if (duracao) {
      duracao.textContent = item.getAttribute('data-duracao') || '';
    }

    tocar(item);
  }

  var capa = frame.querySelector('[data-lf-tv-poster]');

  if (capa) {
    capa.addEventListener('click', function (e) {
      e.preventDefault();
      tocar(atual());
    });
  }

  itens.forEach(function (item) {
    item.addEventListener('click', function (e) {
      e.preventDefault();
      selecionar(item);
    });
  });
})();
