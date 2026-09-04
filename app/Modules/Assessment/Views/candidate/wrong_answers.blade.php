<x-candidate-layout>
    <div class="max-w-5xl mx-auto pb-12">
        <!-- Top Navigation Header -->
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <a href="{{ route('candidate.review', $attempt) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-400 hover:text-indigo-300 transition-colors mb-2 cursor-pointer">
                    &larr; Back to Result
                </a>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-white flex items-center gap-3">
                    <span>Wrong Answer Review</span>
                </h1>
                <p class="text-xs text-slate-400 mt-1">
                    {{ $summary['test_title'] ?? 'Assessment Simulator' }} • Reviewing <strong class="text-rose-400 font-bold">{{ count($incorrectQuestionIds) }}</strong> incorrect {{ Str::plural('question', count($incorrectQuestionIds)) }}
                </p>
            </div>

            <!-- Badges -->
            <div class="flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-bold bg-amber-500/15 text-amber-400 border border-amber-500/30 shadow-sm">
                    <span>📖</span> SIMULATOR PRACTICE REVIEW
                </span>
                <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-bold bg-slate-800 text-slate-300 border border-slate-700">
                    READ-ONLY MODE
                </span>
            </div>
        </div>

        @if($wrongDeliveryUnits->isEmpty())
            <div class="p-12 rounded-2xl bg-slate-900 border border-slate-800 text-center shadow-md">
                <div class="text-4xl mb-3">🎉</div>
                <h3 class="text-lg font-bold text-white mb-1">Perfect Score!</h3>
                <p class="text-xs text-slate-400 mb-6">You answered all questions correctly in this assessment attempt. No incorrect answers to review.</p>
                <a href="{{ route('candidate.review', $attempt) }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-lg shadow-indigo-600/30 transition-all">
                    &larr; Return to Result Summary
                </a>
            </div>
        @else
            <!-- Summary Bar & Quick Palette -->
            <div class="mb-6 p-4 rounded-xl bg-slate-900 border border-slate-800 shadow-sm">
                <div class="flex items-center justify-between gap-3 mb-3 pb-2 border-b border-slate-800 flex-wrap">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">
                        Incorrect Questions Quick Navigation (AGN)
                    </span>
                    <span class="text-xs text-rose-400 font-bold">
                        {{ count($incorrectQuestionIds) }} / {{ $totalQuestionsCount }} Questions Missed
                    </span>
                </div>

                <div class="flex flex-wrap gap-2">
                    @foreach($wrongDeliveryUnits as $uIdx => $u)
                        @foreach($u['questions'] as $q)
                            @php
                                $isWrong = in_array($q->id, $incorrectQuestionIds, true);
                                $agn = $q->agn ?? ($q->canonical_global_number ?? ($q->test_question_order ?? 1));
                            @endphp
                            @if($isWrong)
                                <button type="button"
                                        onclick="scrollToReviewUnit({{ $uIdx }})"
                                        class="px-3 py-1.5 rounded-lg font-black text-xs bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 border border-rose-500/40 hover:border-rose-400 transition-all flex items-center gap-1 cursor-pointer">
                                    <span>Q{{ $agn }}</span>
                                    <span class="text-[10px] text-rose-400 font-normal">({{ $u['type'] === 'audio_group' ? 'Audio Group' : ($u['type'] === 'passage_group' ? 'Passage' : 'Part ' . ($u['part_number'] ?: 'Q')) }})</span>
                                </button>
                            @endif
                        @endforeach
                    @endforeach
                </div>
            </div>

            <!-- List of Incorrect Delivery Units -->
            <div class="space-y-8">
                @foreach($wrongDeliveryUnits as $uIdx => $unit)
                    @php
                        $uType = $unit['type'];
                        $isAudioGroup = ($uType === 'audio_group');
                        $isPassageGroup = ($uType === 'passage_group');
                        $section = $unit['section'];
                        $partNum = $unit['part_number'] ?? null;
                    @endphp

                    <div id="review-unit-card-{{ $uIdx }}" class="p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-md transition-all">
                        @if($isAudioGroup)
                            <!-- ============================================================= -->
                            <!-- AUDIO GROUP WRONG UNIT                                        -->
                            <!-- ============================================================= -->
                            @php
                                $groupTypeLabel = ucfirst($unit['group_type'] ?? ($partNum === 3 ? 'conversation' : 'talk'));
                                $firstQ = $unit['questions']->first();
                                $audioStreamUrl = $firstQ ? route('candidate.exam.audio-stream', [$attempt, $firstQ]) : null;
                            @endphp

                            <!-- Header -->
                            <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-5 flex-wrap gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-black text-indigo-400 uppercase tracking-wider">
                                        PART {{ $partNum }} — {{ strtoupper($groupTypeLabel) }} • {{ $unit['display_question_range'] }}
                                    </span>
                                    @if($section)
                                        <span class="text-slate-600">•</span>
                                        <span class="text-[11px] font-bold text-slate-400">{{ $section->title }}</span>
                                    @endif
                                </div>
                                <span class="px-2.5 py-0.5 rounded text-[10px] font-extrabold bg-indigo-950 text-indigo-300 border border-indigo-500/40 uppercase">
                                    Shared {{ $groupTypeLabel }} Group
                                </span>
                            </div>

                            <!-- Shared Audio Stimulus Player -->
                            <div class="mb-6 p-4 rounded-xl bg-indigo-950/20 border border-indigo-500/30">
                                <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
                                    <div class="flex items-center gap-1.5 text-xs font-bold text-indigo-400 uppercase tracking-wider">
                                        <span>🎧</span>
                                        <span>Shared {{ $groupTypeLabel }} Audio @if(!empty($partNum))(Part {{ $partNum }})@endif • {{ $unit['display_question_range'] }}</span>
                                    </div>
                                    <span class="text-[10px] font-bold text-indigo-300 bg-indigo-950/60 border border-indigo-500/30 px-2 py-0.5 rounded">
                                        Practice Audio Replay
                                    </span>
                                </div>

                                @if($unit['title'])
                                    <div class="text-xs text-slate-300 font-medium mb-3 italic">
                                        📌 Questions refer to: <strong class="text-white">{{ $unit['title'] }}</strong>
                                    </div>
                                @endif

                                @if($audioStreamUrl)
                                    <audio controls controlsList="nodownload noplaybackrate" data-exam-audio="true" class="w-full h-8" src="{{ $audioStreamUrl }}" preload="metadata"></audio>
                                @endif
                            </div>

                            <!-- Questions in this AudioGroup -->
                            <div class="space-y-6">
                                @foreach($unit['questions'] as $qIdx => $question)
                                    @php
                                        $agn = $question->agn ?? ($question->canonical_global_number ?? ($qIdx + 1));
                                        $answer = $answersByQuestion->get($question->id);
                                        $isWrong = in_array($question->id, $incorrectQuestionIds, true);
                                        $selectedChoiceId = $answer?->selected_choice_id;
                                        $correctChoice = $question->choices->firstWhere('is_correct', true);
                                        $selectedChoice = $question->choices->firstWhere('id', $selectedChoiceId);
                                    @endphp

                                    <div class="p-5 rounded-xl border {{ $isWrong ? 'border-rose-500/40 bg-rose-500/5' : 'border-slate-800 bg-slate-950/40 opacity-75' }}">
                                        <!-- Header for each question -->
                                        <div class="flex items-center justify-between gap-2 mb-3 pb-2 border-b border-slate-800 flex-wrap">
                                            <div class="flex items-center gap-2">
                                                <span class="w-7 h-7 rounded-lg bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-xs text-white">
                                                    Q{{ $agn }}
                                                </span>
                                                <span class="text-xs font-bold text-slate-300">Question {{ $agn }}</span>
                                            </div>

                                            @if($isWrong)
                                                <span class="inline-flex items-center gap-1 px-3 py-0.5 rounded-full text-xs font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30">
                                                    ✗ INCORRECT
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-3 py-0.5 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                                    ✓ CORRECT
                                                </span>
                                            @endif
                                        </div>

                                        <!-- Prompt -->
                                        @if(!empty($question->prompt))
                                            <div class="text-sm font-semibold text-white mb-4 leading-snug">
                                                {{ $question->prompt }}
                                            </div>
                                        @endif

                                        <!-- Choices -->
                                        <div class="space-y-2 mb-4">
                                            @foreach($question->choices as $choice)
                                                @php
                                                    $isSelected = ($selectedChoiceId === $choice->id);
                                                    $isChoiceCorrect = (bool) $choice->is_correct;
                                                    
                                                    $cStyle = 'bg-slate-950 border-slate-800 text-slate-400';
                                                    if ($isSelected && $isChoiceCorrect) {
                                                        $cStyle = 'bg-emerald-500/15 border-emerald-500/40 text-emerald-300 font-bold';
                                                    } elseif ($isSelected && !$isChoiceCorrect) {
                                                        $cStyle = 'bg-rose-500/15 border-rose-500/40 text-rose-300 font-bold';
                                                    } elseif (!$isSelected && $isChoiceCorrect) {
                                                        $cStyle = 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400 font-bold';
                                                    }
                                                @endphp
                                                <div class="p-3 rounded-lg border text-xs flex items-center justify-between {{ $cStyle }}">
                                                    <div class="flex items-center gap-2.5">
                                                        <span class="w-6 h-6 rounded bg-slate-900 border border-slate-700 flex items-center justify-center font-bold text-white shrink-0">
                                                            {{ $choice->label }}
                                                        </span>
                                                        <span>{{ $choice->content }}</span>
                                                    </div>

                                                    <div class="text-xs shrink-0 font-bold">
                                                        @if($isSelected && $isChoiceCorrect)
                                                            <span class="text-emerald-400">✓ Your Answer (Correct)</span>
                                                        @elseif($isSelected && !$isChoiceCorrect)
                                                            <span class="text-rose-400">✗ Your Answer (Incorrect)</span>
                                                        @elseif(!$isSelected && $isChoiceCorrect)
                                                            <span class="text-emerald-400">✓ Correct Answer</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>

                                        <!-- Rationale / Explanation -->
                                        @if(!empty($question->explanation))
                                            <div class="p-3.5 rounded-lg bg-indigo-950/20 border border-indigo-500/20 text-xs">
                                                <span class="font-bold text-indigo-300 block mb-1">💡 Explanation &amp; Rationale</span>
                                                <p class="text-slate-300 leading-relaxed">{{ $question->explanation }}</p>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                        @elseif($isPassageGroup)
                            <!-- ============================================================= -->
                            <!-- PASSAGE GROUP WRONG UNIT                                      -->
                            <!-- ============================================================= -->
                            @php
                                $effectivePassages = $unit['passages'];
                                $groupTitle = $unit['title'] ?? ($partNum === 6 ? 'Text Completion' : 'Reading Comprehension');
                            @endphp

                            <!-- Header -->
                            <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-5 flex-wrap gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-black text-indigo-400 uppercase tracking-wider">
                                        PART {{ $partNum }} — {{ strtoupper($groupTitle) }} • {{ $unit['display_question_range'] }}
                                    </span>
                                    @if($section)
                                        <span class="text-slate-600">•</span>
                                        <span class="text-[11px] font-bold text-slate-400">{{ $section->title }}</span>
                                    @endif
                                </div>
                                <span class="px-2.5 py-0.5 rounded text-[10px] font-extrabold bg-indigo-950 text-indigo-300 border border-indigo-500/40 uppercase">
                                    {{ $partNum === 6 ? 'Text Completion Group' : 'Passage Group' }}
                                </span>
                            </div>

                            <!-- Split View for Passage & Questions -->
                            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                                <!-- Left Pane: Passages & Documents -->
                                <div class="lg:col-span-6 bg-slate-950 border border-slate-800 rounded-xl p-5 flex flex-col min-h-[350px] max-h-[75vh] overflow-y-auto">
                                    <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-3 flex-wrap gap-2">
                                        <span class="text-xs font-bold text-indigo-400 uppercase tracking-wider flex items-center gap-1.5">
                                            <span>📄</span>
                                            <span>{{ ($unit['passage_type'] ?? '') ? ('Part ' . $partNum . ' ' . ucfirst($unit['passage_type']) . ' Passage') : 'Reading Passage' }}</span>
                                        </span>

                                        @if($effectivePassages->count() > 1)
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                @foreach($effectivePassages as $pIdx => $pass)
                                                    <button type="button"
                                                            onclick="switchReviewPassageDoc({{ $uIdx }}, {{ $pIdx }})"
                                                            id="rev-passage-tab-{{ $uIdx }}-{{ $pIdx }}"
                                                            class="rev-passage-doc-tab-{{ $uIdx }} text-[11px] font-bold px-2.5 py-1 rounded-lg border transition-all {{ $pIdx === 0 ? 'bg-indigo-600 text-white border-indigo-500' : 'bg-slate-900 text-slate-400 border-slate-800 hover:border-slate-700' }}">
                                                        {{ $pass->title ?: ('Doc ' . ($pIdx + 1)) }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    <div class="space-y-4 text-sm text-slate-200 leading-relaxed">
                                        @foreach($effectivePassages as $pIdx => $pass)
                                            @php
                                                $passImg = $pass->mediaAsset ? route('media.preview', $pass->mediaAsset->id) : $pass->getEffectiveImageUrl();
                                            @endphp
                                            <div id="rev-passage-doc-{{ $uIdx }}-{{ $pIdx }}" class="rev-passage-content-{{ $uIdx }} {{ $pIdx > 0 ? 'hidden' : '' }}">
                                                @if($pass->title && $effectivePassages->count() === 1)
                                                    <h4 class="font-bold text-sm text-indigo-300 mb-2 border-b border-slate-800 pb-1">{{ $pass->title }}</h4>
                                                @endif
                                                @if(!empty($passImg))
                                                    <div class="mb-4 text-center">
                                                        <img src="{{ $passImg }}" alt="Passage Attachment" class="max-w-full rounded-lg mx-auto border border-slate-800 shadow-md">
                                                    </div>
                                                @endif
                                                @if(!empty($pass->content))
                                                    <div class="prose prose-invert max-w-none text-slate-300 text-xs whitespace-pre-line leading-relaxed">
                                                        {!! nl2br(e($pass->content)) !!}
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Right Pane: Questions in PassageGroup -->
                                <div class="lg:col-span-6 space-y-6">
                                    @foreach($unit['questions'] as $qIdx => $question)
                                        @php
                                            $agn = $question->agn ?? ($question->canonical_global_number ?? ($qIdx + 1));
                                            $answer = $answersByQuestion->get($question->id);
                                            $isWrong = in_array($question->id, $incorrectQuestionIds, true);
                                            $selectedChoiceId = $answer?->selected_choice_id;
                                            $correctChoice = $question->choices->firstWhere('is_correct', true);
                                        @endphp

                                        <div class="p-5 rounded-xl border {{ $isWrong ? 'border-rose-500/40 bg-rose-500/5' : 'border-slate-800 bg-slate-950/40 opacity-75' }}">
                                            <div class="flex items-center justify-between gap-2 mb-3 pb-2 border-b border-slate-800 flex-wrap">
                                                <div class="flex items-center gap-2">
                                                    <span class="w-7 h-7 rounded-lg bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-xs text-white">
                                                        Q{{ $agn }}
                                                    </span>
                                                    <span class="text-xs font-bold text-slate-300">Question {{ $agn }}</span>
                                                </div>

                                                @if($isWrong)
                                                    <span class="inline-flex items-center gap-1 px-3 py-0.5 rounded-full text-xs font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30">
                                                        ✗ INCORRECT
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 px-3 py-0.5 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                                        ✓ CORRECT
                                                    </span>
                                                @endif
                                            </div>

                                            @if(!empty($question->prompt))
                                                <div class="text-sm font-semibold text-white mb-4 leading-snug">
                                                    {{ $question->prompt }}
                                                </div>
                                            @endif

                                            <!-- Choices -->
                                            <div class="space-y-2 mb-4">
                                                @foreach($question->choices as $choice)
                                                    @php
                                                        $isSelected = ($selectedChoiceId === $choice->id);
                                                        $isChoiceCorrect = (bool) $choice->is_correct;
                                                        
                                                        $cStyle = 'bg-slate-950 border-slate-800 text-slate-400';
                                                        if ($isSelected && $isChoiceCorrect) {
                                                            $cStyle = 'bg-emerald-500/15 border-emerald-500/40 text-emerald-300 font-bold';
                                                        } elseif ($isSelected && !$isChoiceCorrect) {
                                                            $cStyle = 'bg-rose-500/15 border-rose-500/40 text-rose-300 font-bold';
                                                        } elseif (!$isSelected && $isChoiceCorrect) {
                                                            $cStyle = 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400 font-bold';
                                                        }
                                                    @endphp
                                                    <div class="p-3 rounded-lg border text-xs flex items-center justify-between {{ $cStyle }}">
                                                        <div class="flex items-center gap-2.5">
                                                            <span class="w-6 h-6 rounded bg-slate-900 border border-slate-700 flex items-center justify-center font-bold text-white shrink-0">
                                                                {{ $choice->label }}
                                                            </span>
                                                            <span>{{ $choice->content }}</span>
                                                        </div>

                                                        <div class="text-xs shrink-0 font-bold">
                                                            @if($isSelected && $isChoiceCorrect)
                                                                <span class="text-emerald-400">✓ Your Answer (Correct)</span>
                                                            @elseif($isSelected && !$isChoiceCorrect)
                                                                <span class="text-rose-400">✗ Your Answer (Incorrect)</span>
                                                            @elseif(!$isSelected && $isChoiceCorrect)
                                                                <span class="text-emerald-400">✓ Correct Answer</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>

                                            @if(!empty($question->explanation))
                                                <div class="p-3.5 rounded-lg bg-indigo-950/20 border border-indigo-500/20 text-xs">
                                                    <span class="font-bold text-indigo-300 block mb-1">💡 Explanation &amp; Rationale</span>
                                                    <p class="text-slate-300 leading-relaxed">{{ $question->explanation }}</p>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                        @else
                            <!-- ============================================================= -->
                            <!-- STANDALONE QUESTION WRONG UNIT                                -->
                            <!-- ============================================================= -->
                            @php
                                $question = $unit['questions']->first();
                                $agn = $question->agn ?? ($question->canonical_global_number ?? 1);
                                $answer = $answersByQuestion->get($question->id);
                                $selectedChoiceId = $answer?->selected_choice_id;
                                $correctChoice = $question->choices->firstWhere('is_correct', true);

                                $imgMedia = $question->getEffectiveImageMedia();
                                $qImageUrl = $imgMedia ? route('media.preview', $imgMedia->id) : ($question->getEffectiveImageUrl() ?: null);
                                $audMedia = $question->getEffectiveAudioMedia();
                                $hasAudioSource = !empty($audMedia) || !empty($question->audio_media_asset_id) || !empty($question->audio_url);
                                $audioStreamUrl = $hasAudioSource ? route('candidate.exam.audio-stream', [$attempt, $question]) : null;
                            @endphp

                            <!-- Header -->
                            <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4 flex-wrap gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-8 h-8 rounded-lg bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-sm text-white">
                                        Q{{ $agn }}
                                    </span>
                                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">
                                        Question {{ $agn }} of {{ $totalQuestionsCount }} @if(!empty($partNum))• Part {{ $partNum }}@endif
                                    </span>
                                    @if($section)
                                        <span class="text-slate-600">•</span>
                                        <span class="text-[11px] font-bold text-slate-500">{{ $section->title }}</span>
                                    @endif
                                </div>

                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30">
                                    ✗ INCORRECT (0 / {{ $question->points ?? 1 }} pt)
                                </span>
                            </div>

                            <!-- Part 1 Photograph Stimulus -->
                            @if (!empty($qImageUrl))
                                <div class="mb-5 text-center">
                                    <img src="{{ $qImageUrl }}" alt="Question Stimulus Attachment" class="max-h-72 max-w-full rounded-xl mx-auto border border-slate-800 shadow-md object-contain">
                                </div>
                            @endif

                            <!-- Part 1 / Part 2 / Standalone Audio Stimulus Player -->
                            @if ($hasAudioSource && $audioStreamUrl)
                                <div class="mb-5 p-4 rounded-xl bg-slate-950 border border-slate-800 text-slate-200">
                                    <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
                                        <div class="flex items-center gap-1.5 text-xs font-bold text-indigo-400 uppercase tracking-wider">
                                            <span>🎧</span>
                                            <span>Question Audio Prompt (Practice Replay)</span>
                                        </div>
                                    </div>
                                    <audio controls controlsList="nodownload noplaybackrate" data-exam-audio="true" class="w-full h-8" src="{{ $audioStreamUrl }}" preload="metadata"></audio>
                                </div>
                            @endif

                            <!-- Question Prompt Text -->
                            @if(!empty($question->prompt))
                                <div class="text-base font-semibold text-white mb-4 leading-snug">
                                    {{ $question->prompt }}
                                </div>
                            @endif

                            <!-- Choices Options -->
                            @if($question->choices->isNotEmpty())
                                <div class="space-y-2 mb-4">
                                    @foreach($question->choices as $choice)
                                        @php
                                            $isSelected = ($selectedChoiceId === $choice->id);
                                            $isChoiceCorrect = (bool) $choice->is_correct;

                                            $choiceStyle = 'bg-slate-950 border-slate-800 text-slate-400';
                                            if ($isSelected && $isChoiceCorrect) {
                                                $choiceStyle = 'bg-emerald-500/15 border-emerald-500/40 text-emerald-300 font-bold';
                                            } elseif ($isSelected && !$isChoiceCorrect) {
                                                $choiceStyle = 'bg-rose-500/15 border-rose-500/40 text-rose-300 font-bold';
                                            } elseif (!$isSelected && $isChoiceCorrect) {
                                                $choiceStyle = 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400 font-bold';
                                            }
                                        @endphp

                                        <div class="p-3.5 rounded-lg border text-xs flex items-center justify-between {{ $choiceStyle }}">
                                            <div class="flex items-center gap-2.5">
                                                <span class="w-6 h-6 rounded bg-slate-900 border border-slate-700 flex items-center justify-center font-bold text-white shrink-0">
                                                    {{ $choice->label }}
                                                </span>
                                                <span>{{ $choice->content }}</span>
                                            </div>

                                            <div class="text-xs shrink-0 font-bold">
                                                @if($isSelected && $isChoiceCorrect)
                                                    <span class="text-emerald-400">✓ Your Answer (Correct)</span>
                                                @elseif($isSelected && !$isChoiceCorrect)
                                                    <span class="text-rose-400">✗ Your Answer (Incorrect)</span>
                                                @elseif(!$isSelected && $isChoiceCorrect)
                                                    <span class="text-emerald-400">✓ Correct Answer</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <!-- Textual response summary -->
                                <div class="grid sm:grid-cols-2 gap-3 mb-4 text-xs">
                                    <div class="p-3 rounded-lg bg-slate-950 border border-rose-500/30">
                                        <span class="block text-slate-400 text-[10px] uppercase mb-0.5">Your Response</span>
                                        <span class="font-bold text-rose-300">{{ $answer?->text_response ?: 'Not Answered' }}</span>
                                    </div>
                                    <div class="p-3 rounded-lg bg-slate-950 border border-emerald-500/30">
                                        <span class="block text-slate-400 text-[10px] uppercase mb-0.5">Expected Answer</span>
                                        <span class="font-bold text-emerald-400">{{ $question->explanation ?: 'See Explanation' }}</span>
                                    </div>
                                </div>
                            @endif

                            <!-- Rationale & Explanation Box -->
                            @if(!empty($question->explanation))
                                <div class="p-4 rounded-lg bg-indigo-950/20 border border-indigo-500/20 text-xs">
                                    <span class="font-bold text-indigo-300 block mb-1">💡 Explanation &amp; Rationale</span>
                                    <p class="text-slate-300 leading-relaxed">{{ $question->explanation }}</p>

                                    @if(!empty($answer?->feedback))
                                        <div class="mt-3 pt-3 border-t border-indigo-500/20 text-slate-300">
                                            <span class="font-bold text-amber-400 block mb-0.5">💬 Instructor Feedback</span>
                                            <p>{{ $answer->feedback }}</p>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>

            <!-- Footer Action Bar -->
            <div class="mt-10 pt-6 border-t border-slate-800 flex items-center justify-between flex-wrap gap-4">
                <a href="{{ route('candidate.review', $attempt) }}" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs transition-colors border border-slate-700 flex items-center gap-2 cursor-pointer">
                    &larr; Back to Result Summary
                </a>
                <a href="{{ route('candidate.portal') }}" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs transition-colors shadow-md cursor-pointer">
                    Return to Dashboard
                </a>
            </div>
        @endif
    </div>

    <!-- Candidate Exam Audio Manager & Review Scripts -->
    <script>
        // Universal Candidate Exam Audio Lifecycle Engine (Single-Active Audio Invariant)
        const CandidateExamAudioManager = (function () {
            let activeAudio = null;

            function getAllExamAudioElements() {
                return document.querySelectorAll('audio[data-exam-audio], audio');
            }

            function stopAll(options = {}) {
                const elements = getAllExamAudioElements();
                elements.forEach(audio => {
                    try {
                        if (!audio.paused) {
                            audio.pause();
                        }
                        audio.currentTime = 0;
                    } catch (err) {
                        console.error('CandidateExamAudioManager: error pausing audio', err);
                    }
                });
                activeAudio = null;
            }

            function handlePlayEvent(e) {
                if (e.target && e.target.tagName === 'AUDIO') {
                    const currentAudio = e.target;
                    activeAudio = currentAudio;
                    getAllExamAudioElements().forEach(audio => {
                        if (audio !== currentAudio && !audio.paused) {
                            try {
                                audio.pause();
                                audio.currentTime = 0;
                            } catch (err) {}
                        }
                    });
                }
            }

            function beforeDeliveryTransition(context = {}) {
                stopAll(context);
            }

            function init() {
                document.addEventListener('play', handlePlayEvent, true);
                window.addEventListener('beforeunload', () => stopAll());
                window.addEventListener('pagehide', () => stopAll());
            }

            init();

            return {
                stopAll: stopAll,
                getActiveAudio: () => activeAudio,
                beforeDeliveryTransition: beforeDeliveryTransition,
                getAllElements: getAllExamAudioElements
            };
        })();

        window.CandidateExamAudioManager = CandidateExamAudioManager;

        function scrollToReviewUnit(uIdx) {
            CandidateExamAudioManager.stopAll();
            const elem = document.getElementById('review-unit-card-' + uIdx);
            if (elem) {
                elem.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function switchReviewPassageDoc(uIdx, pIdx) {
            const tabs = document.querySelectorAll('.rev-passage-doc-tab-' + uIdx);
            tabs.forEach((tab, idx) => {
                if (idx === pIdx) {
                    tab.classList.remove('bg-slate-900', 'text-slate-400', 'border-slate-800');
                    tab.classList.add('bg-indigo-600', 'text-white', 'border-indigo-500');
                } else {
                    tab.classList.remove('bg-indigo-600', 'text-white', 'border-indigo-500');
                    tab.classList.add('bg-slate-900', 'text-slate-400', 'border-slate-800');
                }
            });

            const contents = document.querySelectorAll('.rev-passage-content-' + uIdx);
            contents.forEach((content, idx) => {
                if (idx === pIdx) {
                    content.classList.remove('hidden');
                } else {
                    content.classList.add('hidden');
                }
            });
        }
    </script>
</x-candidate-layout>
