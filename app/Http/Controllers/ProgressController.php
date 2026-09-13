<?php

namespace App\Http\Controllers;

use App\Models\Trainee;
use App\Services\ProgressReport;
use App\Services\SpacedRepetition;
use Illuminate\View\View;

class ProgressController extends Controller
{
    public function __invoke(Trainee $trainee, ProgressReport $report, SpacedRepetition $repetition): View
    {
        return view('progress', [
            'readiness' => $report->readiness($trainee),
            'summary' => $report->summary($trainee),
            'stats' => $report->topicStats($trainee),
            'sessions' => $report->recentSessions($trainee, 15),
            'due' => $repetition->due($trainee, 10),
            'dueCount' => $repetition->dueCount($trainee),
        ]);
    }
}
