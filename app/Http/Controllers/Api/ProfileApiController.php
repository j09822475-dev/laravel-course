<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trainee;
use App\Services\ProgressReport;
use App\Services\SpacedRepetition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Профиль, прогресс и управление публичной ссылкой для мобильного клиента. */
class ProfileApiController extends Controller
{
    public function show(Request $request, Trainee $trainee, ProgressReport $report, SpacedRepetition $repetition): JsonResponse
    {
        return response()->json([
            'user' => ['name' => $request->user()->name, 'email' => $request->user()->email],
            'profile' => [
                'name' => $trainee->name,
                'target_level' => $trainee->target_level->value,
                'share_url' => $trainee->shareUrl(),
            ],
            'readiness' => $report->readiness($trainee),
            'summary' => $report->summary($trainee),
            'due_count' => $repetition->dueCount($trainee),
        ]);
    }

    public function update(Request $request, Trainee $trainee): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'target_level' => ['required', 'in:junior,middle,senior'],
        ]);

        $trainee->update($data);

        return response()->json(['profile' => $data]);
    }

    public function progress(Trainee $trainee, ProgressReport $report): JsonResponse
    {
        return response()->json([
            'readiness' => $report->readiness($trainee),
            'topics' => $report->topicStats($trainee)->map(fn (array $row) => [
                'slug' => $row['topic']->slug,
                'name' => $row['topic']->name,
                'answered' => $row['answered'],
                'failed' => $row['failed'],
                'mastery' => $row['mastery'],
                'coverage' => $row['coverage'],
            ])->values(),
            'weak_spots' => $report->weakSpots($trainee)->map(fn (array $row) => $row['topic']->slug)->values(),
        ]);
    }

    public function share(Trainee $trainee): JsonResponse
    {
        if (! $trainee->isShared()) {
            $trainee->startSharing();
        }

        return response()->json(['share_url' => $trainee->shareUrl()]);
    }

    public function unshare(Trainee $trainee): JsonResponse
    {
        $trainee->stopSharing();

        return response()->json(['share_url' => null]);
    }
}
