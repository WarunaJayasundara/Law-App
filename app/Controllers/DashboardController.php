<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Deed;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->view('dashboard/index', [
            'counts' => Deed::counts(),
            'recent' => Deed::recent(8),
        ]);
    }
}
