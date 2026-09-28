<?php

namespace App\Modules\QuestionEngine\Enums;

use App\Modules\QuestionBank\Enums\SectionType;

enum ToeflSkill: string
{
    // Reading Skills & Subskills
    case ReadingProcessMeaningAndForm = 'reading_process_meaning_and_form';
    case ReadingComprehendVariedFormats = 'reading_comprehend_varied_formats';
    case ReadingShortNonacademicTexts = 'reading_short_nonacademic_texts';
    case ReadingAcademicTexts = 'reading_academic_texts';

    // Listening Skills & Subskills
    case ListeningConversationalDialogue = 'listening_conversational_dialogue';
    case ListeningSingleExchangeDialogue = 'listening_single_exchange_dialogue';
    case ListeningShortConversations = 'listening_short_conversations';
    case ListeningExtendedMonologicSpeech = 'listening_extended_monologic_speech';
    case ListeningAnnouncements = 'listening_announcements';
    case ListeningAcademicTalks = 'listening_academic_talks';

    // Writing Skills & Subskills
    case WritingReconstructSentencesGrammar = 'writing_reconstruct_sentences_grammar';
    case WritingEffectiveResponsesAcademicContext = 'writing_effective_responses_academic_context';
    case WritingAcademicDiscussion = 'writing_academic_discussion';

    // Speaking Skills & Subskills
    case SpeakingRepeatSpokenSentences = 'speaking_repeat_spoken_sentences';
    case SpeakingSpontaneousInterview = 'speaking_spontaneous_interview';
    case SpeakingIntelligibly = 'speaking_intelligibly';

    public function label(): string
    {
        return match ($this) {
            self::ReadingProcessMeaningAndForm => 'Process academic written texts for meaning and form',
            self::ReadingComprehendVariedFormats => 'Read and comprehend information presented in a variety of formats',
            self::ReadingShortNonacademicTexts => 'Understand short nonacademic written texts',
            self::ReadingAcademicTexts => 'Understand academic text by identifying main ideas, details, and relationships',

            self::ListeningConversationalDialogue => 'Listen to conversational dialogue between two people',
            self::ListeningSingleExchangeDialogue => 'Understand a single-exchange dialogue',
            self::ListeningShortConversations => 'Understand short conversations',
            self::ListeningExtendedMonologicSpeech => 'Listen to and comprehend extended monologic speech',
            self::ListeningAnnouncements => 'Understand announcements and instructions',
            self::ListeningAcademicTalks => 'Understand academic talks and lectures',

            self::WritingReconstructSentencesGrammar => 'Reconstruct sentences with appropriate grammar',
            self::WritingEffectiveResponsesAcademicContext => 'Write effective responses to common situations in academic contexts',
            self::WritingAcademicDiscussion => 'Write for an academic discussion',

            self::SpeakingRepeatSpokenSentences => 'Repeat spoken sentences accurately',
            self::SpeakingSpontaneousInterview => 'Speak spontaneously in an interview format',
            self::SpeakingIntelligibly => 'Speak intelligibly with accurate pronunciation and coherence',
        };
    }

    public function section(): string
    {
        return match ($this) {
            self::ReadingProcessMeaningAndForm,
            self::ReadingComprehendVariedFormats,
            self::ReadingShortNonacademicTexts,
            self::ReadingAcademicTexts => 'reading',

            self::ListeningConversationalDialogue,
            self::ListeningSingleExchangeDialogue,
            self::ListeningShortConversations,
            self::ListeningExtendedMonologicSpeech,
            self::ListeningAnnouncements,
            self::ListeningAcademicTalks => 'listening',

            self::WritingReconstructSentencesGrammar,
            self::WritingEffectiveResponsesAcademicContext,
            self::WritingAcademicDiscussion => 'writing',

            self::SpeakingRepeatSpokenSentences,
            self::SpeakingSpontaneousInterview,
            self::SpeakingIntelligibly => 'speaking',
        };
    }

    public function claim(): ToeflClaim
    {
        return ToeflClaim::forSection($this->section());
    }

    /**
     * @return list<self>
     */
    public static function forSection(string|SectionType $section): array
    {
        $normalized = $section instanceof SectionType
            ? strtolower($section->value)
            : strtolower(trim((string) $section));

        return array_values(array_filter(
            self::cases(),
            fn (self $s) => $s->section() === $normalized
        ));
    }

    /**
     * @return list<self>
     */
    public static function forClaim(ToeflClaim $claim): array
    {
        return self::forSection($claim->section());
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
