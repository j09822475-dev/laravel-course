<?php

namespace Tests\Unit;

use App\Support\Plural;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PluralTest extends TestCase
{
    #[DataProvider('counts')]
    public function test_russian_forms_are_selected_correctly(int $count, string $expected): void
    {
        $this->assertSame($expected, Plural::ru($count, 'вопрос', 'вопроса', 'вопросов'));
    }

    public static function counts(): array
    {
        return [
            [0, 'вопросов'],
            [1, 'вопрос'],
            [2, 'вопроса'],
            [4, 'вопроса'],
            [5, 'вопросов'],
            [9, 'вопросов'],
            [11, 'вопросов'],
            [12, 'вопросов'],
            [14, 'вопросов'],
            [21, 'вопрос'],
            [22, 'вопроса'],
            [25, 'вопросов'],
            [101, 'вопрос'],
            [111, 'вопросов'],
        ];
    }

    public function test_count_prefixes_the_number(): void
    {
        $this->assertSame('9 вопросов', Plural::count(9, 'вопрос', 'вопроса', 'вопросов'));
        $this->assertSame('1 вопрос', Plural::count(1, 'вопрос', 'вопроса', 'вопросов'));
    }
}
