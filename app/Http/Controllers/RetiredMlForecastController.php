<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RetiredMlForecastController extends Controller
{
    public function __invoke(Request $request)
    {
        // Ang archived ML URLs dili na makapa-run o maka-save og SVR predictions.
        abort_unless($request->user()->isApproved(), 403);
        abort_unless($request->isMethod('get'), 410, 'ML forecasting has been retired. Use statistical forecasting.');
        return redirect()->route('prediction.dashboard', $request->only('barangay_id'));
    }
}
