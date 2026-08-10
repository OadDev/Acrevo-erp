<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class InstallLayout extends Component
{
    public function render(): View
    {
        return view('layouts.install');
    }
}
