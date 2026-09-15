<?php

namespace Tests\Unit;

use App\Support\AnswerMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AnswerMatcherTest extends TestCase
{
    #[DataProvider('answers')]
    public function test_free_text_answers_are_matched_forgivingly(?string $given, bool $expected): void
    {
        $this->assertSame($expected, AnswerMatcher::matches($given, ['responsible', 'in charge of']));
    }

    public static function answers(): array
    {
        return [
            'точное совпадение' => ['responsible', true],
            'другой регистр' => ['Responsible', true],
            'пробелы по краям' => ['  responsible  ', true],
            'точка в конце' => ['responsible.', true],
            'фраза из нескольких слов' => ['in charge of', true],
            'двойные пробелы внутри' => ['in  charge   of', true],
            'другое слово' => ['responsable', false],
            'пусто' => ['', false],
            'null' => [null, false],
            'только пробелы' => ['   ', false],
        ];
    }

    public function test_typographic_apostrophe_is_accepted(): void
    {
        $this->assertTrue(AnswerMatcher::matches('I’ve', ["I've"]));
    }
}
