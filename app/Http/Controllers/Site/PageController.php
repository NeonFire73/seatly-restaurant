<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\SiteContent;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function about(): View
    {
        return view('site.about');
    }

    public function contacts(): View
    {
        return view('site.contacts');
    }

    /**
     * Тимчасова сторінка: майстер бронювання з'являється на етапі 4.
     */
    public function booking(): View
    {
        return view('site.booking');
    }

    public function text(string $slug): View
    {
        $page = SiteContent::page($slug);

        abort_if($page === null, 404);

        return view('site.text-page', ['page' => $page]);
    }
}
