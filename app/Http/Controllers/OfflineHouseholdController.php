<?php

namespace App\Http\Controllers;

class OfflineHouseholdController extends Controller
{
    public function index()
    {
        return view('households.offline-create');
    }
}