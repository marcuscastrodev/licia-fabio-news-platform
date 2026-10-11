# Lícia Fábio — News Portal & Editorial Management System

A custom WordPress news platform consisting of a public-facing theme, an editorial management plugin, and a core content-management plugin.

**Status:** Official project source versions supplied by the developer. The plugins retain their `staging` version identifiers; production readiness and end-to-end installation have not yet been independently verified.

## Architecture

| Component | Purpose | Source |
| --- | --- | --- |
| WordPress theme | Public news portal, article pages, categories, search and responsive layout | `wordpress-theme/` |
| Editorial management plugin | Editorial administration UI, publishing workflows and REST integrations | `editorial-plugin/` |
| Core plugin | Content models, custom fields, events, advertising, featured content and REST endpoints | `core-plugin/` |

All three components are designed to work within WordPress; the theme and editorial plugin rely on the data and functionality provided by the Core plugin.

## Technologies

PHP · WordPress · HTML · CSS · JavaScript · WordPress REST API

## Highlights

- Modular separation of presentation, editorial workflows and content logic
- Custom WordPress templates and reusable components
- Editorial administration and API integration
- Content structures for events, advertising and featured stories
- Responsive news-site experience

## Repository layout

```text
wordpress-theme/
editorial-plugin/
core-plugin/
docs/
README.md
.gitignore
```

## Version notes

- Editorial plugin: `1.0.5-staging`
- Core plugin: `1.0.3-staging` (README corrected to match the main PHP plugin header)
- Theme: supplied official source version

## Local development

A working WordPress installation is required. Install and activate the Core plugin, the editorial plugin and the theme in an isolated development environment. Confirm compatible WordPress/PHP versions and configuration before use.

> Do not deploy directly to a production site without reviewing dependencies, database changes, permissions and security. No database export, user data or live credentials should be committed.

## Portfolio context

Built for the **Lícia Fábio** editorial/news website. This project demonstrates the design and development of both the reader-facing experience and the tools supporting the publishing workflow.

**Developer:** [Marcos Castro](https://github.com/marcuscastrodev)

## Current repository status

The documentation is initialized. Source code will be added after packaging and final review.
