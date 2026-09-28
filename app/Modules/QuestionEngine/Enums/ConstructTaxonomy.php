<?php

namespace App\Modules\QuestionEngine\Enums;

enum ConstructTaxonomy: string
{
    case Vocabulary = 'vocabulary';
    case Grammar = 'grammar';
    case Detail = 'detail';
    case MainIdea = 'main_idea';
    case Purpose = 'purpose';
    case Inference = 'inference';
    case Intent = 'intent';
    case Reference = 'reference';
    case TextCohesion = 'text_cohesion';
    case GraphicInterpretation = 'graphic_interpretation';
    case VisualDescription = 'visual_description';
    case PragmaticResponse = 'pragmatic_response';

    public function label(): string
    {
        return match ($this) {
            self::Vocabulary => 'Vocabulary in Context',
            self::Grammar => 'Grammar & Syntax',
            self::Detail => 'Factual Detail & Information Retrieval',
            self::MainIdea => 'Main Idea & Gist',
            self::Purpose => 'Purpose & Objective',
            self::Inference => 'Inference & Implication',
            self::Intent => 'Speaker / Writer Intent',
            self::Reference => 'Pronoun & Contextual Reference',
            self::TextCohesion => 'Text Cohesion & Sentence Insertion',
            self::GraphicInterpretation => 'Graphic & Visual Data Interpretation',
            self::VisualDescription => 'Visual Description',
            self::PragmaticResponse => 'Pragmatic Response & Conversational Exchange',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $construct) => [$construct->value => $construct->label()])
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
