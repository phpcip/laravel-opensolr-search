@extends('layouts.app', ['title' => 'Index search'])

@section('content')
    <h1>Search the whole index</h1>
    <p class="lead">
        One request to Opensolr's <code>embed_and_search</code>: the query is embedded server-side, keyword and
        semantic scores are fused per document, and highlighting, spelling suggestions and the Search Tuning saved
        for the index come back in the same call. <strong>Ask AI</strong> grounds an answer on the top hits.
    </p>

    <div id="index-search" data-index="{{ config('scout-opensolr.index') }}"></div>
@endsection
