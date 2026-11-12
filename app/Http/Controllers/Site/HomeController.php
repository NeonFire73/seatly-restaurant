<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Dish;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $popular = Dish::query()
            ->with('category')
            ->where('is_popular', true)
            ->where('is_available', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->orderBy('sort')
            ->limit(6)
            ->get();

        return view('site.home', ['popular' => $popular]);
    }
}
