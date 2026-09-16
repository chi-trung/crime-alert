<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Support\BoundedPaginator;

class NewsController extends Controller
{
    public function index()
    {
        // Issue #317: bounded page — see WantedListController for the probe.
        $news = BoundedPaginator::paginate(News::orderByDesc('published_at')->orderByDesc('id'), 12);

        return view('news.index', compact('news'));
    }
}
