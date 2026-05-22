<?php
/**
 * AJAX Endpoint: Fetch AI Insights from Gemini API
 * Aggregates database sales history and calls Gemini or returns simulated insights.
 */
require_once '../includes/auth.php';
requireAdmin();
header('Content-Type: application/json');

$demoMode = isset($_POST['demo']) && $_POST['demo'] === '1';
$apiKey = getSetting('gemini_api_key', '');

if (empty($apiKey) && !$demoMode) {
    echo json_encode([
        'error' => 'API_KEY_MISSING',
        'message' => 'Please configure your Google Gemini API Key in System Settings first, or toggle Demo Mode to preview.'
    ]);
    exit;
}

try {
    $db = getDB();

    // 1. Gather historical data from last 90 days for aggregation
    // Product Sales Volume
    $prodQuery = $db->query("SELECT bi.product_name, SUM(bi.quantity) as total_qty, 
        SUM(bi.final_total) as total_revenue, AVG(bi.unit_price) as avg_price, COUNT(DISTINCT bi.bill_id) as bill_count
        FROM bill_items bi JOIN bills b ON bi.bill_id = b.id
        WHERE b.status = 'completed' AND b.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        GROUP BY bi.product_name ORDER BY total_qty DESC");
    $productSales = $prodQuery->fetchAll();

    // Day of Week sales pattern
    $dowQuery = $db->query("SELECT DAYOFWEEK(created_at) as dow, SUM(grand_total) as total_revenue, COUNT(*) as bill_count
        FROM bills WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        GROUP BY dow ORDER BY dow ASC");
    $dayOfWeekSales = $dowQuery->fetchAll();

    // Monthly Sales
    $monthlyQuery = $db->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(grand_total) as total_revenue, COUNT(*) as bill_count
        FROM bills WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 180 DAY)
        GROUP BY ym ORDER BY ym ASC");
    $monthlySales = $monthlyQuery->fetchAll();

    // Payment Methods
    $payQuery = $db->query("SELECT payment_method, COUNT(*) as bill_count, SUM(grand_total) as total_revenue
        FROM bills WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        GROUP BY payment_method");
    $paymentMethods = $payQuery->fetchAll();

    // Total Discounts vs Subtotal
    $discountQuery = $db->query("SELECT COALESCE(SUM(total_discount),0) as total_discounts, COALESCE(SUM(subtotal),0) as total_subtotal
        FROM bills WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $discountImpact = $discountQuery->fetch();

    $shopName = getSetting('shop_name', 'Jewel One Matale');

    // Aggregate into summary array
    $salesSummary = [
        'shop_name' => $shopName,
        'location' => 'Matale, Sri Lanka',
        'analysis_period_days' => 90,
        'products_sold' => $productSales,
        'day_of_week_distribution_1_to_7' => $dayOfWeekSales,
        'monthly_revenue_history' => $monthlySales,
        'payment_methods_usage' => $paymentMethods,
        'discount_impact' => $discountImpact
    ];

    if ($demoMode) {
        // High fidelity simulated response for Demo Mode
        sleep(1.5); // Simulate network latency

        // Let's customize summary if database is completely empty
        $emptyDb = empty($productSales);
        
        $simulatedResponse = [
            "summary" => $emptyDb 
                ? "The system is running on a fresh database. Based on typical jewelry retail trends in Matale, Friday/Saturday show peak traffic. High demand is anticipated for 22K Gold ornaments due to local wedding preparations."
                : "Matale region POS sales demonstrate strong weekly seasonality with peaks on Friday and Saturday (over 55% of weekly revenue). Total revenue shows consistent monthly growth, with gold rings and bangles maintaining the highest sales velocity.",
            "predicted_sales" => [
                [
                    "product_name" => $emptyDb ? "Gold Ring 22K" : ($productSales[0]['product_name'] ?? "Gold Ring 22K"),
                    "predicted_qty_next_30_days" => $emptyDb ? 24.0 : round(($productSales[0]['total_qty'] ?? 10) * 0.45 + 5, 1),
                    "confidence" => "High",
                    "reason" => "Consistent historical weekend buying patterns and wedding season catalog demand in the Central Province."
                ],
                [
                    "product_name" => $emptyDb ? "Silver Bracelet 925" : ($productSales[1]['product_name'] ?? "Silver Bracelet 925"),
                    "predicted_qty_next_30_days" => $emptyDb ? 15.5 : round(($productSales[1]['total_qty'] ?? 6) * 0.48 + 3, 1),
                    "confidence" => "Medium",
                    "reason" => "High volume of low-mid tier impulse sales, especially during pay-day weekends."
                ],
                [
                    "product_name" => $emptyDb ? "Diamond Necklace 18K" : ($productSales[2]['product_name'] ?? "Diamond Necklace 18K"),
                    "predicted_qty_next_30_days" => $emptyDb ? 3.0 : round(($productSales[2]['total_qty'] ?? 1) * 0.35 + 1, 1),
                    "confidence" => "Low",
                    "reason" => "High ticket price item with longer consideration cycles; demand spikes are tied to bridal events."
                ]
            ],
            "restock_alerts" => [
                [
                    "product_name" => $emptyDb ? "Gold Ring 22K" : ($productSales[0]['product_name'] ?? "Gold Ring 22K"),
                    "urgency" => "High",
                    "suggested_qty" => 12,
                    "reason" => "Demand velocity analysis predicts current stock reserves will run dry in 10-14 days due to weekend run-rates."
                ],
                [
                    "product_name" => $emptyDb ? "Silver Bracelet 925" : ($productSales[1]['product_name'] ?? "Silver Bracelet 925"),
                    "urgency" => "Medium",
                    "suggested_qty" => 8,
                    "reason" => "Consistent transaction attachment rate shows risk of inventory stockouts on peak shopping days."
                ]
            ],
            "cross_selling" => [
                [
                    "combination" => $emptyDb ? "Gold Ring 22K + Gold Bangle" : (($productSales[0]['product_name'] ?? "Gold Ring 22K") . " + " . ($productSales[4]['product_name'] ?? "Gold Bangle 22K")),
                    "conversion_tip" => "Introduce a 'Bridal Harmony Set' package. Provide a complimentary high-end velvet storage box instead of cash discounts to sustain profit margins."
                ],
                [
                    "combination" => $emptyDb ? "Ruby Earring Set + Silver Bracelet" : (($productSales[2]['product_name'] ?? "Ruby Earring Set") . " + " . ($productSales[1]['product_name'] ?? "Silver Bracelet 925")),
                    "conversion_tip" => "Train sales associates to present coordinating earrings as an elegant matched gift at the counter during checkout."
                ]
            ],
            "pricing_strategies" => [
                [
                    "product_name" => $emptyDb ? "Diamond Necklace 18K" : ($productSales[1]['product_name'] ?? "Diamond Necklace 18K"),
                    "strategy" => "Exclusive Experience Bundling",
                    "actionable_tip" => "Bundle with a free professional lifetime clean-and-polish service card rather than cutting unit prices, preserving luxury brand equity."
                ],
                [
                    "product_name" => $emptyDb ? "Silver Bracelet 925" : ($productSales[3]['product_name'] ?? "Silver Bracelet 925"),
                    "strategy" => "Multi-Buy Discount",
                    "actionable_tip" => "Introduce a 'Buy one, get the second at 15% off' to clear silver inventory before restocking newer, lighter designs."
                ]
            ]
        ];

        echo json_encode(['success' => true, 'insights' => $simulatedResponse, 'is_demo' => true]);
        exit;
    }

    // Call real Gemini API
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;
    
    // Construct rich analysis prompt
    $promptText = "You are an expert AI business intelligence consultant specializing in jewelry retail forecasting.
Analyze the following historical sales data for the jewelry store '{$shopName}' in {$salesSummary['location']}:

" . json_encode($salesSummary, JSON_PRETTY_PRINT) . "

Based on this historical data:
1. Predict product demand for the next 30 days.
2. Provide restock alerts and inventory guidance.
3. Identify cross-selling or package opportunities.
4. Suggest strategic pricing and promotion ideas to boost sales.

Your response MUST be a valid JSON object matching this schema exactly. DO NOT wrap the output in markdown block code tags (like ```json), do not include any explanatory text outside the JSON. Return ONLY the raw JSON.

Schema:
{
  \"summary\": \"Overall store performance summary and key growth opportunity (2-3 sentences)\",
  \"predicted_sales\": [
    {\"product_name\": \"Product Name\", \"predicted_qty_next_30_days\": 12.5, \"confidence\": \"High/Medium/Low\", \"reason\": \"Detailed reason for prediction based on data trends\"}
  ],
  \"restock_alerts\": [
    {\"product_name\": \"Product Name\", \"urgency\": \"High/Medium/Low\", \"suggested_qty\": 8, \"reason\": \"Detailed explanation of why restocking is urgent based on velocity\"}
  ],
  \"cross_selling\": [
    {\"combination\": \"Product A + Product B\", \"conversion_tip\": \"Actionable recommendation for staff on how to cross-sell these products\"}
  ],
  \"pricing_strategies\": [
    {\"product_name\": \"Product Name\", \"strategy\": \"Strategy Name (e.g. Value Bundle / Dynamic Hike / Premium Experience)\", \"actionable_tip\": \"Detailed actionable tip for pricing adjustments\"}
  ]
}";

    $postData = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $promptText]
                ]
            ]
        ],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'temperature' => 0.2 // Lower temp for more deterministic business forecasting
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    // Bypass SSL verification if local certificate issues
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $apiResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Gemini API returned HTTP Code {$httpCode}. Error: " . ($curlError ?: $apiResponse));
    }

    $apiData = json_decode($apiResponse, true);
    if (!isset($apiData['candidates'][0]['content']['parts'][0]['text'])) {
        throw new Exception("Invalid response structure from Gemini API.");
    }

    $rawText = trim($apiData['candidates'][0]['content']['parts'][0]['text']);
    
    // Clean potential markdown tags if Gemini ignored generationConfig (failsafe)
    if (strpos($rawText, '```') === 0) {
        $rawText = preg_replace('/^```(?:json)?\s*/i', '', $rawText);
        $rawText = preg_replace('/\s*```$/i', '', $rawText);
    }
    
    $insightsJson = json_decode(trim($rawText), true);
    if ($insightsJson === null) {
        throw new Exception("Gemini AI returned invalid JSON syntax: " . substr($rawText, 0, 100) . "...");
    }

    echo json_encode([
        'success' => true,
        'insights' => $insightsJson,
        'is_demo' => false
    ]);

} catch (Exception $e) {
    echo json_encode([
        'error' => 'API_EXECUTION_FAILED',
        'message' => $e->getMessage()
    ]);
}
