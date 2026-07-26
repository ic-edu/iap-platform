<?php

namespace App\Modules\Assessment\Models;

use App\Modules\QuestionBank\Models\Question;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestQuestion extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'test_questions';

    protected $fillable = [
        'test_section_id',
        'question_id',
        'order',
        'points',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'points' => 'integer',
        ];
    }

    /**
     * Get section.
     *
     * @return BelongsTo<TestSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(TestSection::class, 'test_section_id');
    }

    /**
     * Get underlying question from question bank.
     *
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}
