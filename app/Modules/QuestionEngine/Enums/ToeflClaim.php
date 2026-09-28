<?php

namespace App\Modules\QuestionEngine\Enums;

enum ToeflClaim: string
{
    // Reading Claims
    case ReadingAcademicMeaningAndForm = 'reading_academic_meaning_and_form';
    case ReadingComprehendVariedFormats = 'reading_comprehend_varied_formats';
    case ReadingAcademicComprehension = 'reading_academic_comprehension';

    // Listening Claims
    case ListeningConversationalDialogue = 'listening_conversational_dialogue';
    case ListeningExtendedMonologicSpeech = 'listening_extended_monologic_speech';
    case ListeningAcademicMonologicSpeech = 'listening_academic_monologic_speech';

    // Writing Claims
    case WritingReconstructSentencesGrammar = 'writing_reconstruct_sentences_grammar';
    case WritingEffectiveResponseAcademicContext = 'writing_effective_response_academic_context';
    case WritingAcademicDiscussionResponse = 'writing_academic_discussion_response';

    // Speaking Claims
    case SpeakingRepeatMonologicSpeech = 'speaking_repeat_monologic_speech';
    case SpeakingSpontaneousInterviewResponse = 'speaking_spontaneous_interview_response';

    public function label(): string
    {
        return match ($this) {
            self::ReadingAcademicMeaningAndForm => 'Process academic written texts for meaning and form',
            self::ReadingComprehendVariedFormats => 'Read and comprehend information presented in a variety of formats',
            self::ReadingAcademicComprehension => 'Read and comprehend academic texts',

            self::ListeningConversationalDialogue => 'Listen to conversational dialogue between two people',
            self::ListeningExtendedMonologicSpeech => 'Listen to and comprehend extended monologic speech',
            self::ListeningAcademicMonologicSpeech => 'Listen to and comprehend extended monologic academic speech',

            self::WritingReconstructSentencesGrammar => 'Reconstruct sentences with appropriate grammar',
            self::WritingEffectiveResponseAcademicContext => 'Write effective responses to common situations in academic contexts',
            self::WritingAcademicDiscussionResponse => 'Write effective responses for academic discussions',

            self::SpeakingRepeatMonologicSpeech => 'Repeat spoken sentences accurately with intelligible pronunciation',
            self::SpeakingSpontaneousInterviewResponse => 'Speak spontaneously and meaningfully in response to questions in an interview format',
        };
    }

    public function section(): string
    {
        return match ($this) {
            self::ReadingAcademicMeaningAndForm,
            self::ReadingComprehendVariedFormats,
            self::ReadingAcademicComprehension => 'reading',

            self::ListeningConversationalDialogue,
            self::ListeningExtendedMonologicSpeech,
            self::ListeningAcademicMonologicSpeech => 'listening',

            self::WritingReconstructSentencesGrammar,
            self::WritingEffectiveResponseAcademicContext,
            self::WritingAcademicDiscussionResponse => 'writing',

            self::SpeakingRepeatMonologicSpeech,
            self::SpeakingSpontaneousInterviewResponse => 'speaking',
        };
    }

    /**
     * @return list<self>
     */
    public static function forSection(string $section): array
    {
        $normalized = strtolower(trim($section));

        return array_values(array_filter(
            self::cases(),
            fn (self $c) => $c->section() === $normalized
        ));
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
