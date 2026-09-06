# EXT:index_ke_search

Bridge between [EXT:index](https://github.com/lochmueller/index) and [EXT:ke_search](https://www.kesearch.de/).

EXT:index does the traversing, rendering, file extraction and queueing and dispatches PSR-14 events
for every indexed document. This extension listens to those events and writes the documents into the
ke_search index (`tx_kesearch_index`), so the regular ke_search search plugins, filters and result
templates can be used without any change.

## Requirements

- PHP 8.3+
- TYPO3 v13.4.15+ or v14
- [EXT:index](https://github.com/lochmueller/index) 2.3+
- [EXT:ke_search](https://github.com/tpwd/ke_search) 6.x or 7.x

## Installation

1. Install and configure EXT:index and EXT:ke_search
2. Run `composer require lochmueller/index-ke-search`
3. Create a **dedicated** sysfolder for the index records of this bridge
4. Add the site set `lochmueller/index-ke-search` to every site that should be indexed
5. Configure the bridge in `Site Management > Settings` (see below)
6. Run the EXT:index queue as usual (`vendor/bin/typo3 index:queue`)

The settings of the bridge only exist for sites that use the site set, either via
`Site Management > Sites` or directly in `config/sites/<site>/config.yaml`:

```yaml
dependencies:
  - lochmueller/index-ke-search
```

## Configuration

The bridge is configured per site in `Site Management > Settings`. The values are stored in
`config/sites/<site>/settings.yaml`:

| Setting                                | Default    | Description                                                                                     |
|----------------------------------------|------------|-------------------------------------------------------------------------------------------------|
| `indexKeSearch.enable`                 | `true`     | Enable the bridge for this site                                                                   |
| `indexKeSearch.storagePid`             | `0`        | **Required.** Sysfolder the index records are stored in (`pid`)                                   |
| `indexKeSearch.targetPid`              | `0`        | **Required.** Page the results of files and external documents link to                            |
| `indexKeSearch.pageType`               | `page`     | Value of the `type` column for pages                                                              |
| `indexKeSearch.externalType`           | `external` | Value of the `type` column for documents without page or FAL file                                 |
| `indexKeSearch.filterOption`           | `0`        | Optional uid of a `tx_kesearch_filteroptions` record whose tag is added to every bridge document  |
| `indexKeSearch.pageKeywordsAsTags`     | `true`     | Convert page keywords into ke_search tags                                                         |
| `indexKeSearch.cleanupAfterFullIndex`  | `false`    | Delete documents that were not written again during a full index run                              |

`indexKeSearch.storagePid` and `indexKeSearch.targetPid` are mandatory: ke_search refuses every index
record without a storage PID and without a target PID.

> **Use a dedicated storage folder.** ke_search identifies records only by
> `orig_uid + pid + type + language`. If the bridge shares its folder with a native ke_search
> indexer configuration, both write into the same records - and the cleanup would remove the records
> of the other indexer.

## Event mapping

| EXT:index event           | ke_search result                                                        |
|---------------------------|-------------------------------------------------------------------------|
| `StartIndexProcessEvent`  | -                                                                        |
| `IndexPageEvent`          | `type = page`, `targetpid = <page uid>`                                  |
| `IndexPageEvent` (no page)| `type = external`, `params = <uri>` (HTTP / external reaction indexing)  |
| `IndexFileEvent` (FAL)    | `type = file:<ext>`, `orig_uid = <FAL uid>`                              |
| `IndexFileEvent` (remote) | `type = external`, `params = <uri>`                                      |
| `DeIndexDocumentEvent`    | Deletes the records of that URI                                          |
| `FinishIndexProcessEvent` | Re-enables the index table keys, optional cleanup of outdated documents  |

The `type` column drives the link generation of ke_search
(`\Tpwd\KeSearch\Lib\SearchHelper::getResultLinkConfiguration()`), which is why the bridge picks the
type by what the document actually is.

### Column mapping

| ke_search column | Filled with                                                                     |
|------------------|----------------------------------------------------------------------------------|
| `pid`            | `indexKeSearch.storagePid`                                                         |
| `title`          | Title of the event (file name as fallback for files)                              |
| `content`        | Content of the event, tags stripped and whitespace collapsed                      |
| `abstract`       | `tx_kesearch_abstract` / `abstract` / `description` of the page, file description  |
| `language`       | Language of the page, `-1` (all languages) for files                              |
| `fe_group`       | Access groups of the event, FAL `fe_groups` for files                             |
| `starttime`/`endtime` | Page access times                                                            |
| `tags`           | Page keywords, `#file#` for files, plus the configured filter option              |
| `orig_uid`       | Page uid / FAL uid, CRC32 of the URI for external documents                       |
| `sortdate`       | `lastUpdated` / `SYS_LASTCHANGED` / `tstamp` of the page, mtime for files         |
| `directory`      | Local path of the file (files only)                                               |
| `hash`           | ke_search file hash for files, **md5 of the URI** for pages and external documents |

The `hash` column of page and external documents is unused by ke_search and is used here to find a
document again when EXT:index de-indexes it - `DeIndexDocumentEvent` only carries the URI.

## Extending

The bridge dispatches its own PSR-14 events:

| Event                              | Purpose                                                                  |
|------------------------------------|---------------------------------------------------------------------------|
| `BeforeStoreDocumentEvent`         | Modify a document or skip it (`$event->store = false`)                    |
| `BeforeDeleteDocumentEvent`        | Modify or skip the removal of a de-indexed document                       |
| `ModifyIndexerConfigurationEvent`  | Adjust the synthetic ke_search indexer configuration of the bridge        |

The synthetic indexer configuration uses the type `index` and is passed to the native ke_search
extension points (`modifyFieldValuesBeforeStoring` hook and
`\Tpwd\KeSearch\Event\ModifyFieldValuesBeforeStoringEvent`), so existing ke_search customizations can
recognize the documents of this bridge.

## Extension structure

| Directory        | Description                                                       |
|------------------|--------------------------------------------------------------------|
| `Bridge/`        | Wrapper around `\Tpwd\KeSearch\Indexer\IndexerRunner`              |
| `Configuration/` | Site settings handling                                             |
| `Dto/`           | Value objects for a document and the resolved file information     |
| `Event/`         | PSR-14 events of this extension                                    |
| `EventListener/` | Listeners for the EXT:index events                                 |
| `Mapper/`        | Mapping of EXT:index events to ke_search index records             |
| `Repository/`    | Deletion queries on `tx_kesearch_index`                            |
| `Utility/`       | URI hashing helpers                                                |

## Development

```bash
composer install
composer code-fix    # php-cs-fixer
composer code-check  # PHPStan level 8
composer code-test   # PHPUnit
```
