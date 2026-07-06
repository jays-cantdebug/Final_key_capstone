<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DassQuestion;
use App\Models\Questionnaire;
use App\Models\QuestionnaireVersion;
use Illuminate\Database\Seeder;

class DassQuestionSeeder extends Seeder
{
    /**
     * Seed the 21 official DASS-21 questionnaire items for version 1,
     * in their official published item order and subscale assignment.
     */
    public function run(): void
    {
        $questionnaire = Questionnaire::query()->where('title', 'DASS-21')->firstOrFail();

        $version = QuestionnaireVersion::query()
            ->where('questionnaire_id', $questionnaire->id)
            ->where('version_number', 1)
            ->firstOrFail();

        foreach ($this->officialItems() as $item) {
            DassQuestion::query()->updateOrCreate(
                [
                    'questionnaire_version_id' => $version->id,
                    'item_number' => $item['item_number'],
                ],
                [
                    'question_text' => $item['question_text'],
                    'question_type' => DassQuestion::TYPE_LIKERT_SCALE,
                    'subscale' => $item['subscale'],
                    'display_order' => $item['item_number'],
                    'is_required' => true,
                ]
            );
        }
    }

    /**
     * @return array<int, array{item_number: int, question_text: string, subscale: string}>
     */
    private function officialItems(): array
    {
        return [
            ['item_number' => 1, 'question_text' => 'I found it hard to wind down.', 'subscale' => DassQuestion::SUBSCALE_STRESS],
            ['item_number' => 2, 'question_text' => 'I was aware of dryness of my mouth.', 'subscale' => DassQuestion::SUBSCALE_ANXIETY],
            ['item_number' => 3, 'question_text' => "I couldn't seem to experience any positive feeling at all.", 'subscale' => DassQuestion::SUBSCALE_DEPRESSION],
            ['item_number' => 4, 'question_text' => 'I experienced breathing difficulty (e.g., excessively rapid breathing, breathlessness in the absence of physical exertion).', 'subscale' => DassQuestion::SUBSCALE_ANXIETY],
            ['item_number' => 5, 'question_text' => 'I found it difficult to work up the initiative to do things.', 'subscale' => DassQuestion::SUBSCALE_DEPRESSION],
            ['item_number' => 6, 'question_text' => 'I tended to over-react to situations.', 'subscale' => DassQuestion::SUBSCALE_STRESS],
            ['item_number' => 7, 'question_text' => 'I experienced trembling (e.g., in the hands).', 'subscale' => DassQuestion::SUBSCALE_ANXIETY],
            ['item_number' => 8, 'question_text' => 'I felt that I was using a lot of nervous energy.', 'subscale' => DassQuestion::SUBSCALE_STRESS],
            ['item_number' => 9, 'question_text' => 'I was worried about situations in which I might panic and make a fool of myself.', 'subscale' => DassQuestion::SUBSCALE_ANXIETY],
            ['item_number' => 10, 'question_text' => 'I felt that I had nothing to look forward to.', 'subscale' => DassQuestion::SUBSCALE_DEPRESSION],
            ['item_number' => 11, 'question_text' => 'I found myself getting agitated.', 'subscale' => DassQuestion::SUBSCALE_STRESS],
            ['item_number' => 12, 'question_text' => 'I found it difficult to relax.', 'subscale' => DassQuestion::SUBSCALE_STRESS],
            ['item_number' => 13, 'question_text' => 'I felt down-hearted and blue.', 'subscale' => DassQuestion::SUBSCALE_DEPRESSION],
            ['item_number' => 14, 'question_text' => 'I was intolerant of anything that kept me from getting on with what I was doing.', 'subscale' => DassQuestion::SUBSCALE_STRESS],
            ['item_number' => 15, 'question_text' => 'I felt I was close to panic.', 'subscale' => DassQuestion::SUBSCALE_ANXIETY],
            ['item_number' => 16, 'question_text' => 'I was unable to become enthusiastic about anything.', 'subscale' => DassQuestion::SUBSCALE_DEPRESSION],
            ['item_number' => 17, 'question_text' => "I felt I wasn't worth much as a person.", 'subscale' => DassQuestion::SUBSCALE_DEPRESSION],
            ['item_number' => 18, 'question_text' => 'I felt that I was rather touchy.', 'subscale' => DassQuestion::SUBSCALE_STRESS],
            ['item_number' => 19, 'question_text' => 'I was aware of the action of my heart in the absence of physical exertion (e.g., sense of heart rate increase, heart missing a beat).', 'subscale' => DassQuestion::SUBSCALE_ANXIETY],
            ['item_number' => 20, 'question_text' => 'I felt scared without any good reason.', 'subscale' => DassQuestion::SUBSCALE_ANXIETY],
            ['item_number' => 21, 'question_text' => 'I felt that life was meaningless.', 'subscale' => DassQuestion::SUBSCALE_DEPRESSION],
        ];
    }
}
