<?php
/**
 * Daily Brief Builder Cron Job
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Runs daily at 06:00 Dubai time to build the daily brief
 * Timezone: Asia/Dubai
 */

require_once '../src/Bootstrap.php';

use SamFedBiz\Core\ProgramRegistry;
use SamFedBiz\Adapters\TLSAdapter;
use SamFedBiz\Config\EnvManager;

// Set timezone to Dubai
date_default_timezone_set('Asia/Dubai');

// Log start time
$startTime = new DateTime();
echo "Brief build started at: " . $startTime->format('Y-m-d H:i:s T') . "\n";

try {
    // Check if brief already exists for today
    $stmt = $pdo->prepare("
        SELECT id FROM daily_briefs 
        WHERE DATE(created_at) = CURDATE()
    ");
    $stmt->execute();
    $existingBrief = $stmt->fetch();
    
    if ($existingBrief) {
        echo "Brief already exists for today, skipping build\n";
        exit(0);
    }
    
    // Initialize managers
    $envManager = new EnvManager();
    $programRegistry = new ProgramRegistry($pdo);
    $activePrograms = $programRegistry->getActivePrograms();
    
    $briefSections = [];
    $allTags = [];
    $totalItems = 0;
    
    // Build sections for each program
    foreach ($activePrograms as $program) {
        echo "Building section for program: {$program['code']}\n";
        
        try {
            $sectionData = buildProgramSection($pdo, $program);
            if (!empty($sectionData['content'])) {
                $briefSections[] = $sectionData;
                $allTags = array_merge($allTags, $sectionData['tags']);
                $totalItems += $sectionData['item_count'];
            }
        } catch (Exception $e) {
            error_log("Error building section for {$program['code']}: " . $e->getMessage());
        }
    }
    
    // Add general federal contracting section
    $generalSection = buildGeneralSection($pdo);
    if (!empty($generalSection['content'])) {
        $briefSections[] = $generalSection;
        $allTags = array_merge($allTags, $generalSection['tags']);
        $totalItems += $generalSection['item_count'];
    }
    
    // Generate brief title
    $briefTitle = "Federal BD Brief - " . date('M j, Y');
    
    // Generate brief content
    $briefContent = generateBriefContent($briefSections);
    
    // Insert the brief
    $stmt = $pdo->prepare("
        INSERT INTO daily_briefs (title, content, sections, tags, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $briefTitle,
        $briefContent,
        json_encode($briefSections),
        json_encode(array_unique($allTags))
    ]);
    $briefId = $pdo->lastInsertId();
    
    // Log activity
    $stmt = $pdo->prepare("
        INSERT INTO activity_log (entity_type, entity_id, activity_type, activity_data, created_by, created_at)
        VALUES ('system', 'brief_build', 'build_completed', ?, 1, NOW())
    ");
    $stmt->execute([json_encode([
        'brief_id' => $briefId,
        'sections_count' => count($briefSections),
        'total_items' => $totalItems,
        'build_time' => $startTime->format('c'),
        'timezone' => 'Asia/Dubai'
    ])]);
    
    echo "Brief build completed successfully:\n";
    echo "- Brief ID: {$briefId}\n";
    echo "- Sections: " . count($briefSections) . "\n";
    echo "- Total items: {$totalItems}\n";
    
} catch (Exception $e) {
    error_log("Brief build failed: " . $e->getMessage());
    echo "Brief build failed: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Build section for a specific program
 */
function buildProgramSection($pdo, $program) {
    $programCode = $program['code'];
    $programName = $program['name'];
    
    $sectionContent = '';
    $sectionTags = [$programCode];
    $itemCount = 0;
    $signalsAndRumors = [];
    
    // Get recent news items (last 24 hours)
    $stmt = $pdo->prepare("
        SELECT title, content, url, source, published_at
        FROM news_items 
        WHERE program_code = ? AND published_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY published_at DESC
        LIMIT 5
    ");
    $stmt->execute([$programCode]);
    $newsItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get new/updated solicitations (last 24 hours)
    $stmt = $pdo->prepare("
        SELECT opp_no, title, agency, status, close_date, url
        FROM opportunities 
        WHERE program_code = ? AND (
            created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) OR
            updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        )
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$programCode]);
    $solicitations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get closing soon solicitations (next 7 days)
    $stmt = $pdo->prepare("
        SELECT opp_no, title, agency, close_date, url
        FROM opportunities 
        WHERE program_code = ? AND close_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND status = 'open'
        ORDER BY close_date ASC
        LIMIT 3
    ");
    $stmt->execute([$programCode]);
    $closingSoon = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($newsItems) || !empty($solicitations) || !empty($closingSoon)) {
        $sectionContent .= "## {$programName} Updates\n\n";
        
        // Add news items
        if (!empty($newsItems)) {
            $confirmedNews = [];
            $developingNews = [];
            $signalNews = [];
            
            // Categorize news by reliability
            foreach ($newsItems as $item) {
                $reliability = categorizeReliability($item);
                switch ($reliability) {
                    case 'confirmed':
                        $confirmedNews[] = $item;
                        break;
                    case 'signal':
                        $signalNews[] = $item;
                        $signalsAndRumors[] = "📊 {$item['title']} ({$item['source']})";
                        break;
                    default:
                        $developingNews[] = $item;
                }
                $itemCount++;
            }
            
            // Display confirmed news first
            if (!empty($confirmedNews)) {
                $sectionContent .= "### ✅ Confirmed Updates\n";
                foreach ($confirmedNews as $item) {
                    $sectionContent .= "- **{$item['title']}**\n";
                    $sectionContent .= "  Source: {$item['source']} | " . date('M j', strtotime($item['published_at'])) . "\n";
                    $sectionContent .= "  [Read more]({$item['url']})\n\n";
                }
            }
            
            // Display developing news
            if (!empty($developingNews)) {
                $sectionContent .= "### 🔄 Developing Stories\n";
                foreach ($developingNews as $item) {
                    $sectionContent .= "- **{$item['title']}**\n";
                    $sectionContent .= "  Source: {$item['source']} | " . date('M j', strtotime($item['published_at'])) . "\n";
                    $sectionContent .= "  [Read more]({$item['url']})\n\n";
                }
            }
            
            // Display signals and rumors separately
            if (!empty($signalNews)) {
                $sectionContent .= "### 📊 Signals & Market Intelligence\n";
                $sectionContent .= "*The following items are unconfirmed reports that require verification:*\n\n";
                foreach ($signalNews as $item) {
                    $sectionContent .= "- **{$item['title']}** *(Unverified)*\n";
                    $sectionContent .= "  Source: {$item['source']} | " . date('M j', strtotime($item['published_at'])) . "\n";
                    $sectionContent .= "  [Read more]({$item['url']})\n\n";
                }
            }
        }
        
        // Add new solicitations
        if (!empty($solicitations)) {
            $sectionContent .= "### New Opportunities\n";
            foreach ($solicitations as $opp) {
                $sectionContent .= "- **{$opp['title']}** ({$opp['opp_no']})\n";
                $sectionContent .= "  Agency: {$opp['agency']} | Closes: " . date('M j', strtotime($opp['close_date'])) . "\n";
                $sectionContent .= "  [View Details]({$opp['url']})\n\n";
                $itemCount++;
            }
        }
        
        // Add closing soon
        if (!empty($closingSoon)) {
            $sectionContent .= "### Closing Soon ⏰\n";
            foreach ($closingSoon as $opp) {
                $sectionContent .= "- **{$opp['title']}**\n";
                $sectionContent .= "  Closes: " . date('M j, Y', strtotime($opp['close_date'])) . " | {$opp['agency']}\n";
                $sectionContent .= "  [Submit Response]({$opp['url']})\n\n";
                $itemCount++;
            }
        }
        
        // Add AI-powered analysis
        $analysisData = [
            'program_code' => $programCode,
            'program_name' => $programName,
            'news_items' => $newsItems,
            'solicitations' => $solicitations,
            'closing_soon' => $closingSoon
        ];
        
        // Add what it means section
        $sectionContent .= "### What This Means\n";
        $sectionContent .= generateEnhancedAnalysis($analysisData, 'insights');
        $sectionContent .= "\n";
        
        // Add next actions
        $sectionContent .= "### Next Actions\n";
        $sectionContent .= generateEnhancedAnalysis($analysisData, 'actions');
        $sectionContent .= "\n";
    }
    
    return [
        'title' => $programName,
        'content' => $sectionContent,
        'tags' => array_unique($sectionTags),
        'item_count' => $itemCount,
        'signals_and_rumors' => implode("\n", $signalsAndRumors)
    ];
}

/**
 * Build general federal contracting section
 */
function buildGeneralSection($pdo) {
    $sectionContent = '';
    $sectionTags = ['general', 'federal_contracting'];
    $itemCount = 0;
    $signalsAndRumors = [];
    
    // Get general federal contracting news (not program-specific)
    $stmt = $pdo->prepare("
        SELECT title, content, url, source, published_at
        FROM news_items 
        WHERE program_code IS NULL AND published_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY published_at DESC
        LIMIT 3
    ");
    $stmt->execute();
    $generalNews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($generalNews)) {
        $sectionContent .= "## Federal Contracting Landscape\n\n";
        $sectionContent .= "### Industry News\n";
        
        foreach ($generalNews as $item) {
            $sectionContent .= "- **{$item['title']}**\n";
            $sectionContent .= "  Source: {$item['source']} | " . date('M j', strtotime($item['published_at'])) . "\n";
            $sectionContent .= "  [Read more]({$item['url']})\n\n";
            $itemCount++;
            
            if (containsSignalWords($item['title'] . ' ' . $item['content'])) {
                $signalsAndRumors[] = "📊 {$item['title']} ({$item['source']})";
            }
        }
        
        $sectionContent .= "### Market Intelligence\n";
        $sectionContent .= "- Federal spending continues to prioritize technology modernization and cybersecurity\n";
        $sectionContent .= "- Small business set-asides remain a key opportunity for emerging vendors\n";
        $sectionContent .= "- Agencies are increasingly focused on best-value procurements over lowest-price\n\n";
    }
    
    return [
        'title' => 'Federal Contracting',
        'content' => $sectionContent,
        'tags' => $sectionTags,
        'item_count' => $itemCount,
        'signals_and_rumors' => implode("\n", $signalsAndRumors)
    ];
}

/**
 * Generate enhanced AI-powered analysis
 */
function generateEnhancedAnalysis($data, $type = 'insights') {
    global $envManager;
    
    // Try AI analysis first, fallback to rule-based
    $aiAnalysis = generateAIAnalysis($data, $type, $envManager);
    
    if ($aiAnalysis) {
        return $aiAnalysis;
    }
    
    // Fallback to enhanced rule-based analysis
    if ($type === 'insights') {
        return generateWhatItMeans($data['program_code'], $data['news_items'], $data['solicitations'], $data['closing_soon']);
    } else {
        return generateNextActions($data['program_code'], $data['solicitations'], $data['closing_soon']);
    }
}

/**
 * Generate AI-powered analysis using OpenAI
 */
function generateAIAnalysis($data, $type, $envManager) {
    $openaiKey = $envManager->get('OPENAI_API_KEY');
    if (!$openaiKey) {
        return null;
    }
    
    $programCode = $data['program_code'];
    $programName = $data['program_name'];
    $newsCount = count($data['news_items']);
    $solicitationCount = count($data['solicitations']);
    $closingSoonCount = count($data['closing_soon']);
    
    // Build context for AI
    $context = "Program: {$programName} ({$programCode})\n";
    $context .= "Activity Summary: {$newsCount} news items, {$solicitationCount} new solicitations, {$closingSoonCount} closing soon\n\n";
    
    // Add sample news titles
    if (!empty($data['news_items'])) {
        $context .= "Recent News:\n";
        foreach (array_slice($data['news_items'], 0, 3) as $item) {
            $context .= "- {$item['title']}\n";
        }
        $context .= "\n";
    }
    
    // Add solicitation info
    if (!empty($data['solicitations'])) {
        $context .= "New Opportunities:\n";
        foreach (array_slice($data['solicitations'], 0, 3) as $opp) {
            $context .= "- {$opp['title']} ({$opp['agency']})\n";
        }
        $context .= "\n";
    }
    
    if ($type === 'insights') {
        $prompt = "Based on this federal contracting activity, provide 2-3 bullet points analyzing what this means for businesses pursuing {$programName} opportunities. Focus on market trends, timing implications, and competitive landscape. Be concise and actionable.";
    } else {
        $prompt = "Based on this federal contracting activity, provide 3-4 specific action items for businesses pursuing {$programName} opportunities. Focus on immediate next steps, timing considerations, and strategic moves. Use bullet points starting with action verbs.";
    }
    
    $apiData = [
        'model' => 'gpt-4',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a federal contracting analyst providing strategic insights for business development. Be concise, specific, and actionable. Focus on timing, competition, and opportunity assessment.'
            ],
            [
                'role' => 'user',
                'content' => $context . $prompt
            ]
        ],
        'max_tokens' => 300,
        'temperature' => 0.3
    ];
    
    try {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($apiData),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $openaiKey,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['choices'][0]['message']['content'])) {
                $content = $data['choices'][0]['message']['content'];
                
                // Ensure bullet point format
                if (!str_starts_with(trim($content), '-') && !str_starts_with(trim($content), '•')) {
                    $lines = array_filter(explode("\n", trim($content)));
                    $content = implode("\n", array_map(function($line) {
                        return '- ' . ltrim($line, '- •');
                    }, $lines));
                }
                
                return $content . "\n";
            }
        }
        
    } catch (Exception $e) {
        error_log("AI analysis failed: " . $e->getMessage());
    }
    
    return null;
}

/**
 * Generate "What This Means" analysis (fallback)
 */
function generateWhatItMeans($programCode, $newsItems, $solicitations, $closingSoon) {
    $analysis = '';
    
    // Market activity analysis
    if (!empty($solicitations)) {
        $analysis .= "- Increased agency demand signals growing market opportunity in this sector\n";
    }
    
    if (!empty($closingSoon)) {
        $analysis .= "- " . count($closingSoon) . " opportunity(ies) closing soon require immediate bid/no-bid decisions\n";
    }
    
    if (!empty($newsItems)) {
        $analysis .= "- Recent developments indicate evolving agency priorities and requirements\n";
    }
    
    // Program-specific strategic insights
    switch ($programCode) {
        case 'tls':
            $analysis .= "- TLS opportunities favor companies with rapid deployment capabilities and tactical expertise\n";
            $analysis .= "- Strong past performance in emergency/tactical support increases win probability\n";
            break;
        case 'oasis+':
            $analysis .= "- OASIS+ task orders emphasize technical capability and demonstrated experience\n";
            $analysis .= "- Pool positioning and domain expertise critical for capture success\n";
            break;
        case 'sewp':
            $analysis .= "- SEWP VI competition intensifying - OEM relationships and pricing crucial\n";
            $analysis .= "- Technology refresh cycles driving increased spending\n";
            break;
    }
    
    // Market timing insights
    $month = date('n');
    if ($month >= 6 && $month <= 9) {
        $analysis .= "- End-of-fiscal-year period increases opportunity velocity and urgency\n";
    } elseif ($month >= 10 && $month <= 12) {
        $analysis .= "- New fiscal year brings fresh funding and strategic planning opportunities\n";
    }
    
    return $analysis ?: "- Continue monitoring for emerging opportunities and market developments\n";
}

/**
 * Generate next actions (fallback)
 */
function generateNextActions($programCode, $solicitations, $closingSoon) {
    $actions = '';
    
    // Immediate actions for closing opportunities
    if (!empty($closingSoon)) {
        $actions .= "- Conduct bid/no-bid analysis for " . count($closingSoon) . " closing opportunity(ies)\n";
        $actions .= "- Assemble response teams for qualified opportunities\n";
        $actions .= "- Verify technical compliance and past performance requirements\n";
    }
    
    // Business development actions for new opportunities
    if (!empty($solicitations)) {
        $actions .= "- Analyze new opportunities for capability fit and competitive positioning\n";
        $actions .= "- Initiate prime contractor outreach for teaming discussions\n";
        $actions .= "- Schedule customer engagement calls for requirement clarification\n";
    }
    
    // Program-specific strategic actions
    switch ($programCode) {
        case 'tls':
            $actions .= "- Update tactical logistics capabilities and certifications\n";
            $actions .= "- Review emergency response procedures and rapid deployment assets\n";
            break;
        case 'oasis+':
            $actions .= "- Verify pool alignment and domain positioning for new task orders\n";
            $actions .= "- Update OASIS+ profile with recent contract awards and capabilities\n";
            break;
        case 'sewp':
            $actions .= "- Confirm OEM authorizations and reseller agreements are current\n";
            $actions .= "- Review pricing models for competitiveness in current market\n";
            break;
    }
    
    // General continuous improvement actions
    $actions .= "- Monitor for solicitation amendments and agency Q&A responses\n";
    $actions .= "- Update past performance narratives with recent contract successes\n";
    
    // Timing-based actions
    $month = date('n');
    if ($month >= 6 && $month <= 9) {
        $actions .= "- Prioritize end-of-fiscal-year opportunities for faster procurement cycles\n";
    } elseif ($month >= 10 && $month <= 12) {
        $actions .= "- Engage in strategic planning discussions for new fiscal year requirements\n";
    }
    
    return $actions;
}

/**
 * Check if content contains signal/rumor indicators
 */
function containsSignalWords($content) {
    $signalWords = [
        // Rumor indicators
        'rumor', 'speculation', 'expected', 'anticipated', 'likely',
        'sources say', 'industry chatter', 'unconfirmed', 'potential',
        'may', 'might', 'could', 'possible', 'probable',
        // Uncertainty indicators
        'reportedly', 'allegedly', 'appears to', 'seems to',
        'suggests', 'indicates', 'preliminary', 'tentative',
        // Conditional language
        'if approved', 'pending', 'under consideration', 'proposed',
        'draft', 'preliminary award', 'intent to award'
    ];
    
    $content = strtolower($content);
    foreach ($signalWords as $word) {
        if (strpos($content, $word) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Categorize news item reliability
 */
function categorizeReliability($item) {
    $title = strtolower($item['title']);
    $content = strtolower($item['content']);
    $source = strtolower($item['source']);
    
    // High reliability sources
    $highReliabilitySources = [
        'federal news network', 'sam.gov', 'gsa.gov', 'defense.gov',
        'whitehouse.gov', 'cio.gov', 'treasury.gov'
    ];
    
    // Official announcements vs rumors
    $officialIndicators = [
        'announces', 'awards', 'selects', 'official', 'confirmed',
        'signed', 'released', 'published', 'issued'
    ];
    
    foreach ($highReliabilitySources as $reliableSource) {
        if (strpos($source, $reliableSource) !== false) {
            return 'confirmed';
        }
    }
    
    foreach ($officialIndicators as $indicator) {
        if (strpos($title, $indicator) !== false) {
            return 'confirmed';
        }
    }
    
    if (containsSignalWords($title . ' ' . $content)) {
        return 'signal';
    }
    
    return 'developing';
}

/**
 * Generate complete brief content
 */
function generateBriefContent($sections) {
    $content = "# Federal Business Development Daily Brief\n";
    $content .= "*" . date('l, F j, Y') . " | Dubai Time*\n\n";
    
    $content .= "Good morning from the samfedbiz.com team. Here's your intelligence update for federal business development opportunities.\n\n";
    
    foreach ($sections as $section) {
        $content .= $section['content'] . "\n";
    }
    
    $content .= "---\n\n";
    $content .= "**About This Brief**\n";
    $content .= "This brief is generated daily at 06:00 Dubai time and covers TLS, OASIS+, SEWP, and general federal contracting opportunities.\n\n";
    $content .= "**Intelligence Reliability:**\n";
    $content .= "- ✅ **Confirmed Updates**: Official announcements from verified government sources\n";
    $content .= "- 🔄 **Developing Stories**: News from credible sources requiring further monitoring\n";
    $content .= "- 📊 **Signals & Market Intelligence**: Unconfirmed reports requiring verification\n\n";
    $content .= "**Important:** Always verify information marked as signals or developing stories before taking action.\n\n";
    $content .= "*Developed by AXIVAI.COM for Quartermasters FZC*\n";
    
    return $content;
}

echo "Brief build process completed at: " . (new DateTime())->format('Y-m-d H:i:s T') . "\n";