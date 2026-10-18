<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard - StockPilot')]
class Dashboard extends Component
{
    public function render()
    {
        return view('livewire.dashboard')
            ->layoutData(['header' => 'Dashboard']);
    }
}
