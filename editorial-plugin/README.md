# Painel Editorial — Lícia Fábio

`1.0.5-staging` · **Não instalar em produção.**

Painel de redação próprio, servido em `/redacao/` da **mesma** instalação do
WordPress. A equipe escreve e publica por aqui, sem abrir o wp-admin.

---

## Navegação

O menu principal fica numa **barra lateral fixa**, como na proposta original.
No desktop ela é permanente (248px, `sticky` com altura de viewport) e a área
de trabalho ocupa o resto da tela. Abaixo de 900px a mesma lateral vira
**drawer**: sai da tela, e uma barra estreita com o botão **Menu** aparece.

O drawer fecha pelo botão, pelo X, pela tecla **Escape**, por clique fora e ao
navegar. Enquanto aberto, trava a rolagem do fundo e mantém a tabulação dentro
de si; ao abrir, o foco vai para a navegação, e ao fechar volta ao botão que a
abriu. O botão carrega `aria-expanded`, `aria-controls` e nome acessível.

A navegação **não é duplicada** no topo — a barra estreita traz só o botão, o
nome da tela e a ação de criar. Duplicar faria leitor de tela anunciar tudo
duas vezes.

## Agenda e Publicidade

As duas abas estão ativas desde a 1.0.5. Cadastram e editam eventos
(`lf_evento`) e anúncios (`lf_anuncio`), os tipos registrados pelo plugin
Core.

**Mesma gravação das notícias.** Criação por `POST /noticias` com
`tipo` e chave de operação; atualização por `POST /noticias/{id}` com versão
e trava por item. Eventos e anúncios herdam idempotência, lápides, guarda de
versão e 409 — não há uma segunda implementação. O contador de revisão
(`_lf_ed_rev`) passou a valer para os três tipos.

**Campos**, conferidos no Core (`includes/post-meta.php`):

| Tipo | Campos gravados pelo painel |
| --- | --- |
| Evento | título, descrição, imagem destacada, `_lf_event_date`, `_lf_event_time`, `_lf_event_location`, `_lf_event_address`, `_lf_event_url` |
| Anúncio | nome interno, `_lf_ad_position`, `_lf_ad_desktop_image_id`, `_lf_ad_mobile_image_id`, `_lf_ad_url`, `_lf_ad_alt`, `_lf_ad_start_date`, `_lf_ad_end_date`, `_lf_ad_active`, `_lf_ad_new_tab` |

Lista fechada por tipo, no servidor. Nenhum campo é redeclarado; a
sanitização continua sendo a do Core. A posição do anúncio é conferida contra
`lf_ad_positions()` antes de gravar — o Core aceita qualquer texto ali, e uma
posição inexistente faria o anúncio nunca aparecer, em silêncio.

**Permissões.** As do WordPress, porque o Core registra os dois tipos com
`capability_type: post`. Quem escreve notícia cadastra evento e anúncio; quem
publica notícia publica os dois. Ver a observação sobre isso no relatório.

## Como a autenticação funciona

Não há token, senha ou segredo no navegador. A identidade é a sessão nativa do
WordPress:

1. quem não está autenticado cai no **login nativo** (`wp-login.php`), e
   volta ao painel depois de entrar — `auth_redirect()` cuida do retorno;
2. quem está autenticado **sem** `edit_posts` recebe uma tela de acesso
   negado. Nenhuma permissão é concedida para compensar;
3. quem tem `edit_posts` recebe o painel.

As chamadas à API usam o **cookie de sessão** do próprio usuário mais o
**nonce** da REST API, entregue pelo servidor no carregamento da página. O
nonce não é credencial: só vale junto do cookie daquele usuário, tem vida
curta e não serve em outra origem.

Recuperação de senha e logout são os fluxos nativos. Nada é reimplementado.

**Nada de credencial em localStorage.** O único dado persistido no navegador é
o rascunho de recuperação — o **texto** que o redator digitou, para não se
perder em queda de rede. Ele é apagado quando o salvamento confirma.

## Permissões

O painel **nunca decide** se uma operação é permitida. Ele mostra ou esconde
controles a partir das capabilities, e o WordPress recusa o que não é
permitido — inclusive quando a API é chamada direto, sem passar pela
interface.

| Capability | O que muda no painel |
| --- | --- |
| `edit_posts` | Entrar. Sem ela, acesso negado |
| `publish_posts` | O botão "Publicar agora". Sem ela, só rascunho e "Enviar para revisão" |
| `edit_others_posts` | Ver e abrir notícias de outros autores. Sem ela, a listagem traz só as próprias |
| `upload_files` | Enviar imagens. Sem ela, os botões saem e o painel diz por quê |

Escondê-los é conveniência, não segurança: a verificação real é a do
WordPress, a cada requisição.

## Endpoints

### Escrita — rotas **nativas**

Não existe endpoint de escrita próprio. É o que garante que o schema, a
sanitização e a permissão aplicados sejam os do WordPress e os do plugin Core.

| Operação | Rota |
| --- | --- |
| Listar e buscar notícias | `GET /wp/v2/posts` |
| Abrir para edição | `GET /wp/v2/posts/{id}?context=edit` |
| Criar | `POST /wp/v2/posts` |
| Atualizar | `POST /wp/v2/posts/{id}` |
| Enviar imagem | `POST /wp/v2/media` |
| Alt, legenda e crédito | `POST /wp/v2/media/{id}` |

### Concorrência: o que está protegido e o que não está

Isto merece precisão, porque a resposta é parcial.

O plugin mantém um **contador de revisão** por notícia (`_lf_ed_rev`),
incrementado no hook `save_post`. Esse hook dispara em **todos** os caminhos
de gravação — wp-admin, REST nativa, o endpoint do painel, WP-CLI, outros
plugins. A versão comparada é `{contador}:{modified_gmt}`, e o contador
resolve o caso que o timestamp não resolve: duas gravações no **mesmo
segundo**.

| Caminho de gravação | O painel detecta a alteração? | O caminho é impedido de sobrescrever o painel? |
| --- | --- | --- |
| Painel → painel (outra aba) | Sim | **Sim.** Trava por post; a segunda recebe 409 |
| wp-admin → painel | **Sim.** O contador subiu | **Não.** O wp-admin grava sem consultar nada |
| REST nativa → painel | **Sim** | **Não** |
| Outro plugin, WP-CLI | **Sim** | **Não** |

**O que ficou completo:** duas abas do painel não se sobrescrevem. A
comparação e a gravação acontecem dentro de uma trava por post adquirida com
`add_option()` — atômico —, então uma requisição do painel não entra enquanto
a outra não termina. Versão ausente, vazia ou malformada é **recusada** com
400.

**O que não ficou completo:** o painel nunca sobrescreve o wp-admin, porque
detecta o contador maior e recusa. Mas o wp-admin **pode** sobrescrever o
painel, porque ele grava sem carregar uma versão-base para comparar. A
assimetria é real e não deve ser apresentada à equipe como resolvida.

**Por que não fechei isso, e o que seria preciso.** Fechar exigiria um guarda
em `wp_insert_post_data` que recusasse **qualquer** gravação sem
versão-base — o que quebraria o editor do wp-admin, já que o formulário dele
não envia esse campo. Para não quebrar, seria preciso injetar o campo no
formulário do Editor Clássico e no editor de blocos, ou seja, **alterar o
comportamento do wp-admin** para todos os usuários do site.

Isso está fora do que foi autorizado, e é uma mudança com risco real de
atrapalhar quem já usa o wp-admin hoje. **Não implementei, e preciso de
autorização explícita para implementar.** Se você autorizar, entrego como uma
opção desligável no painel, testável no staging antes de valer.

Também não sei ainda se o banco é MySQL com InnoDB nesta hospedagem, o que
determina se uma alternativa mais forte — uma trava de linha transacional —
é viável. É informação do servidor que eu não tenho.

### Escrita própria — apenas para idempotência e concorrência

Dois endpoints, autorizados na 1.0.1 por um motivo que as rotas nativas não
resolvem. A gravação em si é delegada à rota nativa por `rest_do_request()`,
então permissão, schema, sanitização e os campos do Core continuam sendo
aplicados pelo WordPress.

| Rota | Para quê |
| --- | --- |
| `POST /noticias` | Criação idempotente. Exige `chave_operacao` e compara a **impressão do payload**: repetição com texto diferente devolve 409 em vez de falso sucesso |
| `POST /noticias/{id}` | Atualização com versão obrigatória, comparada dentro de uma trava por post. **409** em conflito, **400** sem versão válida |
| `GET /operacoes/{chave}` | Consulta o desfecho de uma criação pendente. Somente leitura; não cria nada |

### Leitura de edição

| Rota | Devolve |
| --- | --- |
| `GET /noticias/{id}` | Conteúdo, subtítulo, categorias, imagem **e a versão canônica**, na mesma resposta autenticada |

A versão precisa vir daqui porque o formato exigido é `{contador}:{data}`, e
o contador é um meta interno que a rota nativa não expõe. O painel guarda a
string exatamente como recebida e a reenvia sem tocar — montar a versão no
navegador equivaleria a não ter versão.

### Chaves de operação vencidas

Quando o registro de uma operação vence, fica uma **lápide**: um registro
mínimo com o id do post, quando conhecido, guardado por 90 dias.

Sem ela a ausência de registro seria lida como "esta operação nunca
aconteceu", e recuperar um rascunho antigo criaria uma segunda notícia. A
recusa acontece no **endpoint de criação**, não na interface: repetir a
requisição à mão não contorna. Quando o post é identificável, o painel
converte a operação em atualização dele; quando não é, só uma ação explícita
do redator — depois de conferir a lista — libera uma chave nova.

### Leitura — namespace `licia-fabio-editorial/v1`

| Rota | Devolve |
| --- | --- |
| `GET /resumo` | Contagem por status, respeitando a permissão de quem pergunta |
| `GET /credito/{id}` | Crédito, legenda e alt de uma imagem |

### Renovação de nonce — fora do pipeline REST

`GET /redacao/?lf_renovar=1` devolve JSON com um nonce novo.

Não é um endpoint REST de propósito: quando o nonce vence,
`rest_cookie_check_errors()` rejeita a requisição REST inteira, então um
endpoint REST para buscar nonce seria rejeitado pelo mesmo mecanismo — era o
defeito da 1.0.0. Esta rota é uma página comum, autenticada só pelo cookie de
sessão, que não vence junto com o nonce.

Responde 401 quando a sessão terminou e 200 quando era só o nonce — é assim
que os dois casos passam a ser distinguíveis.

## Campos reutilizados do plugin Core

Conferidos no código do `licia-fabio-core 1.0.3-staging`, não deduzidos da
documentação:

| Campo | Meta | Onde | Origem |
| --- | --- | --- | --- |
| Subtítulo | `_lf_subtitulo` | `post` | `includes/post-meta.php` |
| Crédito da foto | `_lf_image_credit` | `attachment` | `includes/media.php` |

Os dois são registrados pelo Core com `register_post_meta`, com
`show_in_rest`, schema, sanitização e `auth_callback`. **Nenhum é
redeclarado aqui** — dois donos para o mesmo dado é fonte de defeito. O painel
escreve neles pelas rotas nativas.

Com o Core inativo, esses dois campos ficam **indisponíveis** no painel, com
aviso. Nada é gravado em campo alternativo. Título, corpo, categoria e imagem
destacada continuam funcionando.

## Sobre o corpo da notícia

O acervo tem HTML clássico com estilos embutidos, e a orientação foi
preservá-lo. Então:

- o HTML sai do WordPress e volta **como está**. Nada é convertido, nem em
  blocos, nem em marcação "limpa";
- o botão **Editar o HTML** mostra o código cru, para quando o editor visual
  não alcançar;
- as ferramentas são as mínimas: parágrafo, dois níveis de título, negrito,
  itálico, listas, link e imagem;
- **colagem** é convertida em texto simples com parágrafos. Colar do Word ou
  do Google Docs traria estilos que o portal não usa — e converter em silêncio
  é pior que pedir a formatação de novo.

## Integridade

- **Sucesso só depois da confirmação.** A mensagem de salvo aparece com o
  **ID**, o **status** e o **link real** — e só quando o WordPress responde.
- **Sem sobrescrever edição alheia.** Antes de gravar, o painel compara o
  `modified_gmt` com o do servidor. Se alguém salvou no meio, nada é gravado
  e o redator é avisado.
- **Sem post duplicado.** Trava de reentrada no botão, e o id volta ao estado
  depois do primeiro salvamento — o segundo clique atualiza, não cria.
- **Sem perder texto.** Falha de rede, validação ou sessão mantém o conteúdo
  na tela, com cópia no navegador.
- **Aviso ao sair** com alterações não salvas.
- **Nenhuma exclusão definitiva** nesta entrega.
- **Sem cache compartilhado:** `nocache_headers()`, `no-store`,
  `X-Robots-Tag: noindex` e as constantes `DONOTCACHEPAGE`/`OBJECT`/`DB`.

## O que este plugin não faz

- Não modifica o tema nem o plugin Core.
- Não cria usuário, não altera papel, não amplia permissão.
- Não bloqueia o wp-admin.
- Não cria página, categoria ou conteúdo.
- Não sobrescreve conteúdo existente na rota — avisa o conflito.
- Não carrega CSS ou JavaScript fora do painel.
- Não carrega o tema do portal dentro do painel.
- Não faz flush de rewrite por requisição: só na ativação e quando a versão
  muda.
- Não usa caminho absoluto fixo. Tudo sai de `home_url()`,
  `rest_url()` e `plugins_url()`, então a instalação em `/staging/`
  funciona sem ajuste.
- Não escreve em `.htaccess` nem em `wp-config.php`.
- Não usa backend externo, Netlify, token ou Application Password.

## Instalação no staging

1. Plugins › Adicionar novo › **Enviar plugin** → `licia-fabio-editorial.zip`.
2. **Ativar**. A regra de URL é criada na ativação.
3. Abrir `https://www.liciafabio.com.br/staging/redacao/`.

Se der 404, salve Configurações › **Links permanentes** uma vez.

Mantenha o **Lícia Fábio Core** ativo: o subtítulo e o crédito são dele.

Não há tela de configuração e não há nada para configurar.

## Build

**Não há compilação.** O JavaScript e o CSS que você lê são os que o navegador
executa: sem bundler, sem `node_modules`, sem etapa de build. Foi decisão de
manutenção — o painel é editável por quem for mexer nele daqui a dois anos.

## Rollback

Plugins › **Desativar**.

O painel deixa de responder em `/redacao/` e as regras de URL são limpas. É
tudo o que acontece: **nenhuma notícia, imagem, categoria, campo do Core ou
configuração é apagada.** As notícias escritas pelo painel são posts normais do
WordPress e continuam lá, editáveis pelo wp-admin.

Se o painel der erro e você precisar do site de volta imediatamente: renomeie
a pasta `wp-content/plugins/licia-fabio-editorial` por FTP. O WordPress
desativa o plugin sozinho.

## Requisitos

PHP 7.4+ · WordPress 5.9+ · HTTPS · REST API acessível em `/wp-json/` ·
`licia-fabio-core` ativo (para subtítulo e crédito)
