# Laravel Opensolr Search

A small, complete Laravel application that puts a Vue search interface in front of an
[Opensolr](https://opensolr.com) index. Search is hybrid: keyword relevance (BM25) and
semantic similarity (kNN over server-side embeddings) fused per document, so "sleepy pets"
finds the article about cats napping. One page searches an Eloquent model through
[Laravel Scout](https://laravel.com/docs/scout); the other searches the whole index and can
write a grounded answer from the top hits.

Everything talks to Opensolr through one package,
[`opensolr/laravel-scout-opensolr`](https://packagist.org/packages/opensolr/laravel-scout-opensolr),
documented at [opensolr.com/laravel-search](https://opensolr.com/laravel-search). There is no
Solr client to configure, no schema to design and no embedding model to run.

## What is inside

| Page | Route | What it demonstrates |
|---|---|---|
| Index search | `/` | `embed_and_search` over the whole index: hybrid ranking, highlighting, spelling suggestions, facet counts, a "fresh results first" toggle and an **Ask AI** button that grounds an answer on the top hits. |
| Eloquent search | `/eloquent` | Stock Scout on an `Article` model: `Article::search($q)->where('category', $c)->paginate()`. Twenty seeded articles in five categories. |

The JSON endpoints behind the pages live in `routes/api.php` and are rate limited per client
address (60 searches and 10 AI answers per minute).

## Requirements

- PHP 8.3 or newer, with the `sqlite3` and `pdo_sqlite` extensions
- Composer
- Node.js 20 or newer

## Quick start

The `.env.example` file points at Opensolr's public demo account, so the application runs
before you have an account of your own.

```bash
composer create-project opensolr/laravel-opensolr-search my-search
cd my-search
php artisan opensolr:create-index
php artisan scout:import "App\Models\Article"
npm install
npm run build
php artisan serve
```

`create-project` copies `.env.example` to `.env`, generates the application key, creates the
SQLite database and seeds the articles. Working from a clone instead:

```bash
git clone https://github.com/phpcip/laravel-opensolr-search.git
cd laravel-opensolr-search
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
```

then continue from `opensolr:create-index` above.

Open http://localhost:8000. The index search works immediately: `OPENSOLR_INDEX` points at
the demo account's news index, which is loaded and read-only. `opensolr:create-index` creates
a vector index of your own on the account and writes its name to `OPENSOLR_SCOUT_INDEX`; that
is where `scout:import` sends the twenty seeded articles. They become searchable on the
Eloquent page about a minute later, once the Data Ingestion API has embedded them
server-side. Progress is visible in the Control Panel under Data Ingestion.

`composer setup` runs the same steps in one go, except the two `opensolr:create-index` and
`scout:import` lines.

For development with hot reloading, run `npm run dev` next to `php artisan serve`.

### The demo account

`mcp@opensolr.com` is a public, shared, throwaway account. Know what you are working with:

- **Anything you create there is deleted after 3 days.** Automatically, without warning
  or export. That includes indexes you created and every document in them.
- **The account is shared with everyone reading this.** Your index is visible to them,
  they can change or delete it, and you can do the same to theirs. Never put anything
  real, private or client-owned in it.
- **The limits are per index, and deliberately small.** 200 MB of bandwidth and 50 MB
  of disk per index. Bandwidth is the one you will hit first: it covers a demo, a
  tutorial and a proof of concept, and it will not carry an application.

When you want an index that is private, yours and still there next week,
[register](https://opensolr.com/register) (the free plan is free forever, no card), create a
vector-enabled index in the Control Panel, and change three lines in `.env`:

```dotenv
OPENSOLR_EMAIL=you@example.com
OPENSOLR_API_KEY=your-api-key
OPENSOLR_INDEX=myapp__dense
OPENSOLR_SCOUT_INDEX=
```

With `OPENSOLR_SCOUT_INDEX` empty, both pages use the same index, which is the normal setup:
one index serves every searchable model and the whole-index search alike. Nothing else in the
code changes.

## How it works

### The Eloquent side

`app/Models/Article.php` uses the `Searchable` trait and returns the fields to index from
`toSearchableArray()`. The driver stores `title` and `description` as the document's title
and description, joins every value into the text that gets embedded, and keeps each scalar
key as a filterable `meta_*` field. That last part is what makes `->where('category', …)`
work: it becomes a Solr filter on `meta_category`.

`app/Http/Controllers/ArticleSearchController.php` is plain Scout. Search, filter, paginate.
The driver adds a `meta_model` scope to every query, so one Opensolr index can serve every
searchable model in an application.

Indexing is asynchronous. `scout:import` and model saves go through Opensolr's Data Ingestion
API, which computes embeddings and derived fields on the server; documents become searchable
within about a minute.

### The index side

`app/Search/IndexSearch.php` calls `OpensolrClient::embedAndSearch()`. That single request
embeds the query, fuses keyword and semantic scores, highlights, facets, spell-checks and
applies the Search Tuning saved for the index in the Control Panel. The service reduces the
response to what the page shows and drops the rest: the raw Solr parameters, the query vector
and the debug block never reach the browser.

The "fresh results first" toggle sets the `fresh_bias` tuning knob, which re-orders by
recency and hides nothing. It is the same control visitors get on Opensolr's hosted search
page.

**Ask AI** calls `OpensolrClient::aiAnswer()`: the platform retrieves the top hybrid hits for
the question and writes an answer from them. The page shows the answer as plain text next to
the results it was written from.

### The Vue side

`resources/js/composables/useSearch.js` is the one piece of shared logic: a request state
machine that aborts the previous fetch, ignores responses that arrive out of order and exposes
`loading`, `error` and `result`. Both page components use it. It replaces the Vue 2 mixin the
first version of this project was built around.

Highlights are the one place a search page usually reaches for `innerHTML`. This one does not.
Solr marks hits with `<em>` and leaves the surrounding text unescaped, so the server splits
every fragment into `[text, hit]` segments and `HighlightText.vue` renders them as text nodes,
wrapping only the hits in `<mark>`.

## Security notes

- Every request is validated by a form request: bounded length, no control characters, page
  numbers capped, categories restricted to a plain character set.
- The API key stays on the server. The browser only ever talks to this application's own
  JSON endpoints.
- Search endpoints are throttled per client address, and the AI endpoint more tightly,
  because an answer is a GPU round-trip billed to the index owner.
- A Content Security Policy allows scripts and styles from this origin only; result
  thumbnails may load from https hosts. `X-Frame-Options`, `nosniff` and a referrer policy
  are set on every response.
- Result links are validated server-side: only absolute http(s) addresses are passed to the
  browser, never `javascript:` or `data:` URLs, and never the synthetic address the ingestion
  API assigns to model documents.
- Upstream failures are reported to the log and answered with a generic 502. Exception text
  never reaches the client.

## Project history

The first version of this repository, from 2019, used Solarium against a Solr endpoint
directly, Laravel 6 and Vue 2 with a mixin. It is preserved in the git history under the tag
`legacy-solarium`. This version replaces all of it with the Opensolr platform API, Laravel 13,
Vue 3 and Vite.

## License

MIT.
