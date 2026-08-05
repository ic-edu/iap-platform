<?php

namespace Database\Seeders;

use App\Models\AclAuditTrail;
use App\Models\AclCategory;
use App\Models\AclVersion;
use App\Models\User;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AclStarterLibrarySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Configurable Taxonomy Categories for all 17 institutional categories
        $categoriesData = [
            // TOEFL Libraries
            ['name' => 'TOEFL Listening Library', 'slug' => 'toefl-listening', 'test_type' => 'toefl', 'section_code' => 'listening', 'target_questions' => 50, 'icon' => '🎧', 'description' => 'Listening comprehension dialogues, conversations, and academic lectures.'],
            ['name' => 'TOEFL Structure Library', 'slug' => 'toefl-structure', 'test_type' => 'toefl', 'section_code' => 'structure', 'target_questions' => 40, 'icon' => '✍️', 'description' => 'Structure and written expression sentence completion and error identification.'],
            ['name' => 'TOEFL Reading Library', 'slug' => 'toefl-reading', 'test_type' => 'toefl', 'section_code' => 'reading', 'target_questions' => 60, 'icon' => '📖', 'description' => 'Academic reading passages with vocabulary and inference questions.'],

            // TOEIC Libraries
            ['name' => 'TOEIC Part 1: Photographs', 'slug' => 'toeic-part-1', 'test_type' => 'toeic', 'section_code' => 'part_1', 'target_questions' => 30, 'icon' => '🖼', 'description' => 'Photo description statements and image evaluation.'],
            ['name' => 'TOEIC Part 2: Question-Response', 'slug' => 'toeic-part-2', 'test_type' => 'toeic', 'section_code' => 'part_2', 'target_questions' => 30, 'icon' => '❓', 'description' => 'Direct spoken question and response options.'],
            ['name' => 'TOEIC Part 3: Conversations', 'slug' => 'toeic-part-3', 'test_type' => 'toeic', 'section_code' => 'part_3', 'target_questions' => 40, 'icon' => '💬', 'description' => 'Workplace dialogues and conversation comprehension.'],
            ['name' => 'TOEIC Part 4: Short Talks', 'slug' => 'toeic-part-4', 'test_type' => 'toeic', 'section_code' => 'part_4', 'target_questions' => 40, 'icon' => '📢', 'description' => 'Monologues, announcements, and office reports.'],
            ['name' => 'TOEIC Part 5: Incomplete Sentences', 'slug' => 'toeic-part-5', 'test_type' => 'toeic', 'section_code' => 'part_5', 'target_questions' => 50, 'icon' => '📝', 'description' => 'Grammar and vocabulary sentence fill-in.'],
            ['name' => 'TOEIC Part 6: Text Completion', 'slug' => 'toeic-part-6', 'test_type' => 'toeic', 'section_code' => 'part_6', 'target_questions' => 40, 'icon' => '📄', 'description' => 'Passage-level sentence and vocabulary completion.'],
            ['name' => 'TOEIC Part 7: Reading Passages', 'slug' => 'toeic-part-7', 'test_type' => 'toeic', 'section_code' => 'part_7', 'target_questions' => 60, 'icon' => '📰', 'description' => 'Single, double, and triple passage reading comprehension.'],

            // IELTS Libraries
            ['name' => 'IELTS Listening Library', 'slug' => 'ielts-listening', 'test_type' => 'ielts', 'section_code' => 'listening', 'target_questions' => 40, 'icon' => '🎧', 'description' => 'Social conversations and academic lecture listening.'],
            ['name' => 'IELTS Reading Library', 'slug' => 'ielts-reading', 'test_type' => 'ielts', 'section_code' => 'reading', 'target_questions' => 40, 'icon' => '📚', 'description' => 'General and academic reading text analysis.'],
            ['name' => 'IELTS Writing Tasks', 'slug' => 'ielts-writing', 'test_type' => 'ielts', 'section_code' => 'writing', 'target_questions' => 20, 'icon' => '✍️', 'description' => 'Task 1 data graph summaries and Task 2 essay prompts.'],
            ['name' => 'IELTS Speaking Prompts', 'slug' => 'ielts-speaking', 'test_type' => 'ielts', 'section_code' => 'speaking', 'target_questions' => 20, 'icon' => '🎙', 'description' => 'Part 1 intro questions, Part 2 cue cards, and Part 3 discussion prompts.'],

            // Institutional Foundational Libraries
            ['name' => 'Institutional Placement Test Bank', 'slug' => 'placement-test', 'test_type' => 'general', 'section_code' => 'placement', 'target_questions' => 50, 'icon' => '📊', 'description' => 'Diagnostic level evaluation questions for candidate streaming.'],
            ['name' => 'Core Grammar Mastery Library', 'slug' => 'core-grammar', 'test_type' => 'general', 'section_code' => 'grammar', 'target_questions' => 60, 'icon' => '🧩', 'description' => 'Tenses, conditionals, passives, and clause structure items.'],
            ['name' => 'Academic Vocabulary Repository', 'slug' => 'academic-vocabulary', 'test_type' => 'general', 'section_code' => 'vocabulary', 'target_questions' => 60, 'icon' => '🔤', 'description' => 'Academic Word List (AWL) collocations and synonyms.'],
        ];

        $categories = [];
        foreach ($categoriesData as $c) {
            $categories[$c['slug']] = AclCategory::updateOrCreate(
                ['slug' => $c['slug']],
                $c
            );
        }

        // Get Teacher user for creator attribution
        $teacher = User::role('teacher')->first() ?? User::factory()->create();

        // Definition of 17 Starter Repositories with 5 Representative Questions each
        $seedRepositories = [
            // 1. TOEFL Listening
            'toefl-listening' => [
                'bank' => [
                    'title' => 'TOEFL iBT Official Listening Starter Pool',
                    'slug'  => 'toefl-listening-core-starter',
                    'test_type' => 'toefl',
                    'description' => 'Official TOEFL listening dialogues, conversations, and academic lectures.',
                ],
                'questions' => [
                    [
                        'prompt' => 'According to the conversation, why does the student visit the professor?',
                        'type' => 'listening',
                        'difficulty' => 'medium',
                        'points' => 10,
                        'passage' => "Professor: Hello Mark, what can I do for you today?\nStudent: I am concerned about my term paper outline for Environmental Science.",
                        'explanation' => 'The student explicitly states he is concerned about his term paper outline.',
                        'choices' => [
                            ['A', 'To discuss his term paper outline', true],
                            ['B', 'To ask for a deadline extension', false],
                            ['C', 'To submit his final exam paper', false],
                            ['D', 'To request a reference letter', false],
                        ],
                    ],
                    [
                        'prompt' => 'What is the main topic of the biology lecture?',
                        'type' => 'listening',
                        'difficulty' => 'medium',
                        'points' => 10,
                        'passage' => 'Professor: Today we will explore how deep-sea organisms adapt to extreme pressure and zero sunlight.',
                        'explanation' => 'The professor opens by declaring deep-sea organism adaptations as the central topic.',
                        'choices' => [
                            ['A', 'Deep-sea organism pressure and light adaptations', true],
                            ['B', 'Photosynthesis in shallow coastal waters', false],
                            ['C', 'The history of submarine exploration', false],
                            ['D', 'Global marine temperature fluctuations', false],
                        ],
                    ],
                    [
                        'prompt' => 'Why does the professor mention hydrothermal vents?',
                        'type' => 'listening',
                        'difficulty' => 'hard',
                        'points' => 10,
                        'passage' => 'Professor: Unlike surface ecosystems relying on sunlight, hydrothermal vents supply chemosynthetic bacteria with chemical nutrients.',
                        'explanation' => 'Hydrothermal vents serve as an example of a non-solar energy source for chemosynthesis.',
                        'choices' => [
                            ['A', 'To illustrate an alternative chemical energy source', true],
                            ['B', 'To prove marine life cannot survive at high pressure', false],
                            ['C', 'To compare ocean currents with wind patterns', false],
                            ['D', 'To argue against geothermal energy investments', false],
                        ],
                    ],
                    [
                        'prompt' => 'What will the student probably do next after the advising session?',
                        'type' => 'listening',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'passage' => 'Advisor: First, revise your primary source list, then email me your draft by Thursday.',
                        'explanation' => 'The advisor explicitly instructs the student to revise the primary source list first.',
                        'choices' => [
                            ['A', 'Revise his primary source list', true],
                            ['B', 'Print out his final transcript', false],
                            ['C', 'Cancel his upcoming seminar', false],
                            ['D', 'Visit the campus health center', false],
                        ],
                    ],
                    [
                        'prompt' => 'What is the professor\'s attitude toward early astronomical theories?',
                        'type' => 'listening',
                        'difficulty' => 'hard',
                        'points' => 10,
                        'passage' => 'Professor: Although geocentric models proved inaccurate, their intricate geometric calculations demonstrated remarkable ingenuity.',
                        'explanation' => 'The professor expresses respect for the mathematical ingenuity of geocentric models.',
                        'choices' => [
                            ['A', 'Respectful of their mathematical ingenuity', true],
                            ['B', 'Dismissive of their unscientific methods', false],
                            ['C', 'Surprised by their modern instrumentation', false],
                            ['D', 'Indifferent to historical developments', false],
                        ],
                    ],
                ],
            ],

            // 2. TOEFL Structure
            'toefl-structure' => [
                'bank' => [
                    'title' => 'TOEFL iBT Structure & Written Expression Pool',
                    'slug'  => 'toefl-structure-starter-pool',
                    'test_type' => 'toefl',
                    'description' => 'Structure, inversion, parallel construction, and written expression grammar.',
                ],
                'questions' => [
                    [
                        'prompt' => 'Not until the 19th century _______ recognized as a distinct scientific discipline.',
                        'type' => 'multiple_choice',
                        'difficulty' => 'hard',
                        'points' => 10,
                        'explanation' => "Negative adverbial phrases like 'Not until' require auxiliary verb inversion (was geology).",
                        'choices' => [
                            ['A', 'was geology', true],
                            ['B', 'geology was', false],
                            ['C', 'geology had been', false],
                            ['D', 'did geology', false],
                        ],
                    ],
                    [
                        'prompt' => 'The committee recommended that the project manager _______ a revised budget proposal.',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 10,
                        'explanation' => "Subjunctive verbs following 'recommended that' use the base form of the verb (submit).",
                        'choices' => [
                            ['A', 'submit', true],
                            ['B', 'submits', false],
                            ['C', 'submitted', false],
                            ['D', 'will submit', false],
                        ],
                    ],
                    [
                        'prompt' => 'Photosynthesis is the process _______ green plants convert sunlight into chemical energy.',
                        'type' => 'multiple_choice',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'explanation' => "'by which' correctly connects the process with the mechanism of conversion.",
                        'choices' => [
                            ['A', 'by which', true],
                            ['B', 'which by', false],
                            ['C', 'that in', false],
                            ['D', 'where on', false],
                        ],
                    ],
                    [
                        'prompt' => 'Neither the research director nor the lab assistants _______ able to replicate the initial trial.',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'explanation' => "In 'neither...nor' structures, the verb agrees with the closer subject ('lab assistants' -> were).",
                        'choices' => [
                            ['A', 'were', true],
                            ['B', 'was', false],
                            ['C', 'has been', false],
                            ['D', 'is', false],
                        ],
                    ],
                    [
                        'prompt' => '_______ more abundant, solar energy would quickly replace fossil fuels globally.',
                        'type' => 'multiple_choice',
                        'difficulty' => 'hard',
                        'points' => 10,
                        'explanation' => "Inverted conditional without 'if' uses 'Were' + subject + adjective.",
                        'choices' => [
                            ['A', 'Were storage technology', true],
                            ['B', 'If storage technology', false],
                            ['C', 'Had storage technology', false],
                            ['D', 'Storage technology being', false],
                        ],
                    ],
                ],
            ],

            // 3. TOEFL Reading
            'toefl-reading' => [
                'bank' => [
                    'title' => 'TOEFL iBT Academic Reading Passage Repository',
                    'slug'  => 'toefl-reading-starter-pool',
                    'test_type' => 'toefl',
                    'description' => 'Academic reading passages with vocabulary, inference, and main idea questions.',
                ],
                'questions' => [
                    [
                        'prompt' => 'The word "subsequent" in paragraph 2 is closest in meaning to:',
                        'type' => 'multiple_choice',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'passage' => 'Initial volcanic eruptions deposited ash layers, while subsequent lava flows solidified into granite plateaus.',
                        'explanation' => "'Subsequent' means occurring after or following in order.",
                        'choices' => [
                            ['A', 'following', true],
                            ['B', 'previous', false],
                            ['C', 'simultaneous', false],
                            ['D', 'catastrophic', false],
                        ],
                    ],
                    [
                        'prompt' => 'Which of the following can be inferred about early agricultural settlements?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'hard',
                        'points' => 10,
                        'passage' => 'The transition from foraging to grain cultivation enabled permanent housing, requiring specialized irrigation management.',
                        'explanation' => 'Irrigation management implies social organization was necessary for communal water projects.',
                        'choices' => [
                            ['A', 'Communal water management required organized social structures', true],
                            ['B', 'Foraging communities produced greater food surpluses', false],
                            ['C', 'Grain cultivation eliminated regional trading networks', false],
                            ['D', 'Permanent housing reduced population density', false],
                        ],
                    ],
                    [
                        'prompt' => 'According to paragraph 3, what factor triggered the glacial retreat?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'passage' => 'Cyclical variations in Earth\'s orbital eccentricity reduced winter snow retention, initiating ice sheet retreat.',
                        'explanation' => 'The passage attributes ice sheet retreat directly to variations in orbital eccentricity.',
                        'choices' => [
                            ['A', 'Orbital eccentricity variations', true],
                            ['B', 'Decreased volcanic activity', false],
                            ['C', 'Oceanic salinity spikes', false],
                            ['D', 'Solar radiation decline', false],
                        ],
                    ],
                    [
                        'prompt' => 'Why does the author mention "carbon isotope ratios" in the passage?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'hard',
                        'points' => 10,
                        'passage' => 'Scientists analyzed carbon isotope ratios in fossilized teeth to reconstruct prehistoric hominid diets.',
                        'explanation' => 'Carbon isotope ratios are cited as scientific evidence for dietary reconstruction.',
                        'choices' => [
                            ['A', 'To explain how prehistoric diets are scientifically reconstructed', true],
                            ['B', 'To prove hominids consumed only wild grains', false],
                            ['C', 'To dispute radioisotope dating techniques', false],
                            ['D', 'To highlight dental decay rates in early mammals', false],
                        ],
                    ],
                    [
                        'prompt' => 'Which sentence best expresses the essential information in the highlighted sentence?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 10,
                        'passage' => 'Although industrial automation reduces manual assembly labor, it demands skilled technicians for system calibration.',
                        'explanation' => 'The essential meaning is that automation shifts labor demands from manual work to technical maintenance.',
                        'choices' => [
                            ['A', 'Automation shifts employment demand from manual labor to technical maintenance', true],
                            ['B', 'Manual assembly labor has been completely eliminated in modern factories', false],
                            ['C', 'Skilled technicians are no longer needed after system calibration', false],
                            ['D', 'Industrial production costs increase when technicians calibrate machinery', false],
                        ],
                    ],
                ],
            ],

            // 4. TOEIC Part 1 Photographs
            'toeic-part-1' => [
                'bank' => [
                    'title' => 'TOEIC Part 1 Photograph Evaluation Pool',
                    'slug'  => 'toeic-part-1-starter-pool',
                    'test_type' => 'toeic',
                    'description' => 'Image description evaluation for office, logistics, and industrial settings.',
                ],
                'questions' => [
                    [
                        'prompt' => 'Look at the image showing a man at a desk. Which statement best describes the photograph?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'image_url' => '/storage/images/toeic_part1_office.jpg',
                        'explanation' => "Statement A accurately describes the action of reviewing documents at a desk.",
                        'choices' => [
                            ['A', 'He is reviewing documents at a desk.', true],
                            ['B', 'He is repairing a computer monitor.', false],
                            ['C', 'He is packing boxes in a warehouse.', false],
                            ['D', 'He is painting a office wall.', false],
                        ],
                    ],
                    [
                        'prompt' => 'Look at the image of the conference room. Which statement best describes the photograph?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'image_url' => '/storage/images/toeic_part1_meeting.jpg',
                        'explanation' => "Statement B correctly describes people seated around a conference table.",
                        'choices' => [
                            ['A', 'Chairs are stacked against the wall.', false],
                            ['B', 'Participants are seated around a conference table.', true],
                            ['C', 'A presenter is erasing the whiteboard.', false],
                            ['D', 'The room is completely empty.', false],
                        ],
                    ],
                    [
                        'prompt' => 'Look at the image of the outdoor street venue. Which statement is correct?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'image_url' => '/storage/images/toeic_part1_street.jpg',
                        'explanation' => "Statement C describes pedestrians walking along a paved sidewalk.",
                        'choices' => [
                            ['A', 'Vehicles are parked inside a garage.', false],
                            ['B', 'Construction workers are digging a trench.', false],
                            ['C', 'Pedestrians are walking along a paved sidewalk.', true],
                            ['D', 'Bicycles are being loaded onto a truck.', false],
                        ],
                    ],
                    [
                        'prompt' => 'Look at the image of the shipping warehouse. Which statement is correct?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'image_url' => '/storage/images/toeic_part1_warehouse.jpg',
                        'explanation' => "Statement D correctly notes boxes stacked on wooden pallets.",
                        'choices' => [
                            ['A', 'Shelves are being disassembled.', false],
                            ['B', 'A worker is driving a passenger car.', false],
                            ['C', 'Containers are floating in the harbor.', false],
                            ['D', 'Cardboard boxes are stacked on wooden pallets.', true],
                        ],
                    ],
                    [
                        'prompt' => 'Look at the image of the laboratory bench. Which statement is correct?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'image_url' => '/storage/images/toeic_part1_lab.jpg',
                        'explanation' => "Statement A correctly describes a scientist adjusting lab equipment.",
                        'choices' => [
                            ['A', 'A researcher is adjusting scientific equipment.', true],
                            ['B', 'Medical supplies are being discarded.', false],
                            ['C', 'The laboratory floor is being swept.', false],
                            ['D', 'Glassware is stored in cardboard boxes.', false],
                        ],
                    ],
                ],
            ],

            // 5. TOEIC Part 2 Question-Response
            'toeic-part-2' => [
                'bank' => [
                    'title' => 'TOEIC Part 2 Question-Response Repository',
                    'slug'  => 'toeic-part-2-starter-pool',
                    'test_type' => 'toeic',
                    'description' => 'Spoken workplace question and response items with 3 options.',
                ],
                'questions' => [
                    [
                        'prompt' => 'Where will the quarterly sales conference be held this year?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'explanation' => "Option A directly answers a 'Where' location question.",
                        'choices' => [
                            ['A', 'At the Grand Hotel downtown.', true],
                            ['B', 'Yes, sales increased by ten percent.', false],
                            ['C', 'Every Monday morning.', false],
                        ],
                    ],
                    [
                        'prompt' => 'When is the project proposal deadline?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'explanation' => "Option B directly answers a 'When' time question.",
                        'choices' => [
                            ['A', 'In the main conference room.', false],
                            ['B', 'By 5:00 PM next Friday.', true],
                            ['C', 'Mr. Jenkins approved it.', false],
                        ],
                    ],
                    [
                        'prompt' => 'Who is responsible for ordering office supplies?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'explanation' => "Option C identifies the person responsible (Sarah).",
                        'choices' => [
                            ['A', 'Paper and printer ink.', false],
                            ['B', 'In the storage cabinet.', false],
                            ['C', 'Sarah from administrative services.', true],
                        ],
                    ],
                    [
                        'prompt' => 'Should we order lunch now or wait until everyone arrives?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'explanation' => "Option A answers an alternative choice question.",
                        'choices' => [
                            ['A', 'Let\'s wait until the rest of the team gets here.', true],
                            ['B', 'The restaurant is on Main Street.', false],
                            ['C', 'Yes, I had sandwiches.', false],
                        ],
                    ],
                    [
                        'prompt' => 'Why hasn\'t the revised contract been signed yet?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'explanation' => "Option B explains the reason for the delay.",
                        'choices' => [
                            ['A', 'On page three of the document.', false],
                            ['B', 'The legal department is still reviewing clause four.', true],
                            ['C', 'Yes, I received two copies.', false],
                        ],
                    ],
                ],
            ],

            // 6. TOEIC Part 3 Conversations
            'toeic-part-3' => [
                'bank' => [
                    'title' => 'TOEIC Part 3 Workplace Conversation Repository',
                    'slug'  => 'toeic-part-3-starter-pool',
                    'test_type' => 'toeic',
                    'description' => 'Multi-turn workplace dialogues and situational questions.',
                ],
                'questions' => [
                    [
                        'prompt' => 'What problem are the speakers discussing?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'passage' => "Man: Have you noticed the color printer on the second floor keeps jamming?\nWoman: Yes, I tried printing the financial handouts and the paper feed mechanism got stuck.",
                        'explanation' => 'The speakers discuss a malfunctioning paper feed on the office printer.',
                        'choices' => [
                            ['A', 'A malfunctioning office printer', true],
                            ['B', 'A delayed flight reservation', false],
                            ['C', 'An incorrect invoice total', false],
                            ['D', 'A missing employee badge', false],
                        ],
                    ],
                    [
                        'prompt' => 'What does the woman suggest doing?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'passage' => 'Woman: I think we should call the technician service helpline before the afternoon meeting.',
                        'explanation' => 'The woman explicitly suggests calling the technician service helpline.',
                        'choices' => [
                            ['A', 'Calling technical support service', true],
                            ['B', 'Buying a brand new printer model', false],
                            ['C', 'Postponing the afternoon meeting', false],
                            ['D', 'Emailing digital files to participants', false],
                        ],
                    ],
                    [
                        'prompt' => 'Where most likely do the speakers work?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'passage' => "Man: Welcome to Apex Logistics. Are you here for the supply chain orientation?\nWoman: Yes, I was hired for the inventory warehouse division.",
                        'explanation' => 'Keywords like Apex Logistics and inventory warehouse division indicate a logistics firm.',
                        'choices' => [
                            ['A', 'At a logistics and shipping company', true],
                            ['B', 'At a residential real estate agency', false],
                            ['C', 'At a public community hospital', false],
                            ['D', 'At a culinary training institute', false],
                        ],
                    ],
                    [
                        'prompt' => 'What does the man offer to do for the client?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'passage' => 'Man: If you would like, I can email you our updated product catalog with discounted bulk pricing.',
                        'explanation' => 'The man offers to email an updated product catalog with discounted pricing.',
                        'choices' => [
                            ['A', 'Email an updated product catalog', true],
                            ['B', 'Process a full refund immediately', false],
                            ['C', 'Schedule an in-person site visit', false],
                            ['D', 'Deliver the items by courier today', false],
                        ],
                    ],
                    [
                        'prompt' => 'Why is the woman calling the hotel front desk?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'passage' => 'Guest: Hello, I am calling from Room 402. I requested extra towels over an hour ago but haven\'t received them.',
                        'explanation' => 'The guest calls to follow up on an unfulfilled request for extra towels.',
                        'choices' => [
                            ['A', 'To follow up on an unfulfilled housekeeping request', true],
                            ['B', 'To extend her reservation stay by two nights', false],
                            ['C', 'To report a broken air conditioning unit', false],
                            ['D', 'To order room service breakfast', false],
                        ],
                    ],
                ],
            ],

            // 7. TOEIC Part 4 Short Talks
            'toeic-part-4' => [
                'bank' => [
                    'title' => 'TOEIC Part 4 Announcement & Monologue Pool',
                    'slug'  => 'toeic-part-4-starter-pool',
                    'test_type' => 'toeic',
                    'description' => 'Public announcements, radio broadcasts, and corporate presentations.',
                ],
                'questions' => [
                    [
                        'prompt' => 'Who is the intended audience for this announcement?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'passage' => 'Speaker: Attention passengers on Flight 408 to Chicago. Boarding will now begin at Gate B12.',
                        'explanation' => 'Keywords Flight 408 and Gate B12 indicate airline passengers.',
                        'choices' => [
                            ['A', 'Airline flight passengers', true],
                            ['B', 'Train station conductors', false],
                            ['C', 'Hotel conference attendees', false],
                            ['D', 'Store retail customers', false],
                        ],
                    ],
                    [
                        'prompt' => 'What change is being announced by the speaker?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'passage' => 'Speaker: Due to scheduled maintenance, the employee parking structure will be closed this weekend.',
                        'explanation' => 'The speaker announces the temporary closure of the employee parking structure.',
                        'choices' => [
                            ['A', 'Temporary closure of the parking structure', true],
                            ['B', 'An increase in cafeteria food prices', false],
                            ['C', 'A new corporate dress code policy', false],
                            ['D', 'Relocation of company headquarters', false],
                        ],
                    ],
                    [
                        'prompt' => 'According to the radio broadcast, what will happen tomorrow afternoon?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'passage' => 'Announcer: Expect heavy rainstorms and strong wind gusts across the metro area tomorrow afternoon.',
                        'explanation' => 'The weather report forecasts heavy rainstorms for tomorrow afternoon.',
                        'choices' => [
                            ['A', 'Heavy rainstorms across the metro area', true],
                            ['B', 'Record high summer temperatures', false],
                            ['C', 'Dense morning fog along coastlines', false],
                            ['D', 'Clear sunny skies all day', false],
                        ],
                    ],
                    [
                        'prompt' => 'What does the speaker invite listeners to do at the end of the tour?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'passage' => 'Guide: At the conclusion of our museum tour, please join us in the gift shop for complimentary refreshments.',
                        'explanation' => 'The guide invites listeners to enjoy complimentary refreshments in the gift shop.',
                        'choices' => [
                            ['A', 'Enjoy complimentary refreshments in the gift shop', true],
                            ['B', 'Fill out a visitor feedback survey online', false],
                            ['C', 'Watch a documentary film in the theater', false],
                            ['D', 'Meet the exhibit lead curator', false],
                        ],
                    ],
                    [
                        'prompt' => 'What is the topic of the workshop presentation?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'hard',
                        'points' => 10,
                        'passage' => 'Presenter: Today\'s session focuses on implementing cybersecurity protocols to safeguard client data.',
                        'explanation' => 'The presentation focuses on implementing cybersecurity protocols for data protection.',
                        'choices' => [
                            ['A', 'Cybersecurity protocols for data protection', true],
                            ['B', 'Effective social media marketing strategies', false],
                            ['C', 'Tax deduction rules for small businesses', false],
                            ['D', 'Office furniture ergonomic standards', false],
                        ],
                    ],
                ],
            ],

            // 8. TOEIC Part 5 Incomplete Sentences
            'toeic-part-5' => [
                'bank' => [
                    'title' => 'TOEIC Part 5 Workplace Grammar & Vocabulary Pool',
                    'slug'  => 'toeic-part-5-starter-pool',
                    'test_type' => 'toeic',
                    'description' => 'Business grammar, collocations, prepositions, and sentence completion.',
                ],
                'questions' => [
                    [
                        'prompt' => 'All annual financial reports must be submitted _______ 5:00 PM on Friday.',
                        'type' => 'multiple_choice',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'explanation' => "'before' correctly indicates the deadline requirement for Friday.",
                        'choices' => [
                            ['A', 'before', true],
                            ['B', 'until', false],
                            ['C', 'since', false],
                            ['D', 'during', false],
                        ],
                    ],
                    [
                        'prompt' => 'Ms. Davies worked _______ to complete the audit before the board meeting.',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'explanation' => "The adverb 'diligently' correctly modifies the verb 'worked'.",
                        'choices' => [
                            ['A', 'diligently', true],
                            ['B', 'diligence', false],
                            ['C', 'diligent', false],
                            ['D', 'more diligent', false],
                        ],
                    ],
                    [
                        'prompt' => 'The new software update is fully _______ with existing database systems.',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'explanation' => "'compatible' is the standard adjective pairing with 'with'.",
                        'choices' => [
                            ['A', 'compatible', true],
                            ['B', 'competent', false],
                            ['C', 'comparable', false],
                            ['D', 'composed', false],
                        ],
                    ],
                    [
                        'prompt' => 'Employees seeking tuition reimbursement must obtain prior _______ from their supervisor.',
                        'type' => 'multiple_choice',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'explanation' => "The noun 'approval' follows the adjective 'prior'.",
                        'choices' => [
                            ['A', 'approval', true],
                            ['B', 'approve', false],
                            ['C', 'approved', false],
                            ['D', 'approvingly', false],
                        ],
                    ],
                    [
                        'prompt' => 'Despite _______ severe weather conditions, the construction team finished on schedule.',
                        'type' => 'multiple_choice',
                        'difficulty' => 'hard',
                        'points' => 10,
                        'explanation' => "'Despite' takes a noun phrase ('severe weather conditions').",
                        'choices' => [
                            ['A', 'facing', true],
                            ['B', 'although', false],
                            ['C', 'even though', false],
                            ['D', 'whereas', false],
                        ],
                    ],
                ],
            ],

            // 9. TOEIC Part 6 Text Completion
            'toeic-part-6' => [
                'bank' => [
                    'title' => 'TOEIC Part 6 Text & Memo Completion Pool',
                    'slug'  => 'toeic-part-6-starter-pool',
                    'test_type' => 'toeic',
                    'description' => 'Contextual sentence and vocabulary completion in workplace memos and emails.',
                ],
                'questions' => [
                    [
                        'prompt' => 'Choose the best word to complete gap [1] in the email.',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'passage' => 'Dear Valued Client, We are pleased to announce that our office is [1] _______ to a larger facility next month.',
                        'explanation' => "'relocating' fits the context of moving an office facility.",
                        'choices' => [
                            ['A', 'relocating', true],
                            ['B', 'demolishing', false],
                            ['C', 'canceling', false],
                            ['D', 'exporting', false],
                        ],
                    ],
                    [
                        'prompt' => 'Choose the best sentence to insert into gap [2].',
                        'type' => 'multiple_choice',
                        'difficulty' => 'hard',
                        'points' => 10,
                        'passage' => 'Our phone numbers will remain unchanged. [2] _______. Thank you for your continued partnership.',
                        'explanation' => 'The inserted sentence reassures clients about business operations continuity.',
                        'choices' => [
                            ['A', 'Business operations will continue without interruption during the move.', true],
                            ['B', 'Our prices will double starting next fiscal quarter.', false],
                            ['C', 'Please return all borrowed equipment to human resources.', false],
                            ['D', 'The old building was destroyed in a fire.', false],
                        ],
                    ],
                    [
                        'prompt' => 'Choose the correct transitional word for gap [3].',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'passage' => 'We experienced shipping delays last week. [3] _______, all pending customer orders have now been dispatched.',
                        'explanation' => "'However' correctly expresses contrast between delays and current dispatch status.",
                        'choices' => [
                            ['A', 'However', true],
                            ['B', 'Furthermore', false],
                            ['C', 'For instance', false],
                            ['D', 'Consequently', false],
                        ],
                    ],
                    [
                        'prompt' => 'Select the appropriate verb tense for gap [4].',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'passage' => 'Since 2018, our engineering team [4] _______ over fifty eco-friendly patents.',
                        'explanation' => "'has registered' is present perfect required by 'Since 2018'.",
                        'choices' => [
                            ['A', 'has registered', true],
                            ['B', 'registers', false],
                            ['C', 'registered', false],
                            ['D', 'will register', false],
                        ],
                    ],
                    [
                        'prompt' => 'Choose the best adjective for gap [5].',
                        'type' => 'multiple_choice',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'passage' => 'All employees are required to attend a [5] _______ safety orientation seminar.',
                        'explanation' => "'mandatory' fits the requirement context of employee attendance.",
                        'choices' => [
                            ['A', 'mandatory', true],
                            ['B', 'optional', false],
                            ['C', 'accidental', false],
                            ['D', 'hesitant', false],
                        ],
                    ],
                ],
            ],

            // 10. TOEIC Part 7 Reading Passages
            'toeic-part-7' => [
                'bank' => [
                    'title' => 'TOEIC Part 7 Reading Comprehension Repository',
                    'slug'  => 'toeic-part-7-starter-pool',
                    'test_type' => 'toeic',
                    'description' => 'Single, double, and triple passage corporate reading comprehension.',
                ],
                'questions' => [
                    [
                        'prompt' => 'What is the purpose of the email?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'passage' => 'To: All Staff\nFrom: HR Department\nSubject: Revised Remote Work Policy\nPlease review the attached document outlining updated remote work eligibility criteria effective September 1st.',
                        'explanation' => 'The email informs staff of updates to the remote work policy.',
                        'choices' => [
                            ['A', 'To inform employees about updated remote work policies', true],
                            ['B', 'To advertise a job vacancy in human resources', false],
                            ['C', 'To confirm a reservation for the annual party', false],
                            ['D', 'To announce the retirement of the CEO', false],
                        ],
                    ],
                    [
                        'prompt' => 'When will the new policy take effect?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'passage' => '...effective September 1st.',
                        'explanation' => 'The passage explicitly states effective September 1st.',
                        'choices' => [
                            ['A', 'On September 1st', true],
                            ['B', 'Immediately today', false],
                            ['C', 'At the end of the year', false],
                            ['D', 'Next spring quarter', false],
                        ],
                    ],
                    [
                        'prompt' => 'What requirement is mentioned for remote work eligibility?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'passage' => 'Staff members must complete six months of continuous service prior to applying for telecommuting approval.',
                        'explanation' => 'Six months of continuous service is required prior to applying.',
                        'choices' => [
                            ['A', 'Six months of continuous service at the company', true],
                            ['B', 'Ownership of a personal laptop and printer', false],
                            ['C', 'Completion of a master\'s degree in management', false],
                            ['D', 'Willingness to work weekend evening shifts', false],
                        ],
                    ],
                    [
                        'prompt' => 'In the press release, the word "pioneering" in paragraph 2 is closest in meaning to:',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'passage' => 'BioTech Corp unveiled its pioneering gene-editing platform at the international summit.',
                        'explanation' => "'Pioneering' in this context means innovative or ground-breaking.",
                        'choices' => [
                            ['A', 'innovative', true],
                            ['B', 'expensive', false],
                            ['C', 'temporary', false],
                            ['D', 'traditional', false],
                        ],
                    ],
                    [
                        'prompt' => 'What is suggested about BioTech Corp\'s new product in the second document?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'hard',
                        'points' => 10,
                        'passage' => 'Document 2 (Market Analysis): Clinical trial approvals granted last month position BioTech Corp to enter European markets by Q3.',
                        'explanation' => 'Clinical trial approvals enable entry into European markets by Q3.',
                        'choices' => [
                            ['A', 'It has received regulatory approval for European expansion', true],
                            ['B', 'It was recalled due to manufacturing defects', false],
                            ['C', 'It is cheaper than competitor products', false],
                            ['D', 'It requires government subsidies to produce', false],
                        ],
                    ],
                ],
            ],

            // 11. IELTS Listening
            'ielts-listening' => [
                'bank' => [
                    'title' => 'IELTS Listening Social & Academic Pool',
                    'slug'  => 'ielts-listening-starter-pool',
                    'test_type' => 'ielts',
                    'description' => 'Social dialogue, form completion, and academic lecture listening items.',
                ],
                'questions' => [
                    [
                        'prompt' => 'What is the student\'s primary reason for calling the housing office?',
                        'type' => 'listening',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'passage' => 'Caller: Hello, I\'d like to inquire about single-room availability in university accommodation for next term.',
                        'explanation' => 'The caller inquires about single-room university accommodation availability.',
                        'choices' => [
                            ['A', 'To inquire about university room availability', true],
                            ['B', 'To report a noise complaint against a roommate', false],
                            ['C', 'To pay tuition fees via credit card', false],
                            ['D', 'To book a parking permit for his vehicle', false],
                        ],
                    ],
                    [
                        'prompt' => 'According to the speaker, what time does the campus library close on Friday evenings?',
                        'type' => 'listening',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'passage' => 'Librarian: Please note our weekend schedule: Monday through Thursday we close at 10 PM, but on Friday we close early at 8 PM.',
                        'explanation' => 'The librarian states Friday closing time is 8 PM.',
                        'choices' => [
                            ['A', '8:00 PM', true],
                            ['B', '10:00 PM', false],
                            ['C', 'Midnight', false],
                            ['D', '6:00 PM', false],
                        ],
                    ],
                    [
                        'prompt' => 'Which facility is currently undergoing renovation on campus?',
                        'type' => 'listening',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'passage' => 'Guide: The main sports center pool is closed for tile refurbishment, so swimming classes meet at the East Annex.',
                        'explanation' => 'The sports center pool is closed for tile refurbishment.',
                        'choices' => [
                            ['A', 'The sports center swimming pool', true],
                            ['B', 'The central dining hall', false],
                            ['C', 'The student union bookstore', false],
                            ['D', 'The science auditorium', false],
                        ],
                    ],
                    [
                        'prompt' => 'What does the lecturer emphasize about urban bee populations?',
                        'type' => 'listening',
                        'difficulty' => 'hard',
                        'points' => 10,
                        'passage' => 'Lecturer: Surprisingly, urban wildflower gardens provide greater floral diversity throughout autumn than rural monoculture farms.',
                        'explanation' => 'Urban gardens offer superior floral diversity compared to rural monocultures.',
                        'choices' => [
                            ['A', 'Urban gardens offer greater floral diversity than rural farms', true],
                            ['B', 'Pesticide levels are higher in city parks than farmland', false],
                            ['C', 'Honey production has doubled in suburban areas', false],
                            ['D', 'Wild bees prefer nesting in concrete structures', false],
                        ],
                    ],
                    [
                        'prompt' => 'Complete the note: The guest speaker\'s research focuses on _______ restoration.',
                        'type' => 'short_answer',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'passage' => 'Host: Dr. Aris Thorne has dedicated twenty years to coastal wetland restoration.',
                        'explanation' => "'coastal wetland' is the exact restoration focus mentioned.",
                        'choices' => [],
                    ],
                ],
            ],

            // 12. IELTS Reading
            'ielts-reading' => [
                'bank' => [
                    'title' => 'IELTS Reading General & Academic Passage Pool',
                    'slug'  => 'ielts-reading-starter-pool',
                    'test_type' => 'ielts',
                    'description' => 'Academic passage analysis, True/False/Not Given, and heading matching.',
                ],
                'questions' => [
                    [
                        'prompt' => 'Do the following statement agree with the passage information? "Geothermal energy plants emit zero greenhouse gases during operation."',
                        'type' => 'true_false',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'passage' => 'While geothermal power generates substantially lower carbon emissions than fossil fuels, trace amounts of hydrogen sulfide and carbon dioxide are vented during steam extraction.',
                        'explanation' => "False, because the passage states trace amounts of carbon dioxide are vented.",
                        'choices' => [
                            ['A', 'FALSE', true],
                            ['B', 'TRUE', false],
                            ['C', 'NOT GIVEN', false],
                        ],
                    ],
                    [
                        'prompt' => 'Which heading best summarizes Paragraph B?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'hard',
                        'points' => 10,
                        'passage' => 'Paragraph B details how ancient Mesopotamians built clay tablets for financial accounting, trade records, and legal contracts.',
                        'explanation' => "'Early administrative and economic record-keeping' captures the paragraph focus.",
                        'choices' => [
                            ['A', 'Early administrative and economic record-keeping', true],
                            ['B', 'The decline of Mesopotamian religious architecture', false],
                            ['C', 'Modern archaeological excavation techniques', false],
                            ['D', 'Trade conflicts between Mediterranean city-states', false],
                        ],
                    ],
                    [
                        'prompt' => 'According to the passage, why are coral reefs vulnerable to ocean acidification?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'passage' => 'Increased carbonic acid concentration reduces available carbonate ions, hindering corals from building calcium carbonate skeletons.',
                        'explanation' => 'Acidification reduces carbonate ions required for skeletal formation.',
                        'choices' => [
                            ['A', 'Reduced carbonate ions hinder skeletal formation', true],
                            ['B', 'Rising water temperatures bleach algae pigments', false],
                            ['C', 'Overfishing depletes herbivorous fish populations', false],
                            ['D', 'Industrial runoff blocks sunlight penetration', false],
                        ],
                    ],
                    [
                        'prompt' => 'Is the statement TRUE, FALSE, or NOT GIVEN? "The manuscript was discovered in an underground vault in 1912."',
                        'type' => 'true_false',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'passage' => 'In 1912, rare book dealer Wilfrid Voynich acquired the mysterious cipher manuscript from a Jesuit college near Rome.',
                        'explanation' => "NOT GIVEN, as the passage states it was acquired from a college, but does not state an underground vault.",
                        'choices' => [
                            ['A', 'NOT GIVEN', true],
                            ['B', 'TRUE', false],
                            ['C', 'FALSE', false],
                        ],
                    ],
                    [
                        'prompt' => 'What is the writer\'s main conclusion regarding renewable energy subsidies?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'hard',
                        'points' => 10,
                        'passage' => 'Subsidies must transition from initial deployment incentives toward grid modernization and storage infrastructure.',
                        'explanation' => 'Subsidies should shift toward grid modernization and storage infrastructure.',
                        'choices' => [
                            ['A', 'Subsidies should shift toward grid modernization and storage', true],
                            ['B', 'All government energy subsidies should be abolished immediately', false],
                            ['C', 'Fossil fuel subsidies are essential for economic growth', false],
                            ['D', 'Nuclear power requires double the current funding', false],
                        ],
                    ],
                ],
            ],

            // 13. IELTS Writing
            'ielts-writing' => [
                'bank' => [
                    'title' => 'IELTS Academic Writing Task 1 & Task 2 Repository',
                    'slug'  => 'ielts-writing-starter-pool',
                    'test_type' => 'ielts',
                    'description' => 'Task 1 data summaries and Task 2 essay prompts with model answers and band criteria.',
                ],
                'questions' => [
                    [
                        'prompt' => 'Task 2 Essay Prompt: Some people believe university education should be free for all students. To what extent do you agree or disagree?',
                        'type' => 'essay',
                        'difficulty' => 'hard',
                        'points' => 20,
                        'passage' => 'Band Target: Band 7.5+\nKey Criteria: Task Achievement, Coherence & Cohesion, Lexical Resource, Grammatical Range.',
                        'explanation' => 'Model Outline: Introduction with thesis statement; Body Paragraph 1 (economic benefits of free higher education); Body Paragraph 2 (financial strain on taxpayers / necessity of merit-based funding); Conclusion summarizing opinion.',
                        'choices' => [],
                    ],
                    [
                        'prompt' => 'Task 1 Data Summary: The chart below shows renewable energy consumption in five countries between 2010 and 2020. Summarize the information by selecting and reporting the main features.',
                        'type' => 'writing',
                        'difficulty' => 'medium',
                        'points' => 15,
                        'passage' => 'Graph Type: Line Graph (Percentage shares over 10-year period).',
                        'explanation' => 'Model Structure: Introduction (paraphrase prompt); Overview (overall upward trend across all nations); Body Paragraph 1 (leading countries Sweden and Norway); Body Paragraph 2 (comparing UK, Germany, and France growth rates).',
                        'choices' => [],
                    ],
                    [
                        'prompt' => 'Task 2 Essay Prompt: In many countries, traditional food culture is being replaced by fast food. What are the causes of this trend, and what are its effects on society?',
                        'type' => 'essay',
                        'difficulty' => 'hard',
                        'points' => 20,
                        'passage' => 'Essay Type: Cause and Effect Essay.',
                        'explanation' => 'Model Outline: Intro; Body 1 (Causes: fast-paced urban lifestyles, convenience, aggressive marketing); Body 2 (Effects: rising obesity rates, loss of culinary heritage); Conclusion with recommendations.',
                        'choices' => [],
                    ],
                    [
                        'prompt' => 'Task 1 Process Diagram: The diagram illustrates the industrial process of desalinating seawater into drinking water. Describe the main stages.',
                        'type' => 'writing',
                        'difficulty' => 'hard',
                        'points' => 15,
                        'passage' => 'Diagram Type: Linear Industrial Process.',
                        'explanation' => 'Model Outline: Intro paraphrase; Overview (multi-stage process involving pre-filtration, reverse osmosis, mineral rebalancing); Body 1 (intake and pre-treatment stages); Body 2 (membrane pressure filtration and final distribution).',
                        'choices' => [],
                    ],
                    [
                        'prompt' => 'Task 2 Essay Prompt: Artificial intelligence is increasingly performing tasks previously done by humans. Do the advantages of this development outweigh the disadvantages?',
                        'type' => 'essay',
                        'difficulty' => 'hard',
                        'points' => 20,
                        'passage' => 'Essay Type: Advantages vs Disadvantages Evaluation.',
                        'explanation' => 'Model Outline: Intro; Body 1 (Disadvantages: job displacement, ethical risks); Body 2 (Advantages: efficiency, medical diagnostic accuracy, productivity gains); Conclusion arguing advantages outweigh disadvantages if regulated.',
                        'choices' => [],
                    ],
                ],
            ],

            // 14. IELTS Speaking
            'ielts-speaking' => [
                'bank' => [
                    'title' => 'IELTS Speaking Interview & Cue Card Prompts',
                    'slug'  => 'ielts-speaking-starter-pool',
                    'test_type' => 'ielts',
                    'description' => 'Part 1 intro questions, Part 2 cue cards, and Part 3 discussion prompts with model notes.',
                ],
                'questions' => [
                    [
                        'prompt' => 'Part 2 Cue Card: Describe a memorable journey you took by public transport. You should say: where you went, what transport you used, who you went with, and explain why it was memorable.',
                        'type' => 'speaking',
                        'difficulty' => 'medium',
                        'points' => 15,
                        'passage' => 'Preparation Time: 1 Minute | Speaking Time: 2 Minutes',
                        'explanation' => 'Model Strategy: Use descriptive adjectives, sequence connectors (initially, mid-way through, ultimately), and express emotions about the scenery or unexpected encounters.',
                        'choices' => [],
                    ],
                    [
                        'prompt' => 'Part 1 Interview: Do you prefer living in a house or an apartment? Why?',
                        'type' => 'speaking',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'explanation' => 'Answer Strategy: Provide a direct preference, 2 supporting reasons (space vs maintenance convenience), and a personal anecdote.',
                        'choices' => [],
                    ],
                    [
                        'prompt' => 'Part 3 Discussion: How has technology changed the way people communicate in your country compared to twenty years ago?',
                        'type' => 'speaking',
                        'difficulty' => 'hard',
                        'points' => 10,
                        'explanation' => 'Answer Strategy: Compare past postal/landline communication with instant messaging/video calls; discuss impact on relationships.',
                        'choices' => [],
                    ],
                    [
                        'prompt' => 'Part 2 Cue Card: Describe a skill you would like to learn in the future. You should say: what the skill is, why you want to learn it, how you plan to learn it, and explain how it will benefit your career.',
                        'type' => 'speaking',
                        'difficulty' => 'medium',
                        'points' => 15,
                        'passage' => 'Preparation Time: 1 Minute | Speaking Time: 2 Minutes',
                        'explanation' => 'Model Strategy: Talk about data analysis or coding; outline learning steps (online course, practice projects); discuss career opportunities.',
                        'choices' => [],
                    ],
                    [
                        'prompt' => 'Part 3 Discussion: Should governments invest more money in public parks or cultural museums?',
                        'type' => 'speaking',
                        'difficulty' => 'hard',
                        'points' => 10,
                        'explanation' => 'Answer Strategy: Balanced argument acknowledging health/social benefits of parks and educational/heritage benefits of museums.',
                        'choices' => [],
                    ],
                ],
            ],

            // 15. Placement Test
            'placement-test' => [
                'bank' => [
                    'title' => 'Institutional Placement Diagnostic Test Bank',
                    'slug'  => 'placement-test-starter-pool',
                    'test_type' => 'general',
                    'description' => 'Multi-level diagnostic questions for candidate level streaming (A1 to C1).',
                ],
                'questions' => [
                    [
                        'prompt' => 'Diagnostic A2: She _______ in London for five years before moving to Paris in 2020.',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'explanation' => "Past Perfect 'had lived' indicates an action completed prior to another past event (moving in 2020).",
                        'choices' => [
                            ['A', 'had lived', true],
                            ['B', 'has lived', false],
                            ['C', 'lives', false],
                            ['D', 'is living', false],
                        ],
                    ],
                    [
                        'prompt' => 'Diagnostic B1: If I _______ more time last weekend, I would have visited the museum.',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'explanation' => "Third Conditional requires 'had had' in the if-clause.",
                        'choices' => [
                            ['A', 'had had', true],
                            ['B', 'have had', false],
                            ['C', 'would have', false],
                            ['D', 'have', false],
                        ],
                    ],
                    [
                        'prompt' => 'Diagnostic B2: The CEO suggested _______ a third-party audit of financial records.',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'explanation' => "The verb 'suggest' takes a gerund ('conducting') when not followed by a clause.",
                        'choices' => [
                            ['A', 'conducting', true],
                            ['B', 'to conduct', false],
                            ['C', 'conduct', false],
                            ['D', 'conducted', false],
                        ],
                    ],
                    [
                        'prompt' => 'Diagnostic C1: Scarcely _______ into the office when the alarm began to sound.',
                        'type' => 'multiple_choice',
                        'difficulty' => 'hard',
                        'points' => 10,
                        'explanation' => "Negative adverb 'Scarcely' requires past perfect inversion ('had he walked').",
                        'choices' => [
                            ['A', 'had he walked', true],
                            ['B', 'he had walked', false],
                            ['C', 'did he walk', false],
                            ['D', 'he walked', false],
                        ],
                    ],
                    [
                        'prompt' => 'Diagnostic A1: Excuse me, _______ is the nearest train station?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'explanation' => "'where' asks for location directions.",
                        'choices' => [
                            ['A', 'where', true],
                            ['B', 'what', false],
                            ['C', 'who', false],
                            ['D', 'when', false],
                        ],
                    ],
                ],
            ],

            // 16. Core Grammar
            'core-grammar' => [
                'bank' => [
                    'title' => 'Core Grammar Mastery & Structure Repository',
                    'slug'  => 'core-grammar-starter-pool',
                    'test_type' => 'general',
                    'description' => 'Comprehensive grammar rules, tenses, passives, and clause structures.',
                ],
                'questions' => [
                    [
                        'prompt' => 'Which sentence correctly uses the passive voice?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'explanation' => "Sentence A correctly uses 'was written by' passive construction.",
                        'choices' => [
                            ['A', 'The report was written by the research assistant.', true],
                            ['B', 'The research assistant written the report.', false],
                            ['C', 'The report has writing by the assistant.', false],
                            ['D', 'Writing the report was the research assistant.', false],
                        ],
                    ],
                    [
                        'prompt' => 'Select the option containing a relative pronoun error:',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'passage' => 'Sentence: The scientist which discovered the antibiotic received a Nobel Prize.',
                        'explanation' => "'which' cannot refer to a person ('scientist'); 'who' must be used.",
                        'choices' => [
                            ['A', 'which discovered (should be "who discovered")', true],
                            ['B', 'The scientist', false],
                            ['C', 'received a Nobel Prize', false],
                            ['D', 'No error', false],
                        ],
                    ],
                    [
                        'prompt' => 'Complete with the appropriate modal verb: You _______ bring an umbrella; the sky is completely clear.',
                        'type' => 'multiple_choice',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'explanation' => "'needn't' expresses absence of necessity.",
                        'choices' => [
                            ['A', 'needn\'t', true],
                            ['B', 'mustn\'t', false],
                            ['C', 'shouldn\'t have', false],
                            ['D', 'ought not', false],
                        ],
                    ],
                    [
                        'prompt' => 'Choose the correct reported speech form: Direct: "I will call you tomorrow," said Alex.',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'explanation' => "Reported speech shifts 'will' to 'would' and 'tomorrow' to 'the following day'.",
                        'choices' => [
                            ['A', 'Alex said he would call me the following day.', true],
                            ['B', 'Alex said he will call me tomorrow.', false],
                            ['C', 'Alex told he would call me tomorrow.', false],
                            ['D', 'Alex says he would call me.', false],
                        ],
                    ],
                    [
                        'prompt' => 'Identify the sentence with correct parallel structure:',
                        'type' => 'multiple_choice',
                        'difficulty' => 'hard',
                        'points' => 10,
                        'explanation' => "Option A maintains parallel gerund forms: 'swimming, hiking, and riding'.",
                        'choices' => [
                            ['A', 'She enjoys swimming, hiking, and riding bicycles.', true],
                            ['B', 'She enjoys to swim, hiking, and rides bicycles.', false],
                            ['C', 'She enjoys swimming, to hike, and bicycle riding.', false],
                            ['D', 'She enjoys swim, hike, and to ride bicycles.', false],
                        ],
                    ],
                ],
            ],

            // 17. Academic Vocabulary
            'academic-vocabulary' => [
                'bank' => [
                    'title' => 'Academic Word List (AWL) Collocation Repository',
                    'slug'  => 'academic-vocabulary-starter-pool',
                    'test_type' => 'general',
                    'description' => 'Academic collocations, AWL vocabulary definitions, and contextual usage.',
                ],
                'questions' => [
                    [
                        'prompt' => 'Select the word that best completes the academic collocation: The study established a direct _______ between diet and heart disease.',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'explanation' => "'correlation' is the standard AWL term pairing with 'direct...between'.",
                        'choices' => [
                            ['A', 'correlation', true],
                            ['B', 'cooperation', false],
                            ['C', 'collaboration', false],
                            ['D', 'coincidence', false],
                        ],
                    ],
                    [
                        'prompt' => 'What is the closest synonym for the AWL term "substantiate"?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'explanation' => "'substantiate' means to verify or validate with evidence.",
                        'choices' => [
                            ['A', 'verify', true],
                            ['B', 'contradict', false],
                            ['C', 'exaggerate', false],
                            ['D', 'speculate', false],
                        ],
                    ],
                    [
                        'prompt' => 'Complete the sentence: The university seeks to _______ sustainable energy practices across all departments.',
                        'type' => 'multiple_choice',
                        'difficulty' => 'easy',
                        'points' => 5,
                        'explanation' => "'integrate' is the AWL verb meaning to combine systematically.",
                        'choices' => [
                            ['A', 'integrate', true],
                            ['B', 'isolate', false],
                            ['C', 'intimidate', false],
                            ['D', 'intervene', false],
                        ],
                    ],
                    [
                        'prompt' => 'Choose the correct form of the AWL word root "hypothesis": Researchers formulated a tentative _______ to explain the anomaly.',
                        'type' => 'multiple_choice',
                        'difficulty' => 'medium',
                        'points' => 5,
                        'explanation' => "The singular noun 'hypothesis' fits after 'a tentative'.",
                        'choices' => [
                            ['A', 'hypothesis', true],
                            ['B', 'hypothesize', false],
                            ['C', 'hypothetical', false],
                            ['D', 'hypothetically', false],
                        ],
                    ],
                    [
                        'prompt' => 'Which term describes an authoritative statement that cannot be questioned without proof?',
                        'type' => 'multiple_choice',
                        'difficulty' => 'hard',
                        'points' => 10,
                        'explanation' => "'premise' in academic logic refers to a foundational statement upon which an argument is built.",
                        'choices' => [
                            ['A', 'premise', true],
                            ['B', 'prejudice', false],
                            ['C', 'precaution', false],
                            ['D', 'predecessor', false],
                        ],
                    ],
                ],
            ],
        ];

        // 2. Loop & Seed Repositories, Questions, Choices, Versions, and Audit Trails
        foreach ($seedRepositories as $catSlug => $repoData) {
            if (!isset($categories[$catSlug])) continue;

            $cat = $categories[$catSlug];
            $bankInfo = $repoData['bank'];

            $bank = QuestionBank::updateOrCreate(
                ['slug' => $bankInfo['slug']],
                [
                    'title'           => $bankInfo['title'],
                    'slug'            => $bankInfo['slug'],
                    'test_type'       => $bankInfo['test_type'],
                    'description'     => $bankInfo['description'],
                    'acl_category_id' => $cat->id,
                    'created_by'      => $teacher->id,
                    'status'          => 'published',
                    'is_published'    => true,
                    'current_version' => '1.0',
                ]
            );

            foreach ($repoData['questions'] as $qData) {
                $imageUrl = $qData['image_url'] ?? null;
                $audioUrl = $qData['audio_url'] ?? null;
                $mediaAssetId = null;

                if ($imageUrl) {
                    $mediaAssetId = \App\Models\MediaAsset::where('path', $imageUrl)->value('id');
                } elseif ($audioUrl) {
                    $mediaAssetId = \App\Models\MediaAsset::where('path', $audioUrl)->value('id');
                }

                $q = Question::updateOrCreate(
                    ['question_bank_id' => $bank->id, 'prompt' => $qData['prompt']],
                    [
                        'question_type'  => $qData['type'],
                        'difficulty'     => $qData['difficulty'],
                        'points'         => $qData['points'],
                        'passage_text'   => $qData['passage'] ?? null,
                        'audio_url'      => $audioUrl,
                        'image_url'      => $imageUrl,
                        'media_asset_id' => $mediaAssetId,
                        'explanation'    => $qData['explanation'] ?? null,
                    ]
                );

                if (!empty($qData['choices'])) {
                    foreach ($qData['choices'] as $choice) {
                        QuestionChoice::updateOrCreate(
                            ['question_id' => $q->id, 'label' => $choice[0]],
                            [
                                'content'    => $choice[1],
                                'is_correct' => $choice[2] ?? false,
                            ]
                        );
                    }
                }
            }

            // Versioning and Audit Trail
            AclVersion::updateOrCreate(
                ['resource_id' => $bank->id, 'version_number' => '1.0'],
                [
                    'resource_type'  => 'QuestionBank',
                    'title'          => $bank->title,
                    'snapshot_data'  => [
                        'id'          => $bank->id,
                        'title'       => $bank->title,
                        'test_type'   => is_object($bank->test_type) ? $bank->test_type->value : $bank->test_type,
                        'description' => $bank->description,
                        'status'      => $bank->status,
                    ],
                    'created_by'     => $teacher->id,
                    'change_reason'  => 'Initial official institutional starter deployment',
                    'is_current'     => true,
                ]
            );

            AclAuditTrail::updateOrCreate(
                ['resource_id' => $bank->id, 'action' => 'created'],
                [
                    'resource_type' => 'QuestionBank',
                    'actor_id'      => $teacher->id,
                    'created_by'    => $teacher->id,
                    'version'       => '1.0',
                    'reason'        => 'Seeded official institutional starter repository content',
                ]
            );
        }
    }
}
