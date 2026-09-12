@extends('layouts.app', ['title' => 'Eloquent search'])

@section('content')
    <h1>Search an Eloquent model</h1>
    <p class="lead">
        Stock Laravel Scout on the <code>Article</code> model: <code>Article::search($q)->where('category', $c)->paginate()</code>.
        The Opensolr driver runs the hybrid query, scopes it to this model and turns the category into a Solr filter.
        Twenty seeded articles, five categories, and meaning-based matching: try <em>sleepy pets</em> or <em>budget dining</em>.
    </p>

    <div id="eloquent-search"></div>
@endsection
