<?php
// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user data
try {
    // Direct query using the service role key
    $endpoint = 'users?user_id=eq.' . urlencode($_SESSION['user_id']);
    $users = supabaseRequest($endpoint, 'GET', null, true);
    
    if (empty($users)) {
        // User not found, log out
        session_destroy();
        header('Location: login.php');
        exit;
    }
    
    $user = $users[0];
    
    // Check subscription status
    $subscription_status = $user['subscription_status'];
    $subscription_end_date = strtotime($user['subscription_end_date']);
    $current_time = time();
    
    if ($subscription_status === 'pending') {
        // Payment is pending, redirect to subscription page
        header('Location: subscribe-direct.php?pending=1');
        exit;
    } else if (($subscription_status === 'trial' && $subscription_end_date < $current_time) || 
        $subscription_status === 'expired') {
        // Subscription has expired, redirect to subscription page
        header('Location: subscribe-direct.php?expired=1');
        exit;
    } else if ($subscription_status !== 'active' && $subscription_status !== 'trial') {
        // User doesn't have an active subscription or trial
        header('Location: subscribe-direct.php?incomplete=1');
        exit;
    }
    
    // Get selected subject and level
    $subject = isset($_GET['subject']) ? $_GET['subject'] : 'all';
    $level = isset($_GET['level']) ? $_GET['level'] : (isset($user['school_level']) ? $user['school_level'] : 'jhs');
    
    // Define formula categories based on level (only subjects that require formulas)
    $all_categories = [
        // JHS Subjects with formulas
        'jhs' => [
            'mathematics' => 'Mathematics',
            'science' => 'Integrated Science',
            'ict' => 'ICT'
        ],
        // SHS Subjects with formulas
        'shs' => [
            // Core subjects
            'mathematics' => 'Core Mathematics',
            'integrated_science' => 'Integrated Science',
            
            // Science subjects
            'elective_mathematics' => 'Elective Mathematics',
            'physics' => 'Physics',
            'chemistry' => 'Chemistry',
            'biology' => 'Biology',
            'further_mathematics' => 'Further Mathematics',
            
            // Business subjects
            'financial_accounting' => 'Financial Accounting',
            'economics' => 'Economics',
            
            // Technical subjects
            'technical_drawing' => 'Technical Drawing',
            'electrical_engineering' => 'Electrical Engineering',
            
            // Geography (has formulas for calculations)
            'geography' => 'Geography'
        ]
    ];
    
    // Get categories based on level
    $categories = $all_categories[$level] ?? $all_categories['jhs'];
    
    // Get formula sheets from database
    $filter = "level=eq.$level";
    if ($subject !== 'all') {
        $filter .= "&subject=eq.$subject";
    }
    $order = "order=subject.asc,title.asc";
    $db_formula_sheets = supabaseRequest("formula_sheets?$filter&$order", 'GET', null, true);
    
    // Group formula sheets by subject
    $formulas = [];
    if (!empty($db_formula_sheets)) {
        foreach ($db_formula_sheets as $formula) {
            $subject_key = $formula['subject'] ?? 'general';
            if (!isset($formulas[$subject_key])) {
                $formulas[$subject_key] = [];
            }
            
            // Process content based on content_type
            $formula_content = [];
            if ($formula['content_type'] === 'text' && !empty($formula['content_text'])) {
                // Split text content into individual formulas
                $formula_content = array_filter(array_map('trim', explode('\n', $formula['content_text'])));
            }
            
            $formulas[$subject_key][] = [
                'title' => $formula['title'],
                'description' => $formula['description'] ?? '',
                'topic' => $formula['topic'] ?? '',
                'formulas' => $formula_content,
                'content_type' => $formula['content_type'],
                'file_path' => $formula['file_path'] ?? '',
                'file_name' => $formula['file_name'] ?? '',
                'formula_id' => $formula['formula_id']
            ];
        }
    }
    
    // If no formula sheets found in the database, use default formulas as fallback
    if (empty($formulas)) {
        // Default formulas as fallback
        $default_formulas = [
            'mathematics' => [
                [
                    'title' => 'Algebra Formulas',
                    'description' => 'Essential algebraic equations and identities',
                    'formulas' => [
                        '(a + b)² = a² + 2ab + b²',
                        '(a - b)² = a² - 2ab + b²',
                        '(a + b)(a - b) = a² - b²',
                        '(a + b)³ = a³ + 3a²b + 3ab² + b³',
                        '(a - b)³ = a³ - 3a²b + 3ab² - b³',
                        'Quadratic Formula: x = (-b ± √(b² - 4ac)) / 2a',
                        'Linear Equation: y = mx + c',
                        'Arithmetic Sequence: an = a1 + (n - 1)d',
                        'Sum of Arithmetic Sequence: Sn = n/2 × (a1 + an)',
                        'Geometric Sequence: an = a1 × r^(n-1)',
                        'Sum of Geometric Sequence: Sn = a1(1 - r^n) / (1 - r)'
                    ],
                    'content_type' => 'text'
                ],
                [
                    'title' => 'Geometry Formulas',
                    'description' => 'Area and volume calculations',
                    'formulas' => [
                        'Area of Rectangle = length × width',
                        'Area of Square = side²',
                        'Area of Triangle = (1/2) × base × height',
                        'Area of Parallelogram = base × height',
                        'Area of Trapezoid = (1/2) × (a + b) × h',
                        'Area of Circle = πr²',
                        'Circumference of Circle = 2πr',
                        'Volume of Cube = side³',
                        'Volume of Rectangular Prism = length × width × height',
                        'Volume of Cylinder = πr²h',
                        'Volume of Cone = (1/3) × πr²h',
                        'Volume of Sphere = (4/3)πr³',
                        'Surface Area of Cube = 6 × side²',
                        'Surface Area of Sphere = 4πr²',
                        'Pythagorean Theorem: a² + b² = c²'
                    ],
                    'content_type' => 'text'
                ],
                [
                    'title' => 'Trigonometry Formulas',
                    'description' => 'Basic trigonometric relationships',
                    'formulas' => [
                        'sin(θ) = opposite / hypotenuse',
                        'cos(θ) = adjacent / hypotenuse',
                        'tan(θ) = opposite / adjacent',
                        'sin²(θ) + cos²(θ) = 1',
                        'Area of Triangle = (1/2) × a × b × sin(C)'
                    ],
                    'content_type' => 'text'
                ]
            ],
            'science' => [
                [
                    'title' => 'Physics Formulas',
                    'description' => 'Basic physics equations for JHS level',
                    'formulas' => [
                        'Speed = distance / time',
                        'Velocity = displacement / time',
                        'Acceleration = change in velocity / time',
                        'Force = mass × acceleration',
                        'Weight = mass × gravity',
                        'Work = force × distance',
                        'Power = work / time',
                        'Potential Energy = mass × gravity × height',
                        'Kinetic Energy = (1/2) × mass × velocity²',
                        'Pressure = force / area',
                        'Density = mass / volume'
                    ],
                    'content_type' => 'text'
                ],
                [
                    'title' => 'Chemistry Formulas',
                    'description' => 'Basic chemistry equations for JHS level',
                    'formulas' => [
                        'Density = mass / volume',
                        'Concentration = mass of solute / volume of solution',
                        'pH + pOH = 14',
                        'pH = -log[H+]',
                        'Percentage Composition = (mass of element / mass of compound) × 100%'
                    ],
                    'content_type' => 'text'
                ],
                [
                    'title' => 'Biology Formulas',
                    'description' => 'Basic biological calculations for JHS level',
                    'formulas' => [
                        'Magnification = image size / actual size',
                        'Population Density = number of organisms / area',
                        'Rate of Photosynthesis = amount of oxygen produced / time',
                        'Rate of Respiration = amount of carbon dioxide produced / time'
                    ],
                    'content_type' => 'text'
                ]
            ],
            'ict' => [
                [
                    'title' => 'ICT Formulas',
                    'description' => 'Basic computing formulas and conversions',
                    'formulas' => [
                        '1 Byte = 8 Bits',
                        '1 Kilobyte (KB) = 1024 Bytes',
                        '1 Megabyte (MB) = 1024 Kilobytes',
                        '1 Gigabyte (GB) = 1024 Megabytes',
                        '1 Terabyte (TB) = 1024 Gigabytes',
                        'Download Time = file size / download speed',
                        'Binary to Decimal: Convert from base 2 to base 10',
                        'Decimal to Binary: Convert from base 10 to base 2',
                        'Resolution = width × height (in pixels)'
                    ],
                    'content_type' => 'text'
                ]
            ]
        ];
        
        // Add SHS formulas if level is SHS
        if ($level === 'shs') {
            // Physics formulas for SHS
            $default_formulas['physics'] = [
                [
                    'title' => 'Mechanics Formulas',
                    'description' => 'Motion, force, and energy equations',
                    'formulas' => [
                        'Newton\'s Laws of Motion:',
                        '1st Law: An object at rest stays at rest, and an object in motion stays in motion unless acted upon by a net external force',
                        '2nd Law: F = ma (Force = mass × acceleration)',
                        '3rd Law: For every action, there is an equal and opposite reaction',
                        'Weight: W = mg',
                        'Momentum: p = mv',
                        'Impulse: J = F∆t = ∆p',
                        'Kinetic Energy: KE = (1/2)mv²',
                        'Potential Energy: PE = mgh',
                        'Conservation of Energy: KE₁ + PE₁ = KE₂ + PE₂',
                        'Work: W = Fd cos(θ)',
                        'Power: P = W/t',
                        'Uniform Motion: v = u + at',
                        's = ut + (1/2)at²',
                        'v² = u² + 2as',
                        's = ((u + v)/2) × t'
                    ],
                    'content_type' => 'text'
                ],
                [
                    'title' => 'Waves and Optics',
                    'description' => 'Wave properties and optical formulas',
                    'formulas' => [
                        'Wave Speed: v = fλ',
                        'Snell\'s Law: n₁sin(θ₁) = n₂sin(θ₂)',
                        'Lens Equation: 1/f = 1/u + 1/v',
                        'Magnification: m = h₂/h₁ = -v/u',
                        'Doppler Effect: f\'= f(v ± v_observer)/(v ∓ v_source)',
                        'Intensity: I = P/A'
                    ],
                    'content_type' => 'text'
                ],
                [
                    'title' => 'Electricity and Magnetism',
                    'description' => 'Electrical and magnetic formulas',
                    'formulas' => [
                        'Coulomb\'s Law: F = k(q₁q₂)/r²',
                        'Electric Field: E = F/q = k(Q)/r²',
                        'Electric Potential: V = k(Q)/r',
                        'Ohm\'s Law: V = IR',
                        'Power: P = VI = I²R = V²/R',
                        'Series Resistors: R_total = R₁ + R₂ + R₃ + ...',
                        'Parallel Resistors: 1/R_total = 1/R₁ + 1/R₂ + 1/R₃ + ...'
                    ],
                    'content_type' => 'text'
                ]
            ];
            
            // Chemistry formulas for SHS
            $default_formulas['chemistry'] = [
                [
                    'title' => 'General Chemistry',
                    'description' => 'Basic chemistry equations and relationships',
                    'formulas' => [
                        'Density: ρ = m/V',
                        'Molar Mass: M = m/n',
                        'Avogadro\'s Number: N_A = 6.022 × 10²³ mol⁻¹',
                        'Ideal Gas Law: PV = nRT',
                        'Combined Gas Law: P₁V₁/T₁ = P₂V₂/T₂',
                        'Boyle\'s Law: P₁V₁ = P₂V₂ (at constant T)',
                        'Charles\'s Law: V₁/T₁ = V₂/T₂ (at constant P)',
                        'Gay-Lussac\'s Law: P₁/T₁ = P₂/T₂ (at constant V)',
                        'Molarity: M = moles of solute/volume of solution in liters'
                    ],
                    'content_type' => 'text'
                ],
                [
                    'title' => 'Chemical Kinetics & Equilibrium',
                    'description' => 'Reaction rates and equilibrium formulas',
                    'formulas' => [
                        'Rate Law: rate = k[A]ᵐ[B]ⁿ',
                        'Arrhenius Equation: k = Ae^(-Ea/RT)',
                        'Half-life (1st order): t₁/₂ = 0.693/k',
                        'Equilibrium Constant: Kc = [C]ᶜ[D]ᵈ/[A]ᵃ[B]ᵇ for aA + bB ⇌ cC + dD',
                        'Relationship between Kc and Kp: Kp = Kc(RT)^Δn',
                        'Le Chatelier\'s Principle: When a system at equilibrium is disturbed, the equilibrium shifts to counteract the disturbance'
                    ],
                    'content_type' => 'text'
                ],
                [
                    'title' => 'Electrochemistry & Thermochemistry',
                    'description' => 'Electrochemical and thermodynamic formulas',
                    'formulas' => [
                        'Nernst Equation: E = E° - (RT/nF)ln(Q)',
                        'Faraday\'s Law: m = (M × Q)/(n × F)',
                        'Enthalpy Change: ΔH = ΔU + PΔV',
                        'Gibbs Free Energy: ΔG = ΔH - TΔS',
                        'Relationship between ΔG and K: ΔG° = -RT ln(K)',
                        'Hess\'s Law: ΔH°reaction = Σ ΔH°f(products) - Σ ΔH°f(reactants)'
                    ],
                    'content_type' => 'text'
                ]
            ];
            
            // Mathematics formulas for SHS
            $default_formulas['mathematics'] = [
                [
                    'title' => 'Advanced Algebra',
                    'description' => 'Advanced algebraic formulas and relationships',
                    'formulas' => [
                        'Quadratic Formula: x = (-b ± √(b² - 4ac))/2a',
                        'Discriminant: Δ = b² - 4ac',
                        'Vieta\'s Formulas: x₁ + x₂ = -b/a, x₁ × x₂ = c/a',
                        'Binomial Theorem: (a + b)ⁿ = Σ(k=0 to n) (n choose k)aⁿ⁻ᵏbᵏ',
                        'Arithmetic Sequence: aₙ = a₁ + (n-1)d',
                        'Sum of Arithmetic Sequence: Sₙ = n/2(a₁ + aₙ) = n/2(2a₁ + (n-1)d)',
                        'Geometric Sequence: aₙ = a₁rⁿ⁻¹',
                        'Sum of Geometric Sequence: Sₙ = a₁(1-rⁿ)/(1-r) for r≠1',
                        'Sum of Infinite Geometric Series: S = a₁/(1-r) for |r|<1',
                        'Logarithm Rules: log(xy) = log(x) + log(y), log(x/y) = log(x) - log(y), log(xⁿ) = n·log(x)'
                    ],
                    'content_type' => 'text'
                ],
                [
                    'title' => 'Trigonometry',
                    'description' => 'Advanced trigonometric formulas',
                    'formulas' => [
                        'Pythagorean Identity: sin²θ + cos²θ = 1',
                        'tan θ = sin θ / cos θ',
                        'cot θ = cos θ / sin θ',
                        'sec θ = 1 / cos θ',
                        'csc θ = 1 / sin θ',
                        'sin(A + B) = sin A cos B + cos A sin B',
                        'cos(A + B) = cos A cos B - sin A sin B',
                        'sin(A - B) = sin A cos B - cos A sin B',
                        'cos(A - B) = cos A cos B + sin A sin B',
                        'sin 2A = 2 sin A cos A',
                        'cos 2A = cos²A - sin²A = 2cos²A - 1 = 1 - 2sin²A',
                        'Law of Sines: a/sin A = b/sin B = c/sin C',
                        'Law of Cosines: c² = a² + b² - 2ab cos C',
                        'Area of Triangle: Area = (1/2)ab sin C'
                    ],
                    'content_type' => 'text'
                ],
                [
                    'title' => 'Calculus',
                    'description' => 'Basic calculus formulas',
                    'formulas' => [
                        'Limit Definition of Derivative: f\'(x) = lim(h→0) [f(x+h) - f(x)]/h',
                        'Power Rule: d/dx(xⁿ) = n·xⁿ⁻¹',
                        'Product Rule: d/dx[f(x)·g(x)] = f\'(x)·g(x) + f(x)·g\'(x)',
                        'Quotient Rule: d/dx[f(x)/g(x)] = [f\'(x)·g(x) - f(x)·g\'(x)]/[g(x)]²',
                        'Chain Rule: d/dx[f(g(x))] = f\'(g(x))·g\'(x)',
                        'Indefinite Integral: ∫xⁿ dx = xⁿ⁺¹/(n+1) + C for n≠-1',
                        'Definite Integral: ∫(a to b) f(x) dx = F(b) - F(a)',
                        'Fundamental Theorem of Calculus: d/dx[∫(a to x) f(t) dt] = f(x)'
                    ],
                    'content_type' => 'text'
                ]
            ];
            
            // Biology formulas for SHS
            $default_formulas['biology'] = [
                [
                    'title' => 'Genetics',
                    'description' => 'Genetic probability and inheritance formulas',
                    'formulas' => [
                        'Hardy-Weinberg Equilibrium: p² + 2pq + q² = 1',
                        'Allele Frequency: p + q = 1',
                        'Heterozygosity: H = 2pq',
                        'Homozygosity: h = p² + q²',
                        'Probability of Independent Events: P(A and B) = P(A) × P(B)',
                        'Chi-Square Test: χ² = Σ[(O-E)²/E]'
                    ],
                    'content_type' => 'text'
                ],
                [
                    'title' => 'Ecology',
                    'description' => 'Ecological relationships and population formulas',
                    'formulas' => [
                        'Population Growth: dN/dt = rN(1-N/K)',
                        'Exponential Growth: N(t) = N₀eʳᵗ',
                        'Population Density: D = N/A',
                        'Species Diversity Index: H\' = -Σ(pᵢ × ln(pᵢ))',
                        'Species Richness: S = number of species',
                        'Species Evenness: E = H\'/ ln(S)',
                        'Trophic Efficiency: TE = (energy at trophic level n+1)/(energy at trophic level n) × 100%'
                    ],
                    'content_type' => 'text'
                ],
                [
                    'title' => 'Physiology',
                    'description' => 'Physiological and metabolic formulas',
                    'formulas' => [
                        'Cardiac Output: CO = HR × SV',
                        'Fick\'s Law of Diffusion: J = -DA(dC/dx)',
                        'Respiratory Exchange Ratio: RER = CO₂ produced/O₂ consumed',
                        'Body Mass Index: BMI = mass(kg)/[height(m)]²',
                        'Metabolic Rate: MR = 3.5 × mass(kg) × time(min)'
                    ],
                    'content_type' => 'text'
                ]
            ];
            
            // Economics formulas for SHS
            $default_formulas['economics'] = [
                [
                    'title' => 'Microeconomics',
                    'description' => 'Basic microeconomic formulas',
                    'formulas' => [
                        'Elasticity of Demand: Ed = (% change in Qd)/(% change in P)',
                        'Elasticity of Supply: Es = (% change in Qs)/(% change in P)',
                        'Total Revenue: TR = P × Q',
                        'Marginal Revenue: MR = ΔTR/ΔQ',
                        'Total Cost: TC = TFC + TVC',
                        'Marginal Cost: MC = ΔTC/ΔQ',
                        'Profit: π = TR - TC',
                        'Profit Maximization: MR = MC'
                    ],
                    'content_type' => 'text'
                ],
                [
                    'title' => 'Macroeconomics',
                    'description' => 'Basic macroeconomic formulas',
                    'formulas' => [
                        'GDP: GDP = C + I + G + (X - M)',
                        'Unemployment Rate: Unemployment Rate = (Unemployed Workers/Labor Force) × 100%',
                        'Inflation Rate: Inflation Rate = [(CPI₂ - CPI₁)/CPI₁] × 100%',
                        'Money Multiplier: Money Multiplier = 1/RRR',
                        'Quantity Theory of Money: MV = PY',
                        'Real Interest Rate: r = i - π',
                        'Okun\'s Law: % change in real GDP = 3% - 2 × (% change in unemployment)'
                    ],
                    'content_type' => 'text'
                ]
            ];
            
            // Geography formulas for SHS
            $default_formulas['geography'] = [
                [
                    'title' => 'Physical Geography',
                    'description' => 'Formulas for physical geography calculations',
                    'formulas' => [
                        'Map Scale: Ground Distance = Map Distance × Scale Denominator',
                        'Gradient: Gradient = (Change in Elevation/Horizontal Distance) × 100%',
                        'Stream Discharge: Q = A × V (where Q is discharge, A is cross-sectional area, V is velocity)',
                        'Drainage Density: Dd = L/A (where L is total stream length, A is drainage area)',
                        'Relative Relief: RR = (Highest Elevation - Lowest Elevation)/Area',
                        'Temperature Lapse Rate: Temperature Change = Lapse Rate × Change in Elevation'
                    ],
                    'content_type' => 'text'
                ],
                [
                    'title' => 'Human Geography',
                    'description' => 'Formulas for human geography calculations',
                    'formulas' => [
                        'Population Density: PD = Population/Area',
                        'Population Growth Rate: r = (CBR - CDR)/10 + (Immigration - Emigration)/Population',
                        'Doubling Time: t = 70/r (where r is growth rate in percent)',
                        'Dependency Ratio: DR = [(Population under 15 + Population over 64)/Population 15-64] × 100',
                        'Nearest Neighbor Index: NNI = Observed Mean Distance/Expected Mean Distance',
                        'Location Quotient: LQ = (Local Employment in Industry/Total Local Employment)/(National Employment in Industry/Total National Employment)'
                    ],
                    'content_type' => 'text'
                ]
            ];
            
            // Technical Drawing formulas for SHS
            $default_formulas['technical_drawing'] = [
                [
                    'title' => 'Geometric Constructions',
                    'description' => 'Formulas for technical drawing and geometric constructions',
                    'formulas' => [
                        'Scale Factor: SF = Drawing Dimension/Actual Dimension',
                        'Area of Regular Polygon: A = (n × s²)/(4 × tan(π/n))',
                        'Interior Angle of Regular Polygon: θ = (n-2) × 180°/n',
                        'Exterior Angle of Regular Polygon: φ = 360°/n',
                        'Circumference of Circle: C = 2πr',
                        'Arc Length: L = r × θ (where θ is in radians)',
                        'Sector Area: A = (1/2) × r² × θ (where θ is in radians)'
                    ],
                    'content_type' => 'text'
                ],
                [
                    'title' => 'Projections',
                    'description' => 'Formulas for projection drawings',
                    'formulas' => [
                        'Isometric Scale Factor: SF = 0.816 (or √(2/3))',
                        'Perspective Scale: PS = D/(D-d) (where D is distance to picture plane, d is distance to object)',
                        'Vanishing Point Location: VP = h × D/d (where h is height of eye level)',
                        'True Length of Line: TL = √(x² + y² + z²)',
                        'Slope of Line: m = rise/run = y/x'
                    ],
                    'content_type' => 'text'
                ]
            ];
            
            // Electrical Engineering formulas for SHS
            $default_formulas['electrical_engineering'] = [
                [
                    'title' => 'Basic Electrical Formulas',
                    'description' => 'Fundamental electrical engineering formulas',
                    'formulas' => [
                        'Ohm\'s Law: V = IR',
                        'Power: P = VI = I²R = V²/R',
                        'Resistance in Series: Rtotal = R₁ + R₂ + R₃ + ...',
                        'Resistance in Parallel: 1/Rtotal = 1/R₁ + 1/R₂ + 1/R₃ + ...',
                        'Capacitance in Series: 1/Ctotal = 1/C₁ + 1/C₂ + 1/C₃ + ...',
                        'Capacitance in Parallel: Ctotal = C₁ + C₂ + C₃ + ...',
                        'Inductance in Series: Ltotal = L₁ + L₂ + L₃ + ...',
                        'Inductance in Parallel: 1/Ltotal = 1/L₁ + 1/L₂ + 1/L₃ + ...'
                    ],
                    'content_type' => 'text'
                ],
                [
                    'title' => 'AC Circuit Formulas',
                    'description' => 'Alternating current circuit formulas',
                    'formulas' => [
                        'Impedance: Z = √(R² + (XL - XC)²)',
                        'Inductive Reactance: XL = 2πfL',
                        'Capacitive Reactance: XC = 1/(2πfC)',
                        'Resonant Frequency: f = 1/(2π√(LC))',
                        'Power Factor: PF = cos(φ) = R/Z',
                        'Apparent Power: S = VI',
                        'Real Power: P = VI cos(φ)',
                        'Reactive Power: Q = VI sin(φ)'
                    ],
                    'content_type' => 'text'
                ]
            ];
        }
        
        $formulas = $default_formulas;
    }
    
    // Filter by subject if not 'all'
    if ($subject !== 'all' && isset($formulas[$subject])) {
        $filtered_formulas = [$subject => $formulas[$subject]];
        $formulas = $filtered_formulas;
    }
    
} catch (Exception $e) {
    error_log('Formula Sheets Error: ' . $e->getMessage());
    $error = 'An error occurred while loading the formula sheets. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formula Sheets - AcademicEase</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    
    <style>
        .formula-card {
            transition: transform 0.2s;
            height: 100%;
        }
        
        .formula-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .formula-list {
            padding-left: 0;
            list-style-type: none;
        }
        
        .formula-list li {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .formula-list li:last-child {
            border-bottom: none;
        }
        
        .formula-img {
            height: 120px;
            object-fit: contain;
            margin-bottom: 15px;
        }
        
        .category-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="../assets/img/logo.png" alt="AcademicEase Logo" height="40">
                AcademicEase
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ai-assistant.php">AI Assistant</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="study.php">Study</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="schedule.php">Schedule</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="dropdown me-3">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> <?php echo isset($user['full_name']) ? htmlspecialchars($user['full_name']) : 'User'; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Formula Sheets Content -->
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="study.php">Study</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Formula Sheets</li>
                    </ol>
                </nav>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="mb-0">Formula Sheets</h2>
                <p class="text-muted">Quick reference guides for essential formulas</p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="btn-group">
                    <a href="?level=jhs" class="btn btn-outline-primary <?php echo $level === 'jhs' ? 'active' : ''; ?>">JHS</a>
                    <a href="?level=shs" class="btn btn-outline-primary <?php echo $level === 'shs' ? 'active' : ''; ?>">SHS</a>
                </div>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
            </div>
        <?php else: ?>
            <!-- Category Filters -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-body p-0">
                            <div class="list-group list-group-horizontal-md list-group-flush">
                                <a href="?level=<?php echo $level; ?>&subject=all" class="list-group-item list-group-item-action <?php echo $subject === 'all' ? 'active' : ''; ?>">
                                    <i class="bi bi-grid-3x3-gap me-2"></i> All Categories
                                </a>
                                <?php foreach ($categories as $cat_key => $cat_name): ?>
                                <a href="?level=<?php echo $level; ?>&subject=<?php echo $cat_key; ?>" class="list-group-item list-group-item-action <?php echo $subject === $cat_key ? 'active' : ''; ?>">
                                    <i class="bi bi-journal-text me-2"></i> <?php echo htmlspecialchars($cat_name); ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Formula Cards -->
            <?php foreach ($formulas as $category => $formula_sets): ?>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h3 class="mb-3"><?php echo htmlspecialchars($categories[$category]); ?> Formulas</h3>
                    </div>
                </div>
                
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-5">
                    <?php foreach ($formula_sets as $formula_set): ?>
                        <div class="col">
                            <div class="card formula-card shadow-sm h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="badge bg-primary"><?php echo isset($categories[$category]) ? htmlspecialchars($categories[$category]) : ucfirst($category); ?></span>
                                        <?php if (!empty($formula_set['topic'])): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($formula_set['topic']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <h5 class="card-title"><?php echo htmlspecialchars($formula_set['title']); ?></h5>
                                    <p class="card-text text-muted"><?php echo htmlspecialchars($formula_set['description']); ?></p>
                                    
                                    <?php if ($formula_set['content_type'] === 'text' && !empty($formula_set['formulas'])): ?>
                                        <ul class="formula-list mt-3">
                                            <?php foreach ($formula_set['formulas'] as $formula): ?>
                                                <li><?php echo htmlspecialchars($formula); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php elseif ($formula_set['content_type'] === 'file' && !empty($formula_set['file_path'])): ?>
                                        <div class="d-flex align-items-center mt-3">
                                            <?php 
                                            $file_ext = pathinfo($formula_set['file_name'], PATHINFO_EXTENSION);
                                            $icon_class = 'bi-file-earmark';
                                            
                                            if (in_array($file_ext, ['pdf'])) {
                                                $icon_class = 'bi-file-earmark-pdf';
                                            } elseif (in_array($file_ext, ['doc', 'docx'])) {
                                                $icon_class = 'bi-file-earmark-word';
                                            } elseif (in_array($file_ext, ['xls', 'xlsx'])) {
                                                $icon_class = 'bi-file-earmark-excel';
                                            } elseif (in_array($file_ext, ['ppt', 'pptx'])) {
                                                $icon_class = 'bi-file-earmark-slides';
                                            } elseif (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                                $icon_class = 'bi-file-earmark-image';
                                            }
                                            ?>
                                            <i class="bi <?php echo $icon_class; ?> text-primary me-2" style="font-size: 1.5rem;"></i>
                                            <span class="text-muted small"><?php echo htmlspecialchars($formula_set['file_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-white border-top-0">
                                    <?php if ($formula_set['content_type'] === 'text'): ?>
                                        <button class="btn btn-sm btn-outline-primary w-100" onclick="printFormula('formula-<?php echo htmlspecialchars($formula_set['formula_id']); ?>')">
                                            <i class="bi bi-printer me-1"></i> Print
                                        </button>
                                    <?php elseif ($formula_set['content_type'] === 'file' && !empty($formula_set['file_path'])): ?>
                                        <a href="../<?php echo htmlspecialchars($formula_set['file_path']); ?>" class="btn btn-sm btn-outline-primary w-100" target="_blank">
                                            <i class="bi bi-download me-1"></i> Download
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($formulas)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle-fill me-2"></i> No formula sheets found for the selected criteria. Please try another category or level.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <footer class="footer py-4 bg-light mt-auto">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; 2025 AcademicEase. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="privacy.php" class="text-muted me-3">Privacy Policy</a>
                    <a href="terms.php" class="text-muted me-3">Terms of Service</a>
                    <a href="support.php" class="text-muted">Support</a>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function printFormula(title) {
            alert('Printing ' + title + ' formula sheet...');
            // In a real implementation, this would open a print dialog
            // window.print();
        }
    </script>
</body>
</html>
