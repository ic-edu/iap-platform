<?php

namespace App\Modules\QuestionEngine\Providers;

use App\Modules\QuestionEngine\Contracts\QuestionGenerationProvider;
use App\Modules\QuestionEngine\DTO\GeneratedQuestionCandidate;
use App\Modules\QuestionEngine\DTO\GenerationProviderRequest;
use App\Modules\QuestionEngine\DTO\GenerationProviderResponse;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\GenerationErrorCode;
use Closure;

class FakeGenerationProvider implements QuestionGenerationProvider
{
    /**
     * @var list<GeneratedQuestionCandidate|array<string, mixed>>
     */
    protected array $predefinedCandidates = [];

    /**
     * @var (Closure(GenerationProviderRequest): (GenerationProviderResponse|GeneratedQuestionCandidate|array<string, mixed>))|null
     */
    protected ?Closure $customGenerator = null;

    protected ?GenerationErrorCode $errorToTrigger = null;

    protected ?string $errorMessageToTrigger = null;

    protected int $simulatedLatencyMs = 25;

    public function __construct(
        protected string $providerName = 'fake',
        protected string $modelName = 'fake-model-orchestrator-v1'
    ) {}

    public function getProviderName(): string
    {
        return $this->providerName;
    }

    public function setResponseGenerator(Closure $callback): self
    {
        $this->customGenerator = $callback;

        return $this;
    }

    /**
     * @param  list<GeneratedQuestionCandidate|array<string, mixed>>  $candidates
     */
    public function setPredefinedCandidates(array $candidates): self
    {
        $this->predefinedCandidates = $candidates;

        return $this;
    }

    public function addPredefinedCandidate(GeneratedQuestionCandidate|array $candidate): self
    {
        $this->predefinedCandidates[] = $candidate;

        return $this;
    }

    public function triggerError(GenerationErrorCode $code, string $message): self
    {
        $this->errorToTrigger = $code;
        $this->errorMessageToTrigger = $message;

        return $this;
    }

    public function setLatency(int $ms): self
    {
        $this->simulatedLatencyMs = $ms;

        return $this;
    }

    public function reset(): self
    {
        $this->predefinedCandidates = [];
        $this->customGenerator = null;
        $this->errorToTrigger = null;
        $this->errorMessageToTrigger = null;

        return $this;
    }

    public function generate(GenerationProviderRequest $request): GenerationProviderResponse
    {
        if ($this->errorToTrigger !== null) {
            $code = $this->errorToTrigger;
            $msg = $this->errorMessageToTrigger ?? $code->label();

            return GenerationProviderResponse::failure(
                errorCode: $code,
                errorMessage: $msg,
                providerName: $this->providerName,
                latencyMs: $this->simulatedLatencyMs,
                metadata: ['batch_id' => $request->batchId, 'item_id' => $request->itemId]
            );
        }

        if ($this->customGenerator !== null) {
            $result = ($this->customGenerator)($request);
            if ($result instanceof GenerationProviderResponse) {
                return $result;
            }
            if ($result instanceof GeneratedQuestionCandidate) {
                return GenerationProviderResponse::success(
                    providerName: $this->providerName,
                    modelName: $this->modelName,
                    content: $result->toArray(),
                    parsedPayload: $result->toArray(),
                    latencyMs: $this->simulatedLatencyMs,
                    tokenUsage: ['prompt_tokens' => 200, 'completion_tokens' => 150, 'total_tokens' => 350]
                );
            }
            if (is_array($result)) {
                return GenerationProviderResponse::success(
                    providerName: $this->providerName,
                    modelName: $this->modelName,
                    content: $result,
                    parsedPayload: $result,
                    latencyMs: $this->simulatedLatencyMs,
                    tokenUsage: ['prompt_tokens' => 200, 'completion_tokens' => 150, 'total_tokens' => 350]
                );
            }
        }

        if (!empty($this->predefinedCandidates)) {
            $candidate = array_shift($this->predefinedCandidates);
            $candidateArray = $candidate instanceof GeneratedQuestionCandidate ? $candidate->toArray() : (array) $candidate;

            return GenerationProviderResponse::success(
                providerName: $this->providerName,
                modelName: $this->modelName,
                content: $candidateArray,
                parsedPayload: $candidateArray,
                latencyMs: $this->simulatedLatencyMs,
                tokenUsage: ['prompt_tokens' => 220, 'completion_tokens' => 160, 'total_tokens' => 380]
            );
        }

        // Deterministic synthetic fallback based on prompt composition target metadata
        $candidateData = $this->synthesizeCandidate($request);

        return GenerationProviderResponse::success(
            providerName: $this->providerName,
            modelName: $this->modelName,
            content: $candidateData,
            parsedPayload: $candidateData,
            latencyMs: $this->simulatedLatencyMs,
            tokenUsage: ['prompt_tokens' => 250, 'completion_tokens' => 180, 'total_tokens' => 430]
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function synthesizeCandidate(GenerationProviderRequest $request): array
    {
        $meta = $request->promptComposition->targetMetadata;
        $family = $meta['assessment_family'] ?? AssessmentFamily::Toeic->value;
        $part = isset($meta['part_number']) ? (int) $meta['part_number'] : null;
        $task = $meta['task_type'] ?? null;
        $seq = $request->slotSequence;

        if ($family === AssessmentFamily::Toeic->value || $family === 'toeic') {
            return $this->synthesizeToeicCandidate($part ?? 5, $seq, $meta);
        }

        return $this->synthesizeToeflCandidate($task ?? 'read_in_daily_life', $seq, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function synthesizeToeicCandidate(int $part, int $seq, array $meta): array
    {
        $domain = $meta['domain'] ?? 'general_business';
        $difficulty = $meta['difficulty'] ?? 'medium';
        $construct = $meta['construct'] ?? ($part === 1 ? 'visual_description' : ($part === 2 ? 'intent' : 'detail'));
        $proficiency = $meta['proficiency_target'] ?? 'b2';
        $standardVersion = $meta['standard_version'] ?? '2026.1';
        $section = $part <= 4 ? 'listening' : 'reading';

        if ($part === 1) {
            return [
                'schema_version' => 'generated_question_candidate_v1',
                'assessment_family' => AssessmentFamily::Toeic->value,
                'standard_version' => $standardVersion,
                'section' => $section,
                'part_number' => 1,
                'construct' => $construct,
                'proficiency_target' => $proficiency,
                'difficulty' => $difficulty,
                'prompt' => 'Look at the photograph and choose the statement that best describes what you see.',
                'passage_text' => null,
                'audio_script' => '(A) A man is carrying a briefcase across the plaza. (B) A man is sitting on a park bench reading. (C) Several cyclists are waiting at a traffic signal. (D) Construction workers are repairing the road.',
                'choices' => [
                    ['label' => 'A', 'content' => 'A man is carrying a briefcase across the plaza.', 'is_correct' => true, 'explanation' => 'Option A accurately describes the primary action in the image.'],
                    ['label' => 'B', 'content' => 'A man is sitting on a park bench reading.', 'is_correct' => false, 'explanation' => 'No one is sitting on a bench.'],
                    ['label' => 'C', 'content' => 'Several cyclists are waiting at a traffic signal.', 'is_correct' => false, 'explanation' => 'No bicycles are visible.'],
                    ['label' => 'D', 'content' => 'Construction workers are repairing the road.', 'is_correct' => false, 'explanation' => 'No construction is taking place.'],
                ],
                'correct_answer' => 'A',
                'explanation' => 'Option A is the only statement that correctly matches the visual scene.',
                'metadata' => [
                    'part_number' => 1,
                    'domain' => $domain,
                    'difficulty' => $difficulty,
                    'construct' => $construct,
                ],
            ];
        }

        if ($part === 2) {
            return [
                'schema_version' => 'generated_question_candidate_v1',
                'assessment_family' => AssessmentFamily::Toeic->value,
                'standard_version' => $standardVersion,
                'section' => $section,
                'part_number' => 2,
                'construct' => $construct,
                'proficiency_target' => $proficiency,
                'difficulty' => $difficulty,
                'prompt' => 'When will the annual corporate budget report be finalized?',
                'passage_text' => null,
                'audio_script' => "M: When will the annual corporate budget report be finalized?\n(A) By next Tuesday afternoon.\n(B) Yes, it was very expensive.\n(C) In the second-floor conference room.",
                'choices' => [
                    ['label' => 'A', 'content' => 'By next Tuesday afternoon.', 'is_correct' => true, 'explanation' => 'Directly and logically answers the "When" question.'],
                    ['label' => 'B', 'content' => 'Yes, it was very expensive.', 'is_correct' => false, 'explanation' => 'Inappropriate yes/no response to a WH-question.'],
                    ['label' => 'C', 'content' => 'In the second-floor conference room.', 'is_correct' => false, 'explanation' => 'Answers "Where" instead of "When".'],
                ],
                'correct_answer' => 'A',
                'explanation' => 'A temporal response is required for a question starting with "When".',
                'metadata' => [
                    'part_number' => 2,
                    'domain' => $domain,
                    'difficulty' => $difficulty,
                    'construct' => $construct,
                ],
            ];
        }

        if ($part === 3 || $part === 4) {
            return [
                'schema_version' => 'generated_question_candidate_v1',
                'assessment_family' => AssessmentFamily::Toeic->value,
                'standard_version' => $standardVersion,
                'section' => $section,
                'part_number' => $part,
                'construct' => $construct,
                'proficiency_target' => $proficiency,
                'difficulty' => $difficulty,
                'prompt' => 'What is the main topic of the discussion?',
                'passage_text' => null,
                'audio_script' => "Speaker A: Good morning, Sarah. Have you had a chance to review the vendor proposals for the upcoming cloud migration?\nSpeaker B: Yes, Mark. TechCore's bid looks promising, but their implementation timeline is slightly longer than we anticipated.",
                'choices' => [
                    ['label' => 'A', 'content' => 'Reviewing vendor proposals for cloud migration', 'is_correct' => true, 'explanation' => 'Speaker A clearly establishes the agenda regarding cloud migration vendors.'],
                    ['label' => 'B', 'content' => 'Planning the office relocation party', 'is_correct' => false, 'explanation' => 'No mention of office relocation.'],
                    ['label' => 'C', 'content' => 'Hiring additional customer support staff', 'is_correct' => false, 'explanation' => 'No mention of hiring.'],
                    ['label' => 'D', 'content' => 'Canceling the quarterly shareholder meeting', 'is_correct' => false, 'explanation' => 'Unrelated to shareholders.'],
                ],
                'correct_answer' => 'A',
                'explanation' => 'The speakers discuss evaluating bids from vendors for cloud migration.',
                'metadata' => [
                    'part_number' => $part,
                    'domain' => $domain,
                    'difficulty' => $difficulty,
                    'construct' => $construct,
                ],
            ];
        }

        if ($part === 6 || $part === 7) {
            return [
                'schema_version' => 'generated_question_candidate_v1',
                'assessment_family' => AssessmentFamily::Toeic->value,
                'standard_version' => $standardVersion,
                'section' => $section,
                'part_number' => $part,
                'construct' => $construct,
                'proficiency_target' => $proficiency,
                'difficulty' => $difficulty,
                'prompt' => 'According to the notice, why is the main lobby entrance temporarily closed?',
                'passage_text' => "MEMORANDUM\n\nTo: All Employees\nFrom: Building Facilities Management\nDate: October 14\nSubject: Main Lobby Renovation\n\nPlease be advised that the main lobby entrance on Elm Street will be closed from October 18 through October 22 due to floor resurfacing and security barrier installations. During this period, all staff and visitors must enter via the North Courtyard entrance.\n\nWe apologize for any inconvenience.",
                'audio_script' => null,
                'choices' => [
                    ['label' => 'A', 'content' => 'Because of scheduled floor resurfacing and security upgrades', 'is_correct' => true, 'explanation' => 'Explicitly mentioned in the notice.'],
                    ['label' => 'B', 'content' => 'Due to severe weather conditions', 'is_correct' => false, 'explanation' => 'No mention of weather.'],
                    ['label' => 'C', 'content' => 'For an executive board private meeting', 'is_correct' => false, 'explanation' => 'No mention of a board meeting.'],
                    ['label' => 'D', 'content' => 'Because the building was permanently sold', 'is_correct' => false, 'explanation' => 'Incorrect assumption.'],
                ],
                'correct_answer' => 'A',
                'explanation' => 'The memo explains the closure is due to floor resurfacing and security installations.',
                'metadata' => [
                    'part_number' => $part,
                    'domain' => $domain,
                    'difficulty' => $difficulty,
                    'construct' => $construct,
                ],
            ];
        }

        // Part 5 Default
        return [
            'schema_version' => 'generated_question_candidate_v1',
            'assessment_family' => AssessmentFamily::Toeic->value,
            'standard_version' => $standardVersion,
            'section' => $section,
            'part_number' => 5,
            'construct' => $construct ?? 'grammar',
            'proficiency_target' => $proficiency,
            'difficulty' => $difficulty,
            'prompt' => 'The regional sales manager praised the procurement team for their _____ handling of international supplier negotiations.',
            'passage_text' => null,
            'audio_script' => null,
            'choices' => [
                ['label' => 'A', 'content' => 'efficient', 'is_correct' => true, 'explanation' => 'An adjective is required to modify the noun phrase "handling".'],
                ['label' => 'B', 'content' => 'efficiency', 'is_correct' => false, 'explanation' => 'Noun form is grammatically incorrect here.'],
                ['label' => 'C', 'content' => 'efficiently', 'is_correct' => false, 'explanation' => 'Adverb cannot directly modify noun "handling".'],
                ['label' => 'D', 'content' => 'efficiencies', 'is_correct' => false, 'explanation' => 'Plural noun cannot modify singular noun here.'],
            ],
            'correct_answer' => 'A',
            'explanation' => 'The adjective "efficient" correctly modifies the noun "handling".',
            'metadata' => [
                'part_number' => 5,
                'domain' => $domain,
                'difficulty' => $difficulty,
                'construct' => $construct ?? 'grammar',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function synthesizeToeflCandidate(string $taskType, int $seq, array $meta): array
    {
        $domain = $meta['domain'] ?? 'academic';
        $difficulty = $meta['difficulty'] ?? 'medium';
        $proficiency = $meta['proficiency_target'] ?? 'b2';
        $standardVersion = $meta['standard_version'] ?? '2026.1';
        $section = $meta['section'] ?? 'reading';
        $claim = $meta['claim'] ?? 'Claim 1 — Reading';
        $skill = $meta['skill'] ?? 'Reading for basic comprehension';

        if (in_array($taskType, ['read_an_academic_passage', 'read_in_daily_life', 'listen_to_an_academic_talk', 'listen_to_a_conversation', 'listen_and_choose_a_response'], true)) {
            return [
                'schema_version' => 'generated_question_candidate_v1',
                'assessment_family' => AssessmentFamily::ToeflIbt->value,
                'standard_version' => $standardVersion,
                'section' => $section,
                'task_type' => $taskType,
                'claim' => $claim,
                'skill' => $skill,
                'proficiency_target' => $proficiency,
                'difficulty' => $difficulty,
                'prompt' => 'In the passage, what does the author imply about biodiversity in temperate rainforests?',
                'passage_text' => 'Temperate rainforests occur in oceanic moist climates where high rainfall supports dense canopy vegetation. Unlike tropical counterparts, biomass in temperate forests is predominantly concentrated in long-lived coniferous trees rather than understory shrub diversity.',
                'audio_script' => null,
                'choices' => [
                    ['label' => 'A', 'content' => 'Most biomass is stored in large conifers rather than small understory plants.', 'is_correct' => true, 'explanation' => 'Directly matches the factual contrast in the text.'],
                    ['label' => 'B', 'content' => 'Temperate rainforests have higher total species count than tropical forests.', 'is_correct' => false, 'explanation' => 'Contradicted by the text.'],
                    ['label' => 'C', 'content' => 'High precipitation prevents large tree species from flourishing.', 'is_correct' => false, 'explanation' => 'Contradicted by the text.'],
                    ['label' => 'D', 'content' => 'Deciduous trees form the majority of the forest canopy.', 'is_correct' => false, 'explanation' => 'Text states conifers dominate.'],
                ],
                'correct_answer' => 'A',
                'explanation' => 'The passage states biomass is concentrated in long-lived conifers.',
                'metadata' => [
                    'task_type' => $taskType,
                    'domain' => $domain,
                    'difficulty' => $difficulty,
                ],
            ];
        }

        if (in_array($taskType, ['write_an_email', 'write_for_an_academic_discussion', 'build_a_sentence'], true)) {
            return [
                'schema_version' => 'generated_question_candidate_v1',
                'assessment_family' => AssessmentFamily::ToeflIbt->value,
                'standard_version' => $standardVersion,
                'section' => 'writing',
                'task_type' => $taskType,
                'claim' => $claim,
                'skill' => $skill,
                'proficiency_target' => $proficiency,
                'difficulty' => $difficulty,
                'prompt' => 'Your university professor has announced a proposal to replace all printed textbooks with open-source digital readings. Write an email to Professor Anderson expressing your perspective, outlining two benefits and one potential challenge of this transition.',
                'passage_text' => null,
                'audio_script' => null,
                'choices' => [],
                'correct_answer' => null,
                'explanation' => 'Assesses academic writing coherence, stance articulation, and register appropriateness.',
                'rubric' => "Score 5: Clearly addresses all prompt components with well-developed arguments, appropriate tone, and minor syntactic errors only.\nScore 4: Addresses main points with adequate development; occasional grammatical slips that do not obscure meaning.\nScore 3: Partially developed ideas or repetitive structures.\nScore 1-2: Inadequate response or severe language limitations.",
                'sample_response' => "Dear Professor Anderson,\n\nI am writing in response to the proposal regarding the transition to open-source digital course materials...",
                'metadata' => [
                    'task_type' => $taskType,
                    'domain' => $domain,
                    'difficulty' => $difficulty,
                ],
            ];
        }

        // Speaking tasks (listen_and_repeat, take_an_interview)
        return [
            'schema_version' => 'generated_question_candidate_v1',
            'assessment_family' => AssessmentFamily::ToeflIbt->value,
            'standard_version' => $standardVersion,
            'section' => 'speaking',
            'task_type' => $taskType,
            'claim' => $claim,
            'skill' => $skill,
            'proficiency_target' => $proficiency,
            'difficulty' => $difficulty,
            'prompt' => 'You will participate in a short simulated interview. Listen to the interviewer\'s question regarding your experience collaborating on academic group projects and provide your response.',
            'passage_text' => null,
            'audio_script' => 'Interviewer: Could you describe a time when you and your classmates had differing opinions on a project topic, and how you resolved that disagreement?',
            'choices' => [],
            'correct_answer' => null,
            'explanation' => 'Assesses spoken fluency, pronunciation clarity, vocabulary precision, and coherence.',
            'rubric' => "Score 4: Highly intelligible speech, natural pacing, effective discourse markers.\nScore 3: Generally intelligible with occasional hesitation or phonological inaccuracies.\nScore 2: Limited expression, frequent pauses affecting comprehensibility.\nScore 1: Insufficient intelligible speech.",
            'sample_response' => 'In my sophomore year biology seminar, our four-member team debated whether to focus our research poster on marine acidification or wetland restoration...',
            'metadata' => [
                'task_type' => $taskType,
                'domain' => $domain,
                'difficulty' => $difficulty,
            ],
        ];
    }
}
