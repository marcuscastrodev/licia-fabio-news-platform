/* Menu mobile do Portal Lícia Fábio */
(function () {
  'use strict';

  var burger = document.querySelector('.lf-mobbar__burger');
  var menu = document.getElementById('lf-mobmenu');

  if (!burger || !menu) {
    return;
  }

  function fechar() {
    menu.classList.remove('is-open');
    burger.setAttribute('aria-expanded', 'false');
    burger.setAttribute('aria-label', burger.getAttribute('data-label-abrir'));
  }

  function abrir() {
    menu.classList.add('is-open');
    burger.setAttribute('aria-expanded', 'true');
    burger.setAttribute('aria-label', burger.getAttribute('data-label-fechar'));
  }

  burger.addEventListener('click', function () {
    if (menu.classList.contains('is-open')) {
      fechar();
    } else {
      abrir();
    }
  });

  // Fecha com a tecla Esc.
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && menu.classList.contains('is-open')) {
      fechar();
      burger.focus();
    }
  });

  // Fecha ao voltar para a largura de desktop.
  var largura = window.matchMedia('(min-width: 901px)');

  function aoMudar() {
    if (largura.matches) {
      fechar();
    }
  }

  if (largura.addEventListener) {
    largura.addEventListener('change', aoMudar);
  } else if (largura.addListener) {
    largura.addListener(aoMudar);
  }
})();
