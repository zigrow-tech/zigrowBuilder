<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Template;
use App\Models\TemplateVisit;
use App\Models\TemplateVisitCount;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;



class AnalyticsController extends Controller
{

    public function getTodayVisitCount()
    {
        $user = Auth::user();
        $count = TemplateVisitCount::where('user_id', $user->id)->where('date', now()->toDateString())->value('count') ?? 0;
        return response()->json(['count' => $count]);
    }

    public function getMonthlyVisitData()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['data' => []]);
        }

        $visits = DB::table('template_visit_counts')
            ->selectRaw("DATE_FORMAT(date, '%c') as month, SUM(count) as total")
            ->where('user_id', $user->id)
            ->where('date', '>=', now()->startOfYear())
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        $monthlyCounts = array_fill(1, 12, 0); // months 1 to 12
        foreach ($visits as $visit) {
            $monthIndex = (int) $visit->month;
            $monthlyCounts[$monthIndex] = (int) $visit->total;
        }
        return response()->json(['data' => array_values($monthlyCounts)]);
    }




}


