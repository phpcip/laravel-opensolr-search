<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

/**
 * A sample article, searchable through Laravel Scout.
 *
 * The Opensolr driver indexes the array returned by toSearchableArray(): "title" and
 * "description" become the document's title and description, every value is joined into the
 * searchable text that gets embedded server-side, and each scalar key is also stored as a
 * filterable meta_* field, which is what makes where('category', …) work.
 *
 * @property int $id
 * @property string $title
 * @property string $category
 * @property string $author
 * @property string $body
 * @property Carbon $published_at
 */
class Article extends Model
{
    use Searchable;

    public const PER_PAGE = 10;

    protected $fillable = ['title', 'category', 'author', 'body', 'published_at'];

    protected function casts(): array
    {
        return [
            'published_at' => 'date',
        ];
    }

    /**
     * The document the index stores for this article.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->excerpt(),
            'body' => $this->body,
            'category' => $this->category,
            'author' => $this->author,
            'published_at' => $this->published_at?->toDateString(),
        ];
    }

    /**
     * The shape the search page renders.
     *
     * @return array<string, mixed>
     */
    public function toSearchResult(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category,
            'author' => $this->author,
            'date' => $this->published_at?->format('m/d/Y'),
            'excerpt' => $this->excerpt(),
        ];
    }

    /**
     * Every category in use, for the filter drop-down.
     *
     * @return list<string>
     */
    public static function categories(): array
    {
        return static::query()->distinct()->orderBy('category')->pluck('category')->all();
    }

    /**
     * The opening of the body, as a one-line summary.
     */
    protected function excerpt(): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', $this->body) ?? ''), 220, '…');
    }
}
