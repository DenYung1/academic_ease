<?php
/**
 * Generate Standard Quizzes Script
 * 
 * This script automatically generates standard quizzes for all subjects
 * following the Ghana Education Service (GES) standards.
 */

// Determine the correct path based on how the script is being called
$base_path = __DIR__ . '/../';

// Include necessary files
require_once $base_path . 'includes/config.php';
require_once $base_path . 'includes/db_connect.php';
require_once $base_path . 'includes/quiz_storage.php';

// Set error reporting for debugging
ini_set('display_errors', 0);
error_log('Starting standard quiz generation script');

// Define quiz templates by subject and level
$quizTemplates = [
    // JHS Mathematics - Algebra
    [
        'title' => 'JHS Mathematics - Algebra',
        'description' => 'A comprehensive quiz on algebraic concepts for JHS students. [Mixed Format: Multiple-choice & Theory]',
        'subject' => 'mathematics',
        'level' => 'jhs',
        'questions' => [
            [
                'question_text' => 'Solve for x: 2x + 3 = 9',
                'answer_type' => 'text',
                'correct_answer' => '3',
                'explanation' => 'To solve for x, we need to isolate x on one side of the equation.\nStep 1: Subtract 3 from both sides\n2x + 3 - 3 = 9 - 3\n2x = 6\nStep 2: Divide both sides by 2\n2x Ã· 2 = 6 Ã· 2\nx = 3',
                'order' => 1
            ],
            [
                'question_text' => 'If f(x) = x + 5, find f(3).',
                'answer_type' => 'text',
                'correct_answer' => '8',
                'explanation' => 'To find f(3), we substitute x = 3 into the function f(x) = x + 5.\nf(3) = 3 + 5 = 8',
                'order' => 2
            ]
        ]
    ],
    
    // SHS Physics - Basic Mechanics
    [
        'title' => 'SHS Physics - Basic Mechanics',
        'description' => 'A quiz on basic mechanics concepts for SHS students. This quiz contains both multiple-choice and theory questions.',
        'subject' => 'physics',
        'level' => 'shs',
        'questions' => [
            [
                'question_text' => 'A 2 kg object is moving with a velocity of 5 m/s when it experiences a constant force of 10 N in the direction of motion for 3 seconds. What is its final velocity?',
                'answer_type' => 'text',
                'correct_answer' => '20',
                'explanation' => 'Using the equation v = u + at, where a = F/m = 10/2 = 5 m/sÂ². So v = 5 + 5 Ã— 3 = 5 + 15 = 20 m/s.',
                'order' => 1
            ],
            [
                'question_text' => 'Explain the concept of simple harmonic motion and derive the equation for the period of a simple pendulum. Discuss the assumptions made in this derivation.',
                'question_type' => 'theory',
                'model_answer' => 'Simple Harmonic Motion (SHM) is a type of periodic motion where the restoring force is directly proportional to the displacement from the equilibrium position and acts in the opposite direction to the displacement.',
                'order' => 2
            ]
        ]
    ],
    
    // JHS Social Studies - Advanced Civics
    [
        'title' => 'JHS Social Studies - Advanced Civics',
        'description' => 'A comprehensive quiz on advanced civics concepts for JHS students. [Mixed Format: Multiple-choice & Theory]',
        'subject' => 'social_studies',
        'level' => 'jhs',
        'questions' => [
            [
                'question_text' => 'Which of the following is NOT a function of the Executive branch of government in Ghana?',
                'options' => [
                    'Implementation of laws',
                    'Interpretation of laws',
                    'Formulation of policies',
                    'Administration of public services'
                ],
                'correct_answer' => 'Interpretation of laws',
                'explanation' => 'Interpretation of laws is a function of the Judicial branch of government, not the Executive branch. The Executive branch is responsible for implementing laws, formulating policies, and administering public services.',
                'order' => 1
            ],
            [
                'question_text' => 'Explain the concept of citizenship and discuss the rights and responsibilities of citizens in Ghana.',
                'question_type' => 'theory',
                'model_answer' => 'Citizenship refers to the status of being a member of a particular country with rights and responsibilities. In Ghana, citizenship can be acquired by birth, registration, or naturalization.\n\nRights of citizens in Ghana include:\n1. Right to life\n2. Right to education\n3. Right to freedom of speech and expression\n4. Right to freedom of religion\n5. Right to vote and be voted for\n6. Right to fair trial\n7. Right to own property\n\nResponsibilities of citizens in Ghana include:\n1. Obeying the laws of the country\n2. Paying taxes\n3. Protecting public property\n4. Participating in community development\n5. Defending the country when necessary\n6. Respecting the rights of others\n7. Participating in the electoral process\n\nThe relationship between rights and responsibilities is crucial for the development and stability of the nation. While citizens have rights that must be protected, they also have responsibilities that must be fulfilled to ensure the well-being of society as a whole.',
                'order' => 2
            ],
            [
                'question_text' => 'Which of the following is a major cause of rural-urban migration in Ghana?',
                'options' => [
                    'Better healthcare facilities in rural areas',
                    'Higher agricultural productivity in rural areas',
                    'Better employment opportunities in urban areas',
                    'Lower cost of living in urban areas'
                ],
                'correct_answer' => 'Better employment opportunities in urban areas',
                'explanation' => 'Better employment opportunities in urban areas is a major pull factor for rural-urban migration in Ghana. People move from rural areas to urban areas in search of better jobs, higher wages, and improved living conditions.',
                'order' => 3
            ],
            [
                'question_text' => 'Discuss the impact of colonialism on the political and economic development of Ghana.',
                'question_type' => 'theory',
                'model_answer' => 'Colonialism had profound impacts on the political and economic development of Ghana (formerly Gold Coast).\n\nPolitical impacts:\n1. Introduction of Western-style governance: The British introduced a centralized administrative system that replaced traditional governance structures in many areas.\n2. Arbitrary boundaries: Colonial borders were drawn without regard to ethnic or cultural boundaries, leading to some ethnic groups being divided across different countries.\n3. Indirect rule: The British used a system of indirect rule, governing through traditional authorities, which sometimes undermined traditional power structures.\n4. Nationalist movements: Colonialism eventually sparked nationalist movements that led to independence in 1957, with Kwame Nkrumah as the first president.\n\nEconomic impacts:\n1. Export-oriented economy: The colonial economy was structured to produce raw materials (cocoa, gold, timber) for export to Britain, creating dependency.\n2. Infrastructure development: Railways, roads, and ports were built primarily to facilitate the extraction and export of resources, not for balanced economic development.\n3. Introduction of cash crops: Traditional subsistence farming was partly replaced by cash crop production, particularly cocoa.\n4. Limited industrialization: There was little investment in manufacturing or processing industries, leaving Ghana dependent on imported manufactured goods.\n5. Uneven development: Resources were concentrated in areas of economic interest to the colonizers, leading to regional disparities.\n\nPost-independence, Ghana has worked to overcome these colonial legacies through various political and economic reforms, though some structural challenges persist.',
                'order' => 4
            ],
            [
                'question_text' => 'Which of the following is NOT a traditional conflict resolution mechanism in Ghana?',
                'options' => [
                    'Arbitration by chiefs and elders',
                    'Family mediation',
                    'Class action lawsuits',
                    'Community forums'
                ],
                'correct_answer' => 'Class action lawsuits',
                'explanation' => 'Class action lawsuits are a Western legal concept and not a traditional conflict resolution mechanism in Ghana. Traditional mechanisms include arbitration by chiefs and elders, family mediation, and community forums.',
                'order' => 5
            ]
        ]
    ],
    
    // SHS Geography - Advanced Physical Geography
    [
        'title' => 'SHS Geography - Advanced Physical Geography',
        'description' => 'A comprehensive quiz on advanced physical geography concepts for SHS students. [Mixed Format: Multiple-choice & Theory]',
        'subject' => 'geography',
        'level' => 'shs',
        'questions' => [
            [
                'question_text' => 'Which of the following is NOT a type of physical weathering?',
                'options' => [
                    'Frost shattering',
                    'Exfoliation',
                    'Carbonation',
                    'Salt crystallization'
                ],
                'correct_answer' => 'Carbonation',
                'explanation' => 'Carbonation is a type of chemical weathering, not physical weathering. It involves the reaction of carbonic acid with rocks. Physical weathering includes frost shattering, exfoliation, and salt crystallization.',
                'order' => 1
            ],
            [
                'question_text' => 'Explain the formation of oxbow lakes and discuss their ecological significance.',
                'question_type' => 'theory',
                'model_answer' => 'Oxbow lakes are formed through a process involving river meandering and erosion.\n\nFormation process:\n1. Rivers naturally develop meanders (bends) as they flow across relatively flat land.\n2. The water on the outside of a meander flows faster than on the inside, causing erosion on the outside bend (concave bank) and deposition on the inside bend (convex bank).\n3. Over time, the meander becomes more pronounced, forming a horseshoe-shaped loop.\n4. During periods of flooding, the river may cut a new, straighter channel across the narrow neck of the meander.\n5. When the floodwaters recede, sediment is deposited at both ends of the abandoned meander, cutting it off from the main river channel.\n6. This isolated water body becomes an oxbow lake.\n\nEcological significance:\n1. Habitat diversity: Oxbow lakes provide unique aquatic habitats distinct from the main river channel, supporting different species and increasing biodiversity.\n2. Nursery areas: They often serve as breeding grounds and nurseries for fish and amphibians due to their calm waters and abundant vegetation.\n3. Wetland functions: They perform important wetland functions such as water purification, flood control, and groundwater recharge.\n4. Succession: Oxbow lakes demonstrate ecological succession as they gradually fill with sediment, transitioning from open water to marsh, swamp, and eventually dry land.\n5. Refugia: During droughts or pollution events in the main river, oxbow lakes can serve as refuges for aquatic species.\n6. Nutrient cycling: They trap nutrients from the river during formation and floods, creating fertile environments for plant and animal life.\n\nIn Ghana, oxbow lakes can be found along major rivers like the Volta and provide important resources for local communities, including fishing opportunities and water for irrigation.',
                'order' => 2
            ],
            [
                'question_text' => 'Calculate the gradient of a slope that rises 150 meters over a horizontal distance of 2 kilometers.',
                'answer_type' => 'text',
                'correct_answer' => '0.075',
                'explanation' => 'The gradient is calculated by dividing the vertical rise by the horizontal distance. In this case, the vertical rise is 150 meters and the horizontal distance is 2 kilometers or 2000 meters. So the gradient is 150/2000 = 0.075 or 1:13.33 or 7.5%.',
                'order' => 3
            ],
            [
                'question_text' => 'Which of the following factors does NOT influence the climate of Ghana?',
                'options' => [
                    'Distance from the equator',
                    'The Inter-Tropical Convergence Zone (ITCZ)',
                    'Ocean currents',
                    'El NiÃ±o Southern Oscillation'
                ],
                'correct_answer' => 'El NiÃ±o Southern Oscillation',
                'explanation' => 'While the El NiÃ±o Southern Oscillation (ENSO) affects global climate patterns, it has minimal direct influence on Ghana\'s climate compared to the other factors listed. Ghana\'s climate is primarily influenced by its distance from the equator, the seasonal movement of the Inter-Tropical Convergence Zone (ITCZ), and ocean currents in the Gulf of Guinea.',
                'order' => 4
            ],
            [
                'question_text' => 'Discuss the causes and effects of desertification in Northern Ghana and propose sustainable solutions to address this environmental challenge.',
                'question_type' => 'theory',
                'model_answer' => 'Desertification in Northern Ghana is a complex environmental issue with multiple causes and far-reaching effects.\n\nCauses:\n1. Climate change: Increasing temperatures and changing rainfall patterns have led to more frequent and severe droughts.\n2. Deforestation: Removal of trees for fuelwood, charcoal production, and agricultural expansion reduces vegetation cover.\n3. Overgrazing: Excessive livestock grazing removes protective vegetation and compacts soil.\n4. Inappropriate agricultural practices: Continuous cultivation without fallow periods, mono-cropping, and limited use of soil conservation techniques deplete soil nutrients.\n5. Bushfires: Regular burning of vegetation, often for hunting or land clearing, destroys soil structure and organic matter.\n6. Population pressure: Growing population increases demand for land and natural resources.\n\nEffects:\n1. Reduced agricultural productivity: Degraded soils produce lower crop yields, threatening food security.\n2. Economic losses: Declining agricultural output leads to income losses for farmers and the national economy.\n3. Migration: Environmental degradation forces people to migrate to urban areas or more productive regions.\n4. Resource conflicts: Competition for diminishing resources can lead to conflicts between farmers and herders.\n5. Biodiversity loss: Native plant and animal species disappear as habitats are degraded.\n6. Water scarcity: Desertification reduces groundwater recharge and surface water availability.\n\nSustainable solutions:\n1. Agroforestry: Integrating trees with crops and livestock provides multiple benefits, including soil improvement, additional income sources, and climate resilience.\n2. Improved agricultural practices: Conservation agriculture, crop rotation, and integrated soil fertility management can restore soil health.\n3. Sustainable land management: Techniques such as contour bunding, stone lines, and zai pits help conserve soil and water.\n4. Alternative livelihoods: Developing non-farm income sources reduces pressure on land resources.\n5. Community-based natural resource management: Empowering local communities to manage their resources sustainably through participatory approaches.\n6. Policy reforms: Implementing and enforcing policies that promote sustainable land use and provide incentives for conservation.\n7. Climate-smart agriculture: Adopting practices that simultaneously increase productivity, enhance resilience, and reduce greenhouse gas emissions.\n8. Education and awareness: Raising awareness about desertification and training farmers in sustainable practices.\n\nImplementing these solutions requires collaboration between government agencies, NGOs, research institutions, and local communities, as well as adequate funding and technical support.',
                'order' => 5
            ]
        ]
    ],
    
    // JHS Mathematics - Quadratic Equations
    [
        'title' => 'JHS Mathematics - Quadratic Equations',
        'description' => 'A comprehensive quiz on quadratic equations and their applications. [Mixed Format: Multiple-choice & Theory]',
        'subject' => 'mathematics',
        'level' => 'jhs',
        'questions' => [
            [
                'question_text' => 'Which of the following is NOT a method for solving quadratic equations?',
                'options' => [
                    'Factoring',
                    'Completing the square',
                    'Quadratic formula',
                    'Linear interpolation'
                ],
                'correct_answer' => 'Linear interpolation',
                'explanation' => 'Linear interpolation is a method used for approximating values between two known points, not for solving quadratic equations. The three main methods for solving quadratic equations are factoring, completing the square, and using the quadratic formula.',
                'order' => 1
            ],
            [
                'question_text' => 'What is the quadratic formula for solving the equation axÂ² + bx + c = 0?',
                'options' => [
                    'x = (-b Â± âˆš(bÂ² - 4ac)) / 2a',
                    'x = (-b Â± âˆš(bÂ² + 4ac)) / 2a',
                    'x = (b Â± âˆš(bÂ² - 4ac)) / 2a',
                    'x = (-b Â± âˆš(bÂ² - 4ac)) / a'
                ],
                'correct_answer' => 'x = (-b Â± âˆš(bÂ² - 4ac)) / 2a',
                'explanation' => 'The quadratic formula is x = (-b Â± âˆš(bÂ² - 4ac)) / 2a, where a, b, and c are the coefficients in the standard form of a quadratic equation: axÂ² + bx + c = 0.',
                'order' => 2
            ],
            [
                'question_text' => 'Solve the quadratic equation: xÂ² - 5x + 6 = 0',
                'answer_type' => 'text',
                'correct_answer' => 'x = 2, x = 3',
                'explanation' => 'To solve xÂ² - 5x + 6 = 0, we can factor the left side: (x - 2)(x - 3) = 0. Setting each factor equal to zero: x - 2 = 0 or x - 3 = 0, which gives us x = 2 or x = 3.',
                'order' => 3
            ],
            [
                'question_text' => 'Explain how to form a quadratic equation when you know its roots. Give an example.',
                'question_type' => 'theory',
                'model_answer' => 'When we know the roots of a quadratic equation, we can use them to form the equation by following these steps:\n\n1. Write the equation in factored form: (x - r)(x - s) = 0, where r and s are the roots.\n\n2. Multiply the factors to get the standard form: axÂ² + bx + c = 0\n\nExample:\nIf the roots are 3 and 4, then:\n\nStep 1: Write in factored form\n(x - 3)(x - 4) = 0\n\nStep 2: Multiply the factors\n(x - 3)(x - 4) = xÂ² - 4x - 3x + 12 = xÂ² - 7x + 12\n\nSo the quadratic equation with roots 3 and 4 is: xÂ² - 7x + 12 = 0\n\nWe can verify this by solving the equation:\n\nxÂ² - 7x + 12 = 0\n(x - 3)(x - 4) = 0\nx = 3 or x = 4\n\nThis method works for any pair of numbers as roots. If the roots are fractions or decimals, the same process applies.',
                'order' => 4
            ],
            [
                'question_text' => 'A ball is thrown upward from the ground with an initial velocity of 20 m/s. The height h (in meters) of the ball after t seconds is given by the equation h = 20t - 5tÂ². When will the ball hit the ground again?',
                'options' => [
                    '2 seconds',
                    '4 seconds',
                    '5 seconds',
                    '10 seconds'
                ],
                'correct_answer' => '4 seconds',
                'explanation' => 'The ball hits the ground when h = 0. So we need to solve the equation 20t - 5tÂ² = 0. Factoring out t: t(20 - 5t) = 0. This gives us t = 0 or t = 4. Since t = 0 is the initial time (when the ball is thrown), the ball will hit the ground again at t = 4 seconds.',
                'order' => 5
            ]
        ]
    ],
    
    // JHS Mathematics - Geometry and Measurement
    [
        'title' => 'JHS Mathematics - Geometry and Measurement',
        'description' => 'A comprehensive quiz on geometric concepts and measurement techniques. [Mixed Format: Multiple-choice & Theory]',
        'subject' => 'mathematics',
        'level' => 'jhs',
        'questions' => [
            [
                'question_text' => 'Which of the following is NOT a type of triangle classification based on angles?',
                'options' => [
                    'Acute triangle',
                    'Right triangle',
                    'Obtuse triangle',
                    'Parallel triangle'
                ],
                'correct_answer' => 'Parallel triangle',
                'explanation' => 'Parallel triangle is not a valid classification of triangles based on angles. The three classifications based on angles are: acute triangle (all angles less than 90Â°), right triangle (one angle equals 90Â°), and obtuse triangle (one angle greater than 90Â°).',
                'order' => 1
            ],
            [
                'question_text' => 'What is the formula for the area of a trapezoid?',
                'options' => [
                    'A = Â½h(a + b)',
                    'A = Â½(a Ã— b)',
                    'A = h(a + b)',
                    'A = ab'
                ],
                'correct_answer' => 'A = Â½h(a + b)',
                'explanation' => 'The area of a trapezoid is calculated using the formula A = Â½h(a + b), where h is the height (perpendicular distance between the parallel sides) and a and b are the lengths of the parallel sides.',
                'order' => 2
            ],
            [
                'question_text' => 'Calculate the volume of a cylinder with radius 5 cm and height 10 cm. Use Ï€ = 3.14.',
                'answer_type' => 'text',
                'correct_answer' => '785 cmÂ³',
                'explanation' => 'The volume of a cylinder is calculated using the formula V = Ï€rÂ²h, where r is the radius and h is the height. Substituting the values: V = 3.14 Ã— 5Â² Ã— 10 = 3.14 Ã— 25 Ã— 10 = 785 cmÂ³.',
                'order' => 3
            ],
            [
                'question_text' => 'Explain the Pythagorean theorem and give one example of how it can be used in real life.',
                'question_type' => 'theory',
                'model_answer' => 'The Pythagorean theorem states that in a right-angled triangle, the square of the length of the hypotenuse (the longest side, opposite the right angle) is equal to the sum of squares of the other two sides.\n\nMathematically, if a and b are the lengths of the two shorter sides and c is the length of the hypotenuse, then:\n\ncÂ² = aÂ² + bÂ²\n\nFor example, in a triangle with sides of 3 cm and 4 cm, the hypotenuse would be:\ncÂ² = 3Â² + 4Â²\ncÂ² = 9 + 16\ncÂ² = 25\nc = 5 cm\n\nReal-life application:\nThe Pythagorean theorem is used in construction to ensure that corners are perfectly square (90 degrees). Builders use what\'s called the "3-4-5 rule." They measure 3 units along one wall from the corner, 4 units along the perpendicular wall from the same corner, and then check if the diagonal distance between these two points is exactly 5 units. If it is, then the corner is a perfect right angle.\n\nThis works because 3Â² + 4Â² = 9 + 16 = 25 = 5Â².\n\nOther examples of where the Pythagorean theorem is used include:\n1. Finding the shortest distance between two points\n2. Determining the length of a ladder needed to reach a certain height\n3. Calculating distances in sports fields\n4. Measuring the diagonal of a television or computer screen',
                'order' => 4
            ],
            [
                'question_text' => 'A rectangular garden is 12 meters long and 8 meters wide. What is the length of the diagonal path from one corner to the opposite corner?',
                'options' => [
                    '14.42 meters',
                    '20 meters',
                    '10 meters',
                    '16 meters'
                ],
                'correct_answer' => '14.42 meters',
                'explanation' => 'We can use the Pythagorean theorem to find the diagonal. If the length is 12 m and the width is 8 m, then the diagonal d can be calculated as: dÂ² = 12Â² + 8Â² = 144 + 64 = 208. Taking the square root: d = âˆš208 â‰ˆ 14.42 meters.',
                'order' => 5
            ]
        ]
    ],
    
    // JHS ICT - Computer Programming
    [
        'title' => 'JHS ICT - Computer Programming',
        'description' => 'A comprehensive quiz on basic programming concepts for JHS students. [Mixed Format: Multiple-choice & Theory]',
        'subject' => 'ict',
        'level' => 'jhs',
        'questions' => [
            [
                'question_text' => 'Which of the following is NOT a programming language?',
                'options' => [
                    'Python',
                    'Java',
                    'Microsoft Word',
                    'JavaScript'
                ],
                'correct_answer' => 'Microsoft Word',
                'explanation' => 'Microsoft Word is a word processing application, not a programming language. Python, Java, and JavaScript are all programming languages used for software development.',
                'order' => 1
            ],
            [
                'question_text' => 'What is the correct output of the following pseudocode? x = 5; y = 3; Print(x + y * 2)',
                'options' => [
                    '11',
                    '16',
                    '13',
                    '8'
                ],
                'correct_answer' => '11',
                'explanation' => 'Following the order of operations (multiplication before addition), y * 2 = 3 * 2 = 6, then x + 6 = 5 + 6 = 11.',
                'order' => 2
            ],
            [
                'question_text' => 'Explain the concept of variables in programming and provide three examples of how they are used in real programs.',
                'question_type' => 'theory',
                'model_answer' => 'Variables in programming are named storage locations that hold data which can be modified during program execution. They are fundamental building blocks in programming that allow developers to store, retrieve, and manipulate data.\n\nKey characteristics of variables:\n1. Name: Each variable has a unique identifier or name\n2. Data type: Variables store specific types of data (e.g., numbers, text, boolean values)\n3. Value: The actual data stored in the variable\n4. Scope: The region of code where the variable can be accessed\n\nExamples of how variables are used in real programs:\n\n1. User data storage:\n   ```\n   userName = "John"  // Stores the user\'s name\n   userAge = 15      // Stores the user\'s age\n   isStudent = true  // Stores whether the user is a student\n   ```\n   These variables would be used to personalize the user experience in an application, such as displaying "Welcome, John" or providing age-appropriate content.\n\n2. Calculation and math operations:\n   ```\n   length = 10\n   width = 5\n   area = length * width  // Calculates and stores the area\n   ```\n   In this example, variables store values needed for calculations and the result of those calculations, which could be used in an educational app teaching geometry.\n\n3. Program state tracking:\n   ```\n   score = 0\n   level = 1\n   livesRemaining = 3\n   ```\n   These variables would be used in a game to keep track of the player\'s progress, increment the score when they succeed, advance levels, or decrease lives when they fail.',
                'order' => 3
            ],
            [
                'question_text' => 'What does the acronym HTML stand for?',
                'options' => [
                    'Hyper Text Markup Language',
                    'High Tech Modern Language',
                    'Home Tool Markup Language',
                    'Hyper Transfer Machine Language'
                ],
                'correct_answer' => 'Hyper Text Markup Language',
                'explanation' => 'HTML (Hyper Text Markup Language) is the standard markup language for creating web pages and web applications.',
                'order' => 4
            ],
            [
                'question_text' => 'Which of the following is an example of an algorithm?',
                'options' => [
                    'A computer mouse',
                    'A recipe for making jollof rice',
                    'Microsoft Windows',
                    'A keyboard'
                ],
                'correct_answer' => 'A recipe for making jollof rice',
                'explanation' => 'An algorithm is a step-by-step procedure for solving a problem or accomplishing a task. A recipe is a perfect example of an algorithm as it provides a sequence of well-defined steps to achieve a specific outcome.',
                'order' => 5
            ]
        ]
    ],
    
    // JHS English Language - Grammar and Usage
    [
        'title' => 'JHS English Language - Grammar and Usage',
        'description' => 'A comprehensive quiz on English grammar rules and proper usage. [Mixed Format: Multiple-choice & Theory]',
        'subject' => 'english',
        'level' => 'jhs',
        'questions' => [
            [
                'question_text' => 'Which of the following sentences contains a grammatical error?',
                'options' => [
                    'She and I went to the market yesterday.',
                    'The committee have decided to postpone the meeting.',
                    'Neither John nor his sisters were at the party.',
                    'Everyone who attended the ceremony was impressed.'
                ],
                'correct_answer' => 'The committee have decided to postpone the meeting.',
                'explanation' => 'The sentence "The committee have decided to postpone the meeting" contains a subject-verb agreement error. "Committee" is a collective noun that should take a singular verb when considered as a single unit, so it should be "The committee has decided to postpone the meeting."',
                'order' => 1
            ],
            [
                'question_text' => 'Identify the part of speech of the underlined word in the following sentence: "She spoke very eloquently during the debate."',
                'options' => [
                    'Adverb',
                    'Adjective',
                    'Verb',
                    'Noun'
                ],
                'correct_answer' => 'Adverb',
                'explanation' => '"Eloquently" is an adverb because it modifies the verb "spoke" by describing how she spoke. Adverbs often end with -ly and answer questions like how, when, where, or to what extent.',
                'order' => 2
            ],
            [
                'question_text' => 'Explain the difference between active and passive voice. Give two examples of each and explain when to use each one.',
                'question_type' => 'theory',
                'model_answer' => 'Active voice and passive voice are two different ways of structuring sentences.\n\nActive Voice:\nIn active voice, the subject of the sentence performs the action. The structure is: Subject + Verb + Object.\n\nExamples:\n1. The student wrote the essay.\n2. The chef prepared a delicious meal.\n\nPassive Voice:\nIn passive voice, the subject of the sentence receives the action. The structure is: Subject + "to be" verb + Past Participle + "by" + doer (optional).\n\nExamples:\n1. The essay was written by the student.\n2. A delicious meal was prepared by the chef.\n\nWhen to use Active Voice:\n1. When you want to be clear and direct\n2. When it\'s important to know who did the action\n3. In most everyday writing and storytelling\n4. When you want your writing to be more lively and engaging\n\nWhen to use Passive Voice:\n1. When the doer of the action is unknown or unimportant\n   Example: "My phone was stolen yesterday." (We don\'t know who stole it)\n2. When you want to emphasize what happened rather than who did it\n   Example: "The school was built in 1995." (The important information is about the school)\n3. In scientific writing to sound more objective\n   Example: "The experiment was conducted under controlled conditions."\n4. To be polite when discussing mistakes\n   Example: "Mistakes were made." (instead of "You made mistakes")\n\nIn general, active voice is preferred in most writing because it\'s clearer and more direct. However, passive voice is useful in certain situations as described above.',
                'order' => 3
            ],
            [
                'question_text' => 'Which of the following is a complex sentence?',
                'options' => [
                    'The rain fell, and the flowers bloomed.',
                    'Although it was raining, we went for a walk.',
                    'The rain fell heavily.',
                    'The rain fell; the flowers bloomed.'
                ],
                'correct_answer' => 'Although it was raining, we went for a walk.',
                'explanation' => 'A complex sentence contains one independent clause and at least one dependent clause. "Although it was raining, we went for a walk" is a complex sentence because "Although it was raining" is a dependent clause (it cannot stand alone), and "we went for a walk" is an independent clause (it can stand alone as a complete sentence).',
                'order' => 4
            ],
            [
                'question_text' => 'Choose the correct form of the verb to complete this sentence: "If I _____ rich, I would travel around the world."',
                'options' => [
                    'am',
                    'was',
                    'were',
                    'had been'
                ],
                'correct_answer' => 'were',
                'explanation' => 'The correct form is "were" because this is a second conditional sentence expressing an unreal or hypothetical condition in the present. In the second conditional, we use "were" for all persons (I, you, he, she, it, we, they) in the if-clause, followed by "would" + base verb in the main clause.',
                'order' => 5
            ]
        ]
    ],
    
    // JHS English Language - Reading Comprehension
    [
        'title' => 'JHS English Language - Reading Comprehension',
        'description' => 'A comprehensive quiz on reading comprehension skills and strategies. [Mixed Format: Multiple-choice & Theory]',
        'subject' => 'english',
        'level' => 'jhs',
        'questions' => [
            [
                'question_text' => 'Read the following passage and answer the question:\n\nThe baobab is one of Africa\'s most recognizable trees. It has a massive trunk that can reach up to 11 meters in diameter, and its branches resemble roots, giving it the nickname "the upside-down tree." Baobabs can live for thousands of years and are known for their ability to store water in their trunksâ€”up to 120,000 litersâ€”which helps them survive in dry climates. The fruit of the baobab, often called "monkey bread," is rich in vitamin C and other nutrients.\n\nAccording to the passage, why is the baobab called "the upside-down tree"?',
                'options' => [
                    'Because it grows from the top down',
                    'Because its branches resemble roots',
                    'Because it stores water upside down',
                    'Because monkeys hang upside down from it'
                ],
                'correct_answer' => 'Because its branches resemble roots',
                'explanation' => 'The passage states that the baobab "has a massive trunk that can reach up to 11 meters in diameter, and its branches resemble roots, giving it the nickname "the upside-down tree."',
                'order' => 1
            ],
            [
                'question_text' => 'Which of the following is NOT a strategy for effective reading comprehension?',
                'options' => [
                    'Predicting what might happen next',
                    'Memorizing every detail in the text',
                    'Connecting the text to prior knowledge',
                    'Identifying the main idea and supporting details'
                ],
                'correct_answer' => 'Memorizing every detail in the text',
                'explanation' => 'Memorizing every detail in a text is not an effective reading comprehension strategy. Effective strategies include focusing on understanding the main ideas, making connections, asking questions, visualizing, and summarizing, rather than trying to memorize everything.',
                'order' => 2
            ],
            [
                'question_text' => 'Read the following poem and analyze its theme, literary devices, and meaning:\n\nThe Road Not Taken (excerpt)\nBy Robert Frost\n\nTwo roads diverged in a yellow wood,\nAnd sorry I could not travel both\nAnd be one traveler, long I stood\nAnd looked down one as far as I could\nTo where it bent in the undergrowth;\n\nThen took the other, as just as fair,\nAnd having perhaps the better claim,\nBecause it was grassy and wanted wear;\nThough as for that the passing there\nHad worn them really about the same.\n\nI shall be telling this with a sigh\nSomewhere ages and ages hence:\nTwo roads diverged in a wood, and Iâ€”\nI took the one less traveled by,\nAnd that has made all the difference.',
                'question_type' => 'theory',
                'model_answer' => 'Analysis of "The Road Not Taken" by Robert Frost:\n\nTheme:\nThe central theme of this poem is the significance of choices and decision-making in life. The poem explores how the choices we make, even seemingly small ones, can fundamentally shape our lives and lead us down entirely different paths. There\'s also a subtle theme about how we tend to romanticize our past decisions, often convincing ourselves that we chose the more difficult or unconventional path.\n\nLiterary Devices:\n\n1. Symbolism: The "two roads" symbolize life choices and the diverging paths that one\'s life can take based on decisions made. The "yellow wood" symbolizes autumn, which can represent a time of change or maturity when important decisions must be made.\n\n2. Extended Metaphor: The entire poem uses the extended metaphor of a physical journey (walking through the woods and choosing between paths) to represent the journey of life and the choices we make.\n\n3. Imagery: Frost uses vivid imagery to help readers visualize the scene, such as "yellow wood," roads that "bent in the undergrowth," and one path being "grassy and wanted wear."\n\n4. Alliteration: Examples include "wanted wear" and "first for."\n\n5. Personification: The less-traveled road is described as "wanting wear," giving it human-like qualities.\n\n6. Irony: Despite the famous final lines suggesting the speaker took "the road less traveled by," earlier in the poem, the speaker admits that both paths "had worn them really about the same," creating irony in how we remember and justify our choices.\n\nMeaning and Interpretation:\nOn the surface, "The Road Not Taken" appears to be a celebration of individualism and choosing one\'s own unique path in life. The final lines are often quoted as an endorsement of taking the less conventional route.\n\nHowever, a deeper reading reveals more complexity. The poem actually suggests that the roads were "really about the same," and the speaker admits they will later claim ("with a sigh") that taking the less-traveled road "made all the difference." This suggests that how we narrate our past choices may be more about how we want to see ourselves than about the actual significance of those choices at the time they were made.\n\nFrost seems to be commenting on the human tendency to look back on life decisions and assign them more significance or deliberateness than they may have had at the time. We like to think of ourselves as independent thinkers who forge our own paths, even when our choices might have been quite ordinary or arbitrary.\n\nThe poem\'s enduring appeal lies in its ambiguity and its ability to speak to the universal human experience of making choices and then living withâ€”and making meaning fromâ€”those choices for the rest of our lives.',
                'order' => 3
            ],
            [
                'question_text' => 'What is the main purpose of a topic sentence in a paragraph?',
                'options' => [
                    'To provide supporting details',
                    'To conclude the paragraph',
                    'To introduce the main idea of the paragraph',
                    'To transition to the next paragraph'
                ],
                'correct_answer' => 'To introduce the main idea of the paragraph',
                'explanation' => 'The main purpose of a topic sentence is to introduce the main idea or focus of a paragraph. It typically appears at the beginning of the paragraph and tells the reader what the paragraph will be about. The rest of the paragraph then provides supporting details, examples, or explanations related to that main idea.',
                'order' => 4
            ],
            [
                'question_text' => 'When making inferences while reading, you are:',
                'options' => [
                    'Directly stating what the text says',
                    'Drawing conclusions based on textual evidence and prior knowledge',
                    'Summarizing the main points of the text',
                    'Identifying the author\'s purpose'
                ],
                'correct_answer' => 'Drawing conclusions based on textual evidence and prior knowledge',
                'explanation' => 'Making inferences involves drawing conclusions or making educated guesses based on textual evidence combined with your prior knowledge or experience. It\'s about reading "between the lines" to understand ideas that are implied but not explicitly stated in the text.',
                'order' => 5
            ]
        ]
    ],
    
    // JHS Mathematics - Number Systems
    [
        'title' => 'JHS Mathematics - Number Systems',
        'description' => 'A comprehensive quiz on number systems and operations for JHS students. [Mixed Format: Multiple-choice & Theory]',
        'subject' => 'mathematics',
        'level' => 'jhs',
        'questions' => [
            [
                'question_text' => 'Which of the following is NOT a type of number system?',
                'options' => [
                    'Natural numbers',
                    'Imaginary numbers',
                    'Logical numbers',
                    'Rational numbers'
                ],
                'correct_answer' => 'Logical numbers',
                'explanation' => 'Logical numbers is not a standard number system. The standard number systems include natural numbers (counting numbers), whole numbers (natural numbers plus zero), integers (whole numbers and their negatives), rational numbers (fractions), irrational numbers (numbers that cannot be expressed as fractions), and real numbers (rational and irrational numbers combined).',
                'order' => 1
            ],
            [
                'question_text' => 'Convert the decimal number 25 to binary.',
                'options' => [
                    '10001',
                    '11001',
                    '11010',
                    '11011'
                ],
                'correct_answer' => '11001',
                'explanation' => 'To convert decimal to binary:\n1. Divide the number by 2\n2. Write down the remainder\n3. Repeat until the quotient is 0\n4. Read the remainders from bottom to top\n\n25 Ã· 2 = 12 remainder 1\n12 Ã· 2 = 6 remainder 0\n6 Ã· 2 = 3 remainder 0\n3 Ã· 2 = 1 remainder 1\n1 Ã· 2 = 0 remainder 1\n\nReading from bottom to top: 11001',
                'order' => 2
            ],
            [
                'question_text' => 'Explain the concept of prime numbers and list all prime numbers between 1 and 20. Then explain one real-life application of prime numbers.',
                'question_type' => 'theory',
                'model_answer' => 'Prime numbers are natural numbers greater than 1 that cannot be formed by multiplying two smaller natural numbers. In other words, a prime number has exactly two factors: 1 and itself.\n\nThe prime numbers between 1 and 20 are:\n2, 3, 5, 7, 11, 13, 17, 19\n\nNote that 1 is not a prime number because it has only one factor (itself).\n\nReal-life application of prime numbers:\n\nCryptography and Internet Security: Prime numbers are the foundation of many encryption systems used to secure internet communications. For example, the RSA encryption algorithm (widely used for secure data transmission) relies on the fact that it\'s easy to multiply two large prime numbers together, but very difficult to determine the original prime numbers if you only know their product. This property makes it possible to create secure codes that are extremely difficult to break without knowing the specific prime numbers used.\n\nOther applications include:\n- Computer hashing functions\n- Random number generation\n- Cicada life cycles (some species emerge only every 13 or 17 years, both prime numbers)\n- Creating efficient hash tables in computer programming',
                'order' => 3
            ],
            [
                'question_text' => 'Simplify: (2Â³ Ã— 5Â²) Ã· (2 Ã— 5)',
                'options' => [
                    '10',
                    '20',
                    '25',
                    '40'
                ],
                'correct_answer' => '20',
                'explanation' => 'Step 1: Calculate the powers\n2Â³ = 2 Ã— 2 Ã— 2 = 8\n5Â² = 5 Ã— 5 = 25\n\nStep 2: Multiply the numerator\n8 Ã— 25 = 200\n\nStep 3: Multiply the denominator\n2 Ã— 5 = 10\n\nStep 4: Divide\n200 Ã· 10 = 20',
                'order' => 4
            ],
            [
                'question_text' => 'If a = 3 and b = -2, calculate the value of 2aÂ² - 3bÂ² + 4ab',
                'options' => [
                    '6',
                    '18',
                    '24',
                    '30'
                ],
                'correct_answer' => '18',
                'explanation' => 'Substitute the values of a = 3 and b = -2 into the expression 2aÂ² - 3bÂ² + 4ab:\n\n2aÂ² - 3bÂ² + 4ab\n= 2(3)Â² - 3(-2)Â² + 4(3)(-2)\n= 2(9) - 3(4) + 4(3)(-2)\n= 18 - 12 + 4(3)(-2)\n= 18 - 12 + 4(-6)\n= 18 - 12 - 24\n= 6 - 24\n= -18\n\nWait, I made a mistake. Let me recalculate:\n\n2aÂ² - 3bÂ² + 4ab\n= 2(3)Â² - 3(-2)Â² + 4(3)(-2)\n= 2(9) - 3(4) + 4(3)(-2)\n= 18 - 12 + 4(3)(-2)\n= 18 - 12 + 4(-6)\n= 18 - 12 - 24\n= 6 - 24\n= -18\n\nOh, I see the issue. The correct answer is -18, not 18. Let me double-check:\n\n2(9) = 18\n3(4) = 12\n4(3)(-2) = 4(-6) = -24\n\n18 - 12 - 24 = 6 - 24 = -18\n\nSo the correct answer is -18.',
                'order' => 5
            ]
        ]
    ],
    
    // JHS Mathematics - Statistics and Probability
    [
        'title' => 'JHS Mathematics - Statistics and Probability',
        'description' => 'A comprehensive quiz on statistics and probability concepts for JHS students. [Mixed Format: Multiple-choice & Theory]',
        'subject' => 'mathematics',
        'level' => 'jhs',
        'questions' => [
            [
                'question_text' => 'What is the mean of the following set of numbers: 3, 7, 8, 12, 15?',
                'options' => [
                    '7',
                    '8',
                    '9',
                    '10'
                ],
                'correct_answer' => '9',
                'explanation' => 'The mean (average) is calculated by adding all the numbers and dividing by the count of numbers.\n\nMean = (3 + 7 + 8 + 12 + 15) Ã· 5\nMean = 45 Ã· 5\nMean = 9',
                'order' => 1
            ],
            [
                'question_text' => 'What is the median of the following set of numbers: 4, 7, 9, 10, 13, 18?',
                'options' => [
                    '9',
                    '9.5',
                    '10',
                    '11.5'
                ],
                'correct_answer' => '9.5',
                'explanation' => 'To find the median of an even number of values, arrange the numbers in order and take the average of the two middle values.\n\nArranged in order: 4, 7, 9, 10, 13, 18\n\nThe two middle values are 9 and 10.\nMedian = (9 + 10) Ã· 2 = 19 Ã· 2 = 9.5',
                'order' => 2
            ],
            [
                'question_text' => 'When rolling a fair six-sided die, what is the probability of rolling an even number?',
                'options' => [
                    '1/6',
                    '1/3',
                    '1/2',
                    '2/3'
                ],
                'correct_answer' => '1/2',
                'explanation' => 'The even numbers on a six-sided die are 2, 4, and 6.\n\nProbability = Number of favorable outcomes Ã· Total number of possible outcomes\nProbability = 3 Ã· 6 = 1/2 or 0.5 or 50%',
                'order' => 3
            ],
            [
                'question_text' => 'Explain the difference between mean, median, and mode. When would you use each measure of central tendency?',
                'question_type' => 'theory',
                'model_answer' => 'Mean, median, and mode are three different measures of central tendency that describe the typical or central value in a dataset.\n\nMean:\nThe mean is the average of all values in a dataset. It is calculated by adding all values and dividing by the number of values.\n\nFormula: Mean = (Sum of all values) Ã· (Number of values)\n\nExample: For the dataset [3, 5, 8, 9, 10], the mean is (3 + 5 + 8 + 9 + 10) Ã· 5 = 35 Ã· 5 = 7\n\nMedian:\nThe median is the middle value when all values are arranged in order. If there is an even number of values, the median is the average of the two middle values.\n\nExample: For the dataset [3, 5, 8, 9, 10], the median is 8 (the middle value when arranged in order).\nFor the dataset [3, 5, 8, 9, 10, 12], the median is (8 + 9) Ã· 2 = 8.5\n\nMode:\nThe mode is the value that appears most frequently in the dataset. A dataset may have no mode (if all values appear equally often), one mode, or multiple modes (if multiple values appear with the same highest frequency).\n\nExample: For the dataset [3, 5, 5, 8, 9, 10], the mode is 5 (it appears twice, while all other values appear only once).\n\nWhen to use each measure:\n\nMean:\n- Best used when the data is symmetrically distributed without extreme values (outliers)\n- Useful for numerical calculations and further statistical analysis\n- Commonly used in scientific research and when working with normally distributed data\n- Not ideal when there are extreme values that can skew the result\n\nMedian:\n- Best used when the data contains outliers or is skewed\n- More representative than the mean for non-symmetric distributions\n- Commonly used for income/salary data, house prices, and other datasets with potential outliers\n- Less affected by extreme values\n\nMode:\n- Best used when looking for the most common or popular item\n- Useful for categorical data (data that falls into categories rather than numerical values)\n- Can be used to find the most common size, color, or choice in a survey\n- The only measure of central tendency that can be used for nominal data (data that can only be categorized)\n- Useful in marketing to identify the most popular products',
                'order' => 4
            ],
            [
                'question_text' => 'A bag contains 4 red marbles, 3 blue marbles, and 5 green marbles. If you pick one marble at random, what is the probability of picking either a red or a blue marble?',
                'options' => [
                    '4/12',
                    '7/12',
                    '5/12',
                    '9/12'
                ],
                'correct_answer' => '7/12',
                'explanation' => 'To find the probability of picking either a red or a blue marble, we add the individual probabilities.\n\nTotal number of marbles = 4 + 3 + 5 = 12\n\nProbability of picking a red marble = 4/12\nProbability of picking a blue marble = 3/12\n\nProbability of picking either red or blue = 4/12 + 3/12 = 7/12',
                'order' => 5
            ]
        ]
    ],
    
    // JHS English Language - Writing Skills
    [
        'title' => 'JHS English Language - Writing Skills',
        'description' => 'A comprehensive quiz on writing skills and techniques for JHS students. [Mixed Format: Multiple-choice & Theory]',
        'subject' => 'english',
        'level' => 'jhs',
        'questions' => [
            [
                'question_text' => 'Which of the following is NOT a type of essay?',
                'options' => [
                    'Narrative',
                    'Descriptive',
                    'Persuasive',
                    'Interrogative'
                ],
                'correct_answer' => 'Interrogative',
                'explanation' => 'Interrogative is not a standard type of essay. The main types of essays are narrative (telling a story), descriptive (describing something in detail), expository (explaining a topic), persuasive (convincing the reader of a point of view), and argumentative (presenting arguments for or against a position).',
                'order' => 1
            ],
            [
                'question_text' => 'Which of the following is an example of a compound sentence?',
                'options' => [
                    'The boy ran quickly.',
                    'The boy ran quickly, and he won the race.',
                    'The boy, who ran quickly, won the race.',
                    'Because the boy ran quickly, he won the race.'
                ],
                'correct_answer' => 'The boy ran quickly, and he won the race.',
                'explanation' => 'A compound sentence contains two or more independent clauses (complete sentences) joined by a coordinating conjunction (and, but, or, nor, for, so, yet). "The boy ran quickly, and he won the race" contains two independent clauses joined by "and."',
                'order' => 2
            ],
            [
                'question_text' => 'What is the purpose of a thesis statement in an essay?',
                'options' => [
                    'To summarize the entire essay',
                    'To state the main idea or argument of the essay',
                    'To introduce the topic of the essay',
                    'To conclude the essay'
                ],
                'correct_answer' => 'To state the main idea or argument of the essay',
                'explanation' => 'A thesis statement is a sentence that states the main idea or argument of an essay. It typically appears in the introduction and tells the reader what to expect from the rest of the essay. A good thesis statement is specific, debatable, and sets the direction for the entire essay.',
                'order' => 3
            ],
            [
                'question_text' => 'Write a formal letter to your school principal requesting permission to start a reading club. Include details about the purpose of the club, when and where it will meet, and how it will benefit students.',
                'question_type' => 'theory',
                'model_answer' => '[Your Address]\n[City, Region]\n[Date]\n\nThe Principal\n[School Name]\n[School Address]\n[City, Region]\n\nDear Sir/Madam,\n\nRE: REQUEST TO START A READING CLUB\n\nI am writing to request your permission to establish a Reading Club at our school. As a student passionate about literature and reading, I believe this club would greatly benefit our school community.\n\nThe primary purpose of the Reading Club would be to foster a love for reading among students, improve literacy skills, and create a platform for literary discussions and analysis. In today\'s digital age, many students are losing interest in reading books, and this club aims to revive that interest.\n\nThe club would meet every Wednesday after school from 3:00 PM to 4:30 PM in the school library, if available. We would read a variety of literature including novels, short stories, poems, and non-fiction works from both African and international authors. Each meeting would include book discussions, reading activities, and occasional guest speakers from the literary community.\n\nThe benefits of this club to students would be numerous:\n\n1. Improved reading comprehension and vocabulary\n2. Enhanced critical thinking and analytical skills\n3. Better writing abilities through exposure to different writing styles\n4. Increased knowledge across various subjects\n5. A supportive community for students who enjoy reading\n6. Preparation for higher education where extensive reading is required\n\nTo start the club, we would need your approval and possibly a teacher to serve as an advisor. I have already spoken with [Teacher\'s Name], who has expressed interest in supervising the club.\n\nI would be grateful if you could consider this request and grant us permission to establish this Reading Club. I am available to discuss this proposal further at your convenience.\n\nThank you for your time and consideration.\n\nYours faithfully,\n\n[Your Full Name]\n[Your Class]',
                'order' => 4
            ],
            [
                'question_text' => 'Which of the following is NOT a step in the writing process?',
                'options' => [
                    'Prewriting',
                    'Drafting',
                    'Memorizing',
                    'Revising'
                ],
                'correct_answer' => 'Memorizing',
                'explanation' => 'Memorizing is not a standard step in the writing process. The main steps in the writing process are: prewriting (brainstorming, researching, planning), drafting (writing the first draft), revising (improving content and organization), editing (correcting grammar, spelling, and punctuation), and publishing/sharing the final work.',
                'order' => 5
            ]
        ]
    ],
    
    // JHS Science - Basic Physics
    [
        'title' => 'JHS Science - Basic Physics',
        'description' => 'A comprehensive quiz on basic physics concepts for JHS students. [Mixed Format: Multiple-choice & Theory]',
        'subject' => 'science',
        'level' => 'jhs',
        'questions' => [
            [
                'question_text' => 'Which of the following is the SI unit of force?',
                'options' => [
                    'Joule',
                    'Newton',
                    'Watt',
                    'Pascal'
                ],
                'correct_answer' => 'Newton',
                'explanation' => 'The newton (N) is the SI unit of force. It is named after Sir Isaac Newton and is defined as the force needed to accelerate a mass of one kilogram by one meter per second squared. The other options are: joule (unit of energy), watt (unit of power), and pascal (unit of pressure).',
                'order' => 1
            ],
            [
                'question_text' => 'What happens to the mass of an object when it is taken to the moon?',
                'options' => [
                    'It increases',
                    'It decreases',
                    'It remains the same',
                    'It becomes zero'
                ],
                'correct_answer' => 'It remains the same',
                'explanation' => 'Mass is a measure of the amount of matter in an object and does not change with location. Weight, on the other hand, is a force that depends on gravity and would decrease on the moon because the moon\'s gravitational pull is about 1/6 that of Earth\'s.',
                'order' => 2
            ],
            [
                'question_text' => 'Explain the three states of matter (solid, liquid, and gas) in terms of particle arrangement, movement, and energy. Give one example of each state.',
                'question_type' => 'theory',
                'model_answer' => 'The three states of matter - solid, liquid, and gas - differ in their particle arrangement, movement, and energy levels.\n\nSolids:\n- Particle Arrangement: Particles in solids are tightly packed in a regular, ordered pattern (usually a crystalline structure).\n- Particle Movement: Particles vibrate in place but do not move from their fixed positions.\n- Energy: Particles have the least amount of kinetic energy compared to other states.\n- Properties: Solids have a definite shape and volume, are difficult to compress, and generally have higher densities than liquids and gases.\n- Example: Ice (frozen water), wood, iron, or a rock.\n\nLiquids:\n- Particle Arrangement: Particles are close together but not in a regular pattern. They can slide past each other.\n- Particle Movement: Particles can move around each other freely but remain in contact.\n- Energy: Particles have more kinetic energy than in solids but less than in gases.\n- Properties: Liquids have a definite volume but take the shape of their container, are difficult to compress, and flow easily.\n- Example: Water, oil, milk, or blood.\n\nGases:\n- Particle Arrangement: Particles are far apart with no regular arrangement.\n- Particle Movement: Particles move freely and rapidly in all directions.\n- Energy: Particles have the highest amount of kinetic energy.\n- Properties: Gases have no definite shape or volume, expand to fill their container, are easily compressible, and have the lowest densities.\n- Example: Oxygen, carbon dioxide, water vapor, or helium.\n\nChanges between states occur when energy is added or removed:\n- Melting: Solid to liquid (adding energy)\n- Freezing: Liquid to solid (removing energy)\n- Vaporization: Liquid to gas (adding energy)\n- Condensation: Gas to liquid (removing energy)\n- Sublimation: Solid directly to gas (adding energy)\n- Deposition: Gas directly to solid (removing energy)',
                'order' => 3
            ],
            [
                'question_text' => 'Which of the following is an example of a renewable energy source?',
                'options' => [
                    'Coal',
                    'Natural gas',
                    'Solar power',
                    'Petroleum'
                ],
                'correct_answer' => 'Solar power',
                'explanation' => 'Solar power is a renewable energy source because it comes from the sun, which will continue to provide energy for billions of years. Coal, natural gas, and petroleum are fossil fuels, which are non-renewable resources that take millions of years to form and will eventually be depleted with continued use.',
                'order' => 4
            ],
            [
                'question_text' => 'A car travels 120 kilometers in 2 hours. What is its average speed?',
                'options' => [
                    '40 km/h',
                    '60 km/h',
                    '80 km/h',
                    '120 km/h'
                ],
                'correct_answer' => '60 km/h',
                'explanation' => 'Average speed is calculated using the formula:\n\nAverage speed = Total distance Ã· Total time\n\nSubstituting the values:\nAverage speed = 120 km Ã· 2 h = 60 km/h',
                'order' => 5
            ]
        ]
    ],
    
    // JHS Science - Basic Chemistry
    [
        'title' => 'JHS Science - Basic Chemistry',
        'description' => 'A comprehensive quiz on basic chemistry concepts for JHS students. [Mixed Format: Multiple-choice & Theory]',
        'subject' => 'science',
        'level' => 'jhs',
        'questions' => [
            [
                'question_text' => 'Which of the following is NOT an element?',
                'options' => [
                    'Oxygen',
                    'Carbon',
                    'Water',
                    'Gold'
                ],
                'correct_answer' => 'Water',
                'explanation' => 'Water (Hâ‚‚O) is a compound made up of two elements: hydrogen and oxygen. Oxygen, carbon, and gold are all elements, which are substances that cannot be broken down into simpler substances by chemical means.',
                'order' => 1
            ],
            [
                'question_text' => 'What is the pH of a neutral solution?',
                'options' => [
                    '0',
                    '7',
                    '10',
                    '14'
                ],
                'correct_answer' => '7',
                'explanation' => 'The pH scale ranges from 0 to 14. A pH of 7 is considered neutral (neither acidic nor basic). Solutions with a pH less than 7 are acidic, while solutions with a pH greater than 7 are basic (alkaline).',
                'order' => 2
            ],
            [
                'question_text' => 'Explain the difference between physical and chemical changes, and provide three examples of each.',
                'question_type' => 'theory',
                'model_answer' => 'Physical and chemical changes are two types of changes that matter can undergo.\n\nPhysical Changes:\nA physical change alters the form or appearance of a substance without changing its chemical composition. In a physical change, no new substance is formed, and the change can usually be reversed.\n\nCharacteristics of physical changes:\n- No new substance is formed\n- The chemical composition remains the same\n- Usually reversible\n- Often involves changes in state, shape, size, or color\n- Typically requires less energy than chemical changes\n\nExamples of physical changes:\n1. Melting ice into water (change of state from solid to liquid)\n2. Cutting paper into smaller pieces (change in size and shape)\n3. Dissolving salt in water (the salt can be recovered by evaporating the water)\n4. Grinding coffee beans (change in size)\n5. Boiling water to make steam (change of state from liquid to gas)\n\nChemical Changes:\nA chemical change (also called a chemical reaction) results in the formation of one or more new substances with different properties and composition. In a chemical change, chemical bonds are broken and new bonds are formed.\n\nCharacteristics of chemical changes:\n- New substance(s) with different properties are formed\n- The chemical composition changes\n- Usually irreversible or difficult to reverse\n- Often accompanied by energy changes (release or absorption of heat, light, etc.)\n- May be accompanied by color change, gas formation, precipitate formation, or odor\n\nExamples of chemical changes:\n1. Burning wood (wood combines with oxygen to form carbon dioxide, water vapor, and ash)\n2. Rusting of iron (iron combines with oxygen to form iron oxide)\n3. Cooking an egg (the proteins in the egg change their structure)\n4. Digestion of food (food is broken down into simpler substances)\n5. Photosynthesis (plants convert carbon dioxide and water into glucose and oxygen)\n\nIn summary, physical changes affect only the physical properties of a substance without changing its chemical composition, while chemical changes result in the formation of new substances with different chemical compositions and properties.',
                'order' => 3
            ],
            [
                'question_text' => 'Which of the following is a chemical property of a substance?',
                'options' => [
                    'Melting point',
                    'Density',
                    'Flammability',
                    'Color'
                ],
                'correct_answer' => 'Flammability',
                'explanation' => 'Flammability is a chemical property because it describes how a substance reacts with oxygen during combustion, which is a chemical change. Melting point, density, and color are physical properties because they can be observed or measured without changing the chemical composition of the substance.',
                'order' => 4
            ],
            [
                'question_text' => 'What is the chemical formula for water?',
                'options' => [
                    'Hâ‚‚O',
                    'COâ‚‚',
                    'Oâ‚‚',
                    'Hâ‚‚Oâ‚‚'
                ],
                'correct_answer' => 'Hâ‚‚O',
                'explanation' => 'The chemical formula for water is Hâ‚‚O, which indicates that each water molecule consists of two hydrogen atoms (H) bonded to one oxygen atom (O). COâ‚‚ is carbon dioxide, Oâ‚‚ is molecular oxygen, and Hâ‚‚Oâ‚‚ is hydrogen peroxide.',
                'order' => 5
            ]
        ]
    ],
    
    // SHS Economics - Market Structures
    [
        'title' => 'SHS Economics - Market Structures',
        'description' => 'A comprehensive quiz on different market structures and their characteristics. [Mixed Format: Multiple-choice & Theory]',
        'subject' => 'economics',
        'level' => 'shs',
        'questions' => [
            [
                'question_text' => 'Which of the following is NOT a characteristic of a perfectly competitive market?',
                'options' => [
                    'Many buyers and sellers',
                    'Homogeneous products',
                    'Price discrimination',
                    'Free entry and exit'
                ],
                'correct_answer' => 'Price discrimination',
                'explanation' => 'Price discrimination is not a characteristic of perfect competition. In perfect competition, all firms are price takers and sell identical products at the market price. Price discrimination is more commonly associated with monopolistic or oligopolistic market structures.',
                'order' => 1
            ],
            [
                'question_text' => 'In which market structure do firms have the most control over price?',
                'options' => [
                    'Perfect competition',
                    'Monopolistic competition',
                    'Oligopoly',
                    'Monopoly'
                ],
                'correct_answer' => 'Monopoly',
                'explanation' => 'A monopoly has the most control over price because it is the sole supplier of a product with no close substitutes. Without competition, a monopolist can set prices to maximize profits, subject only to consumer demand and potential government regulation.',
                'order' => 2
            ],
            [
                'question_text' => 'Compare and contrast perfect competition and monopoly market structures, discussing their key characteristics, efficiency implications, and real-world examples in Ghana.',
                'question_type' => 'theory',
                'model_answer' => 'Perfect Competition and Monopoly represent opposite ends of the market structure spectrum, with significant differences in their characteristics, efficiency implications, and prevalence in the real world.\n\nPerfect Competition:\n\nCharacteristics:\n1. Many buyers and sellers: The market consists of numerous small firms, each too small to influence the market price.\n2. Homogeneous products: All firms sell identical products, making them perfect substitutes.\n3. Perfect information: All market participants have complete knowledge about prices and products.\n4. Free entry and exit: Firms can enter or leave the market without significant barriers.\n5. Price takers: Firms must accept the market price determined by supply and demand.\n\nEfficiency Implications:\n1. Allocative efficiency: Resources are allocated to produce goods most valued by society (P = MC).\n2. Productive efficiency: Firms produce at the lowest possible average cost (P = minimum ATC).\n3. No economic profits in the long run: Competition drives profits to normal levels.\n4. Consumer surplus is maximized: Prices are as low as sustainably possible.\n\nReal-world Examples in Ghana:\n1. Agricultural markets: Small-scale farmers selling identical crops like maize or cassava in local markets.\n2. Artisanal fishing: Many small fishing operations selling similar fish products.\n3. Small-scale retail: Numerous small retailers selling identical manufactured goods.\n\nMonopoly:\n\nCharacteristics:\n1. Single seller: One firm is the sole supplier of a product with no close substitutes.\n2. Unique product: The product has no close substitutes.\n3. Significant barriers to entry: New firms cannot easily enter the market due to legal, technical, or economic barriers.\n4. Price maker: The firm can set its own price to maximize profits.\n5. Imperfect information: Information asymmetry may exist between the monopolist and consumers.\n\nEfficiency Implications:\n1. Allocative inefficiency: Production is below the socially optimal level (P > MC).\n2. Productive inefficiency: Firms may not produce at minimum average cost.\n3. Economic profits: Monopolists can earn above-normal profits in the long run.\n4. Deadweight loss: Society experiences a net welfare loss compared to perfect competition.\n5. Wealth transfer from consumers to producers: Higher prices transfer consumer surplus to producer surplus.\n\nReal-world Examples in Ghana:\n1. Ghana Water Company Limited: Sole provider of piped water services in many areas.\n2. Electricity Company of Ghana (ECG): Primary distributor of electricity in southern Ghana.\n3. Ghana Post: Has monopoly rights over certain postal services.\n4. Cocoa Marketing Board: Has significant control over cocoa exports.\n\nComparison:\n\nPrice and Output:\n- Perfect competition: Prices are lower and output is higher.\n- Monopoly: Prices are higher and output is lower than the socially optimal level.\n\nEfficiency:\n- Perfect competition: Generally more efficient both allocatively and productively.\n- Monopoly: Generally less efficient, though economies of scale may provide some productive efficiency.\n\nInnovation:\n- Perfect competition: Strong incentives for process innovation to reduce costs.\n- Monopoly: May have greater resources for research and development but less pressure to innovate.\n\nConsumer Welfare:\n- Perfect competition: Generally maximizes consumer welfare through lower prices.\n- Monopoly: Often reduces consumer welfare through higher prices and restricted output.\n\nPolicy Implications:\n- Perfect competition: Generally requires minimal government intervention.\n- Monopoly: Often requires regulation to prevent abuse of market power.\n\nIn Ghana, many markets fall somewhere between these two extremes, with elements of both competition and monopoly power. Government policy aims to promote competition where possible while regulating natural monopolies to protect consumer interests.',
                'order' => 3
            ],
            [
                'question_text' => 'What is the primary characteristic of an oligopoly market structure?',
                'options' => [
                    'Many small firms with no market power',
                    'A single firm controlling the entire market',
                    'A few large firms dominating the market',
                    'Many firms selling differentiated products'
                ],
                'correct_answer' => 'A few large firms dominating the market',
                'explanation' => 'Oligopoly is characterized by a market dominated by a small number of large firms. These firms are interdependent, meaning the actions of one firm significantly affect the others, leading to strategic decision-making and potential for collusion.',
                'order' => 4
            ],
            [
                'question_text' => 'In monopolistic competition, products are:',
                'options' => [
                    'Identical',
                    'Differentiated',
                    'Complementary',
                    'Inferior'
                ],
                'correct_answer' => 'Differentiated',
                'explanation' => 'In monopolistic competition, firms sell products that are differentiated (through branding, quality, features, etc.) but serve as close substitutes. This product differentiation gives each firm some market power to set prices, unlike in perfect competition where products are identical.',
                'order' => 5
            ]
        ]
    ],
    
    // SHS Physics - Mechanics and Motion
    [
        'title' => 'SHS Physics - Mechanics and Motion',
        'description' => 'A comprehensive quiz on mechanics, forces, and motion. [Mixed Format: Multiple-choice & Theory]',
        'subject' => 'physics',
        'level' => 'shs',
        'questions' => [
            [
                'question_text' => 'Which of Newton\'s laws states that for every action, there is an equal and opposite reaction?',
                'options' => [
                    'First Law',
                    'Second Law',
                    'Third Law',
                    'Law of Conservation of Momentum'
                ],
                'correct_answer' => 'Third Law',
                'explanation' => 'Newton\'s Third Law states that for every action, there is an equal and opposite reaction. This means that when one object exerts a force on a second object, the second object exerts an equal and opposite force on the first object.',
                'order' => 1
            ],
            [
                'question_text' => 'Calculate the acceleration of a 2 kg object when a force of 10 N is applied to it.',
                'options' => [
                    '5 m/sÂ²',
                    '20 m/sÂ²',
                    '0.2 m/sÂ²',
                    '12 m/sÂ²'
                ],
                'correct_answer' => '5 m/sÂ²',
                'explanation' => 'Using Newton\'s Second Law, F = ma, we can calculate acceleration as a = F/m. With F = 10 N and m = 2 kg, a = 10/2 = 5 m/sÂ².',
                'order' => 2
            ],
            [
                'question_text' => 'Explain what projectile motion is and give an example of how it is used in sports.',
                'question_type' => 'theory',
                'model_answer' => 'Projectile motion is the movement of an object that is thrown or launched into the air and then moves under the influence of gravity only. Examples include throwing a ball, firing a cannon, or kicking a football.\n\nKey features of projectile motion:\n\n1. The object follows a curved path called a parabola\n2. The motion can be split into horizontal and vertical movements\n3. Horizontally, the object moves at a constant speed (if we ignore air resistance)\n4. Vertically, the object accelerates downward due to gravity\n\nThe basic equations for projectile motion are:\n\nHorizontal position: x = (initial speed Ã— cosine of launch angle) Ã— time\nVertical position: y = (initial speed Ã— sine of launch angle) Ã— time - Â½ Ã— gravity Ã— timeÂ²\n\nExample in sports - Basketball shooting:\n\nProjectile motion is very important in basketball. When a player shoots a basketball, the ball follows a parabolic path determined by:\n\n1. The initial speed of the ball (how hard the player throws it)\n2. The angle at which the ball is released\n3. The height at which the player releases the ball\n\nBasketball players learn through practice how to adjust these factors:\n\n- When shooting from farther away, they need to throw the ball harder and at a higher angle\n- A higher arc (steeper angle) gives the ball a better chance of going through the hoop\n- The best angle for a free throw is about 52-55 degrees\n\nOther sports that involve projectile motion include:\n- Football (kicking the ball)\n- Cricket and baseball (hitting and throwing the ball)\n- Long jump and high jump (the athlete becomes the projectile)\n- Golf (the flight of the ball after being hit)\n\nUnderstanding projectile motion helps athletes improve their performance by making better adjustments to their technique.',
                'order' => 3
            ],
            [
                'question_text' => 'A ball rolls off a table that is 1 meter high. How long does it take to hit the ground?',
                'options' => [
                    '0.25 seconds',
                    '0.45 seconds',
                    '0.5 seconds',
                    '1.0 seconds'
                ],
                'correct_answer' => '0.45 seconds',
                'explanation' => 'We can use the formula for the distance an object falls under gravity:\n\ny = Â½ gtÂ²\n\nWhere:\n- y is the height (1 meter)\n- g is the acceleration due to gravity (9.8 m/sÂ²)\n- t is the time\n\nRearranging to solve for t:\n\nt = âˆš(2y/g)\nt = âˆš(2 Ã— 1/9.8)\nt = âˆš(0.204)\nt â‰ˆ 0.45 seconds',
                'order' => 4
            ],
            [
                'question_text' => 'Which of the following is NOT conserved in an inelastic collision?',
                'options' => [
                    'Mass',
                    'Momentum',
                    'Kinetic energy',
                    'Total energy'
                ],
                'correct_answer' => 'Kinetic energy',
                'explanation' => 'In an inelastic collision, kinetic energy is not conserved as some of it is converted to other forms of energy such as heat, sound, or deformation energy. Mass, momentum, and total energy (including all forms of energy) are conserved in all collisions.',
                'order' => 5
            ]
        ]
    ],
    
    // SHS Physics - Electricity and Magnetism
    [
        'title' => 'SHS Physics - Electricity and Magnetism',
        'description' => 'A comprehensive quiz on electrical and magnetic phenomena. [Mixed Format: Multiple-choice & Theory]',
        'subject' => 'physics',
        'level' => 'shs',
        'questions' => [
            [
                'question_text' => 'What is the relationship between voltage (V), current (I), and resistance (R) according to Ohm\'s Law?',
                'options' => [
                    'V = IR',
                    'V = I/R',
                    'R = VI',
                    'I = VR'
                ],
                'correct_answer' => 'V = IR',
                'explanation' => 'Ohm\'s Law states that the voltage (V) across a conductor is directly proportional to the current (I) flowing through it, with the constant of proportionality being the resistance (R). This is expressed mathematically as V = IR.',
                'order' => 1
            ],
            [
                'question_text' => 'What is the equivalent resistance when a 4Î© resistor and a 4Î© resistor are connected in series?',
                'options' => [
                    '2Î©',
                    '4Î©',
                    '8Î©',
                    '16Î©'
                ],
                'correct_answer' => '8Î©',
                'explanation' => 'For resistors in series, the equivalent resistance is the sum of the individual resistances.\n\nRâ‚‘â‚‘ = Râ‚ + Râ‚‚\n\nSubstituting the values:\n\nRâ‚‘â‚‘ = 4Î© + 4Î© = 8Î©\n\nThis is why the total resistance increases when resistors are added in series.',
                'order' => 2
            ],
            [
                'question_text' => 'Explain what electromagnetic induction is and describe three everyday applications of this principle.',
                'question_type' => 'theory',
                'model_answer' => 'Electromagnetic induction is the process of generating electricity from magnetism. It occurs when a conductor (like a wire) is placed in a changing magnetic field, which causes an electric current to flow in the conductor. This important principle was discovered by Michael Faraday in 1831.\n\nThe basic principles of electromagnetic induction are:\n\n1. When a conductor moves through a magnetic field, or when a magnetic field changes around a conductor, an electric current is produced in the conductor.\n\n2. The amount of electricity generated depends on:\n   - How strong the magnetic field is\n   - How fast the magnetic field is changing\n   - How many coils of wire are in the conductor\n\n3. Lenz\'s Law states that the direction of the induced current will create a magnetic field that opposes the change that produced it.\n\nThree everyday applications of electromagnetic induction:\n\n1. Electric Generators:\n   Generators convert mechanical energy into electrical energy. They work by rotating a coil of wire inside a magnetic field (or by rotating magnets around a coil). This movement creates a changing magnetic field, which induces an electric current in the coil. This is how most of our electricity is generated in power plants, whether they use water (hydroelectric), steam (coal, nuclear), or wind.\n\n2. Wireless Phone Chargers:\n   Wireless chargers use electromagnetic induction to charge phones without plugging them in. The charger contains a coil that creates a changing magnetic field. When you place your phone on the charger, this magnetic field induces a current in a coil inside your phone, which charges the battery. This is convenient because it eliminates the need for connecting cables.\n\n3. Induction Cooktops:\n   These modern stoves use electromagnetic induction to heat food without heating the stovetop itself. The cooktop has a coil beneath its surface that creates a rapidly changing magnetic field. When you place an iron or steel pot on the cooktop, the magnetic field induces electric currents (called eddy currents) in the metal of the pot. These currents generate heat directly in the pot, while the cooktop itself stays relatively cool. This makes induction cooking more energy-efficient and safer than traditional electric or gas stoves.\n\nOther common applications include transformers (which change voltage levels in power lines), electric motors, metal detectors, and electric guitar pickups. Electromagnetic induction is truly one of the most important principles in physics, as it forms the foundation of our modern electrical world.',
                'order' => 3
            ],
            [
                'question_text' => 'What happens to the magnetic field around a current-carrying conductor when the current is doubled?',
                'options' => [
                    'The magnetic field strength doubles',
                    'The magnetic field strength quadruples',
                    'The magnetic field strength remains the same',
                    'The magnetic field strength becomes half'
                ],
                'correct_answer' => 'The magnetic field strength doubles',
                'explanation' => 'According to the Biot-Savart Law, the magnetic field strength around a current-carrying conductor is directly proportional to the current flowing through it. Therefore, if the current is doubled, the magnetic field strength also doubles.',
                'order' => 4
            ],
            [
                'question_text' => 'Which of the following materials has the highest electrical conductivity?',
                'options' => [
                    'Copper',
                    'Silver',
                    'Gold',
                    'Aluminum'
                ],
                'correct_answer' => 'Silver',
                'explanation' => 'Silver has the highest electrical conductivity of all metals. The order of conductivity from highest to lowest among the options is: Silver, Copper, Gold, and then Aluminum. Despite silver\'s superior conductivity, copper is more commonly used in electrical wiring due to its lower cost.',
                'order' => 5
            ]
        ]
    ],
    
    // SHS Chemistry - Organic Chemistry
    [
        'title' => 'SHS Chemistry - Organic Chemistry',
        'description' => 'A comprehensive quiz on organic compounds and reactions. [Mixed Format: Multiple-choice & Theory]',
        'subject' => 'chemistry',
        'level' => 'shs',
        'questions' => [
            [
                'question_text' => 'Which of the following functional groups is characterized by the presence of a carbonyl group bonded to a hydroxyl group?',
                'options' => [
                    'Alcohol',
                    'Aldehyde',
                    'Ketone',
                    'Carboxylic acid'
                ],
                'correct_answer' => 'Carboxylic acid',
                'explanation' => 'A carboxylic acid functional group (-COOH) contains a carbonyl group (C=O) bonded to a hydroxyl group (-OH). This combination gives carboxylic acids their acidic properties, as the hydrogen in the hydroxyl group can be donated as a proton.',
                'order' => 1
            ],
            [
                'question_text' => 'What type of reaction is represented by the following equation? CHâ‚ƒCHâ‚‚Br + OHâ» â†’ CHâ‚ƒCHâ‚‚OH + Brâ»',
                'options' => [
                    'Elimination',
                    'Addition',
                    'Substitution',
                    'Oxidation'
                ],
                'correct_answer' => 'Substitution',
                'explanation' => 'This is a nucleophilic substitution reaction. The hydroxide ion (OHâ») acts as a nucleophile and substitutes for the bromine atom (Br) in the alkyl halide (CHâ‚ƒCHâ‚‚Br), forming an alcohol (CHâ‚ƒCHâ‚‚OH) and a bromide ion (Brâ»).',
                'order' => 2
            ],
            [
                'question_text' => 'Explain what isomers are in organic chemistry. Give two main types of isomers with examples and explain why isomers are important in medicine.',
                'question_type' => 'theory',
                'model_answer' => 'Isomers are compounds that have the same molecular formula (same number and types of atoms) but different arrangements of these atoms. Because of these different arrangements, isomers can have very different properties, even though they contain the same atoms.\n\nThe two main types of isomers are:\n\n1. Structural Isomers (Constitutional Isomers):\n   These have the same molecular formula but different connectivity of atoms.\n\n   Examples:\n   a) Butane and isobutane (Câ‚„Hâ‚â‚€)\n      - Butane has a straight chain of four carbon atoms.\n      - Isobutane (2-methylpropane) has a branched structure.\n\n   b) Ethanol and dimethyl ether (Câ‚‚Hâ‚†O)\n      - Ethanol (CHâ‚ƒCHâ‚‚OH) is an alcohol.\n      - Dimethyl ether (CHâ‚ƒOCHâ‚ƒ) is an ether.\n\n2. Stereoisomers:\n   These have the same molecular formula and connectivity but different spatial arrangement of atoms.\n\n   Examples:\n   a) Cis-2-butene and trans-2-butene (Câ‚„Hâ‚ˆ)\n      - In cis-2-butene, both methyl groups are on the same side of the double bond.\n      - In trans-2-butene, the methyl groups are on opposite sides.\n\n   b) L-lactic acid and D-lactic acid (Câ‚ƒHâ‚†Oâ‚ƒ)\n      - These are mirror images of each other that cannot be superimposed.\n\nImportance in Medicine:\n\n1. Different Biological Effects:\n   Different isomers can have completely different effects in the body. This is because proteins and enzymes in our bodies are very specific about the shape of molecules they interact with.\n\n   Example: The drug thalidomide\n   - One isomer helped with morning sickness (the intended effect).\n   - The other isomer caused birth defects when given to pregnant women in the 1950s and 1960s.\n   This tragic case led to stricter drug testing and showed why understanding isomers is so important.\n\n2. Better Medicines:\n   Understanding isomers helps scientists make more effective and safer medicines.\n\n   Example: Ibuprofen\n   - Only one isomer is responsible for reducing pain and inflammation.\n   - By using just this active isomer, patients can take lower doses with fewer side effects.\n\n3. Drug Development:\n   Pharmaceutical companies often develop "single-isomer drugs" that contain only the beneficial isomer, making medicines more effective and safer.\n\nIn conclusion, isomers show us that the arrangement of atoms in a molecule is just as important as which atoms are present. This understanding is crucial for developing safe and effective medicines.',
                'order' => 3
            ],
            [
                'question_text' => 'Which of the following polymers is formed by addition polymerization?',
                'options' => [
                    'Nylon',
                    'Polyester',
                    'Polyethylene',
                    'Polyurethane'
                ],
                'correct_answer' => 'Polyethylene',
                'explanation' => 'Polyethylene is formed by addition polymerization, where ethylene monomers (CHâ‚‚=CHâ‚‚) add together without the loss of any atoms. The double bonds in the ethylene molecules open up and link together to form a long chain. Nylon, polyester, and polyurethane are formed by condensation polymerization, which involves the loss of small molecules (usually water) during the reaction.',
                'order' => 4
            ],
            [
                'question_text' => 'What is the IUPAC name for the following compound? CHâ‚ƒ-CH(CHâ‚ƒ)-CHâ‚‚-CHâ‚‚-CHâ‚ƒ',
                'options' => [
                    '2-methylpentane',
                    '4-methylpentane',
                    '2-methylhexane',
                    'Isohexane'
                ],
                'correct_answer' => '2-methylpentane',
                'explanation' => 'To name this compound using IUPAC nomenclature, we first identify the longest continuous carbon chain, which is 5 carbons (pentane). There is a methyl group (-CHâ‚ƒ) attached to the second carbon, so the name is 2-methylpentane. The name 4-methylpentane would be incorrect because IUPAC rules require numbering from the end that gives the substituent the lowest possible number.',
                'order' => 5
            ]
        ]
    ]
];

// Function to generate a unique ID
function generateUniqueId() {
    return uniqid();
}

// Function to generate a timestamp suffix for quiz titles
function generateTimestampSuffix() {
    return ' [' . date('Y-m-d H:i:s') . ']';
}

/**
 * Check if a quiz with the same title and subject already exists
 * 
 * @param string $title The quiz title
 * @param string $subject The quiz subject
 * @param string $level The education level (jhs/shs)
 * @return bool True if the quiz already exists, false otherwise
 */
function quizExists($title, $subject, $level) {
    $existingQuizzes = getAllQuizzes();
    
    // Extract the base title without timestamp if it exists
    $baseTitle = preg_replace('/\s\[\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}(:\d{2})?\]$/', '', $title);
    
    // Also extract the topic if it exists (e.g., "Algebra" from "JHS Mathematics - Algebra")
    $titleParts = explode(' - ', $baseTitle);
    $subjectPart = $titleParts[0]; // e.g., "JHS Mathematics"
    $topicPart = count($titleParts) > 1 ? $titleParts[1] : ''; // e.g., "Algebra"
    
    // If we don't have a topic part, just use the subject part
    if (empty($topicPart)) {
        $topicPart = $subjectPart;
    }
    
    foreach ($existingQuizzes as $quiz) {
        // Extract base title and topic from existing quiz title
        $existingBaseTitle = preg_replace('/\s\[\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}(:\d{2})?\]$/', '', $quiz['title']);
        $existingTitleParts = explode(' - ', $existingBaseTitle);
        $existingSubjectPart = $existingTitleParts[0];
        $existingTopicPart = count($existingTitleParts) > 1 ? $existingTitleParts[1] : $existingSubjectPart;
        
        // If the subject, level, and topic match, consider it a duplicate
        if ($quiz['subject'] === $subject && 
            $quiz['level'] === $level && 
            strtolower($existingTopicPart) === strtolower($topicPart)) {
            error_log("Found duplicate: {$existingBaseTitle} matches {$baseTitle}");
            return true;
        }
    }
    
    return false;
}

// Function to check if any quiz exists for a subject and level
function subjectHasQuiz($subject, $level) {
    $existingQuizzes = getAllQuizzes();
    foreach ($existingQuizzes as $quiz) {
        if (isset($quiz['subject']) && isset($quiz['level']) && 
            $quiz['subject'] === $subject && $quiz['level'] === $level) {
            return true;
        }
    }
    return false;
}

// Function to get existing quiz topics for a subject and level
function getExistingQuizTopics($subject, $level) {
    $existingTopics = [];
    $existingQuizzes = getAllQuizzes();
    
    foreach ($existingQuizzes as $quiz) {
        if (isset($quiz['subject']) && isset($quiz['level']) && 
            $quiz['subject'] === $subject && $quiz['level'] === $level && 
            isset($quiz['title'])) {
            // Extract the topic from the title (e.g., "JHS Mathematics - Algebra" -> "Algebra")
            $titleParts = explode(' - ', $quiz['title']);
            if (count($titleParts) > 1) {
                $topic = trim($titleParts[1]);
                // Remove any timestamp if present
                $topic = preg_replace('/\s\[\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}\]$/', '', $topic);
                $existingTopics[] = strtolower($topic);
            }
        }
    }
    
    return $existingTopics;
}

// Get a list of subjects that don't have quizzes yet
function getSubjectsWithoutQuizzes($quizTemplates) {
    $subjectsWithoutQuizzes = [];
    $processedSubjects = [];
    
    // Process each quiz template to find unique subject/level combinations
    foreach ($quizTemplates as $template) {
        $subject = $template['subject'];
        $level = $template['level'];
        $key = $subject . '_' . $level;
        
        // Skip if we've already processed this subject/level combination
        if (isset($processedSubjects[$key])) {
            continue;
        }
        
        $processedSubjects[$key] = true;
        
        // Check if this subject/level already has quizzes
        if (!subjectHasQuiz($subject, $level)) {
            $subjectsWithoutQuizzes[] = ['subject' => $subject, 'level' => $level];
        }
    }
    
    return $subjectsWithoutQuizzes;
}

// Counter for tracking created quizzes
$createdCount = 0;
$errorCount = 0;

// Flag to control whether to generate quizzes for all subjects or only for subjects without quizzes
// This variable can be passed from the admin interface
if (!isset($generateForAllSubjects)) {
    $generateForAllSubjects = true; // Default to true if not set
}

// Get subjects without quizzes (only used if $generateForAllSubjects is false)
$subjectsWithoutQuizzes = [];
if (!$generateForAllSubjects) {
    $subjectsWithoutQuizzes = getSubjectsWithoutQuizzes($quizTemplates);
}

// Always allow multiple topics per subject, even if the subject already has quizzes
// But ensure we don't create duplicate topics
$allowMultipleTopicsPerSubject = true;

// Track topics we've already seen in this run to prevent duplicates
$processedTopics = [];

// Process each quiz template
foreach ($quizTemplates as $template) {
    try {
        $subject = $template['subject'];
        $level = $template['level'];
        
        // Get the topic from the template title
        $titleParts = explode(' - ', $template['title']);
        $topic = '';
        if (count($titleParts) > 1) {
            $topic = trim($titleParts[1]);
        }
        
        // Create a unique key for this subject, level, and topic combination
        $topicKey = strtolower($subject . '_' . $level . '_' . $topic);
        
        // Skip if we've already processed this topic in this run
        if (isset($processedTopics[$topicKey])) {
            error_log("Skipping duplicate topic in this run: {$subject} - {$topic} ({$level})");
            continue;
        }
        
        // Check if we should skip this template
        if (!$generateForAllSubjects) {
            // If we're not generating for all subjects, check if this subject already has quizzes
            $needsQuiz = false;
            foreach ($subjectsWithoutQuizzes as $subjectInfo) {
                if ($subjectInfo['subject'] === $subject && $subjectInfo['level'] === $level) {
                    $needsQuiz = true;
                    break;
                }
            }
            
            if (!$needsQuiz && !$allowMultipleTopicsPerSubject) {
                // Subject already has quizzes, but check if this specific topic exists
                $existingTopics = getExistingQuizTopics($subject, $level);
                
                // If this topic already exists, skip it
                if (!empty($topic) && in_array(strtolower($topic), $existingTopics)) {
                    error_log("Skipping topic that already exists: {$subject} - {$topic} ({$level})");
                    continue; // Skip to the next template
                }
            }
        }
        
        // Create quiz data structure
        $quizId = generateUniqueId();
        
        // Check if a quiz with this topic already exists before adding timestamp
        // Only add a timestamp if we're actually going to create the quiz
        $baseTitle = $template['title'];
        
        // Check if this exact topic already exists, but only if we don't allow multiple topics
        if (!$allowMultipleTopicsPerSubject && quizExists($baseTitle, $subject, $level)) {
            error_log("Skipping duplicate quiz topic: {$baseTitle}");
            continue; // Skip to the next template
        }
        
        // Add timestamp to quiz title to ensure uniqueness
        $uniqueTitle = $baseTitle . generateTimestampSuffix();
        
        $quiz = [
            'quiz_id' => $quizId,
            'title' => $uniqueTitle,
            'description' => $template['description'],
            'subject' => $template['subject'],
            'level' => $template['level'],
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => 'system',
            'questions' => []
        ];
        
        // Add questions to the quiz
        foreach ($template['questions'] as $index => $questionData) {
            $questionId = $quizId . '_q' . ($index + 1);
            
            // Check if this is a text input question or multiple choice
            $isTextInput = isset($questionData['answer_type']) && $questionData['answer_type'] === 'text';
            $isTheory = isset($questionData['question_type']) && $questionData['question_type'] === 'theory';
            
            if ($isTheory) {
                // Theory question (essay/long answer)
                $question = [
                    'question_id' => $questionId,
                    'quiz_id' => $quizId,
                    'question_text' => $questionData['question_text'],
                    'answer_type' => 'theory',
                    'model_answer' => $questionData['model_answer'],
                    'explanation' => isset($questionData['explanation']) ? $questionData['explanation'] : '',
                    'order' => isset($questionData['order']) ? $questionData['order'] : ($index + 1)
                ];
            } elseif ($isTextInput) {
                // Text input question (short answer)
                $question = [
                    'question_id' => $questionId,
                    'quiz_id' => $quizId,
                    'question_text' => $questionData['question_text'],
                    'answer_type' => 'text',
                    'correct_answer' => $questionData['correct_answer'],
                    'explanation' => isset($questionData['explanation']) ? $questionData['explanation'] : '',
                    'order' => isset($questionData['order']) ? $questionData['order'] : ($index + 1)
                ];
            } else {
                // Multiple choice question
                $question = [
                    'question_id' => $questionId,
                    'quiz_id' => $quizId,
                    'question_text' => isset($questionData['question_text']) ? $questionData['question_text'] : $questionData['question'],
                    'option_a' => $questionData['options'][0],
                    'option_b' => $questionData['options'][1],
                    'option_c' => $questionData['options'][2],
                    'option_d' => $questionData['options'][3],
                    'explanation' => isset($questionData['explanation']) ? $questionData['explanation'] : '',
                    'order' => isset($questionData['order']) ? $questionData['order'] : ($index + 1)
                ];
                
                // Standardize the correct answer format to use letter options (A, B, C, D, E)
                // This ensures consistency across the system
                if (isset($questionData['correct_answer'])) {
                    // If the correct answer is already a letter, use it as is
                    if (in_array($questionData['correct_answer'], ['A', 'B', 'C', 'D', 'E'])) {
                        $question['correct_answer'] = $questionData['correct_answer'];
                    } 
                    // If the correct answer matches one of the options text, convert to the corresponding letter
                    else {
                        $correctAnswerText = $questionData['correct_answer'];
                        $letterFound = false;
                        
                        foreach (['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4] as $letter => $index) {
                            if (isset($questionData['options'][$index]) && $questionData['options'][$index] === $correctAnswerText) {
                                $question['correct_answer'] = $letter;
                                $letterFound = true;
                                break;
                            }
                        }
                        
                        // If no match found, use the original value
                        if (!$letterFound) {
                            $question['correct_answer'] = $questionData['correct_answer'];
                        }
                    }
                }
                
                // Add option E if it exists
                if (isset($questionData['options'][4])) {
                    $question['option_e'] = $questionData['options'][4];
                }
            }
            
            $quiz['questions'][] = $question;
        }
        
        // Save the quiz to storage
        $result = saveQuiz($quiz);
        
        if ($result) {
            // Mark this topic as processed so we don't create duplicates
            $processedTopics[$topicKey] = true;
            
            $createdCount++;
            error_log("Created quiz: {$template['title']}");
        } else {
            $errorCount++;
            error_log("Failed to create quiz: {$template['title']}");
        }
    } catch (Exception $e) {
        $errorCount++;
        error_log("Error creating quiz {$template['title']}: " . $e->getMessage());
    }
}

// Output results
echo "Quiz generation completed.\n";
echo "Successfully created: $createdCount quizzes\n";
echo "Errors: $errorCount\n";
echo "See error log for details.\n";

// Function to run the script from the browser
if (isset($_GET['run']) && $_GET['run'] === 'true') {
    header('Content-Type: text/plain');
    echo "Running standard quiz generation...\n";
    echo "Created $createdCount quizzes with $errorCount errors.\n";
    echo "Refresh the quizzes page to see the new quizzes.";
}
?>
