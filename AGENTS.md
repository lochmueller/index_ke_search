# AGENTS.md - Coding Agent Instructions for EXT:index_ke_search

## Project Overview

- **Name**: EXT:index_ke_search (lochmueller/index-ke-search)
- **Type**: TYPO3 CMS Extension
- **Purpose**: Bridge between EXT:index and EXT:ke_search - the indexing events of EXT:index are
  written into the ke_search index (`tx_kesearch_index`)
- **Language**: PHP 8.3+
- **Framework**: TYPO3 CMS v13.4.15+ / v14
- **Namespace**: `Lochmueller\IndexKeSearch\`

## Build/Lint/Test Commands

```bash
composer install
composer code-fix           # php-cs-fixer
composer code-check         # PHPStan level 8
composer code-test          # PHPUnit
composer code-test-coverage # PHPUnit with coverage

.Build/bin/phpunit -c Tests/UnitTests.xml Tests/Unit/Path/To/YourTest.php
.Build/bin/phpunit -c Tests/UnitTests.xml --filter testMethodName
```

## Project Structure

```
Classes/                # PSR-4: Lochmueller\IndexKeSearch\
  Bridge/               # Wrapper around \Tpwd\KeSearch\Indexer\IndexerRunner
  Configuration/        # Site settings DTO + loader
  Dto/                  # IndexDocument, FileInformation
  Event/                # PSR-14 events of this extension
  EventListener/        # Listeners for the EXT:index events
  Mapper/               # EXT:index event -> ke_search index record
  Repository/           # Deletion queries on tx_kesearch_index
  Utility/              # URI hashing
Configuration/          # Services.yaml, Icons.php, Sets/ (site set + settings definitions)
Resources/              # Icon
Tests/Unit/             # Unit tests
```

## Domain Knowledge

### The two sides

- **EXT:index** dispatches `StartIndexProcessEvent`, `IndexPageEvent`, `IndexFileEvent`,
  `DeIndexDocumentEvent` and `FinishIndexProcessEvent`. A Start/Finish pair is dispatched **per
  EXT:index configuration record**, not per site.
- **EXT:ke_search** stores everything in `tx_kesearch_index` and identifies a record by
  `orig_uid + pid + type + language` (files: `type + hash + pid + sortdate + language`).

### Configuration lives in site settings

The bridge ships the site set `lochmueller/index-ke-search`
(`Configuration/Sets/IndexKeSearch/`). The settings are only available for sites that list the set in
their `dependencies`. Labels and descriptions are resolved by the site set convention from
`labels.xlf` (`settings.<key>` / `settings.description.<key>` / `categories.<id>`), so a new setting
needs three places: `settings.definitions.yaml`, `labels.xlf` and
`Configuration::SETTING_IDENTIFIERS` - `SetDefinitionTest` fails if they drift apart.

### IndexerRunner setup

`\Tpwd\KeSearch\Indexer\IndexerRunner::storeInIndex()` cannot be called out of the box. Before the
first record `Bridge\KeSearchIndexer::prepare()` has to run, because `startIndexing()` normally does:

1. collect the additional columns from the `registerAdditionalFields` hook
2. `prepareStatements()` - creates the **MySQL session** prepared statements `insertStmt`/`updateStmt`
   and disables the table keys when the index is empty
3. set `$indexerRunner->indexerConfig`

The prepared statements live in the database session, so the setup is done once per PHP process and
`cleanUpProcessAfterIndexing()` is called on `FinishIndexProcessEvent`.

### Type column drives the result link

`\Tpwd\KeSearch\Lib\SearchHelper::getResultLinkConfiguration()`:

- `page` (default branch) -> typolink to `targetpid`, `params` as additional parameters
- `file:*` -> `t3://file?uid=<orig_uid>`, fallback `directory . title`
- `external` -> typolink to the URI in `params`

Never change the type mapping without checking that method.

### hash column

For page and external documents ke_search does not use `hash`, so the bridge stores `md5(uri)` there
(`Utility\UriHash`). This is the only way to find a document again for `DeIndexDocumentEvent`, which
only carries the URI. For files the hash **must** stay identical to
`\Tpwd\KeSearch\Indexer\Types\File::getUniqueHashForFile()` (`md5($localPath . $fileName)`),
otherwise files are indexed twice.

## Code Style Guidelines

- PER-CS3.0 with risky rules, PHP 8.3 migration rules, `declare(strict_types=1)` in every file
- No unused imports
- Constructor property promotion, `readonly` for immutable dependencies
- Full type hints on parameters and return types, PHPDoc for arrays and generics
- Catch `\Exception` in event listeners and log via `LoggerAwareTrait` - an indexing bridge must
  never break the indexing run of EXT:index
- Register listeners with `#[AsEventListener('index-ke-search-*')]`

## Before Committing

1. `composer code-fix`
2. `composer code-check`
3. `composer code-test`
