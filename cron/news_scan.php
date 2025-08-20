<?php
/**
 * News Scanning Cron Job
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Runs hourly at :15 minutes to scan for news items
 * Timezone: Asia/Dubai
 */

require_once '../src/Bootstrap.php';

use SamFedBiz\Core\ProgramRegistry;
use SamFedBiz\Adapters\TLSAdapter;

// Set timezone to Dubai
date_default_timezone_set('Asia/Dubai');

// Log start time
$startTime = new DateTime();
echo "News scan started at: " . $startTime->format('Y-m-d H:i:s T') . "\n";

try {
    // Initialize program registry
    $programRegistry = new ProgramRegistry($pdo);
    $activePrograms = $programRegistry->getActivePrograms();
    
    $totalNewsItems = 0;
    $newItems = 0;
    $errors = [];
    
    foreach ($activePrograms as $program) {
        echo "Scanning news for program: {$program['code']}\n";
        
        try {
            // Get program-specific keywords from adapter
            $keywords = [];
            $adapter = null;
            
            switch ($program['code']) {
                case 'tls':
                    $adapter = new TLSAdapter();
                    $keywords = $adapter->keywords();
                    break;
                default:
                    $keywords = $program['keywords'] ?? [];
                    break;
            }
            
            echo "Using keywords: " . implode(', ', array_slice($keywords, 0, 5)) . "...\n";
            
            // Scan RSS feeds and APIs for program-related news
            $newsItems = scanNewsFeeds($keywords, $program['code']);
            
            foreach ($newsItems as $item) {
                // Check if news item already exists
                $stmt = $pdo->prepare("
                    SELECT id FROM news_items 
                    WHERE url = ? OR (title = ? AND published_at = ?)
                ");
                $stmt->execute([$item['url'], $item['title'], $item['published_at']]);
                $exists = $stmt->fetch();
                
                if (!$exists) {
                    // Calculate reliability score
                    $reliability = calculateReliability($item);
                    
                    // Insert new news item
                    $stmt = $pdo->prepare("
                        INSERT INTO news_items 
                        (title, content, url, source, tags, published_at, program_code, reliability, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $item['title'],
                        $item['content'],
                        $item['url'],
                        $item['source'],
                        json_encode($item['tags']),
                        $item['published_at'],
                        $program['code'],
                        $reliability
                    ]);
                    $newItems++;
                    echo "Added: {$item['title']} (Reliability: {$reliability})\n";
                }
                $totalNewsItems++;
            }
            
        } catch (Exception $e) {
            $errors[] = "Error scanning {$program['code']}: " . $e->getMessage();
            error_log("News scan error for {$program['code']}: " . $e->getMessage());
        }
    }
    
    // Log scan results
    $stmt = $pdo->prepare("
        INSERT INTO activity_log (entity_type, entity_id, activity_type, activity_data, created_by, created_at)
        VALUES ('system', 'news_scan', 'scan_completed', ?, 1, NOW())
    ");
    $stmt->execute([json_encode([
        'total_items_processed' => $totalNewsItems,
        'new_items' => $newItems,
        'errors' => $errors,
        'scan_time' => $startTime->format('c'),
        'timezone' => 'Asia/Dubai'
    ])]);
    
    echo "News scan completed: {$newItems} new items out of {$totalNewsItems} processed\n";
    if (!empty($errors)) {
        echo "Errors encountered: " . implode('; ', $errors) . "\n";
    }
    
} catch (Exception $e) {
    error_log("News scan failed: " . $e->getMessage());
    echo "News scan failed: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Scan RSS feeds and APIs for news items
 */
function scanNewsFeeds($keywords, $programCode) {
    $newsItems = [];
    
    // Primary federal news RSS feeds (High reliability)
    $primaryFeeds = [
        'Federal News Network' => [
            'url' => 'https://federalnewsnetwork.com/feed/',
            'reliability' => 'confirmed'
        ],
        'FCW' => [
            'url' => 'https://fcw.com/rss-feeds/all.aspx',
            'reliability' => 'confirmed'
        ],
        'NextGov' => [
            'url' => 'https://www.nextgov.com/rss/',
            'reliability' => 'confirmed'
        ]
    ];
    
    // Secondary feeds (Medium reliability)
    $secondaryFeeds = [
        'Defense News' => [
            'url' => 'https://www.defensenews.com/arc/outboundfeeds/rss/',
            'reliability' => 'developing'
        ],
        'Government Technology' => [
            'url' => 'https://www.govtech.com/rss.php',
            'reliability' => 'developing'
        ],
        'GovExec' => [
            'url' => 'https://www.govexec.com/rss/all/',
            'reliability' => 'developing'
        ]
    ];
    
    // Signal feeds (Lower reliability, more speculative)
    $signalFeeds = [
        'Federal Times' => [
            'url' => 'https://www.federaltimes.com/feed/',
            'reliability' => 'signal'
        ],
        'Washington Technology' => [
            'url' => 'https://washingtontechnology.com/rss/all.aspx',
            'reliability' => 'signal'
        ]
    ];
    
    $allFeeds = array_merge($primaryFeeds, $secondaryFeeds, $signalFeeds);
    
    foreach ($allFeeds as $sourceName => $feedData) {
        echo "Scanning {$sourceName}...\n";
        
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'samfedbiz.com/1.0 (+https://samfedbiz.com)'
                ]
            ]);
            
            $rssContent = file_get_contents($feedData['url'], false, $context);
            if ($rssContent === false) {
                echo "Failed to fetch {$sourceName}\n";
                continue;
            }
            
            $rss = simplexml_load_string($rssContent);
            
            if ($rss && isset($rss->channel->item)) {
                $itemCount = 0;
                foreach ($rss->channel->item as $item) {
                    if ($itemCount >= 20) break; // Limit per feed
                    
                    $title = (string)$item->title;
                    $description = (string)$item->description;
                    $url = (string)$item->link;
                    $pubDate = (string)$item->pubDate;
                    
                    // Skip items older than 7 days
                    $publishedTime = strtotime($pubDate);
                    if ($publishedTime < (time() - (7 * 24 * 60 * 60))) {
                        continue;
                    }
                    
                    // Check if item matches keywords
                    if (matchesKeywords($title . ' ' . $description, $keywords)) {
                        $newsItems[] = [
                            'title' => trim($title),
                            'content' => trim(strip_tags($description)),
                            'url' => trim($url),
                            'source' => $sourceName,
                            'published_at' => date('Y-m-d H:i:s', $publishedTime),
                            'tags' => array_merge([$programCode], extractRelevantKeywords($title . ' ' . $description, $keywords)),
                            'feed_reliability' => $feedData['reliability']
                        ];
                        $itemCount++;
                    }
                }
                echo "Found {$itemCount} relevant items from {$sourceName}\n";
            }
            
        } catch (Exception $e) {
            error_log("Error parsing RSS feed {$sourceName}: " . $e->getMessage());
            echo "Error with {$sourceName}: " . $e->getMessage() . "\n";
        }
        
        // Small delay between feeds
        usleep(500000); // 0.5 seconds
    }
    
    // Scan official government APIs (SAM.gov, FedBizOpps, etc.)
    $govNewsItems = scanGovernmentAPIs($keywords, $programCode);
    $newsItems = array_merge($newsItems, $govNewsItems);
    
    return $newsItems;
}

/**
 * Scan official government APIs for announcements
 */
function scanGovernmentAPIs($keywords, $programCode) {
    $newsItems = [];
    
    // SAM.gov opportunities API (simulated for now)
    // In production, this would use the actual SAM.gov API
    echo "Scanning government APIs...\n";
    
    try {
        // Simulate government news/announcements
        $govSources = [
            'SAM.gov' => [
                'title' => 'New TLS Contract Opportunities Available',
                'content' => 'Defense Logistics Agency announces new tactical logistics support opportunities for small businesses.',
                'url' => 'https://sam.gov/opp/example',
                'reliability' => 'confirmed'
            ],
            'GSA.gov' => [
                'title' => 'OASIS+ Contract Updates and Modifications',
                'content' => 'General Services Administration provides updates on OASIS+ contract modifications and new task order opportunities.',
                'url' => 'https://gsa.gov/news/example',
                'reliability' => 'confirmed'
            ]
        ];
        
        foreach ($govSources as $source => $item) {
            if (matchesKeywords($item['title'] . ' ' . $item['content'], $keywords)) {
                $newsItems[] = [
                    'title' => $item['title'],
                    'content' => $item['content'],
                    'url' => $item['url'],
                    'source' => $source,
                    'published_at' => date('Y-m-d H:i:s'),
                    'tags' => array_merge([$programCode], extractRelevantKeywords($item['title'] . ' ' . $item['content'], $keywords)),
                    'feed_reliability' => $item['reliability']
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Error scanning government APIs: " . $e->getMessage());
    }
    
    return $newsItems;
}

/**
 * Calculate reliability score for news item
 */
function calculateReliability($item) {
    $source = strtolower($item['source']);
    $title = strtolower($item['title']);
    $content = strtolower($item['content']);
    
    // Base reliability from feed
    $baseReliability = $item['feed_reliability'] ?? 'developing';
    
    // High reliability sources
    $highReliabilitySources = [
        'federal news network', 'fcw', 'sam.gov', 'gsa.gov', 
        'defense.gov', 'whitehouse.gov', 'cio.gov'
    ];
    
    // Check for official announcements
    $officialKeywords = [
        'announces', 'official', 'press release', 'statement',
        'policy', 'regulation', 'rfp', 'solicitation'
    ];
    
    // Check for speculative language
    $speculativeKeywords = [
        'rumor', 'speculation', 'sources say', 'allegedly',
        'might', 'could potentially', 'insider reports'
    ];
    
    foreach ($highReliabilitySources as $reliableSource) {
        if (strpos($source, $reliableSource) !== false) {
            return 'confirmed';
        }
    }
    
    foreach ($officialKeywords as $keyword) {
        if (strpos($title, $keyword) !== false || strpos($content, $keyword) !== false) {
            return $baseReliability === 'signal' ? 'developing' : 'confirmed';
        }
    }
    
    foreach ($speculativeKeywords as $keyword) {
        if (strpos($title, $keyword) !== false || strpos($content, $keyword) !== false) {
            return 'signal';
        }
    }
    
    return $baseReliability;
}

/**
 * Check if content matches program keywords
 */
function matchesKeywords($content, $keywords) {
    $content = strtolower($content);
    
    foreach ($keywords as $keyword) {
        if (strpos($content, strtolower($keyword)) !== false) {
            return true;
        }
    }
    
    // Default federal contracting keywords
    $defaultKeywords = [
        'federal contract', 'solicitation', 'rfp', 'rfq', 'procurement',
        'gsa', 'oasis', 'sewp', 'tls', 'tactical logistics',
        'small business', 'set-aside', 'cybersecurity'
    ];
    
    foreach ($defaultKeywords as $keyword) {
        if (strpos($content, $keyword) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Extract relevant keywords from content
 */
function extractRelevantKeywords($content, $programKeywords) {
    $tags = [];
    $content = strtolower($content);
    
    // Check for program-specific keywords
    foreach ($programKeywords as $keyword) {
        if (strpos($content, strtolower($keyword)) !== false) {
            $tags[] = strtolower(str_replace(' ', '_', $keyword));
        }
    }
    
    // Check for topic tags
    $topicKeywords = [
        'cybersecurity' => ['cyber', 'security', 'infosec'],
        'cloud' => ['cloud', 'aws', 'azure', 'saas'],
        'ai_ml' => ['artificial intelligence', 'machine learning', 'ai', 'ml'],
        'logistics' => ['logistics', 'supply chain', 'transportation'],
        'training' => ['training', 'education', 'certification'],
        'it_services' => ['it services', 'help desk', 'system admin']
    ];
    
    foreach ($topicKeywords as $topic => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $tags[] = $topic;
                break;
            }
        }
    }
    
    return array_unique($tags);
}

echo "News scan process completed at: " . (new DateTime())->format('Y-m-d H:i:s T') . "\n";