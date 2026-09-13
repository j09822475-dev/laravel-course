<?php

namespace App\Http\Controllers;

use App\Enums\Difficulty;
use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\Topic;
use App\Services\QuestionBank;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuestionController extends Controller
{
    /** Справочник: весь банк вопросов с фильтрами и поиском. */
    public function index(Request $request, QuestionBank $bank): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'topic' => ['nullable', 'integer', 'exists:topics,id'],
            'type' => ['nullable', 'in:theory,quiz,coding,behavioral,system_design'],
            'difficulty' => ['nullable', 'in:junior,middle,senior'],
        ]);

        $questions = $bank->query([
            'search' => $filters['search'] ?? null,
            'topics' => isset($filters['topic']) ? [$filters['topic']] : null,
            'types' => isset($filters['type']) ? [$filters['type']] : null,
            'difficulties' => isset($filters['difficulty']) ? [$filters['difficulty']] : null,
        ])
            ->orderBy('topic_id')
            ->orderBy('external_id')
            ->paginate(15)
            ->withQueryString();

        return view('questions.index', [
            'questions' => $questions,
            'topics' => Topic::orderBy('position')->get(),
            'types' => QuestionType::cases(),
            'levels' => Difficulty::cases(),
            'filters' => $filters,
        ]);
    }

    public function show(Question $question): View
    {
        return view('questions.show', ['question' => $question->load('topic')]);
    }
}
