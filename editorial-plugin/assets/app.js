/* ==========================================================================
   Painel Editorial — Lícia Fábio
   1.0.5-staging

   JavaScript puro, sem framework e sem etapa de compilação: o arquivo que
   você lê é o que o navegador executa. Não há bundler, não há node_modules.

   Autenticação: cookie de sessão nativo do WordPress mais o nonce da REST
   API, entregue pelo servidor no boot. Nada é gravado em localStorage — a
   única coisa persistida é o rascunho local de recuperação, que guarda o TEXTO
   do redator (nunca credencial), para não perdê-lo em falha de rede.

   Escrita: sempre pelas rotas nativas /wp/v2/posts e /wp/v2/media. O painel
   nunca decide permissão; ele mostra ou esconde controles, e o WordPress
   recusa o que não é permitido.
   ========================================================================== */
(function () {
  'use strict';

  var BOOT = JSON.parse(document.getElementById('lfed-boot').textContent);
  var raiz = document.getElementById('lfed-app');

  /* ---------------------------------------------------------------- estado */
  var estado = {
    tela: 'inicio',
    nonce: BOOT.nonce,
    usuario: BOOT.usuario,
    resumo: null,
    lista: { itens: [], pagina: 1, paginas: 1, total: 0, carregando: false },
    filtros: { busca: '', status: '', autor: '' },
    editor: null,
    ocupado: false,
    resolvendo: false,
    menuAberto: false,
    itens: null,
    item: null,
    mensagem: null,
  };

  /* --------------------------------------------------------------- utilidades */
  function esc(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function url(caminho, params) {
    var u = new URL(BOOT.restUrl.replace(/\/+$/, '/') + caminho.replace(/^\/+/, ''));

    if (params) {
      Object.keys(params).forEach(function (k) {
        if (params[k] !== '' && params[k] != null) {
          u.searchParams.set(k, params[k]);
        }
      });
    }

    return u.toString();
  }

  /**
   * Chamada à REST API.
   *
   * Quando o nonce vence, a renovação NÃO passa por um endpoint REST — ele
   * seria rejeitado pelo mesmo mecanismo que rejeitou a chamada original. A
   * renovação usa uma rota do painel, autenticada só pelo cookie de sessão.
   *
   * E a repetição automática é seletiva, de propósito:
   *
   *   - LEITURA: repetida sem cerimônia, porque repetir um GET não tem efeito.
   *   - GRAVAÇÃO com chave de operação: repetida, porque a chave garante que
   *     o servidor devolva o mesmo post em vez de criar outro.
   *   - GRAVAÇÃO sem chave: NÃO é repetida automaticamente. O resultado da
   *     primeira tentativa é incerto, e repetir às cegas é justamente como se
   *     duplica conteúdo. O painel renova o nonce e devolve o controle ao
   *     redator, com o texto intacto.
   */
  function api(caminho, opcoes, jaTentou) {
    opcoes = opcoes || {};

    var cabecalhos = { 'X-WP-Nonce': estado.nonce };

    if (opcoes.json !== undefined) {
      cabecalhos['Content-Type'] = 'application/json';
    }

    Object.keys(opcoes.headers || {}).forEach(function (k) {
      cabecalhos[k] = opcoes.headers[k];
    });

    var metodo = opcoes.method || 'GET';

    return fetch(url(caminho, opcoes.params), {
      method: metodo,
      credentials: 'same-origin',
      headers: cabecalhos,
      body: opcoes.json !== undefined ? JSON.stringify(opcoes.json) : opcoes.body,
    }).then(function (r) {
      return r.text().then(function (texto) {
        var dados = null;

        if (texto) {
          try {
            dados = JSON.parse(texto);
          } catch (e) {
            dados = null;
          }
        }

        if (r.ok) {
          return { dados: dados, headers: r.headers, status: r.status };
        }

        var codigo = (dados && dados.code) || '';
        var ehNonce = (r.status === 401 || r.status === 403) && /nonce|cookie/i.test(codigo);

        if (!ehNonce || jaTentou) {
          throw erroDe(r.status, dados);
        }

        // Só repete sozinho o que é seguro repetir.
        var podeRepetir = metodo === 'GET' || opcoes.idempotente === true;

        return renovarNonce().then(function (res) {
          if (res.erro) {
            throw res.erro;
          }

          if (podeRepetir) {
            return api(caminho, opcoes, true);
          }

          var e = new Error(
            'Sua sessão ficou parada um tempo e o pedido não foi aceito. A conexão já foi renovada e nada foi salvo ainda — clique em salvar de novo. Seu texto está intacto.'
          );
          e.nonceRenovado = true;
          throw e;
        });
      });
    }, function () {
      // Falha de rede: fetch rejeita sem resposta.
      throw erroDe(0, null);
    });
  }

  /**
   * Busca um nonce novo pela rota do painel.
   *
   * Distingue os dois casos que a 1.0.0 confundia: 401 é sessão encerrada e
   * exige login; 200 significa que era só o nonce.
   */
  function renovarNonce() {
    return fetch(BOOT.renovarUrl, {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json' },
    })
      .then(function (r) {
        return r.text().then(function (t) {
          var d = null;

          try {
            d = JSON.parse(t);
          } catch (e) {
            d = null;
          }

          if (r.status === 401) {
            var eSessao = new Error((d && d.mensagem) || 'Sua sessão terminou. Entre novamente.');
            eSessao.sessao = true;
            eSessao.loginUrl = (d && d.loginUrl) || '';
            return { erro: eSessao };
          }

          if (r.status === 403) {
            var ePerm = new Error((d && d.mensagem) || 'Sua conta não tem mais permissão para escrever notícias.');
            ePerm.permissao = true;
            return { erro: ePerm };
          }

          if (!r.ok || !d || !d.nonce) {
            var eOutro = new Error('Não foi possível renovar a conexão com o site. Recarregue a página.');
            return { erro: eOutro };
          }

          estado.nonce = d.nonce;

          if (d.usuario && d.usuario.caps) {
            estado.usuario.caps = d.usuario.caps;
          }

          return { nonce: d.nonce };
        });
      })
      .catch(function () {
        return { erro: new Error('Sem conexão com o site agora. Seu texto continua nesta tela.') };
      });
  }

  function erroDe(status, dados) {
    var e = new Error(
      (dados && dados.message ? String(dados.message).replace(/<[^>]*>/g, '') : '') ||
        'Não foi possível concluir a operação.'
    );

    e.status = status;
    e.codigo = (dados && dados.code) || '';

    if (status === 401) {
      e.message = 'Sua sessão terminou. Entre novamente em outra aba e volte — o texto continua nesta tela.';
      e.sessao = true;
    }

    if (status === 409) {
      e.conflito = true;
      e.dadosConflito = (dados && dados.data) || {};
    }

    if (status === 403 && !/nonce/i.test(e.codigo)) {
      e.message = e.message || 'Sua conta não tem permissão para isso.';
    }

    if (status === 0) {
      e.rede = true;
      e.message =
        'Não deu para falar com o WordPress agora. Seu texto continua nesta tela e foi guardado neste navegador.';
    }

    if (status >= 500) {
      e.rede = true;
    }

    return e;
  }

  function avisar(tipo, texto, extras) {
    estado.mensagem = Object.assign({ tipo: tipo, texto: texto }, extras || {});
    pintar();
  }

  function dataBr(iso) {
    if (!iso) {
      return '';
    }

    var d = new Date(iso);

    if (isNaN(d)) {
      return '';
    }

    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' }) +
      ' · ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  }

  var ROTULO_STATUS = {
    publish: 'Publicada',
    draft: 'Rascunho',
    pending: 'Em revisão',
    future: 'Agendada',
    private: 'Privada',
  };

  /* ---------------------------------- rascunho local de recuperação ------- */
  /*
   * PRIVACIDADE, dito com clareza: este é o único dado que o painel guarda no
   * navegador, e é o TEXTO que o redator digitou — nunca senha, cookie ou
   * nonce. Em computador compartilhado, quem usar o mesmo perfil do navegador
   * e entrar com a MESMA conta pode ver um rascunho não salvo. Por isso ele é
   * apagado ao salvar com sucesso, ao sair pelo botão do painel, e vence em 7
   * dias. Para trabalho sensível, use o próprio computador ou salve como
   * rascunho no WordPress antes de deixar a máquina.
   *
   * A chave carrega o escopo da instalação e do usuário (BOOT.escopo, que é um
   * hash de home_url + id do usuário). Foi o que corrigiu o defeito da 1.0.0:
   * a chave era só o id da notícia, então duas contas no mesmo navegador — e
   * staging e produção na mesma origem — compartilhavam rascunho.
   */
  var PREFIXO_RASCUNHO = 'lfed:v2:';
  var VALIDADE_RASCUNHO = 7 * 24 * 60 * 60 * 1000;

  function chaveRascunho(id) {
    return PREFIXO_RASCUNHO + BOOT.escopo + ':' + (id || 'novo');
  }

  /**
   * Guarda o rascunho local.
   *
   * Guarda também a CHAVE DE OPERAÇÃO de uma criação em andamento. Sem isso —
   * o defeito da 1.0.1 — recarregar a página perdia a chave, e a tentativa
   * seguinte usava uma nova: duas criações, duas notícias.
   *
   * Nunca guarda credencial, cookie ou nonce. Só o texto e a chave, que é um
   * identificador de operação sem valor de autenticação.
   */
  function salvarLocal(id, dados, chaveOperacao) {
    try {
      window.localStorage.setItem(
        chaveRascunho(id),
        JSON.stringify({
          quando: Date.now(),
          escopo: BOOT.escopo,
          chaveOperacao: chaveOperacao || '',
          dados: dados,
        })
      );
    } catch (e) {
      /* cota cheia ou modo privado: seguimos sem a rede de segurança */
    }
  }

  function lerLocal(id) {
    try {
      var bruto = window.localStorage.getItem(chaveRascunho(id));

      if (!bruto) {
        return null;
      }

      var reg = JSON.parse(bruto);

      // Escopo diferente nunca é oferecido, mesmo se a chave coincidir.
      if (!reg || reg.escopo !== BOOT.escopo) {
        return null;
      }

      if (!reg.quando || Date.now() - reg.quando > VALIDADE_RASCUNHO) {
        window.localStorage.removeItem(chaveRascunho(id));
        return null;
      }

      return reg;
    } catch (e) {
      return null;
    }
  }

  function limparLocal(id) {
    try {
      window.localStorage.removeItem(chaveRascunho(id));
    } catch (e) {
      /* nada a fazer */
    }
  }

  /** Apaga os rascunhos deste escopo. Chamado ao sair. */
  function limparTodosDoEscopo() {
    try {
      var alvo = PREFIXO_RASCUNHO + BOOT.escopo + ':';

      Object.keys(window.localStorage)
        .filter(function (k) {
          return k.indexOf(alvo) === 0;
        })
        .forEach(function (k) {
          window.localStorage.removeItem(k);
        });
    } catch (e) {
      /* nada a fazer */
    }
  }

  /**
   * Remove rascunhos vencidos e as chaves da 1.0.0.
   *
   * As chaves antigas (lfed:rascunho:*) não registram de quem são. Não há como
   * saber se pertencem a esta conta, então NÃO são importadas — são apagadas.
   * Oferecer um texto sem saber o dono seria pior que perder um rascunho.
   */
  function faxinaLocal() {
    try {
      Object.keys(window.localStorage).forEach(function (k) {
        if (k.indexOf('lfed:rascunho:') === 0) {
          window.localStorage.removeItem(k);
          return;
        }

        if (k.indexOf(PREFIXO_RASCUNHO) !== 0) {
          return;
        }

        try {
          var reg = JSON.parse(window.localStorage.getItem(k));

          if (!reg || !reg.quando || Date.now() - reg.quando > VALIDADE_RASCUNHO) {
            window.localStorage.removeItem(k);
          }
        } catch (e) {
          window.localStorage.removeItem(k);
        }
      });
    } catch (e) {
      /* nada a fazer */
    }
  }

  /* ---------------------------------------------------------- aviso de saída */
  window.addEventListener('beforeunload', function (e) {
    if ((estado.editor && estado.editor.sujo) || (estado.item && estado.item.sujo)) {
      e.preventDefault();
      e.returnValue = '';
      return '';
    }
  });

  /**
   * Chave de operação para uma criação.
   *
   * Gerada uma vez por notícia nova e mantida enquanto o editor estiver
   * aberto. É o que torna a criação idempotente: se a resposta se perder e o
   * redator tentar de novo, a MESMA chave vai ao servidor, que devolve o post
   * já criado em vez de criar outro.
   *
   * O escopo da instalação e do usuário entra na chave, e o servidor a
   * associa ao usuário autenticado — a chave de uma pessoa nunca resolve para
   * o post de outra.
   *
   * Uma notícia nova legítima recebe outra chave, porque o editor é reaberto.
   */
  function novaChaveOperacao() {
    var aleatorio;

    if (window.crypto && window.crypto.randomUUID) {
      aleatorio = window.crypto.randomUUID().replace(/-/g, '');
    } else if (window.crypto && window.crypto.getRandomValues) {
      var bytes = new Uint8Array(16);
      window.crypto.getRandomValues(bytes);
      aleatorio = Array.prototype.map
        .call(bytes, function (b) {
          return ('0' + b.toString(16)).slice(-2);
        })
        .join('');
    } else {
      aleatorio = String(Date.now()) + Math.random().toString(36).slice(2, 12);
    }

    return (BOOT.escopo + '-' + aleatorio).toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 64);
  }

  /* =========================================================== carregamentos */
  function carregarResumo() {
    return api('licia-fabio-editorial/v1/resumo')
      .then(function (r) {
        estado.resumo = r.dados;
        pintar();
      })
      .catch(function (e) {
        avisar('erro', e.message);
      });
  }

  function carregarLista(pagina) {
    estado.lista.carregando = true;
    pintar();

    var params = {
      per_page: 20,
      page: pagina || 1,
      context: 'edit',
      orderby: 'modified',
      order: 'desc',
      _fields: 'id,title,status,date_gmt,modified_gmt,link,author,categories',
    };

    if (estado.filtros.busca) {
      params.search = estado.filtros.busca;
    }

    params.status = estado.filtros.status || 'publish,draft,pending,future,private';

    // Sem edit_others_posts, o WordPress já limita — mas pedir explicitamente
    // evita uma consulta que voltaria vazia e confundiria o redator.
    if (!estado.usuario.caps.editarDeOutros) {
      params.author = estado.usuario.id;
    } else if (estado.filtros.autor === 'minhas') {
      params.author = estado.usuario.id;
    }

    return api('wp/v2/posts', { params: params })
      .then(function (r) {
        estado.lista.itens = r.dados || [];
        estado.lista.pagina = pagina || 1;
        estado.lista.paginas = parseInt(r.headers.get('X-WP-TotalPages') || '1', 10);
        estado.lista.total = parseInt(r.headers.get('X-WP-Total') || '0', 10);
        estado.lista.carregando = false;
        pintar();
      })
      .catch(function (e) {
        estado.lista.carregando = false;
        avisar('erro', e.message);
      });
  }

  /* ================================================================= editor */
  function editorVazio() {
    return {
      id: 0,
      titulo: '',
      subtitulo: '',
      corpo: '',
      categorias: [],
      catsConhecidas: [],
      categoriasTocadas: false,
      destacadaId: 0,
      destacadaUrl: '',
      destacadaAlt: '',
      destacadaLegenda: '',
      destacadaCredito: '',
      status: 'draft',
      link: '',
      modificadoEm: '',
      chaveOperacao: '',
      chaveBloqueada: false,
      versao: '',
      sujo: false,
      modoCodigo: false,
      salvando: false,
      autorId: estado.usuario.id,
      podeEditar: true,
    };
  }

  function abrirNovo() {
    estado.editor = editorVazio();

    /*
     * Se havia um rascunho local de criação, a MESMA chave de operação volta
     * com ele. Uma chave nova é gerada só quando não há operação pendente —
     * gerar uma nova sobre uma criação de resultado desconhecido é o que
     * produz duplicata.
     */
    var pendente = lerLocal(0);

    estado.editor.chaveOperacao =
      pendente && pendente.chaveOperacao ? pendente.chaveOperacao : novaChaveOperacao();

    var local = lerLocal(0);

    if (local && local.dados && (local.dados.titulo || local.dados.corpo)) {
      estado.editor.recuperavel = local;
    }

    estado.tela = 'editor';
    estado.mensagem = null;
    pintar();
  }

  /**
   * Abre uma notícia para edição.
   *
   * Usa a leitura do painel, que devolve o conteúdo E a versão canônica na
   * mesma resposta autenticada. A versão vem no formato que a atualização
   * exige e é guardada EXATAMENTE como recebida — o navegador não a monta nem
   * a completa. Na 1.0.2 o painel guardava só o modified_gmt da rota nativa e
   * montava uma versão inválida: todo salvamento de notícia existente morria
   * em 400.
   */
  function abrirEdicao(id) {
    estado.ocupado = true;
    pintar();

    api('licia-fabio-editorial/v1/noticias/' + id)
      .then(function (r) {
        var p = r.dados;
        var ed = editorVazio();

        ed.id = p.id;
        ed.titulo = p.titulo || '';
        ed.corpo = p.conteudo || '';
        ed.subtitulo = p.subtitulo || '';

        /*
         * TODAS as categorias, não a primeira. catsConhecidas guarda a lista
         * como veio do servidor: se o redator não mexer no seletor, é ela que
         * volta no salvamento, inclusive as que não aparecem por estarem fora
         * das 200 carregadas.
         */
        ed.categorias = (p.categorias || []).map(Number);
        ed.catsConhecidas = ed.categorias.slice();
        ed.categoriasTocadas = false;

        ed.status = p.status;
        ed.link = p.link || '';
        ed.autorId = p.autor || 0;
        ed.destacadaId = p.imagemDestaque || 0;

        // Guardada como recebida, sem qualquer transformação.
        ed.versao = p.versao || '';
        ed.modificadoEm = ed.versao;

        estado.editor = ed;
        estado.tela = 'editor';
        estado.ocupado = false;

        var local = lerLocal(p.id);

        if (local && local.dados && local.dados.corpo && local.dados.corpo !== ed.corpo) {
          estado.editor.recuperavel = local;
        }

        pintar();

        if (ed.destacadaId) {
          carregarDestacada(ed.destacadaId);
        }
      })
      .catch(function (e) {
        estado.ocupado = false;
        avisar('erro', e.message);
      });
  }

  function carregarDestacada(id) {
    api('wp/v2/media/' + id, { params: { context: 'edit', _fields: 'id,source_url,alt_text,caption,media_details,meta' } })
      .then(function (r) {
        if (!estado.editor) {
          return;
        }

        var m = r.dados;
        var tam = m.media_details && m.media_details.sizes;

        estado.editor.destacadaUrl = (tam && tam.medium_large && tam.medium_large.source_url) ||
          (tam && tam.medium && tam.medium.source_url) || m.source_url;
        estado.editor.destacadaAlt = m.alt_text || '';
        estado.editor.destacadaLegenda = (m.caption && (m.caption.raw !== undefined ? m.caption.raw : '')) || '';
        estado.editor.destacadaCredito = (m.meta && m.meta[BOOT.metaCred]) || '';

        pintar();
      })
      .catch(function () {
        /* imagem inacessível: a prévia fica vazia, sem derrubar o editor */
      });
  }

  /**
   * Salva.
   *
   * Três cuidados que valem nomear:
   *
   *  - trava de reentrada, então duplo clique não cria dois posts;
   *  - o id volta para o estado depois do primeiro salvamento, então o segundo
   *    clique ATUALIZA em vez de criar de novo;
   *  - antes de sobrescrever, compara modified_gmt com o do servidor: se
   *    alguém salvou no meio, o painel avisa e NÃO sobrescreve em silêncio.
   */
  function salvar(novoStatus) {
    var ed = estado.editor;

    if (!ed || ed.salvando) {
      return;
    }

    /*
     * Chave bloqueada: o servidor recusou criar com ela e o resultado anterior
     * é desconhecido. Só "Criar como notícia nova" desbloqueia — e é o redator
     * quem decide, depois de conferir a lista.
     */
    if (!ed.id && ed.chaveBloqueada) {
      avisar(
        'erro',
        'Não vou salvar com este pedido: ele já foi usado e não sei o resultado. Confira a lista de notícias e, se a notícia não existir, use “Criar como notícia nova”.',
        { ofereceNova: true }
      );
      return;
    }

    // Não salva enquanto o resultado de uma criação pendente é desconhecido.
    if (estado.resolvendo) {
      avisar('info', 'Aguarde: ainda estou confirmando o que aconteceu com o pedido anterior.');
      return;
    }

    if (!ed.titulo.trim()) {
      avisar('erro', 'A notícia precisa de um título antes de ser salva.');
      return;
    }

    if (novoStatus === 'publish' && !estado.usuario.caps.publicar) {
      avisar('erro', 'Sua conta não tem permissão para publicar. Salve como rascunho ou envie para revisão.');
      return;
    }

    ed.salvando = true;
    estado.mensagem = null;
    pintar();

    // Guarda local antes de tentar: se a rede cair, o texto não se perde.
    salvarLocal(ed.id, { titulo: ed.titulo, subtitulo: ed.subtitulo, corpo: ed.corpo }, ed.chaveOperacao);

    var corpoAtual = ed.modoCodigo ? ed.corpo : lerConteudoDoDom();

    var carga = {
      title: ed.titulo,
      content: corpoAtual,
      status: novoStatus || ed.status,
    };

    /*
     * Categorias: só são enviadas quando o redator mexeu nelas. Caso
     * contrário, devolvemos exatamente a lista que veio do servidor — nada é
     * removido por omissão.
     */
    if (ed.categoriasTocadas) {
      carga.categories = ed.categorias.map(Number);
    } else if (ed.catsConhecidas.length) {
      carga.categories = ed.catsConhecidas.map(Number);
    } else if (ed.categorias.length) {
      carga.categories = ed.categorias.map(Number);
    }

    if (ed.destacadaId) {
      carga.featured_media = ed.destacadaId;
    }

    if (BOOT.coreAtivo) {
      carga.meta = {};
      carga.meta[BOOT.metaSub] = ed.subtitulo;
    }

    /*
     * Os campos ENVIADOS são congelados agora, antes da requisição sair.
     *
     * O editor continua editável enquanto a requisição está no ar. Quando a
     * resposta chega, comparamos o que foi enviado com o que está na tela: se
     * o redator digitou no meio, a tela NÃO é marcada como salva e o rascunho
     * local NÃO é apagado. Na 1.0.1 qualquer resposta de sucesso limpava tudo,
     * inclusive alterações que nunca chegaram ao servidor.
     */
    var enviado = {
      titulo: ed.titulo,
      subtitulo: ed.subtitulo,
      corpo: corpoAtual,
      categorias: (carga.categories || []).slice().sort().join(','),
      destacadaId: ed.destacadaId,
    };

    var chamada;

    if (ed.id) {
      /*
       * Atualização: a versão vai no corpo e o SERVIDOR compara dentro de uma
       * trava por post, imediatamente antes de gravar.
       */
      carga.versao = ed.versao || '';

      if (!carga.versao) {
        ed.salvando = false;
        avisar(
          'erro',
          'Não sei de qual versão desta notícia você partiu, então não vou gravar às cegas. Recarregue a notícia e reaplique suas alterações — o texto continua nesta tela.'
        );
        return;
      }

      chamada = api('licia-fabio-editorial/v1/noticias/' + ed.id, {
        method: 'POST',
        json: carga,
        idempotente: true,
      });
    } else {
      // Criação: chave de operação, para uma repetição nunca criar dois posts.
      if (!ed.chaveOperacao) {
        ed.chaveOperacao = novaChaveOperacao();
      }

      carga.chave_operacao = ed.chaveOperacao;

      chamada = api('licia-fabio-editorial/v1/noticias', {
        method: 'POST',
        json: carga,
        idempotente: true,
      });
    }

    chamada
      .then(function (r) {
        aplicarSucesso(r.dados || {}, enviado, carga.status);
      })
      .catch(function (e) {
        ed.salvando = false;

        /*
         * A criação já havia chegado ao servidor, mas com o texto anterior.
         * Não é sucesso e não é erro: é uma operação a ser convertida em
         * atualização do mesmo post, com o texto atual.
         */
        /*
         * A chave já foi usada e o registro venceu, mas o post é
         * identificável: converte em atualização daquele post. O texto local
         * é preservado e nada é criado.
         */
        if (e.codigo === 'lf_ed_operacao_expirada' && e.dadosConflito && e.dadosConflito.post_id) {
          var x = e.dadosConflito;

          ed.id = x.post_id;
          ed.versao = x.versao || '';
          ed.status = x.status_post || ed.status;
          ed.link = x.link || '';

          if (x.categorias) {
            ed.catsConhecidas = x.categorias.map(Number);
          }

          avisar(
            'info',
            'Este pedido já havia criado uma notícia (ID ' + x.post_id +
              ') e o registro dele venceu. Nenhuma cópia foi feita. Estou gravando suas alterações nela agora.'
          );

          window.setTimeout(function () {
            salvar(novoStatus);
          }, 300);

          return;
        }

        /*
         * Chave usada, resultado desconhecido e post não identificável. O
         * servidor RECUSA criar — e o painel não contorna isso gerando outra
         * chave sozinho. Só o redator, depois de conferir a lista, pode pedir
         * uma notícia nova.
         */
        if (e.codigo === 'lf_ed_operacao_desconhecida') {
          ed.chaveBloqueada = true;

          avisar('erro', e.message, { ofereceNova: true });
          return;
        }

        if (e.codigo === 'lf_ed_criacao_recuperada' && e.dadosConflito && e.dadosConflito.post_id) {
          var d = e.dadosConflito;

          ed.id = d.post_id;
          ed.versao = d.versao || '';
          ed.status = d.status_post || ed.status;
          ed.link = d.link || '';

          if (d.categorias) {
            ed.catsConhecidas = d.categorias.map(Number);
          }

          avisar(
            'info',
            'A notícia já havia sido criada pelo pedido anterior, com o texto de antes — nenhuma cópia foi feita. Suas alterações continuam aqui e ainda NÃO estão salvas. Estou gravando agora.'
          );

          // Segue como atualização, uma única vez.
          window.setTimeout(function () {
            salvar(novoStatus);
          }, 300);

          return;
        }

        if (e.conflito) {
          var quem = e.dadosConflito && e.dadosConflito.modificada_por;

          avisar(
            'erro',
            e.message +
              (quem ? ' A última alteração foi de ' + quem + '.' : '') +
              ' Seu texto continua nesta tela e segue guardado neste navegador. Abra a notícia em outra aba para comparar e reaplique o que for seu — nada é sobrescrito automaticamente.'
          );
          return;
        }

        if (e.sessao) {
          avisar('erro', e.message, { loginUrl: e.loginUrl || '' });
          return;
        }

        if (e.nonceRenovado) {
          avisar('info', e.message);
          return;
        }

        if (e.rede) {
          avisar('erro', e.message + ' Tente salvar de novo em instantes.');
          return;
        }

        avisar('erro', e.message);
      });
  }

  /**
   * Aplica uma resposta de sucesso ao editor.
   *
   * Só marca a tela como salva e apaga o rascunho local quando os campos que
   * ESTÃO na tela são os mesmos que foram enviados. Se o redator digitou
   * enquanto a requisição estava no ar, o id e a versão são atualizados — para
   * que o próximo salvamento seja uma atualização correta — mas a tela
   * permanece "com alterações não salvas", que é a verdade.
   */
  function aplicarSucesso(p, enviado, statusEnviado) {
    var ed = estado.editor;

    if (!ed) {
      return;
    }

    ed.salvando = false;
    ed.id = p.id || ed.id;
    ed.status = p.status || statusEnviado || ed.status;
    ed.link = p.link || '';
    ed.versao = p.versao || '';
    ed.modificadoEm = p.versao || '';

    if (p.categorias) {
      ed.catsConhecidas = p.categorias.map(Number);
    }

    // O que está na tela agora.
    var agora = {
      titulo: ed.titulo,
      subtitulo: ed.subtitulo,
      corpo: ed.modoCodigo ? ed.corpo : lerConteudoDoDom(),
      categorias: (ed.categoriasTocadas ? ed.categorias : ed.catsConhecidas).slice().sort().join(','),
      destacadaId: ed.destacadaId,
    };

    var iguais =
      agora.titulo === enviado.titulo &&
      agora.subtitulo === enviado.subtitulo &&
      agora.corpo === enviado.corpo &&
      agora.categorias === enviado.categorias &&
      agora.destacadaId === enviado.destacadaId;

    var rotulo = ROTULO_STATUS[ed.status] || ed.status;

    if (iguais) {

      ed.sujo = false;
      ed.corpo = enviado.corpo;

      if (!ed.categoriasTocadas) {
        ed.categorias = ed.catsConhecidas.slice();
      } else {
        ed.categoriasTocadas = false;
        ed.categorias = ed.catsConhecidas.slice();
      }

      // Só aqui o rascunho local sai: o servidor confirmou ESTES campos.
      limparLocal(0);
      limparLocal(ed.id);

      estado.mensagem = {
        tipo: 'ok',
        texto: p.repetida
          ? 'Esta notícia já havia sido criada — o pedido anterior chegou ao WordPress. ID ' + ed.id + ' · ' + rotulo + '. Nenhuma cópia foi criada.'
          : 'Salvo no WordPress. ID ' + ed.id + ' · ' + rotulo + '.',
        link: ed.status === 'publish' ? ed.link : '',
      };

      pintar();
      return;
    }

    /*
     * Houve digitação durante o envio. O que foi gravado é o texto anterior.
     * O rascunho local FICA, e a tela continua marcada como não salva.
     */
    ed.sujo = true;

    salvarLocal(ed.id, { titulo: ed.titulo, subtitulo: ed.subtitulo, corpo: agora.corpo }, ed.chaveOperacao);

    estado.mensagem = {
      tipo: 'info',
      texto:
        'Gravei no WordPress a versão que foi enviada. ID ' + ed.id + ' · ' + rotulo +
        '. Você digitou depois disso, e essas alterações ainda NÃO estão salvas — clique em salvar de novo.',
      link: ed.status === 'publish' ? ed.link : '',
    };

    pintar();
  }

  function lerConteudoDoDom() {
    var campo = document.getElementById('lfed-conteudo');
    return campo ? campo.innerHTML : estado.editor.corpo;
  }

  /* ================================================================ upload */
  function enviarArquivo(arquivo, aoConcluir) {
    if (!estado.usuario.caps.enviarArquivos) {
      avisar('erro', 'Sua conta não tem permissão para enviar imagens. Fale com quem administra o site.');
      return;
    }

    if (BOOT.maxUpload && arquivo.size > BOOT.maxUpload) {
      var mb = Math.floor(BOOT.maxUpload / 1048576);
      avisar('erro', 'Esta imagem tem ' + Math.ceil(arquivo.size / 1048576) + ' MB e o limite do servidor é ' + mb + ' MB. Reduza a imagem e tente de novo.');
      return;
    }

    if (!/^image\//.test(arquivo.type)) {
      avisar('erro', 'Só imagens são aceitas aqui.');
      return;
    }

    avisar('info', 'Enviando “' + arquivo.name + '”…');

    api('wp/v2/media', {
      method: 'POST',
      body: arquivo,
      headers: {
        'Content-Type': arquivo.type,
        'Content-Disposition': 'attachment; filename="' + encodeURIComponent(arquivo.name) + '"',
      },
    })
      .then(function (r) {
        estado.mensagem = null;
        aoConcluir(r.dados);
      })
      .catch(function (e) {
        avisar('erro', e.message);
      });
  }

  /** Grava alt, legenda e crédito de uma imagem já enviada. */
  function salvarDadosDaImagem(id, campos) {
    var carga = {
      alt_text: campos.alt || '',
      caption: campos.legenda || '',
    };

    if (BOOT.coreAtivo) {
      carga.meta = {};
      carga.meta[BOOT.metaCred] = campos.credito || '';
    }

    return api('wp/v2/media/' + id, { method: 'POST', json: carga });
  }

  /* ================================================================ pintura */
  /**
   * Monta a interface.
   *
   * A casca é uma grade de duas colunas: barra lateral fixa e área de
   * trabalho. A proposta original tinha o menu na lateral; a implementação
   * anterior o havia colocado no topo, em faixa horizontal. O que muda aqui é
   * a ESTRUTURA visual — os destinos, ações, permissões e o comportamento de
   * cada item continuam exatamente os mesmos.
   */
  function pintar() {
    raiz.setAttribute('aria-busy', estado.ocupado ? 'true' : 'false');

    raiz.innerHTML =
      '<div class="lfed-shell" data-menu="' + (estado.menuAberto ? 'aberto' : 'fechado') + '">' +
      veu() +
      lateral() +
      '<div class="lfed-area">' +
      barraMobile() +
      '<main class="lfed-corpo" id="lfed-principal">' + telaAtual() + '</main>' +
      '</div>' +
      '</div>';

    ligarEventos();
    aplicarTravaDeRolagem();
    moverFocoSePreciso();
  }

  /** Véu do drawer. Só existe quando o menu está aberto. */
  function veu() {
    if (!estado.menuAberto) {
      return '';
    }

    // Clicar fora fecha. É um div sem foco próprio: o Escape e o botão também
    // fecham, então não há armadilha para quem usa teclado.
    return '<div class="lfed-veu" data-acao="fechar-menu" aria-hidden="true"></div>';
  }

  /**
   * Barra lateral: marca, navegação, ação principal e conta.
   *
   * No desktop fica sempre visível. Em telas estreitas vira drawer, e o
   * `inert`-equivalente é feito por `data-menu`: fechada, ela sai da tela e
   * recebe `tabindex="-1"` nos controles via CSS `visibility`, para não
   * capturar tabulação de quem não a abriu.
   */
  function lateral() {
    var u = estado.usuario;
    var inicial = (u.nome || '?').trim().charAt(0).toUpperCase();

    return (
      '<aside class="lfed-lateral" id="lfed-nav"' +
      (estado.menuAberto ? '' : '') +
      ' aria-label="Navegação do painel">' +

      '<div class="lfed-lateral__topo">' +
      '<div class="lfed-marca">' +
      '<img src="' + esc(BOOT.assetsUrl + 'logo-licia-fabio.png') + '" alt="Lícia Fábio">' +
      '<span class="lfed-marca__nome">Painel Editorial</span>' +
      '</div>' +
      '<button class="lfed-lateral__fechar" data-acao="fechar-menu" aria-label="Fechar navegação">&times;</button>' +
      '</div>' +

      '<div class="lfed-lateral__acao">' +
      '<button class="lfed-btn lfed-btn--principal" data-acao="novo">Nova notícia</button>' +
      '</div>' +

      navegacao() +

      '<div class="lfed-lateral__pe">' +
      '<div class="lfed-conta">' +
      '<div class="lfed-conta__inicial">' + esc(inicial) + '</div>' +
      '<div class="lfed-conta__id">' +
      '<div class="lfed-conta__nome">' + esc(u.nome) + '</div>' +
      '<div class="lfed-conta__papel">' + esc(u.papel) + '</div>' +
      '</div>' +
      '</div>' +
      '<button class="lfed-btn lfed-btn--pequeno lfed-lateral__sair" data-acao="sair">Sair</button>' +
      '</div>' +

      '</aside>'
    );
  }

  /**
   * Barra de topo das telas estreitas.
   *
   * Só aparece abaixo de 900px, por CSS. O botão é o único caminho para abrir
   * o drawer, e carrega `aria-expanded`, `aria-controls` e nome acessível.
   *
   * Não é um segundo menu: não repete nenhum item de navegação. Duplicar a
   * navegação aqui faria leitor de tela anunciar tudo duas vezes.
   */
  function barraMobile() {
    return (
      '<header class="lfed-barra-mob">' +
      '<button class="lfed-menu-btn" data-acao="abrir-menu" id="lfed-menu-btn"' +
      ' aria-expanded="' + (estado.menuAberto ? 'true' : 'false') + '"' +
      ' aria-controls="lfed-nav" aria-label="Abrir navegação do painel">' +
      '<span class="lfed-menu-btn__barras" aria-hidden="true"></span>' +
      '<span>Menu</span>' +
      '</button>' +
      '<span class="lfed-barra-mob__nome">' + esc(rotuloDaTela()) + '</span>' +
      '<button class="lfed-btn lfed-btn--principal lfed-btn--pequeno" data-acao="novo">Nova</button>' +
      '</header>'
    );
  }

  /** Nome da tela atual, para a barra estreita. */
  function rotuloDaTela() {
    var nomes = {
      inicio: 'Início',
      lista: 'Notícias',
      editor: estado.editor && estado.editor.id ? 'Editar notícia' : 'Nova notícia',
      biblioteca: 'Imagens',
      eventos: 'Agenda',
      anuncios: 'Publicidade',
      item: estado.item ? (estado.item.id ? 'Editar' : TIPOS[estado.item.tipo].novo) : '',
    };

    return nomes[estado.tela] || 'Painel Editorial';
  }

  /**
   * Itens de navegação.
   *
   * Continuam sendo os mesmos cinco, com os mesmos destinos e os mesmos dois
   * desabilitados. Mudou a orientação visual, não o comportamento.
   */
  /** Qual item da navegação representa a tela atual. */
  function telaDaNav() {
    if (estado.tela === 'item' && estado.item) {
      return TIPOS[estado.item.tipo].tela;
    }

    return estado.tela === 'editor' ? 'lista' : estado.tela;
  }

  function navegacao() {
    function item(chave, rotulo, desativado) {
      return (
        '<button class="lfed-nav__item" data-tela="' + chave + '"' +
        (telaDaNav() === chave ? ' aria-current="page"' : '') +
        (desativado ? ' disabled title="Requer o plugin Lícia Fábio Core ativo"' : '') +
        '><span class="lfed-nav__marca" aria-hidden="true"></span>' +
        '<span>' + rotulo + '</span></button>'
      );
    }

    return (
      '<nav class="lfed-nav" aria-label="Seções do painel">' +
      item('inicio', 'Início') +
      item('lista', 'Notícias') +
      item('biblioteca', 'Imagens') +
      item('eventos', 'Agenda', !tipoDisponivel('evento')) +
      item('anuncios', 'Publicidade', !tipoDisponivel('anuncio')) +
      '</nav>'
    );
  }

  /* ------------------------------------------------- drawer: foco e rolagem */

  /** Para onde o foco deve ir depois da próxima pintura. */
  var focoPendente = null;

  function abrirMenu() {
    if (estado.menuAberto) {
      return;
    }

    estado.menuAberto = true;
    focoPendente = 'nav';
    pintar();
  }

  function fecharMenu(devolverFoco) {
    if (!estado.menuAberto) {
      return;
    }

    estado.menuAberto = false;
    focoPendente = false === devolverFoco ? null : 'botao';
    pintar();
  }

  /**
   * Move o foco depois da pintura.
   *
   * Ao abrir, o foco vai para a navegação. Ao fechar, volta ao botão que a
   * abriu — sem isso, quem usa teclado ficaria perdido no início do documento.
   */
  function moverFocoSePreciso() {
    if (!focoPendente) {
      return;
    }

    var alvo =
      focoPendente === 'nav'
        ? raiz.querySelector('.lfed-lateral__fechar')
        : raiz.querySelector('#lfed-menu-btn');

    focoPendente = null;

    if (alvo && typeof alvo.focus === 'function') {
      alvo.focus();
    }
  }

  /**
   * Impede a rolagem do conteúdo ao fundo com o drawer aberto.
   *
   * Mexe apenas em `overflow` do documento, e só enquanto o menu está aberto.
   */
  function aplicarTravaDeRolagem() {
    var travar = estado.menuAberto && window.matchMedia('(max-width: 900px)').matches;

    document.documentElement.style.overflow = travar ? 'hidden' : '';
    document.body.style.overflow = travar ? 'hidden' : '';
  }

  /*
   * Escape fecha o drawer, e a armadilha de foco o mantém utilizável por
   * teclado. Ligados uma única vez: pintar() roda a cada mudança de estado, e
   * registrar de novo acumularia ouvintes.
   */
  document.addEventListener('keydown', function (e) {
    if (!estado.menuAberto) {
      return;
    }

    if (e.key === 'Escape' || e.key === 'Esc') {
      e.preventDefault();
      fecharMenu(true);
      return;
    }

    if (e.key !== 'Tab') {
      return;
    }

    // Mantém a tabulação dentro do drawer enquanto ele está aberto.
    var lateralEl = raiz.querySelector('.lfed-lateral');

    if (!lateralEl) {
      return;
    }

    var focaveis = lateralEl.querySelectorAll('button:not([disabled]), a[href], input, select, textarea');

    if (!focaveis.length) {
      return;
    }

    var primeiro = focaveis[0];
    var ultimo = focaveis[focaveis.length - 1];

    if (e.shiftKey && document.activeElement === primeiro) {
      e.preventDefault();
      ultimo.focus();
      return;
    }

    if (!e.shiftKey && document.activeElement === ultimo) {
      e.preventDefault();
      primeiro.focus();
    }
  });

  /*
   * Ao alargar a janela, o drawer deixa de fazer sentido: a lateral volta a
   * ser permanente. Fechar o estado evita que a trava de rolagem fique presa.
   */
  window.addEventListener('resize', function () {
    if (estado.menuAberto && window.innerWidth > 900) {
      fecharMenu(false);
    }
  });

  function mensagem() {
    if (!estado.mensagem) {
      return '';
    }

    var m = estado.mensagem;
    var classe = m.tipo === 'ok' ? 'ok' : m.tipo === 'erro' ? 'erro' : 'info';
    var extra = m.link
      ? ' <a href="' + esc(m.link) + '" target="_blank" rel="noopener">Ver no site</a>'
      : '';

    if (m.loginUrl) {
      extra = ' <a href="' + esc(m.loginUrl) + '">Entrar novamente</a>';
    }

    if (m.ofereceNovaItem) {
      extra = ' <button class="lfed-btn lfed-btn--pequeno" data-acao="nova-chave-item">Cadastrar como novo</button>';
    }

    if (m.ofereceNova) {
      extra =
        ' <button class="lfed-btn lfed-btn--pequeno" data-acao="nova-chave">Criar como notícia nova</button>';
    }

    return (
      '<div class="lfed-aviso lfed-aviso--' + classe + '" role="' + (m.tipo === 'erro' ? 'alert' : 'status') + '">' +
      '<div>' + esc(m.texto) + extra + '</div></div>'
    );
  }

  function telaAtual() {
    switch (estado.tela) {
      case 'lista':
        return telaLista();
      case 'editor':
        return telaEditor();
      case 'biblioteca':
        return telaBiblioteca();
      case 'eventos':
        return telaItens('evento');
      case 'anuncios':
        return telaItens('anuncio');
      case 'item':
        return telaItemEditor();
      default:
        return telaInicio();
    }
  }

  function telaInicio() {
    var c = estado.resumo && estado.resumo.contagens;

    var atalhos = [
      ['publish', 'Publicadas'],
      ['draft', 'Rascunhos'],
      ['pending', 'Em revisão'],
      ['future', 'Agendadas'],
    ]
      .map(function (par) {
        return (
          '<button class="lfed-atalho" data-status="' + par[0] + '">' +
          '<div class="lfed-atalho__n">' + (c ? esc(c[par[0]]) : '—') + '</div>' +
          '<div class="lfed-atalho__r">' + par[1] + '</div></button>'
        );
      })
      .join('');

    var nota = estado.resumo && estado.resumo.apenasMinhas
      ? '<p class="lfed-campo__dica">As contagens mostram apenas as suas notícias, porque sua conta não tem permissão para editar as de outros autores.</p>'
      : '';

    return (
      '<h1 class="lfed-titulo-tela">Olá, ' + esc((estado.usuario.nome || '').split(' ')[0]) + '</h1>' +
      '<p class="lfed-sub-tela">O que você quer fazer agora?</p>' +
      mensagem() +
      '<div class="lfed-grade-atalhos">' + atalhos + '</div>' +
      nota +
      (BOOT.coreAtivo
        ? ''
        : '<div class="lfed-aviso lfed-aviso--info"><span class="lfed-aviso__marca">Atenção</span>' +
          '<div>O plugin Lícia Fábio Core está inativo. Os campos de subtítulo e crédito da foto ficam indisponíveis até que ele seja reativado — nada é gravado em campo alternativo.</div></div>')
    );
  }

  function telaLista() {
    var l = estado.lista;

    var opcoesStatus = [
      ['', 'Todos os status'],
      ['publish', 'Publicadas'],
      ['draft', 'Rascunhos'],
      ['pending', 'Em revisão'],
      ['future', 'Agendadas'],
    ]
      .map(function (p) {
        return '<option value="' + p[0] + '"' + (estado.filtros.status === p[0] ? ' selected' : '') + '>' + p[1] + '</option>';
      })
      .join('');

    var filtroAutor = estado.usuario.caps.editarDeOutros
      ? '<label class="lfed-campo" style="margin:0"><span class="lfed-campo__rotulo">Autor</span>' +
        '<select id="lfed-f-autor"><option value="">Todos</option>' +
        '<option value="minhas"' + (estado.filtros.autor === 'minhas' ? ' selected' : '') + '>Só as minhas</option>' +
        '</select></label>'
      : '';

    var corpo;

    if (l.carregando) {
      corpo = '<div class="lfed-vazio">Carregando…</div>';
    } else if (!l.itens.length) {
      corpo =
        '<div class="lfed-vazio"><p class="lfed-vazio__t">Nenhuma notícia encontrada</p>' +
        '<p>' + (estado.filtros.busca ? 'Tente outro termo ou limpe a busca.' : 'Quando você criar a primeira, ela aparece aqui.') + '</p></div>';
    } else {
      corpo = l.itens
        .map(function (p) {
          var titulo = (p.title && (p.title.raw || p.title.rendered)) || '(sem título)';

          return (
            '<div class="lfed-linha-item">' +
            '<div><p class="lfed-item__titulo">' + esc(titulo) + '</p>' +
            '<div class="lfed-item__meta">Atualizada em ' + esc(dataBr(p.modified_gmt + 'Z')) + '</div></div>' +
            '<div><span class="lfed-etiqueta lfed-etiqueta--' + esc(p.status) + '">' + esc(ROTULO_STATUS[p.status] || p.status) + '</span></div>' +
            '<div class="lfed-linha-item__oculto-mob lfed-item__meta">' + esc(dataBr(p.date_gmt + 'Z')) + '</div>' +
            '<div class="lfed-linha-item__oculto-mob">' +
            (p.status === 'publish' && p.link
              ? '<a href="' + esc(p.link) + '" target="_blank" rel="noopener">Ver no site</a>'
              : '<span class="lfed-item__meta">—</span>') +
            '</div>' +
            '<div><button class="lfed-btn lfed-btn--pequeno" data-editar="' + p.id + '">Abrir</button></div>' +
            '</div>'
          );
        })
        .join('');

      corpo =
        '<div class="lfed-linha-item lfed-linha-item--cabecalho">' +
        '<div>Notícia</div><div>Status</div><div>Publicação</div><div>No site</div><div></div></div>' + corpo;
    }

    var paginacao =
      l.paginas > 1
        ? '<div class="lfed-paginacao">' +
          '<button class="lfed-btn lfed-btn--pequeno" data-pagina="' + (l.pagina - 1) + '"' + (l.pagina <= 1 ? ' disabled' : '') + '>Anterior</button>' +
          '<span class="lfed-paginacao__pos">Página ' + l.pagina + ' de ' + l.paginas + ' · ' + l.total + ' notícias</span>' +
          '<button class="lfed-btn lfed-btn--pequeno" data-pagina="' + (l.pagina + 1) + '"' + (l.pagina >= l.paginas ? ' disabled' : '') + '>Próxima</button>' +
          '</div>'
        : '';

    return (
      '<h1 class="lfed-titulo-tela">Notícias</h1>' +
      '<p class="lfed-sub-tela">' +
      (estado.usuario.caps.editarDeOutros
        ? 'Todas as notícias do portal.'
        : 'Suas notícias. Sua conta não tem permissão para editar as de outros autores.') +
      '</p>' +
      mensagem() +
      '<div class="lfed-filtros">' +
      '<label class="lfed-campo lfed-filtros__busca" style="margin:0"><span class="lfed-campo__rotulo">Buscar por título</span>' +
      '<input type="search" id="lfed-f-busca" value="' + esc(estado.filtros.busca) + '" placeholder="Digite e pressione Enter"></label>' +
      '<label class="lfed-campo" style="margin:0"><span class="lfed-campo__rotulo">Status</span>' +
      '<select id="lfed-f-status">' + opcoesStatus + '</select></label>' +
      filtroAutor +
      '</div>' +
      '<div class="lfed-tabela">' + corpo + '</div>' + paginacao
    );
  }

  function telaEditor() {
    var ed = estado.editor;

    if (!ed) {
      return '';
    }

    var cats = BOOT.categorias || [];
    var escolhidas = ed.categoriasTocadas ? ed.categorias : ed.catsConhecidas.length ? ed.catsConhecidas : ed.categorias;

    var opcoesCat = cats
      .map(function (c) {
        var marcada = escolhidas.indexOf(c.id) !== -1;

        return (
          '<label style="display:flex;gap:9px;align-items:flex-start;padding:7px 0;cursor:pointer">' +
          '<input type="checkbox" class="lfed-cat" value="' + c.id + '"' + (marcada ? ' checked' : '') +
          ' style="margin-top:3px;width:18px;height:18px;flex-shrink:0">' +
          '<span style="font-size:14px;line-height:1.35">' + esc(c.nome) + '</span></label>'
        );
      })
      .join('');

    /*
     * Categorias atribuídas que NÃO estão no seletor — por estarem fora das
     * 200 carregadas. São mostradas e preservadas: omiti-las do envio as
     * apagaria da notícia.
     */
    var idsVisiveis = cats.map(function (c) {
      return c.id;
    });

    var foraDaLista = escolhidas.filter(function (id) {
      return idsVisiveis.indexOf(id) === -1;
    });

    var notaFora = foraDaLista.length
      ? '<p class="lfed-campo__dica">' + foraDaLista.length +
        (foraDaLista.length === 1 ? ' categoria atribuída' : ' categorias atribuídas') +
        ' não aparece' + (foraDaLista.length === 1 ? '' : 'm') +
        ' nesta lista, e continua' + (foraDaLista.length === 1 ? '' : 'm') +
        ' preservada' + (foraDaLista.length === 1 ? '' : 's') + ' ao salvar.</p>'
      : '';

    var recuperacao = ed.recuperavel
      ? '<div class="lfed-aviso lfed-aviso--info"><span class="lfed-aviso__marca">Recuperação</span>' +
        '<div>Encontrei um texto não salvo neste navegador, de ' + esc(dataBr(new Date(ed.recuperavel.quando).toISOString())) + '.' +
        (ed.recuperavel.chaveOperacao && !ed.id
          ? ' Havia um envio pendente: ao recuperar, eu confiro se a notícia já foi criada antes de salvar de novo.'
          : '') +
        ' <button class="lfed-btn lfed-btn--pequeno" data-acao="recuperar">Recuperar</button> ' +
        '<button class="lfed-btn lfed-btn--pequeno" data-acao="descartar-local">Descartar</button></div></div>'
      : '';

    var destacada = ed.destacadaUrl
      ? '<div class="lfed-destacada__previa"><img src="' + esc(ed.destacadaUrl) + '" alt=""></div>'
      : '<div class="lfed-destacada__previa"><div class="lfed-destacada__vazio">Nenhuma imagem destacada escolhida</div></div>';

    var camposImagem = ed.destacadaId
      ? '<label class="lfed-campo"><span class="lfed-campo__rotulo">Texto alternativo</span>' +
        '<input type="text" id="lfed-img-alt" value="' + esc(ed.destacadaAlt) + '" placeholder="Descreva a imagem para quem não a vê">' +
        '<p class="lfed-campo__dica">Descreva o que a foto mostra. Não escreva o crédito aqui.</p></label>' +
        '<label class="lfed-campo"><span class="lfed-campo__rotulo">Legenda</span>' +
        '<input type="text" id="lfed-img-legenda" value="' + esc(ed.destacadaLegenda) + '"></label>' +
        (BOOT.coreAtivo
          ? '<label class="lfed-campo"><span class="lfed-campo__rotulo">Crédito da foto</span>' +
            '<input type="text" id="lfed-img-credito" value="' + esc(ed.destacadaCredito) + '" placeholder="Nome do fotógrafo ou agência">' +
            '<p class="lfed-campo__dica">O crédito pertence à imagem: vale em toda notícia que a usar.</p></label>'
          : '') +
        '<button class="lfed-btn lfed-btn--pequeno" data-acao="salvar-imagem">Salvar dados da imagem</button>'
      : '';

    var uploadBloqueado = !estado.usuario.caps.enviarArquivos;

    var editorCorpo = ed.modoCodigo
      ? '<textarea class="lfed-codigo" id="lfed-codigo" spellcheck="false">' + esc(ed.corpo) + '</textarea>'
      : '<div class="lfed-conteudo" id="lfed-conteudo" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Corpo da notícia"></div>';

    var ferramentas = ed.modoCodigo
      ? ''
      : '<div class="lfed-ferramentas" role="toolbar" aria-label="Formatação">' +
        '<button type="button" data-cmd="formatBlock" data-val="p" title="Parágrafo">Parágrafo</button>' +
        '<button type="button" data-cmd="formatBlock" data-val="h2" title="Intertítulo">Título</button>' +
        '<button type="button" data-cmd="formatBlock" data-val="h3" title="Subtítulo interno">Título 2</button>' +
        '<span class="lfed-ferramentas__risco"></span>' +
        '<button type="button" data-cmd="bold" title="Negrito"><strong>N</strong></button>' +
        '<button type="button" data-cmd="italic" title="Itálico"><em>I</em></button>' +
        '<span class="lfed-ferramentas__risco"></span>' +
        '<button type="button" data-cmd="insertUnorderedList" title="Lista">Lista</button>' +
        '<button type="button" data-cmd="insertOrderedList" title="Lista numerada">1. Lista</button>' +
        '<span class="lfed-ferramentas__risco"></span>' +
        '<button type="button" data-acao="link" title="Inserir link">Link</button>' +
        '<button type="button" data-acao="imagem-corpo" title="Inserir imagem"' + (uploadBloqueado ? ' disabled' : '') + '>Imagem</button>' +
        '</div>';

    var acoes = [];

    acoes.push('<button class="lfed-btn" data-salvar="draft"' + (ed.salvando ? ' disabled' : '') + '>Salvar rascunho</button>');
    acoes.push('<button class="lfed-btn" data-salvar="pending"' + (ed.salvando ? ' disabled' : '') + '>Enviar para revisão</button>');

    if (estado.usuario.caps.publicar) {
      acoes.push(
        '<button class="lfed-btn lfed-btn--principal" data-salvar="publish"' + (ed.salvando ? ' disabled' : '') + '>' +
        (ed.status === 'publish' ? 'Atualizar publicada' : 'Publicar agora') + '</button>'
      );
    }

    var notaPublicar = estado.usuario.caps.publicar
      ? ''
      : '<div class="lfed-aviso lfed-aviso--info"><span class="lfed-aviso__marca">Permissão</span>' +
        '<div>Sua conta não publica direto. Use “Enviar para revisão” — quem tem permissão publica depois.</div></div>';

    return (
      '<div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:18px">' +
      '<button class="lfed-btn lfed-btn--pequeno" data-tela="lista">← Notícias</button>' +
      '<h1 class="lfed-titulo-tela" style="margin:0">' + (ed.id ? 'Editar notícia' : 'Nova notícia') + '</h1>' +
      (ed.id ? '<span class="lfed-etiqueta lfed-etiqueta--' + esc(ed.status) + '">' + esc(ROTULO_STATUS[ed.status] || ed.status) + '</span>' : '') +
      (ed.id ? '<span class="lfed-item__meta">ID ' + ed.id + '</span>' : '') +
      '</div>' +
      mensagem() +
      recuperacao +
      notaPublicar +
      '<div class="lfed-editor">' +
      '<div>' +
      '<label class="lfed-campo"><span class="lfed-campo__rotulo">Título</span>' +
      '<input type="text" class="lfed-titulo-campo" id="lfed-titulo" value="' + esc(ed.titulo) + '" placeholder="Título da notícia"></label>' +
      (BOOT.coreAtivo
        ? '<label class="lfed-campo"><span class="lfed-campo__rotulo">Subtítulo</span>' +
          '<textarea id="lfed-subtitulo" rows="2" placeholder="Uma linha que complementa o título">' + esc(ed.subtitulo) + '</textarea>' +
          '<p class="lfed-campo__dica">Aparece entre o título e a foto. Pode ficar vazio.</p></label>'
        : '') +
      '<div class="lfed-campo"><span class="lfed-campo__rotulo">Corpo da notícia</span>' +
      ferramentas + editorCorpo +
      '<p class="lfed-campo__dica">' +
      '<button class="lfed-btn lfed-btn--pequeno" data-acao="modo-codigo" style="margin-top:8px">' +
      (ed.modoCodigo ? 'Voltar ao editor visual' : 'Editar o HTML') + '</button> ' +
      'O HTML das notícias antigas é preservado como está — nada é convertido.</p>' +
      '</div>' +
      '</div>' +
      '<aside class="lfed-editor__lateral">' +
      '<div class="lfed-cartao">' +
      '<span class="lfed-campo__rotulo">Categorias</span>' +
      '<div id="lfed-cats" style="max-height:260px;overflow-y:auto;margin-top:4px">' +
      (opcoesCat || '<p class="lfed-campo__dica">Nenhuma categoria cadastrada no site.</p>') +
      '</div>' + notaFora +
      '</div>' +
      '<div class="lfed-cartao">' +
      '<span class="lfed-campo__rotulo">Imagem destacada</span>' +
      destacada +
      (uploadBloqueado
        ? '<p class="lfed-campo__dica">Sua conta não tem permissão para enviar imagens.</p>'
        : '<div style="display:flex;gap:8px;flex-wrap:wrap">' +
          '<button class="lfed-btn lfed-btn--pequeno" data-acao="enviar-destacada">Enviar imagem</button>' +
          (ed.destacadaId ? '<button class="lfed-btn lfed-btn--pequeno" data-acao="remover-destacada">Remover</button>' : '') +
          '</div>') +
      camposImagem +
      '</div>' +
      (ed.link && ed.status === 'publish'
        ? '<div class="lfed-cartao"><span class="lfed-campo__rotulo">No site</span>' +
          '<p class="lfed-campo__dica" style="margin-top:0"><a href="' + esc(ed.link) + '" target="_blank" rel="noopener">' + esc(ed.link) + '</a></p></div>'
        : '') +
      '</aside>' +
      '</div>' +
      '<div class="lfed-barra-salvar">' +
      '<span class="lfed-barra-salvar__estado">' +
      (ed.salvando ? 'Salvando…' : ed.sujo ? 'Alterações não salvas' : ed.id ? 'Tudo salvo' : 'Nada salvo ainda') +
      '</span>' + acoes.join('') +
      '</div>'
    );
  }

  function telaBiblioteca() {
    return (
      '<h1 class="lfed-titulo-tela">Imagens</h1>' +
      '<p class="lfed-sub-tela">As imagens ficam na biblioteca do WordPress. Escolha uma notícia para usá-las.</p>' +
      mensagem() +
      '<div class="lfed-cartao" id="lfed-galeria-caixa"><div class="lfed-vazio">Carregando…</div></div>'
    );
  }

  function telaIndisponivel(nome) {
    return (
      '<h1 class="lfed-titulo-tela">' + esc(nome) + '</h1>' +
      '<div class="lfed-aviso lfed-aviso--info"><span class="lfed-aviso__marca">Em breve</span>' +
      '<div>Esta seção fica para uma entrega futura. Nada aqui está ligado ao WordPress ainda — e preferimos deixar a tela honestamente vazia a mostrar botões que não fazem nada. Por enquanto, ' + esc(nome.toLowerCase()) + ' continua sendo gerenciada no painel do WordPress.</div></div>'
    );
  }

  /* =============================================================== eventos */
  var delegacaoLigada = false;

  function ligarEventos() {
    /*
     * A delegação é ligada UMA vez. pintar() roda a cada mudança de estado, e
     * registrar o mesmo ouvinte a cada pintura acumularia handlers — um clique
     * passaria a disparar a ação várias vezes, que é exatamente o caminho para
     * criar post duplicado.
     */
    if (!delegacaoLigada) {
      raiz.addEventListener('click', aoClicar);

      // Formulários de evento e anúncio: um ouvinte, sem repintar a cada tecla.
      raiz.addEventListener('input', function (e) {
        if (e.target && e.target.dataset && e.target.dataset.campo && e.target.type !== 'checkbox' && e.target.tagName !== 'SELECT') {
          aoEditarItem(e.target);
        }
      });

      raiz.addEventListener('change', function (e) {
        if (e.target && e.target.dataset && e.target.dataset.campo && (e.target.type === 'checkbox' || e.target.tagName === 'SELECT' || e.target.type === 'date' || e.target.type === 'time')) {
          aoEditarItem(e.target);
        }
      });

      delegacaoLigada = true;
    }

    var conteudo = document.getElementById('lfed-conteudo');

    if (conteudo && estado.editor) {
      // innerHTML atribuído fora do template: preserva o HTML antigo como está.
      conteudo.innerHTML = estado.editor.corpo;

      conteudo.addEventListener('input', function () {
        estado.editor.sujo = true;
        estado.editor.corpo = conteudo.innerHTML;
        atualizarEstadoBarra();
        salvarLocal(
          estado.editor.id,
          {
            titulo: estado.editor.titulo,
            subtitulo: estado.editor.subtitulo,
            corpo: estado.editor.corpo,
          },
          estado.editor.chaveOperacao
        );
      });

      /*
       * Colagem: usa o texto simples e monta parágrafos. Colar HTML do Word ou
       * do Google Docs traria estilos e marcação que o portal não usa — e
       * converter em silêncio é pior que pedir a formatação de novo.
       */
      conteudo.addEventListener('paste', function (e) {
        var texto = (e.clipboardData || window.clipboardData).getData('text/plain');

        if (!texto) {
          return;
        }

        e.preventDefault();

        var html = texto
          .split(/\n{2,}/)
          .map(function (p) {
            return '<p>' + esc(p.replace(/\n/g, ' ')).trim() + '</p>';
          })
          .join('');

        document.execCommand('insertHTML', false, html);
      });
    }

    var codigo = document.getElementById('lfed-codigo');

    if (codigo && estado.editor) {
      codigo.addEventListener('input', function () {
        estado.editor.sujo = true;
        estado.editor.corpo = codigo.value;
        atualizarEstadoBarra();
      });
    }

    ligar('lfed-titulo', 'input', function (el) {
      estado.editor.titulo = el.value;
      estado.editor.sujo = true;
      atualizarEstadoBarra();
    });

    ligar('lfed-subtitulo', 'input', function (el) {
      estado.editor.subtitulo = el.value;
      estado.editor.sujo = true;
      atualizarEstadoBarra();
    });

    var caixaCats = document.getElementById('lfed-cats');

    if (caixaCats && estado.editor) {
      caixaCats.addEventListener('change', function (e) {
        if (!e.target.classList.contains('lfed-cat')) {
          return;
        }

        var ed = estado.editor;
        var base = ed.categoriasTocadas ? ed.categorias : ed.catsConhecidas.slice();
        var id = Number(e.target.value);

        if (e.target.checked) {
          if (base.indexOf(id) === -1) {
            base.push(id);
          }
        } else {
          base = base.filter(function (x) {
            return x !== id;
          });
        }

        ed.categorias = base;
        ed.categoriasTocadas = true;
        ed.sujo = true;
        atualizarEstadoBarra();
      });
    }

    ligar('lfed-f-busca', 'keydown', function (el, e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        estado.filtros.busca = el.value.trim();
        carregarLista(1);
      }
    });

    ligar('lfed-f-status', 'change', function (el) {
      estado.filtros.status = el.value;
      carregarLista(1);
    });

    ligar('lfed-f-autor', 'change', function (el) {
      estado.filtros.autor = el.value;
      carregarLista(1);
    });

    if (estado.tela === 'biblioteca') {
      carregarGaleria();
    }
  }

  function ligar(id, evento, fn) {
    var el = document.getElementById(id);

    if (el) {
      el.addEventListener(evento, function (e) {
        fn(el, e);
      });
    }
  }

  /** Atualiza só o texto da barra, sem repintar e perder o cursor. */
  function atualizarEstadoBarra() {
    var el = document.querySelector('.lfed-barra-salvar__estado');

    if (el && estado.editor) {
      el.textContent = estado.editor.salvando
        ? 'Salvando…'
        : estado.editor.sujo
        ? 'Alterações não salvas'
        : estado.editor.id
        ? 'Tudo salvo'
        : 'Nada salvo ainda';
    }
  }

  function aoClicar(e) {
    var alvo = e.target.closest('[data-tela],[data-acao],[data-editar],[data-salvar],[data-status],[data-pagina],[data-cmd]');

    if (!alvo || alvo.disabled) {
      return;
    }

    // Formatação: age na seleção, sem repintar.
    if (alvo.dataset.cmd) {
      e.preventDefault();

      var campo = document.getElementById('lfed-conteudo');

      if (campo) {
        campo.focus();
        document.execCommand(alvo.dataset.cmd, false, alvo.dataset.val || null);
        estado.editor.sujo = true;
        estado.editor.corpo = campo.innerHTML;
        atualizarEstadoBarra();
      }

      return;
    }

    if (alvo.dataset.tela) {
      if (!sairDoEditor()) {
        return;
      }

      // Navegar pelo drawer fecha o drawer, sem devolver foco ao botão: o
      // conteúdo novo é o destino natural da atenção.
      if (estado.menuAberto) {
        estado.menuAberto = false;
      }

      estado.tela = alvo.dataset.tela;
      estado.mensagem = null;

      if (alvo.dataset.tela === 'lista') {
        estado.editor = null;
        carregarLista(1);
      }

      if (alvo.dataset.tela === 'eventos' || alvo.dataset.tela === 'anuncios') {
        estado.item = null;
        carregarItens(alvo.dataset.tela === 'eventos' ? 'evento' : 'anuncio', 1);
        return;
      }

      pintar();
      return;
    }

    if (alvo.dataset.status) {
      estado.filtros.status = alvo.dataset.status;
      estado.tela = 'lista';
      carregarLista(1);
      return;
    }

    if (alvo.dataset.pagina) {
      carregarLista(Number(alvo.dataset.pagina));
      return;
    }

    if (alvo.dataset.editar) {
      if (!sairDoEditor()) {
        return;
      }

      abrirEdicao(Number(alvo.dataset.editar));
      return;
    }

    if (alvo.dataset.salvar) {
      salvar(alvo.dataset.salvar);
      return;
    }

    acao(alvo.dataset.acao, alvo);
  }

  function sairDoEditor() {
    if ((estado.editor && estado.editor.sujo) || (estado.item && estado.item.sujo)) {
      return window.confirm('Você tem alterações não salvas. Sair e perder o que digitou?');
    }

    return true;
  }

  function acao(nome, alvo) {
    var ed = estado.editor;

    if (acaoItem(nome, alvo)) {
      return;
    }

    if (nome === 'abrir-menu') {
      if (estado.menuAberto) {
        fecharMenu(true);
      } else {
        abrirMenu();
      }

      return;
    }

    if (nome === 'fechar-menu') {
      fecharMenu(true);
      return;
    }

    /*
     * Gerar uma chave nova é uma ação EXPLÍCITA do redator, tomada depois de
     * ele conferir que a notícia não existe. O painel nunca faz isso sozinho:
     * seria justamente como se cria a duplicata.
     */
    if (nome === 'nova-chave') {
      if (!window.confirm('Você conferiu na lista que esta notícia ainda não existe? Se ela já existir, isto vai criar uma cópia.')) {
        return;
      }

      ed.chaveOperacao = novaChaveOperacao();
      ed.chaveBloqueada = false;

      salvarLocal(0, { titulo: ed.titulo, subtitulo: ed.subtitulo, corpo: ed.corpo }, ed.chaveOperacao);

      avisar('info', 'Pode salvar: esta passou a ser uma notícia nova.');
      return;
    }

    if (nome === 'sair') {
      if (estado.editor && estado.editor.sujo) {
        if (!window.confirm('Você tem alterações não salvas. Sair e perder o que digitou?')) {
          return;
        }
      }

      // Nenhum rascunho fica no navegador depois de sair.
      limparTodosDoEscopo();
      window.location.href = BOOT.logoutUrl;
      return;
    }

    if (nome === 'novo') {
      if (!sairDoEditor()) {
        return;
      }

      estado.menuAberto = false;
      abrirNovo();
      return;
    }

    if (nome === 'modo-codigo') {
      ed.corpo = ed.modoCodigo ? document.getElementById('lfed-codigo').value : lerConteudoDoDom();
      ed.modoCodigo = !ed.modoCodigo;
      pintar();
      return;
    }

    if (nome === 'recuperar') {
      var rec = ed.recuperavel;

      ed.titulo = rec.dados.titulo || ed.titulo;
      ed.subtitulo = rec.dados.subtitulo || ed.subtitulo;
      ed.corpo = rec.dados.corpo || ed.corpo;
      ed.recuperavel = null;
      ed.sujo = true;

      if (rec.chaveOperacao) {
        ed.chaveOperacao = rec.chaveOperacao;
      }

      pintar();

      /*
       * Se o rascunho trazia uma criação pendente, o painel precisa saber o
       * que aconteceu com ela ANTES de permitir outro salvamento. Sem isso —
       * o defeito da 1.0.1 — recarregar e salvar criava uma segunda notícia.
       */
      if (!ed.id && rec.chaveOperacao) {
        resolverOperacaoPendente(rec.chaveOperacao);
      }

      return;
    }

    if (nome === 'descartar-local') {
      limparLocal(ed.id);
      ed.recuperavel = null;
      pintar();
      return;
    }

    if (nome === 'link') {
      var endereco = window.prompt('Endereço do link (começando com https://)');

      if (endereco && /^https?:\/\//i.test(endereco)) {
        document.getElementById('lfed-conteudo').focus();
        document.execCommand('createLink', false, endereco);
        ed.sujo = true;
        ed.corpo = lerConteudoDoDom();
        atualizarEstadoBarra();
      } else if (endereco) {
        avisar('erro', 'O link precisa começar com http:// ou https://.');
      }

      return;
    }

    if (nome === 'enviar-destacada' || nome === 'imagem-corpo') {
      escolherArquivo(function (arquivo) {
        // Guarda o corpo atual antes de qualquer repintura.
        if (!ed.modoCodigo) {
          ed.corpo = lerConteudoDoDom();
        }

        enviarArquivo(arquivo, function (midia) {
          if (nome === 'enviar-destacada') {
            ed.destacadaId = midia.id;
            ed.sujo = true;
            carregarDestacada(midia.id);
            avisar('ok', 'Imagem enviada para a biblioteca do WordPress. ID ' + midia.id + '.');
          } else {
            inserirImagemNoCorpo(midia);
          }
        });
      });

      return;
    }

    if (nome === 'remover-destacada') {
      ed.destacadaId = 0;
      ed.destacadaUrl = '';
      ed.sujo = true;
      pintar();
      return;
    }

    if (nome === 'salvar-imagem') {
      var campos = {
        alt: (document.getElementById('lfed-img-alt') || {}).value || '',
        legenda: (document.getElementById('lfed-img-legenda') || {}).value || '',
        credito: (document.getElementById('lfed-img-credito') || {}).value || '',
      };

      salvarDadosDaImagem(ed.destacadaId, campos)
        .then(function () {
          ed.destacadaAlt = campos.alt;
          ed.destacadaLegenda = campos.legenda;
          ed.destacadaCredito = campos.credito;
          avisar('ok', 'Dados da imagem salvos no WordPress.');
        })
        .catch(function (e) {
          avisar('erro', e.message);
        });

      return;
    }

    if (nome === 'usar-imagem' && alvo.dataset.midia) {
      avisar('info', 'Abra ou crie uma notícia para usar esta imagem.');
    }
  }

  /**
   * Descobre o que aconteceu com uma criação cujo resultado é desconhecido.
   *
   * Quatro desfechos, e nenhum deles cria notícia:
   *
   *   criada       → o painel passa a tratar como ATUALIZAÇÃO daquele post;
   *   em_andamento → espera, e tenta de novo em alguns segundos;
   *   inexistente  → a operação nunca chegou; pode criar com a mesma chave;
   *   desconhecida → o registro venceu. NÃO cria sozinho: pede que o redator
   *                  confira a lista, porque criar às cegas um post cujo
   *                  resultado anterior é desconhecido é como se duplica.
   */
  function resolverOperacaoPendente(chave, tentativa) {
    tentativa = tentativa || 1;

    estado.resolvendo = true;
    pintar();

    api('licia-fabio-editorial/v1/operacoes/' + encodeURIComponent(chave))
      .then(function (r) {
        var d = r.dados || {};
        var ed = estado.editor;

        estado.resolvendo = false;

        if (!ed) {
          return;
        }

        if (d.situacao === 'criada') {
          ed.id = d.id;
          ed.versao = d.versao || '';
          ed.status = d.status || ed.status;
          ed.link = d.link || '';

          if (d.categorias) {
            ed.catsConhecidas = d.categorias.map(Number);
          }

          avisar(
            'info',
            (d.expirada
              ? 'Este pedido já havia criado uma notícia (ID ' + d.id + ') e o registro dele venceu, mas a notícia foi localizada. '
              : 'A notícia já havia sido criada pelo pedido anterior — ID ' + d.id + '. ') +
              'Nenhuma cópia será feita. O texto recuperado ainda não está salvo: clique em salvar para gravá-lo nessa notícia.'
          );
          return;
        }

        if (d.situacao === 'em_andamento') {
          if (tentativa < 4) {
            avisar('info', 'O pedido anterior ainda está sendo processado. Aguarde um instante antes de salvar.');

            window.setTimeout(function () {
              resolverOperacaoPendente(chave, tentativa + 1);
            }, 2500);

            return;
          }

          avisar(
            'erro',
            'Não consegui confirmar o que aconteceu com o pedido anterior. Antes de salvar, abra a lista de notícias em outra aba e confira se a notícia já existe — para não criar uma cópia.'
          );
          return;
        }

        if (d.situacao === 'desconhecida' || d.expirada) {
          estado.editor.chaveBloqueada = true;

          avisar(
            'erro',
            'O registro do pedido anterior venceu e não sei se a notícia foi criada. Não vou criar outra por conta própria: confira a lista de notícias em outra aba. Se ela não existir, use “Criar como notícia nova”.',
            { ofereceNova: true }
          );
          return;
        }

        // inexistente: a operação nunca chegou. Pode criar com a mesma chave.
        estado.mensagem = null;
        pintar();
      })
      .catch(function (e) {
        estado.resolvendo = false;

        avisar(
          'erro',
          'Não consegui verificar o pedido anterior (' + e.message +
            ') Antes de salvar, confira a lista de notícias em outra aba, para não criar uma cópia.'
        );
      });
  }

  /* =====================================================================
   * AGENDA E PUBLICIDADE
   *
   * As duas abas usam os MESMOS endpoints de gravação das notícias —
   * /noticias com chave de operação e /noticias/{id} com versão e trava —
   * passando o tipo. Então herdam idempotência, lápides, guarda de versão,
   * 409 e trava por item, sem uma segunda implementação que possa divergir.
   *
   * Leitura de listas pela rota nativa de cada tipo (lf-eventos,
   * lf-anuncios). Campos: os registrados pelo Core, conferidos no código.
   * ================================================================== */

  var TIPOS = {
    evento: {
      wp: 'lf_evento',
      rest: 'wp/v2/lf-eventos',
      tela: 'eventos',
      nome: 'Agenda',
      novo: 'Novo evento',
      vazio: 'Nenhum evento cadastrado',
    },
    anuncio: {
      wp: 'lf_anuncio',
      rest: 'wp/v2/lf-anuncios',
      tela: 'anuncios',
      nome: 'Publicidade',
      novo: 'Novo anúncio',
      vazio: 'Nenhum anúncio cadastrado',
    },
  };

  var ROTULO_STATUS_ITEM = {
    publish: 'Publicado',
    draft: 'Rascunho',
    pending: 'Em revisão',
    future: 'Agendado',
    private: 'Privado',
  };

  function tipoDisponivel(tipo) {
    return !!(BOOT.tiposAtivos && BOOT.tiposAtivos[tipo]);
  }

  function chaveLocalItem(tipo, id) {
    return 'it-' + tipo + '-' + (id || 'novo');
  }

  function metaVazia(tipo) {
    if (tipo === 'evento') {
      return {
        _lf_event_date: '',
        _lf_event_time: '',
        _lf_event_location: '',
        _lf_event_address: '',
        _lf_event_url: '',
      };
    }

    return {
      _lf_ad_position: '',
      _lf_ad_desktop_image_id: 0,
      _lf_ad_mobile_image_id: 0,
      _lf_ad_url: '',
      _lf_ad_alt: '',
      _lf_ad_start_date: '',
      _lf_ad_end_date: '',
      _lf_ad_active: false,
      _lf_ad_new_tab: false,
    };
  }

  function itemVazio(tipo) {
    return {
      tipo: tipo,
      id: 0,
      titulo: '',
      conteudo: '',
      status: 'draft',
      link: '',
      versao: '',
      meta: metaVazia(tipo),
      destacadaId: 0,
      previas: {},
      chaveOperacao: '',
      chaveBloqueada: false,
      sujo: false,
      salvando: false,
    };
  }

  /** O que identifica o conteúdo de um item, para comparar enviado × tela. */
  function retratoItem(it) {
    return JSON.stringify({
      t: it.titulo,
      c: it.tipo === 'evento' ? it.conteudo : '',
      m: it.meta,
      d: it.tipo === 'evento' ? it.destacadaId : 0,
    });
  }

  function guardarItemLocal(it) {
    salvarLocal(
      chaveLocalItem(it.tipo, it.id),
      { titulo: it.titulo, conteudo: it.conteudo, meta: it.meta, destacadaId: it.destacadaId },
      it.chaveOperacao
    );
  }

  /* ------------------------------------------------------------ listas */

  function carregarItens(tipo, pagina) {
    var t = TIPOS[tipo];

    estado.itens = { tipo: tipo, lista: [], pagina: pagina || 1, paginas: 1, total: 0, carregando: true };
    pintar();

    var params = {
      per_page: 20,
      page: pagina || 1,
      context: 'edit',
      orderby: 'modified',
      order: 'desc',
      status: 'publish,draft,pending,future,private',
      _fields: 'id,title,status,modified_gmt,meta,link',
    };

    // Mesma regra das notícias: sem edit_others_posts, só os próprios.
    if (!estado.usuario.caps.editarDeOutros) {
      params.author = estado.usuario.id;
    }

    api(t.rest, { params: params })
      .then(function (r) {
        estado.itens = {
          tipo: tipo,
          lista: r.dados || [],
          pagina: pagina || 1,
          paginas: parseInt(r.headers.get('X-WP-TotalPages') || '1', 10),
          total: parseInt(r.headers.get('X-WP-Total') || '0', 10),
          carregando: false,
        };
        pintar();
      })
      .catch(function (e) {
        estado.itens.carregando = false;
        avisar('erro', e.message);
      });
  }

  function rotuloPosicao(chave) {
    var p = (BOOT.posicoesAnuncio || []).filter(function (x) {
      return x.chave === chave;
    })[0];

    return p ? p.rotulo : chave ? chave : 'Sem posição';
  }

  function dataCurta(ymd) {
    if (!ymd || !/^\d{4}-\d{2}-\d{2}$/.test(ymd)) {
      return '';
    }

    var p = ymd.split('-');
    return p[2] + '/' + p[1] + '/' + p[0];
  }

  function telaItens(tipo) {
    var t = TIPOS[tipo];

    if (!tipoDisponivel(tipo)) {
      return (
        '<h1 class="lfed-titulo-tela">' + esc(t.nome) + '</h1>' +
        '<div class="lfed-aviso lfed-aviso--info"><span class="lfed-aviso__marca">Atenção</span>' +
        '<div>Este tipo de conteúdo é registrado pelo plugin Lícia Fábio Core, que não está ativo. Reative-o para usar esta seção.</div></div>'
      );
    }

    var l = estado.itens && estado.itens.tipo === tipo ? estado.itens : { lista: [], carregando: true, pagina: 1, paginas: 1, total: 0 };
    var corpo;

    if (l.carregando) {
      corpo = '<div class="lfed-vazio">Carregando…</div>';
    } else if (!l.lista.length) {
      corpo =
        '<div class="lfed-vazio"><p class="lfed-vazio__t">' + esc(t.vazio) + '</p>' +
        '<p>Use “' + esc(t.novo) + '” para cadastrar o primeiro.</p></div>';
    } else {
      corpo = l.lista
        .map(function (p) {
          var m = p.meta || {};
          var titulo = (p.title && (p.title.raw || p.title.rendered)) || '(sem título)';
          var detalhe;

          if (tipo === 'evento') {
            detalhe =
              [dataCurta(m._lf_event_date), m._lf_event_time, m._lf_event_location]
                .filter(Boolean)
                .join(' · ') || 'Sem data definida';
          } else {
            var periodo = [dataCurta(m._lf_ad_start_date), dataCurta(m._lf_ad_end_date)].filter(Boolean).join(' a ');
            detalhe = rotuloPosicao(m._lf_ad_position) + (periodo ? ' · ' + periodo : '') + (m._lf_ad_active ? ' · Ativo' : ' · Inativo');
          }

          return (
            '<div class="lfed-linha-item">' +
            '<div><p class="lfed-item__titulo">' + esc(titulo) + '</p>' +
            '<div class="lfed-item__meta">' + esc(detalhe) + '</div></div>' +
            '<div><span class="lfed-etiqueta lfed-etiqueta--' + esc(p.status) + '">' + esc(ROTULO_STATUS_ITEM[p.status] || p.status) + '</span></div>' +
            '<div class="lfed-linha-item__oculto-mob lfed-item__meta">Atualizado em ' + esc(dataBr(p.modified_gmt + 'Z')) + '</div>' +
            '<div class="lfed-linha-item__oculto-mob">' +
            (tipo === 'evento' && p.status === 'publish' && p.link
              ? '<a href="' + esc(p.link) + '" target="_blank" rel="noopener">Ver no site</a>'
              : '<span class="lfed-item__meta">—</span>') +
            '</div>' +
            '<div><button class="lfed-btn lfed-btn--pequeno" data-acao="item-abrir" data-tipo="' + tipo + '" data-id="' + p.id + '">Abrir</button></div>' +
            '</div>'
          );
        })
        .join('');

      corpo =
        '<div class="lfed-linha-item lfed-linha-item--cabecalho">' +
        '<div>' + (tipo === 'evento' ? 'Evento' : 'Anúncio') + '</div><div>Status</div><div>Atualização</div><div>No site</div><div></div></div>' + corpo;
    }

    var paginacao =
      l.paginas > 1
        ? '<div class="lfed-paginacao">' +
          '<button class="lfed-btn lfed-btn--pequeno" data-acao="item-pagina" data-tipo="' + tipo + '" data-p="' + (l.pagina - 1) + '"' + (l.pagina <= 1 ? ' disabled' : '') + '>Anterior</button>' +
          '<span class="lfed-paginacao__pos">Página ' + l.pagina + ' de ' + l.paginas + ' · ' + l.total + '</span>' +
          '<button class="lfed-btn lfed-btn--pequeno" data-acao="item-pagina" data-tipo="' + tipo + '" data-p="' + (l.pagina + 1) + '"' + (l.pagina >= l.paginas ? ' disabled' : '') + '>Próxima</button>' +
          '</div>'
        : '';

    var nota =
      tipo === 'anuncio'
        ? '<p class="lfed-sub-tela">Um anúncio só aparece no site quando está <strong>publicado</strong>, marcado como <strong>ativo</strong>, dentro do período e com imagem de computador.</p>'
        : '<p class="lfed-sub-tela">Eventos publicados com data futura aparecem na agenda do portal.</p>';

    return (
      '<div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:4px">' +
      '<h1 class="lfed-titulo-tela" style="margin:0">' + esc(t.nome) + '</h1>' +
      '<button class="lfed-btn lfed-btn--principal" data-acao="item-novo" data-tipo="' + tipo + '">' + esc(t.novo) + '</button>' +
      '</div>' +
      nota +
      mensagem() +
      '<div class="lfed-tabela">' + corpo + '</div>' + paginacao
    );
  }

  /* ------------------------------------------------------------ abrir */

  function novoItem(tipo) {
    var it = itemVazio(tipo);
    var local = lerLocal(chaveLocalItem(tipo, 0));

    /*
     * Mesma regra das notícias: se havia uma criação pendente, a MESMA chave
     * volta, e o painel descobre o desfecho antes de deixar salvar.
     */
    if (local && local.chaveOperacao && local.dados) {
      it.chaveOperacao = local.chaveOperacao;
      it.titulo = local.dados.titulo || '';
      it.conteudo = local.dados.conteudo || '';
      it.meta = Object.assign(metaVazia(tipo), local.dados.meta || {});
      it.destacadaId = local.dados.destacadaId || 0;
      it.sujo = true;
      estado.item = it;
      estado.tela = 'item';
      estado.mensagem = { tipo: 'info', texto: 'Recuperei um cadastro não salvo deste navegador. Conferindo se ele já chegou ao site…' };
      pintar();
      carregarPreviasItem();
      resolverPendenteItem(it.chaveOperacao, 1);
      return;
    }

    it.chaveOperacao = novaChaveOperacao();
    estado.item = it;
    estado.tela = 'item';
    estado.mensagem = null;
    pintar();
  }

  function abrirItem(tipo, id) {
    estado.ocupado = true;
    pintar();

    api('licia-fabio-editorial/v1/noticias/' + id)
      .then(function (r) {
        var p = r.dados || {};
        var it = itemVazio(tipo);

        it.id = p.id;
        it.titulo = p.titulo || '';
        it.conteudo = p.conteudo || '';
        it.status = p.status;
        it.link = p.link || '';
        it.destacadaId = p.imagemDestaque || 0;
        it.meta = Object.assign(metaVazia(tipo), p.meta || {});

        // Guardada como recebida, sem transformação.
        it.versao = p.versao || '';

        estado.item = it;
        estado.tela = 'item';
        estado.ocupado = false;
        estado.mensagem = null;
        pintar();
        carregarPreviasItem();
      })
      .catch(function (e) {
        estado.ocupado = false;
        avisar('erro', e.message);
      });
  }

  function resolverPendenteItem(chave, tentativa) {
    estado.resolvendo = true;

    api('licia-fabio-editorial/v1/operacoes/' + encodeURIComponent(chave))
      .then(function (r) {
        var d = r.dados || {};
        var it = estado.item;

        estado.resolvendo = false;

        if (!it) {
          return;
        }

        if (d.situacao === 'criada') {
          it.id = d.id;
          it.versao = d.versao || '';
          it.status = d.status || it.status;
          avisar('info', 'O cadastro anterior já havia chegado ao site (ID ' + d.id + '). Nenhuma cópia será feita. O que está na tela ainda não está salvo: clique em salvar para gravar nele.');
          return;
        }

        if (d.situacao === 'em_andamento' && tentativa < 4) {
          window.setTimeout(function () {
            resolverPendenteItem(chave, tentativa + 1);
          }, 2500);
          return;
        }

        if (d.situacao === 'desconhecida' || d.expirada || d.situacao === 'em_andamento') {
          it.chaveBloqueada = true;
          avisar(
            'erro',
            'Não sei se o cadastro anterior chegou ao site. Não vou criar outro por conta própria: confira a lista. Se ele não existir, use “Cadastrar como novo”.',
            { ofereceNovaItem: true }
          );
          return;
        }

        estado.mensagem = null;
        pintar();
      })
      .catch(function (e) {
        estado.resolvendo = false;
        avisar('erro', 'Não consegui verificar o cadastro anterior (' + e.message + '). Confira a lista antes de salvar.');
      });
  }

  function carregarPreviasItem() {
    var it = estado.item;

    if (!it) {
      return;
    }

    var ids = {};

    if (it.tipo === 'evento' && it.destacadaId) {
      ids.destacada = it.destacadaId;
    }

    if (it.tipo === 'anuncio') {
      if (it.meta._lf_ad_desktop_image_id) {
        ids.desktop = it.meta._lf_ad_desktop_image_id;
      }

      if (it.meta._lf_ad_mobile_image_id) {
        ids.mobile = it.meta._lf_ad_mobile_image_id;
      }
    }

    Object.keys(ids).forEach(function (campo) {
      api('wp/v2/media/' + ids[campo], { params: { _fields: 'id,source_url,media_details' } })
        .then(function (r) {
          if (!estado.item || estado.item !== it) {
            return;
          }

          var m = r.dados || {};
          var tam = m.media_details && m.media_details.sizes;

          it.previas[campo] = (tam && tam.medium && tam.medium.source_url) || m.source_url || '';
          pintarPreviaItem(campo);
        })
        .catch(function () {
          /* imagem inacessível: a prévia fica vazia */
        });
    });
  }

  /** Atualiza só a miniatura, sem repintar e perder o que está sendo digitado. */
  function pintarPreviaItem(campo) {
    var caixa = raiz.querySelector('[data-previa="' + campo + '"]');
    var src = estado.item && estado.item.previas[campo];

    if (caixa) {
      caixa.innerHTML = src
        ? '<img src="' + esc(src) + '" alt="">'
        : '<div class="lfed-destacada__vazio">Nenhuma imagem</div>';
    }
  }

  /* ------------------------------------------------------------ editor */

  function campoTexto(rotulo, campo, valor, extra) {
    extra = extra || {};

    return (
      '<label class="lfed-campo"><span class="lfed-campo__rotulo">' + esc(rotulo) + '</span>' +
      '<input type="' + (extra.tipo || 'text') + '" data-campo="' + campo + '" value="' + esc(valor || '') + '"' +
      (extra.placeholder ? ' placeholder="' + esc(extra.placeholder) + '"' : '') + '>' +
      (extra.dica ? '<p class="lfed-campo__dica">' + esc(extra.dica) + '</p>' : '') +
      '</label>'
    );
  }

  function campoImagem(rotulo, campo, id, dica) {
    var bloqueado = !estado.usuario.caps.enviarArquivos;
    var src = estado.item.previas[campo];

    return (
      '<div class="lfed-campo"><span class="lfed-campo__rotulo">' + esc(rotulo) + '</span>' +
      '<div class="lfed-destacada__previa" data-previa="' + campo + '">' +
      (src ? '<img src="' + esc(src) + '" alt="">' : '<div class="lfed-destacada__vazio">' + (id ? 'Carregando…' : 'Nenhuma imagem') + '</div>') +
      '</div>' +
      (bloqueado
        ? '<p class="lfed-campo__dica">Sua conta não tem permissão para enviar imagens.</p>'
        : '<div style="display:flex;gap:8px;flex-wrap:wrap">' +
          '<button class="lfed-btn lfed-btn--pequeno" data-acao="item-img" data-alvo="' + campo + '">Enviar imagem</button>' +
          (id ? '<button class="lfed-btn lfed-btn--pequeno" data-acao="item-img-remover" data-alvo="' + campo + '">Remover</button>' : '') +
          '</div>') +
      (dica ? '<p class="lfed-campo__dica">' + esc(dica) + '</p>' : '') +
      '</div>'
    );
  }

  function telaItemEditor() {
    var it = estado.item;

    if (!it) {
      return '';
    }

    var t = TIPOS[it.tipo];
    var m = it.meta;
    var principal;
    var lateralCampos;

    if (it.tipo === 'evento') {
      principal =
        '<label class="lfed-campo"><span class="lfed-campo__rotulo">Nome do evento</span>' +
        '<input type="text" class="lfed-titulo-campo" data-campo="titulo" value="' + esc(it.titulo) + '" placeholder="Nome do evento"></label>' +
        '<label class="lfed-campo"><span class="lfed-campo__rotulo">Descrição</span>' +
        '<textarea data-campo="conteudo" rows="10" placeholder="Texto que aparece na página do evento">' + esc(it.conteudo) + '</textarea>' +
        '<p class="lfed-campo__dica">O texto é gravado como está. HTML de eventos antigos é preservado sem conversão.</p></label>' +
        '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:0 16px">' +
        campoTexto('Data', 'meta._lf_event_date', m._lf_event_date, { tipo: 'date' }) +
        campoTexto('Horário', 'meta._lf_event_time', m._lf_event_time, { tipo: 'time' }) +
        '</div>' +
        campoTexto('Local', 'meta._lf_event_location', m._lf_event_location, { placeholder: 'Ex.: Teatro Castro Alves' }) +
        campoTexto('Endereço', 'meta._lf_event_address', m._lf_event_address) +
        campoTexto('Link de ingressos ou informações', 'meta._lf_event_url', m._lf_event_url, { tipo: 'url', placeholder: 'https://' });

      lateralCampos = '<div class="lfed-cartao">' + campoImagem('Imagem destacada', 'destacada', it.destacadaId) + '</div>';
    } else {
      var posicoes = BOOT.posicoesAnuncio || [];

      var opcoes =
        '<option value="">Escolha a posição</option>' +
        posicoes
          .map(function (p) {
            return (
              '<option value="' + esc(p.chave) + '"' + (m._lf_ad_position === p.chave ? ' selected' : '') + '>' +
              esc(p.rotulo) + (p.formato ? ' · ' + esc(p.formato) : '') + '</option>'
            );
          })
          .join('');

      var formato = (posicoes.filter(function (p) {
        return p.chave === m._lf_ad_position;
      })[0] || {}).formato;

      principal =
        '<label class="lfed-campo"><span class="lfed-campo__rotulo">Nome interno do anúncio</span>' +
        '<input type="text" class="lfed-titulo-campo" data-campo="titulo" value="' + esc(it.titulo) + '" placeholder="Ex.: Restaurante X · setembro">' +
        '<p class="lfed-campo__dica">Só aparece aqui no painel, para identificar o anúncio.</p></label>' +
        '<label class="lfed-campo"><span class="lfed-campo__rotulo">Posição no site</span>' +
        '<select data-campo="meta._lf_ad_position">' + opcoes + '</select>' +
        (posicoes.length ? '' : '<p class="lfed-campo__dica">O plugin Core não informou as posições. Reative-o para cadastrar anúncios.</p>') +
        '</label>' +
        campoTexto('Link de destino', 'meta._lf_ad_url', m._lf_ad_url, { tipo: 'url', placeholder: 'https://' }) +
        campoTexto('Texto alternativo', 'meta._lf_ad_alt', m._lf_ad_alt, { dica: 'Descreva o anúncio para quem não vê a imagem.' }) +
        '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:0 16px">' +
        campoTexto('Início', 'meta._lf_ad_start_date', m._lf_ad_start_date, { tipo: 'date', dica: 'Opcional.' }) +
        campoTexto('Fim', 'meta._lf_ad_end_date', m._lf_ad_end_date, { tipo: 'date', dica: 'Opcional.' }) +
        '</div>' +
        '<label style="display:flex;gap:10px;align-items:center;min-height:44px;cursor:pointer">' +
        '<input type="checkbox" data-campo="meta._lf_ad_active"' + (m._lf_ad_active ? ' checked' : '') + ' style="width:20px;height:20px">' +
        '<span style="font-size:14.5px;font-weight:700">Anúncio ativo</span></label>' +
        '<label style="display:flex;gap:10px;align-items:center;min-height:44px;cursor:pointer;margin-bottom:12px">' +
        '<input type="checkbox" data-campo="meta._lf_ad_new_tab"' + (m._lf_ad_new_tab ? ' checked' : '') + ' style="width:20px;height:20px">' +
        '<span style="font-size:14.5px">Abrir o link em nova aba</span></label>';

      lateralCampos =
        '<div class="lfed-cartao">' +
        campoImagem('Imagem para computador', 'desktop', m._lf_ad_desktop_image_id, formato ? 'Formato de referência: ' + formato + '.' : 'Obrigatória para o anúncio aparecer.') +
        '</div>' +
        '<div class="lfed-cartao">' +
        campoImagem('Imagem para celular', 'mobile', m._lf_ad_mobile_image_id, 'Opcional. Sem ela, o celular usa a de computador.') +
        '</div>';
    }

    var acoes = [
      '<button class="lfed-btn" data-acao="item-salvar" data-st="draft"' + (it.salvando ? ' disabled' : '') + '>Salvar rascunho</button>',
      '<button class="lfed-btn" data-acao="item-salvar" data-st="pending"' + (it.salvando ? ' disabled' : '') + '>Enviar para revisão</button>',
    ];

    if (estado.usuario.caps.publicar) {
      acoes.push(
        '<button class="lfed-btn lfed-btn--principal" data-acao="item-salvar" data-st="publish"' + (it.salvando ? ' disabled' : '') + '>' +
        (it.status === 'publish' ? 'Atualizar publicado' : 'Publicar') + '</button>'
      );
    }

    return (
      '<div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:18px">' +
      '<button class="lfed-btn lfed-btn--pequeno" data-acao="item-voltar">← ' + esc(t.nome) + '</button>' +
      '<h1 class="lfed-titulo-tela" style="margin:0">' + (it.id ? 'Editar' : esc(t.novo)) + '</h1>' +
      (it.id ? '<span class="lfed-etiqueta lfed-etiqueta--' + esc(it.status) + '">' + esc(ROTULO_STATUS_ITEM[it.status] || it.status) + '</span>' : '') +
      (it.id ? '<span class="lfed-item__meta">ID ' + it.id + '</span>' : '') +
      '</div>' +
      mensagem() +
      (estado.usuario.caps.publicar
        ? ''
        : '<div class="lfed-aviso lfed-aviso--info"><span class="lfed-aviso__marca">Permissão</span><div>Sua conta não publica direto. Use “Enviar para revisão”.</div></div>') +
      '<div class="lfed-editor"><div>' + principal + '</div>' +
      '<aside class="lfed-editor__lateral">' + lateralCampos + '</aside></div>' +
      '<div class="lfed-barra-salvar">' +
      '<span class="lfed-barra-salvar__estado">' + (it.salvando ? 'Salvando…' : it.sujo ? 'Alterações não salvas' : it.id ? 'Tudo salvo' : 'Nada salvo ainda') + '</span>' +
      acoes.join('') +
      '</div>'
    );
  }

  /** Atualiza um campo do item a partir do formulário, sem repintar. */
  function aoEditarItem(el) {
    var it = estado.item;
    var campo = el.dataset.campo;

    if (!it || !campo) {
      return;
    }

    var valor = el.type === 'checkbox' ? el.checked : el.value;

    if (campo.indexOf('meta.') === 0) {
      it.meta[campo.slice(5)] = valor;
    } else {
      it[campo] = valor;
    }

    it.sujo = true;
    guardarItemLocal(it);

    var barra = raiz.querySelector('.lfed-barra-salvar__estado');

    if (barra) {
      barra.textContent = 'Alterações não salvas';
    }

    // A dica de formato do anúncio depende da posição: repinta só nesse caso.
    if (campo === 'meta._lf_ad_position') {
      pintar();
    }
  }

  /* ------------------------------------------------------------ salvar */

  function salvarItem(status, jaConvertido) {
    var it = estado.item;

    if (!it || it.salvando) {
      return;
    }

    if (estado.resolvendo) {
      avisar('info', 'Aguarde: ainda estou conferindo o cadastro anterior.');
      return;
    }

    if (!it.id && it.chaveBloqueada) {
      avisar('erro', 'Não vou salvar com este pedido: ele já foi usado e não sei o resultado. Confira a lista e, se o cadastro não existir, use “Cadastrar como novo”.', { ofereceNovaItem: true });
      return;
    }

    if (!String(it.titulo || '').trim()) {
      avisar('erro', it.tipo === 'evento' ? 'O evento precisa de um nome.' : 'O anúncio precisa de um nome interno.');
      return;
    }

    if (status === 'publish' && !estado.usuario.caps.publicar) {
      avisar('erro', 'Sua conta não tem permissão para publicar. Envie para revisão.');
      return;
    }

    // Validações de conveniência. O servidor valida de novo.
    var urlCampo = it.tipo === 'evento' ? it.meta._lf_event_url : it.meta._lf_ad_url;

    if (urlCampo && !/^https?:\/\//i.test(urlCampo)) {
      avisar('erro', 'O link precisa começar com http:// ou https://.');
      return;
    }

    if (it.tipo === 'anuncio') {
      if (it.meta._lf_ad_start_date && it.meta._lf_ad_end_date && it.meta._lf_ad_end_date < it.meta._lf_ad_start_date) {
        avisar('erro', 'A data de fim é anterior à de início.');
        return;
      }

      if (status === 'publish' && (!it.meta._lf_ad_position || !it.meta._lf_ad_desktop_image_id)) {
        avisar('erro', 'Para publicar, escolha a posição e envie a imagem para computador.');
        return;
      }
    }

    it.salvando = true;
    estado.mensagem = null;
    pintar();

    guardarItemLocal(it);

    var carga = {
      tipo: TIPOS[it.tipo].wp,
      title: it.titulo,
      status: status || it.status,
      meta: Object.assign({}, it.meta),
    };

    if (it.tipo === 'evento') {
      carga.content = it.conteudo;
      carga.featured_media = it.destacadaId || 0;
    } else {
      carga.meta._lf_ad_desktop_image_id = Number(carga.meta._lf_ad_desktop_image_id) || 0;
      carga.meta._lf_ad_mobile_image_id = Number(carga.meta._lf_ad_mobile_image_id) || 0;
      carga.meta._lf_ad_active = !!carga.meta._lf_ad_active;
      carga.meta._lf_ad_new_tab = !!carga.meta._lf_ad_new_tab;
    }

    // Congelado antes de sair: se o usuário digitar durante o envio, a tela
    // não é marcada como salva.
    var enviado = retratoItem(it);
    var chamada;

    if (it.id) {
      if (!it.versao) {
        it.salvando = false;
        avisar('erro', 'Não sei de qual versão você partiu, então não vou gravar às cegas. Volte à lista, reabra e reaplique.');
        return;
      }

      carga.versao = it.versao;
      chamada = api('licia-fabio-editorial/v1/noticias/' + it.id, { method: 'POST', json: carga, idempotente: true });
    } else {
      carga.chave_operacao = it.chaveOperacao || (it.chaveOperacao = novaChaveOperacao());
      chamada = api('licia-fabio-editorial/v1/noticias', { method: 'POST', json: carga, idempotente: true });
    }

    chamada
      .then(function (r) {
        var p = r.dados || {};
        var idAnterior = it.id;

        it.salvando = false;
        it.id = p.id || it.id;
        it.status = p.status || carga.status;
        it.link = p.link || '';
        it.versao = p.versao || '';

        var rotulo = ROTULO_STATUS_ITEM[it.status] || it.status;

        if (retratoItem(it) === enviado) {
          it.sujo = false;
          limparLocal(chaveLocalItem(it.tipo, 0));
          limparLocal(chaveLocalItem(it.tipo, idAnterior));
          limparLocal(chaveLocalItem(it.tipo, it.id));

          estado.mensagem = {
            tipo: 'ok',
            texto: (p.repetida ? 'Este cadastro já havia chegado ao site — nenhuma cópia foi criada. ' : 'Salvo no WordPress. ') + 'ID ' + it.id + ' · ' + rotulo + '.',
            link: it.tipo === 'evento' && it.status === 'publish' ? it.link : '',
          };
        } else {
          it.sujo = true;
          guardarItemLocal(it);
          estado.mensagem = {
            tipo: 'info',
            texto: 'Gravei a versão enviada (ID ' + it.id + ' · ' + rotulo + '). Você alterou depois disso, e essas mudanças ainda NÃO estão salvas.',
          };
        }

        pintar();
      })
      .catch(function (e) {
        it.salvando = false;

        var d = e.dadosConflito || {};

        // Criação que já havia chegado: vira atualização, uma única vez.
        if ((e.codigo === 'lf_ed_criacao_recuperada' || e.codigo === 'lf_ed_operacao_expirada') && d.post_id && !jaConvertido) {
          it.id = d.post_id;
          it.versao = d.versao || '';
          it.status = d.status_post || it.status;

          avisar('info', 'Este cadastro já havia chegado ao site (ID ' + d.post_id + '). Nenhuma cópia foi feita. Gravando suas alterações nele agora.');

          window.setTimeout(function () {
            salvarItem(status, true);
          }, 300);
          return;
        }

        if (e.codigo === 'lf_ed_operacao_desconhecida') {
          it.chaveBloqueada = true;
          avisar('erro', e.message, { ofereceNovaItem: true });
          return;
        }

        if (e.conflito) {
          var quem = d.modificada_por;
          avisar('erro', e.message + (quem ? ' A última alteração foi de ' + quem + '.' : '') + ' O que você digitou continua na tela e neste navegador. Reabra em outra aba para comparar — nada é sobrescrito automaticamente.');
          return;
        }

        if (e.sessao) {
          avisar('erro', e.message, { loginUrl: e.loginUrl || '' });
          return;
        }

        if (e.nonceRenovado) {
          avisar('info', e.message);
          return;
        }

        avisar('erro', e.message + (e.rede ? ' Tente de novo em instantes.' : ''));
      });
  }

  /* ------------------------------------------------------------ ações */

  function acaoItem(nome, alvo) {
    var it = estado.item;

    if (nome === 'item-novo') {
      if (!sairDoEditor()) {
        return true;
      }

      estado.menuAberto = false;
      novoItem(alvo.dataset.tipo);
      return true;
    }

    if (nome === 'item-abrir') {
      if (!sairDoEditor()) {
        return true;
      }

      abrirItem(alvo.dataset.tipo, Number(alvo.dataset.id));
      return true;
    }

    if (nome === 'item-pagina') {
      carregarItens(alvo.dataset.tipo, Number(alvo.dataset.p));
      return true;
    }

    if (nome === 'item-voltar') {
      if (!sairDoEditor()) {
        return true;
      }

      var tipo = it ? it.tipo : 'evento';

      estado.item = null;
      estado.tela = TIPOS[tipo].tela;
      estado.mensagem = null;
      carregarItens(tipo, 1);
      return true;
    }

    if (nome === 'item-salvar') {
      salvarItem(alvo.dataset.st);
      return true;
    }

    if (nome === 'item-img') {
      var alvoImg = alvo.dataset.alvo;

      escolherArquivo(function (arquivo) {
        enviarArquivo(arquivo, function (midia) {
          if (!estado.item || estado.item !== it) {
            return;
          }

          if (alvoImg === 'destacada') {
            it.destacadaId = midia.id;
          } else if (alvoImg === 'desktop') {
            it.meta._lf_ad_desktop_image_id = midia.id;
          } else {
            it.meta._lf_ad_mobile_image_id = midia.id;
          }

          var tam = midia.media_details && midia.media_details.sizes;
          it.previas[alvoImg] = (tam && tam.medium && tam.medium.source_url) || midia.source_url || '';
          it.sujo = true;
          guardarItemLocal(it);

          avisar('ok', 'Imagem enviada para a biblioteca do WordPress (ID ' + midia.id + '). Salve para vinculá-la.');
        });
      });
      return true;
    }

    if (nome === 'item-img-remover') {
      var c = alvo.dataset.alvo;

      if (c === 'destacada') {
        it.destacadaId = 0;
      } else if (c === 'desktop') {
        it.meta._lf_ad_desktop_image_id = 0;
      } else {
        it.meta._lf_ad_mobile_image_id = 0;
      }

      it.previas[c] = '';
      it.sujo = true;
      guardarItemLocal(it);
      pintar();
      return true;
    }

    if (nome === 'nova-chave-item') {
      if (!window.confirm('Você conferiu na lista que este cadastro ainda não existe? Se já existir, isto vai criar uma cópia.')) {
        return true;
      }

      it.chaveOperacao = novaChaveOperacao();
      it.chaveBloqueada = false;
      guardarItemLocal(it);
      avisar('info', 'Pode salvar: este passou a ser um cadastro novo.');
      return true;
    }

    return false;
  }

  function escolherArquivo(aoEscolher) {
    var entrada = document.createElement('input');

    entrada.type = 'file';
    entrada.accept = 'image/*';

    entrada.addEventListener('change', function () {
      if (entrada.files && entrada.files[0]) {
        aoEscolher(entrada.files[0]);
      }
    });

    entrada.click();
  }

  function inserirImagemNoCorpo(midia) {
    var legenda = window.prompt('Legenda da imagem (deixe vazio se não houver)') || '';
    var alt = window.prompt('Texto alternativo — descreva a imagem para quem não a vê') || '';
    var credito = BOOT.coreAtivo ? window.prompt('Crédito da foto (nome do fotógrafo ou agência)') || '' : '';

    salvarDadosDaImagem(midia.id, { alt: alt, legenda: legenda, credito: credito })
      .then(function () {
        var tam = midia.media_details && midia.media_details.sizes;
        var origem = (tam && tam.large && tam.large.source_url) || midia.source_url;

        var rodape = legenda;

        if (credito) {
          rodape = rodape ? rodape + ' · Foto: ' + credito : 'Foto: ' + credito;
        }

        var html =
          '<figure class="wp-caption alignnone">' +
          '<img src="' + esc(origem) + '" alt="' + esc(alt) + '" class="wp-image-' + midia.id + '" />' +
          (rodape ? '<figcaption class="wp-caption-text">' + esc(rodape) + '</figcaption>' : '') +
          '</figure>';

        estado.editor.corpo += html;
        estado.editor.sujo = true;

        pintar();
        avisar('ok', 'Imagem inserida no corpo e salva na biblioteca. ID ' + midia.id + '.');
      })
      .catch(function (e) {
        avisar('erro', e.message);
      });
  }

  function carregarGaleria() {
    var caixa = document.getElementById('lfed-galeria-caixa');

    if (!caixa) {
      return;
    }

    api('wp/v2/media', {
      params: { per_page: 40, media_type: 'image', _fields: 'id,source_url,alt_text,media_details' },
    })
      .then(function (r) {
        var itens = r.dados || [];

        if (!itens.length) {
          caixa.innerHTML = '<div class="lfed-vazio"><p class="lfed-vazio__t">Nenhuma imagem</p><p>Envie a primeira pelo editor de uma notícia.</p></div>';
          return;
        }

        caixa.innerHTML =
          '<div class="lfed-galeria">' +
          itens
            .map(function (m) {
              var tam = m.media_details && m.media_details.sizes;
              var mini = (tam && tam.thumbnail && tam.thumbnail.source_url) || m.source_url;

              return (
                '<button class="lfed-galeria__item" data-acao="usar-imagem" data-midia="' + m.id + '">' +
                '<img src="' + esc(mini) + '" alt="' + esc(m.alt_text || '') + '" loading="lazy"></button>'
              );
            })
            .join('') +
          '</div>';
      })
      .catch(function (e) {
        caixa.innerHTML = '<div class="lfed-vazio">' + esc(e.message) + '</div>';
      });
  }

  /* ================================================================= início */
  faxinaLocal();
  pintar();
  carregarResumo();
})();
