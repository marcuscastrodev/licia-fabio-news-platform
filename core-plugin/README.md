# Lícia Fábio Core

> **Versão para validação em staging** (`1.0.3-staging`). Não instalar em
> produção antes de concluir o `CHECKLIST-TESTES.md`.

Plugin de estrutura editorial do portal Lícia Fábio.

Este plugin guarda os **dados**. O tema cuida da **aparência**. A separação é
deliberada: trocar, atualizar ou desativar o tema não apaga nem torna
inacessível nenhum subtítulo, destaque, anúncio ou evento.

## O que o plugin registra

| Item | Onde | Chave |
| --- | --- | --- |
| Subtítulo da matéria | Notícias | `_lf_subtitulo` |
| Destaque na home | Notícias | `_lf_home_featured`, `_lf_home_featured_position` |
| Anúncios | Tipo de conteúdo | `lf_anuncio` |
| Agenda de eventos | Tipo de conteúdo | `lf_evento` |
| Visualizações | Notícias | `post_views_count` — lido sempre; **incrementado** apenas quando o administrador liga o contador próprio, que nasce desligado. `Views` é lido como legado, nunca escrito |
| Crédito da foto | Anexos de mídia | `_lf_image_credit` |

Todos os campos estão na REST API, com schema, sanitização e verificação de
permissão por post.

## Funções reaproveitáveis

```php
lf_get_home_featured();                          // 3 destaques, manual + fallback
lf_query_most_read( 5, 7 );                      // mais lidas, ordenado numericamente
lf_get_post_views( $post_id );                   // visualizações, sem inventar número
lf_render_ad_position( 'home_billboard_top' );    // imprime a posição publicitária
lf_has_ad( 'home_billboard_top' );               // há algo para exibir?
lf_query_upcoming_events( 4 );                   // próximos eventos, por data do evento
lf_get_section_category( 'galeria' );            // categoria pelo slug configurado
lf_get_image_credit( $attachment_id );           // crédito da foto, vazio se não houver
```

## O que este plugin não faz

- Não executa migração ao ativar.
- Não apaga nada ao desativar: reativar devolve tudo.
- Não apaga nada ao desinstalar, salvo ação administrativa explícita (ver
  `uninstall.php`) — e, mesmo nela, conteúdo nunca é removido por código.
- Não desativa, renomeia nem exclui temas ou plugins. O Newspaper permanece
  instalado como rota de reversão.
- Não apaga nenhum campo antigo, incluindo `td_post_theme_settings`,
  `classic-editor-remember` e os metadados de visualizações do plugin atual.
- Não cria nem renomeia categorias.
- Não altera slugs, datas, autores ou IDs.
- Não desativa nem remove Newspaper, tagDiv, Elementor ou MetaSlider.
- Não cria endpoint REST público para ação administrativa.
- Não toca em `.htaccess` nem em `wp-config.php`.

## Configuração

Painel › **Portal**. Três grupos: editorias (relação seção ↔ categoria por
slug), visualizações (contador próprio e período do bloco) e transição do
MetaSlider.

## Ordem de instalação

Ative **este plugin primeiro**, depois o tema. O tema verifica a presença do
plugin e avisa no painel se ele estiver faltando.
