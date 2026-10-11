# Lícia Fábio | News Portal & Editorial Platform

**A custom WordPress news experience and an integrated editorial workspace.**

Developed as a modular publishing platform, this project brings together a reader-facing news portal, a dedicated newsroom interface, and a reusable content layer. Rather than placing business logic in the theme, the architecture keeps editorial data and workflows in WordPress plugins.

**Live website:** [liciafabio.com.br](https://liciafabio.com.br/) · **Developer:** [Marcos Castro](https://github.com/marcuscastrodev)

> **Release note:** The source versions in this repository are labeled `staging` by their original authors. They are official project versions, but this repository does not claim that the exact commits have passed a full production deployment or security audit.

## What I built

### 1. Reader-facing news portal
A custom WordPress theme focused on publishing and browsing editorial content.

- Homepage with featured stories and reusable news cards
- Article, category, archive, search, event and error-page templates
- Responsive navigation, image galleries and a dedicated TV Lícia page
- Advertising placements, event listings and related-content components
- Presentation separated from persistent editorial data

**Explore the code:** [WordPress theme](./wordpress-theme/)

### 2. Custom editorial workspace
A separate newsroom interface available at `/redacao/` within the same WordPress installation, designed to let editors work without navigating the standard WordPress admin screens.

- Responsive sidebar and mobile drawer with keyboard and focus handling
- Create and edit news, events and advertising content
- Native WordPress authentication and capability-based permissions
- WordPress REST API integration and media management
- Revision checks, per-item locking and conflict responses for concurrent edits
- Draft-recovery behavior for interrupted editing sessions

**Important limitation:** Concurrent writes are protected between editorial-panel sessions. External edits through wp-admin or other integrations can still overwrite panel changes; the plugin documentation explains this distinction.

**Explore the code:** [Editorial plugin](./editorial-plugin/)

### 3. Reusable editorial core
A dedicated plugin registers and manages the data used by the theme and editorial workspace.

- Custom post types for events (`lf_evento`) and advertisements (`lf_anuncio`)
- Post metadata for subtitles, homepage features and image credits
- Helpers for featured articles, popular stories, upcoming events and ad slots
- WordPress REST API fields with schema and permission handling
- Compatibility-minded separation of content from visual presentation

**Explore the code:** [Core plugin](./core-plugin/)

## System architecture

```text
                     WordPress
                         |
             +-----------+-----------+
             |                       |
     Public-facing site       Editorial workspace
      Custom WP theme          Custom WP plugin
             |                       |
             +-----------+-----------+
                         |
                  Editorial Core
                  WordPress plugin
                         |
            Posts, metadata, events,
             advertisements and media
```

The editorial workspace uses WordPress sessions, roles and capabilities. The Core plugin defines shared content structures, while the theme is responsible for rendering the public experience.

## Tech stack

**Backend:** PHP, WordPress, WordPress REST API  
**Frontend:** HTML, CSS, JavaScript, responsive layouts  
**Content:** Custom post types, post metadata, media, template parts  
**Integration:** Native WordPress authentication and permissions

## Repository structure

```text
.
├── wordpress-theme/       # Public site templates, styles and scripts
├── editorial-plugin/      # Newsroom interface and editorial workflows
├── core-plugin/           # Shared content models and API integration
├── .gitignore
└── README.md
```

## Official source versions

| Component | Version |
| --- | --- |
| WordPress theme | `1.0.5-staging` |
| Editorial plugin | `1.0.5-staging` |
| Core plugin | `1.0.3-staging` |

These are the official source versions provided for this portfolio repository. Version identifiers are preserved rather than relabeled as production releases.

## Running locally

Use an **isolated WordPress development or staging environment**. The theme and plugins require WordPress **5.9+** and PHP **7.4+** according to the Core and theme headers; confirm compatibility with your own installation and plugin dependencies.

1. Copy `core-plugin/` to `wp-content/plugins/licia-fabio-core/`.
2. Copy `editorial-plugin/` to `wp-content/plugins/licia-fabio-editorial/`.
3. Copy `wordpress-theme/` to `wp-content/themes/licia-fabio/`.
4. Activate **Lícia Fábio Core** first, then the editorial plugin, and finally the theme.
5. Review the Core settings under **Portal**, configure editorial sections, and refresh permalinks if necessary.
6. Validate permissions, REST endpoints, templates, event and ad content, and publishing workflows before considering any deployment.

**Do not install these staging versions on a production site without completing testing and a security review.** This repository does not include a WordPress database, media library, credentials or a preconfigured environment.

## Engineering decisions

**Separation of concerns.** Editorial fields, event and ad records, and reusable data helpers live in a plugin instead of the theme. Theme replacement does not inherently delete those stored records.

**Native security model.** The editorial workspace relies on WordPress login sessions and capability checks rather than introducing a separate password system.

**Explicit conflict behavior.** Editorial-panel edits track revisions and can reject conflicting saves instead of silently overwriting another panel session. The protections do not cover every external WordPress editing path.

**Compatibility first.** The Core documentation describes safeguards intended to preserve legacy metadata and avoid destructive changes during migration.

## Further reading

- [Core architecture and configuration](./core-plugin/README.md)
- [Editorial interface, API and concurrency behavior](./editorial-plugin/README.md)
- [Theme implementation](./wordpress-theme/)

---

**Built by [Marcos Castro](https://github.com/marcuscastrodev)** · Web Development & Digital Experiences · Valencia, Spain
