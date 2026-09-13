<?php

namespace App\Http\Controllers;

use App\Enums\SessionMode;
use App\Models\Topic;
use App\Models\Trainee;
use App\Services\ProgressReport;
use App\Services\SpacedRepetition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Trainee $trainee, ProgressReport $report, SpacedRepetition $repetition): View
    {
        return view('dashboard', [
            'readiness' => $report->readiness($trainee),
            'summary' => $report->summary($trainee),
            'weakSpots' => $report->weakSpots($trainee),
            'dueCount' => $repetition->dueCount($trainee),
            'modes' => SessionMode::cases(),
            'topics' => Topic::query()->withCount('questions')->orderBy('position')->get(),
            'activeSession' => $trainee->sessions()
                ->where('status', 'in_progress')
                ->latest()
                ->first(),
            'recent' => $report->recentSessions($trainee, 5),
        ]);
    }

    /** Настройки кандидата: имя и целевой грейд. */
    public function update(Request $request, Trainee $trainee): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'target_level' => ['required', 'in:junior,middle,senior'],
        ]);

        $trainee->update($data);

        return redirect()->route('dashboard')->with('status', 'Профиль обновлён.');
    }
}
