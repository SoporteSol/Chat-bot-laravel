---
name: code-graph
description: >-
  Use this skill to understand the project architecture via its Codebase Knowledge Graph
  (routes, controllers, views, models, configs, and external API edges) before searching or
  reading raw files. Always consult references/project-graph.md first for impact analysis and symbol lookup.
---

# Code Graph Navigation & Impact Analysis Skill

## Core Principle: Graph First, File Second
Never rely on blind file-reading or brute-force searches as a first resort when exploring the codebase or planning changes. Query the project's **Codebase Knowledge Graph** ([project-graph.md](./references/project-graph.md)) first to trace routes, controllers, views, models, and external integrations.

## Workflow Instructions

### 1. Architecture & Dependency Lookup
1. Read [references/project-graph.md](./references/project-graph.md) to locate the exact nodes (Routes, Controllers, Views, Models, Configs) and directed edges (`ROUTED_TO`, `RENDERS`, `EXTENDS`, `CALLS_API`, `AUTHENTICATES`).
2. Identify the exact file path and line numbers from the graph before opening any file.

### 2. Blast Radius & Impact Analysis (Pre-Change)
Before modifying any file:
1. Check all **incoming and outgoing edges** for the target node in [references/project-graph.md](./references/project-graph.md).
2. Verify downstream dependents (e.g., if modifying `MensajeController::funEnviarMensaje`, check which Blade form submits `POST /mi-mensaje` and what payload fields like `name="mensaje"` it expects).
3. If you add, rename, or remove routes, controllers, models, or views, **update [references/project-graph.md](./references/project-graph.md)** so the knowledge graph stays 100% synchronized with the codebase.

