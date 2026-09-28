<?php

namespace App\Modules\QuestionEngine\Enums;

use App\Modules\QuestionBank\Enums\SectionType;

enum ToeflTaskType: string
{
    // Reading Task Types (3)
    case CompleteTheWords = 'complete_the_words';
    case ReadInDailyLife = 'read_in_daily_life';
    case ReadAnAcademicPassage = 'read_an_academic_passage';

    // Listening Task Types (4)
    case ListenAndChooseAResponse = 'listen_and_choose_a_response';
    case ListenToAConversation = 'listen_to_a_conversation';
    case ListenToAnAnnouncement = 'listen_to_an_announcement';
    case ListenToAnAcademicTalk = 'listen_to_an_academic_talk';

    // Writing Task Types (3)
    case BuildASentence = 'build_a_sentence';
    case WriteAnEmail = 'write_an_email';
    case WriteForAnAcademicDiscussion = 'write_for_an_academic_discussion';

    // Speaking Task Types (2)
    case ListenAndRepeat = 'listen_and_repeat';
    case TakeAnInterview = 'take_an_interview';

    public function label(): string
    {
        return match ($this) {
            self::CompleteTheWords => 'Complete the Words',
            self::ReadInDailyLife => 'Read in Daily Life',
            self::ReadAnAcademicPassage => 'Read an Academic Passage',

            self::ListenAndChooseAResponse => 'Listen and Choose a Response',
            self::ListenToAConversation => 'Listen to a Conversation',
            self::ListenToAnAnnouncement => 'Listen to an Announcement',
            self::ListenToAnAcademicTalk => 'Listen to an Academic Talk',

            self::BuildASentence => 'Build a Sentence',
            self::WriteAnEmail => 'Write an Email',
            self::WriteForAnAcademicDiscussion => 'Write for an Academic Discussion',

            self::ListenAndRepeat => 'Listen and Repeat',
            self::TakeAnInterview => 'Take an Interview',
        };
    }

    public function section(): string
    {
        return match ($this) {
            self::CompleteTheWords,
            self::ReadInDailyLife,
            self::ReadAnAcademicPassage => 'reading',

            self::ListenAndChooseAResponse,
            self::ListenToAConversation,
            self::ListenToAnAnnouncement,
            self::ListenToAnAcademicTalk => 'listening',

            self::BuildASentence,
            self::WriteAnEmail,
            self::WriteForAnAcademicDiscussion => 'writing',

            self::ListenAndRepeat,
            self::TakeAnInterview => 'speaking',
        };
    }

    public function responseMode(): ToeflResponseMode
    {
        return match ($this) {
            self::CompleteTheWords => ToeflResponseMode::Reconstruction,
            self::ReadInDailyLife,
            self::ReadAnAcademicPassage,
            self::ListenAndChooseAResponse,
            self::ListenToAConversation,
            self::ListenToAnAnnouncement,
            self::ListenToAnAcademicTalk => ToeflResponseMode::SelectedResponse,
            self::BuildASentence => ToeflResponseMode::Reconstruction,
            self::WriteAnEmail,
            self::WriteForAnAcademicDiscussion => ToeflResponseMode::ConstructedWritten,
            self::ListenAndRepeat,
            self::TakeAnInterview => ToeflResponseMode::ConstructedSpoken,
        };
    }

    public function scoringMode(): ToeflScoringMode
    {
        return match ($this) {
            self::CompleteTheWords,
            self::ReadInDailyLife,
            self::ReadAnAcademicPassage,
            self::ListenAndChooseAResponse,
            self::ListenToAConversation,
            self::ListenToAnAnnouncement,
            self::ListenToAnAcademicTalk,
            self::BuildASentence => ToeflScoringMode::Machine,

            self::WriteAnEmail,
            self::WriteForAnAcademicDiscussion,
            self::ListenAndRepeat,
            self::TakeAnInterview => ToeflScoringMode::AiScored,
        };
    }

    public function cefrMin(): string
    {
        return match ($this) {
            self::ReadInDailyLife,
            self::ListenAndChooseAResponse,
            self::BuildASentence,
            self::ListenAndRepeat,
            self::TakeAnInterview => 'A1',

            self::ListenToAConversation,
            self::ListenToAnAnnouncement,
            self::ListenToAnAcademicTalk => 'A2',

            self::CompleteTheWords,
            self::ReadAnAcademicPassage,
            self::WriteAnEmail,
            self::WriteForAnAcademicDiscussion => 'B1',
        };
    }

    public function cefrMax(): string
    {
        return match ($this) {
            self::ListenAndChooseAResponse => 'B2',
            self::CompleteTheWords => 'C1+',
            self::ReadInDailyLife,
            self::ListenToAConversation,
            self::ListenToAnAnnouncement => 'C1',

            self::ReadAnAcademicPassage,
            self::ListenToAnAcademicTalk,
            self::BuildASentence,
            self::WriteAnEmail,
            self::WriteForAnAcademicDiscussion,
            self::ListenAndRepeat,
            self::TakeAnInterview => 'C2',
        };
    }

    /**
     * @return array{min: string, max: string}
     */
    public function cefrRange(): array
    {
        return [
            'min' => $this->cefrMin(),
            'max' => $this->cefrMax(),
        ];
    }

    /**
     * @return list<ToeflClaim>
     */
    public function allowedClaims(): array
    {
        return match ($this) {
            self::CompleteTheWords => [
                ToeflClaim::ReadingAcademicMeaningAndForm,
            ],
            self::ReadInDailyLife => [
                ToeflClaim::ReadingComprehendVariedFormats,
            ],
            self::ReadAnAcademicPassage => [
                ToeflClaim::ReadingComprehendVariedFormats,
                ToeflClaim::ReadingAcademicComprehension,
            ],
            self::ListenAndChooseAResponse,
            self::ListenToAConversation => [
                ToeflClaim::ListeningConversationalDialogue,
            ],
            self::ListenToAnAnnouncement => [
                ToeflClaim::ListeningExtendedMonologicSpeech,
            ],
            self::ListenToAnAcademicTalk => [
                ToeflClaim::ListeningExtendedMonologicSpeech,
                ToeflClaim::ListeningAcademicMonologicSpeech,
            ],
            self::BuildASentence => [
                ToeflClaim::WritingReconstructSentencesGrammar,
            ],
            self::WriteAnEmail => [
                ToeflClaim::WritingEffectiveResponseAcademicContext,
            ],
            self::WriteForAnAcademicDiscussion => [
                ToeflClaim::WritingAcademicDiscussionResponse,
                ToeflClaim::WritingEffectiveResponseAcademicContext,
            ],
            self::ListenAndRepeat => [
                ToeflClaim::SpeakingRepeatMonologicSpeech,
            ],
            self::TakeAnInterview => [
                ToeflClaim::SpeakingSpontaneousInterviewResponse,
            ],
        };
    }

    /**
     * @return list<ToeflLanguageUseContext>
     */
    public function allowedLanguageUseContexts(): array
    {
        return match ($this) {
            self::CompleteTheWords,
            self::ReadAnAcademicPassage,
            self::ListenToAnAcademicTalk,
            self::WriteForAnAcademicDiscussion => [
                ToeflLanguageUseContext::Academic,
            ],
            self::ReadInDailyLife => [
                ToeflLanguageUseContext::AcademicNavigational,
                ToeflLanguageUseContext::SocialInterpersonal,
            ],
            self::ListenAndChooseAResponse => [
                ToeflLanguageUseContext::SocialInterpersonal,
            ],
            self::ListenToAConversation => [
                ToeflLanguageUseContext::SocialInterpersonal,
                ToeflLanguageUseContext::AcademicNavigational,
            ],
            self::ListenToAnAnnouncement,
            self::WriteAnEmail => [
                ToeflLanguageUseContext::AcademicNavigational,
            ],
            self::BuildASentence => [
                ToeflLanguageUseContext::Academic,
                ToeflLanguageUseContext::AcademicNavigational,
            ],
            self::ListenAndRepeat => [
                ToeflLanguageUseContext::Academic,
                ToeflLanguageUseContext::SocialInterpersonal,
            ],
            self::TakeAnInterview => [
                ToeflLanguageUseContext::SocialInterpersonal,
                ToeflLanguageUseContext::AcademicNavigational,
            ],
        };
    }

    public function itemCountFixed(): ?int
    {
        return match ($this) {
            self::CompleteTheWords => 30,
            self::ListenToAConversation => 10,
            self::BuildASentence => 10,
            self::WriteAnEmail => 1,
            self::WriteForAnAcademicDiscussion => 1,
            self::ListenAndRepeat => 7,
            self::TakeAnInterview => 4,
            default => null,
        };
    }

    /**
     * @return array{min: int, max: int}|null
     */
    public function itemCountRange(): ?array
    {
        return match ($this) {
            self::ReadInDailyLife => ['min' => 5, 'max' => 15],
            self::ReadAnAcademicPassage => ['min' => 5, 'max' => 15],
            self::ListenAndChooseAResponse => ['min' => 15, 'max' => 19],
            self::ListenToAnAnnouncement => ['min' => 6, 'max' => 10],
            self::ListenToAnAcademicTalk => ['min' => 8, 'max' => 16],
            default => null,
        };
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
            fn (self $type) => $type->section() === $normalized
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
