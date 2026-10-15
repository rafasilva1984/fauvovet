<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use Illuminate\View\View;

final class TeamController extends Controller
{
    public function __invoke(): View
    {
        $members = Membership::query()->with('user:id,name,email')->get()
            ->sortBy(fn (Membership $m) => mb_strtolower($m->user->name))->values();

        return view('team.index', ['members' => $members]);
    }
}
