<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class SoilCalculatorController extends Controller
{
    public function index(Request $request): View
    {
        $rerunData = $request->session()->get('calculation_history_rerun');

        return view('soil-calculator', [
            'rerunData' => $rerunData,
        ]);
    }
}
