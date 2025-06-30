<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WantedPerson;

class WantedListController extends Controller
{
    public function index(Request $request)
    {
        $query = WantedPerson::query();
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function($sub) use ($q) {
                $sub->where(function($query) use ($q) {
                    $query->where('name', 'like', "$q")
                        ->orWhere('name', 'like', "$q %")
                        ->orWhere('name', 'like', "% $q %")
                        ->orWhere('name', 'like', "% $q")
                        ->orWhere('name', 'like', "$q");
                });
            });
        }
        $wantedPeople = $query->orderByDesc('id')->paginate(20)->withQueryString();
        return view('wanted_list.index', compact('wantedPeople'));
    }
} 