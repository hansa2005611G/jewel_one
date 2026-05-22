<?php
/**
 * AI Product Selling Predictions
 * Admin panel showing sales projections, restock recommendations, and Gemini-powered insights.
 */
require_once 'includes/auth.php';
requireAdmin(); // Admin only page
$db = getDB();
$currency = getSetting('currency_symbol', 'Rs.');
$apiKey = getSetting('gemini_api_key', '');
$csrf = generateCSRF();

// --- 1. LOCAL OFFLINE ANALYTICS ENGINE ---
// Get distinct products sold in the last 60 days
$prodListQuery = $db->query("SELECT DISTINCT bi.product_name 
    FROM bill_items bi 
    JOIN bills b ON bi.bill_id = b.id 
    WHERE b.status = 'completed' AND b.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)");
$activeProducts = $prodListQuery->fetchAll(PDO::FETCH_COLUMN);

$groupedSales = [];
foreach ($activeProducts as $pName) {
    $groupedSales[$pName] = array_fill(1, 8, 0.0);
}

// Get weekly sales totals for the last 56 days (8 weeks)
$weeklySalesQuery = $db->query("SELECT bi.product_name, 
    FLOOR(DATEDIFF(NOW(), b.created_at) / 7) as weeks_ago, 
    SUM(bi.quantity) as total_qty
    FROM bill_items bi
    JOIN bills b ON bi.bill_id = b.id
    WHERE b.status = 'completed' AND b.created_at >= DATE_SUB(NOW(), INTERVAL 56 DAY)
    GROUP BY bi.product_name, weeks_ago");
$weeklyRaw = $weeklySalesQuery->fetchAll();

foreach ($weeklyRaw as $row) {
    $pName = $row['product_name'];
    $weeksAgo = (int)$row['weeks_ago'];
    if ($weeksAgo >= 0 && $weeksAgo <= 7) {
        $xVal = 8 - $weeksAgo; // Map 0 weeks ago (current) to 8, 7 weeks ago to 1
        if (isset($groupedSales[$pName])) {
            $groupedSales[$pName][$xVal] = (float)$row['total_qty'];
        }
    }
}

$predictions = [];
foreach ($groupedSales as $pName => $history) {
    $n = 8;
    $sumX = 36;    // Sum(1..8)
    $sumX2 = 204;  // Sum(1^2..8^2)
    $sumY = array_sum($history);
    $sumXY = 0;
    foreach ($history as $x => $y) {
        $sumXY += $x * $y;
    }
    
    $denominator = ($n * $sumX2) - ($sumX * $sumX);
    $slope = 0;
    if ($denominator != 0) {
        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
    }
    
    $intercept = ($sumY - ($slope * $sumX)) / $n;
    
    // Classify trends
    $trend = 'stable';
    if ($slope > 0.15) {
        $trend = 'up';
    } elseif ($slope < -0.15) {
        $trend = 'down';
    }
    
    // Project next 4 weeks (weeks 9, 10, 11, 12)
    $projectedNext4Weeks = 0;
    $projectedCurve = [];
    for ($futureX = 9; $futureX <= 12; $futureX++) {
        $futureY = ($slope * $futureX) + $intercept;
        $val = max(0.0, $futureY);
        $projectedCurve[] = round($val, 2);
        $projectedNext4Weeks += $val;
    }
    
    $recent30Qty = 0;
    for ($xVal = 5; $xVal <= 8; $xVal++) {
        $recent30Qty += $history[$xVal];
    }
    
    // Dynamic restock safety coefficient
    $safetyFactor = 1.0;
    if ($trend === 'up') $safetyFactor = 1.3;
    elseif ($trend === 'down') $safetyFactor = 0.7;
    $suggestedRestock = ceil($projectedNext4Weeks * $safetyFactor);

    // Reorder urgency assessment
    $urgency = 'Low';
    if ($trend === 'up' && $recent30Qty > 4) {
        $urgency = 'High';
    } elseif ($recent30Qty > 1) {
        $urgency = 'Medium';
    }

    $predictions[] = [
        'product_name' => $pName,
        'history' => array_values($history),
        'slope' => $slope,
        'trend' => $trend,
        'recent_30_qty' => $recent30Qty,
        'projected_30_qty' => $projectedNext4Weeks,
        'projected_curve' => $projectedCurve,
        'suggested_restock' => $suggestedRestock,
        'urgency' => $urgency
    ];
}

// Sort predictions so active ones display first
usort($predictions, fn($a, $b) => $b['recent_30_qty'] <=> $a['recent_30_qty']);

// Day of week Seasonality Index over last 90 days
$seasonQuery = $db->query("SELECT DAYOFWEEK(created_at) as dow, SUM(grand_total) as total_revenue
    FROM bills WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
    GROUP BY dow");
$seasonRaw = $seasonQuery->fetchAll();

$dowRevenue = array_fill(1, 7, 0.0);
foreach ($seasonRaw as $row) {
    $dowRevenue[(int)$row['dow']] = (float)$row['total_revenue'];
}

$totalDowRev = array_sum($dowRevenue);
$avgDowRev = $totalDowRev / 7;
$seasonalityIndex = [];
$dowNames = [
    1 => 'Sunday', 
    2 => 'Monday', 
    3 => 'Tuesday', 
    4 => 'Wednesday', 
    5 => 'Thursday', 
    6 => 'Friday', 
    7 => 'Saturday'
];

foreach ($dowNames as $num => $name) {
    $index = $avgDowRev > 0 ? ($dowRevenue[$num] / $avgDowRev) : 1.0;
    $seasonalityIndex[] = [
        'day' => $name,
        'revenue' => $dowRevenue[$num],
        'index' => $index
    ];
}

include 'includes/header.php';
?>

<meta name="csrf" content="<?= h($csrf) ?>">

<!-- Add extra CSS for glassmorphic elements and loading shimmers -->
<style>
.prediction-tab-container {
    display: flex;
    gap: 10px;
    margin-bottom: 24px;
    border-bottom: 1px solid rgba(201,168,76,0.12);
    padding-bottom: 10px;
}
.prediction-tab {
    padding: 10px 20px;
    border-radius: var(--radius-sm);
    color: var(--gray-3);
    font-weight: 600;
    cursor: pointer;
    background: none;
    border: 1px solid transparent;
    transition: all var(--transition);
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 0.5px;
}
.prediction-tab:hover {
    color: var(--gold);
    background: rgba(201,168,76,0.05);
}
.prediction-tab.active {
    color: var(--black);
    background: linear-gradient(135deg, var(--gold) 0%, var(--gold-light) 100%);
    box-shadow: var(--shadow-gold);
}
.prediction-content {
    display: none;
}
.prediction-content.active {
    display: block;
    animation: fadeInRow 0.3s ease;
}

/* Glassmorphism Insights Cards */
.ai-insight-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
}
@media(min-width: 900px) {
    .ai-insight-grid {
        grid-template-columns: 1.2fr 1fr;
    }
}
.ai-summary-box {
    background: linear-gradient(135deg, rgba(201,168,76,0.08) 0%, rgba(10,10,10,0.8) 100%);
    border: 1px solid rgba(201,168,76,0.25);
    border-radius: var(--radius);
    padding: 24px;
    margin-bottom: 20px;
    position: relative;
}
.ai-summary-box::before {
    content: '✦';
    position: absolute;
    top: 15px; right: 20px;
    color: var(--gold);
    font-size: 24px;
    opacity: 0.5;
    animation: floatSparkle 3s infinite ease-in-out;
}
@keyframes floatSparkle {
    0%, 100% { transform: translateY(0) scale(1); opacity: 0.4; }
    50% { transform: translateY(-5px) scale(1.2); opacity: 0.8; }
}
.insight-card {
    background: var(--dark-2);
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: var(--radius-sm);
    padding: 16px;
    margin-bottom: 12px;
    transition: all var(--transition);
}
.insight-card:hover {
    border-color: rgba(201,168,76,0.15);
    background: rgba(201,168,76,0.02);
}
.insight-title {
    font-weight: 700;
    color: var(--gold-light);
    font-size: 14px;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.insight-body {
    font-size: 13px;
    color: var(--gray-2);
    line-height: 1.5;
}
.status-badge-pulse {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 700;
    font-size: 10px;
    padding: 4px 10px;
    border-radius: 20px;
    text-transform: uppercase;
}
.status-badge-pulse.online {
    background: rgba(76,175,130,0.12);
    color: var(--success);
    border: 1px solid rgba(76,175,130,0.3);
}
.status-badge-pulse.offline {
    background: rgba(240,168,48,0.12);
    color: var(--warning);
    border: 1px solid rgba(240,168,48,0.3);
}

/* Shimmer Loading Animation */
.shimmer-placeholder {
    height: 100px;
    background: linear-gradient(90deg, var(--dark-2) 25%, var(--dark-3) 50%, var(--dark-2) 75%);
    background-size: 200% 100%;
    animation: loadingShimmer 1.5s infinite;
    border-radius: var(--radius-sm);
    margin-bottom: 12px;
    border: 1px solid rgba(255,255,255,0.03);
}
@keyframes loadingShimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.api-instructions-card {
    background: rgba(201,168,76,0.02);
    border: 1px dashed rgba(201,168,76,0.3);
    border-radius: var(--radius);
    padding: 20px;
    text-align: center;
    margin-top: 20px;
}
</style>

<div class="page-header fade-in">
  <div>
    <h1><i class="fas fa-magic" style="color:var(--gold);margin-right:10px"></i>AI Selling <span>Predictions</span></h1>
    <div class="page-breadcrumb">Smart Demand Forecasting · Inventory Alerts · Cloud AI Consultations</div>
  </div>
  <div style="display:flex;gap:10px;align-items:center">
    <span class="status-badge-pulse <?= !empty($apiKey) ? 'online' : 'offline' ?>">
        <i class="fas <?= !empty($apiKey) ? 'fa-signal' : 'fa-exclamation-triangle' ?>"></i> 
        <?= !empty($apiKey) ? 'Gemini AI Ready' : 'Gemini Offline Mode' ?>
    </span>
    <button class="btn btn-outline" onclick="triggerMockDataGeneration()">
        <i class="fas fa-database"></i> Generate Demo Sales Data
    </button>
  </div>
</div>

<!-- Tabs to switch between Local and Cloud models -->
<div class="prediction-tab-container fade-in">
    <button class="prediction-tab active" onclick="switchTab('local')"><i class="fas fa-chart-line"></i> Local Statistical Forecast</button>
    <button class="prediction-tab" onclick="switchTab('cloud')"><i class="fas fa-brain"></i> Cloud AI Consultant</button>
</div>

<!-- TAB 1: LOCAL STATISTICAL MODEL -->
<div id="tab_local" class="prediction-content active">
    <!-- Top Level Dashboard summary -->
    <div class="grid-3 fade-in" style="margin-bottom: 24px">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-value"><?= count($predictions) ?></div>
            <div class="stat-label">Active Forecasts</div>
            <div class="stat-change up">Products Tracked</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-arrow-trend-up"></i></div>
            <div class="stat-value">
                <?= count(array_filter($predictions, fn($p) => $p['trend'] === 'up')) ?>
            </div>
            <div class="stat-label">High Velocity Items</div>
            <div class="stat-change up">Demand Rising</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-bell"></i></div>
            <div class="stat-value">
                <?= count(array_filter($predictions, fn($p) => $p['urgency'] === 'High')) ?>
            </div>
            <div class="stat-label">Urgent Restocks</div>
            <div class="stat-change down"><i class="fas fa-exclamation"></i> Low Reserves</div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid-2 fade-in" style="margin-bottom:24px">
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-chart-area"></i> 8-Week Historical Demand Trends</span>
            </div>
            <div class="card-body">
                <div class="chart-wrap" style="height: 250px"><canvas id="historyChart"></canvas></div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-calendar-day"></i> Day-of-Week Seasonality Heatmap</span>
            </div>
            <div class="card-body">
                <div class="chart-wrap" style="height: 250px"><canvas id="seasonalityChart"></canvas></div>
            </div>
        </div>
    </div>

    <!-- Predictions Listing Table -->
    <div class="card fade-in">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-list"></i> Detailed Product Sales Predictions (Next 30 Days)</span>
        </div>
        <div class="card-body" style="padding:0">
            <?php if (empty($predictions)): ?>
            <div class="empty-state" style="padding: 40px">
                <i class="fas fa-exclamation-triangle" style="font-size:36px;color:var(--gold);margin-bottom:12px"></i>
                <p>No transactions found in the last 60 days to formulate calculations.</p>
                <p style="font-size:12px;color:var(--gray-4)">Click "Generate Demo Sales Data" above to instantly load mock transactions for evaluation.</p>
            </div>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Trend Status</th>
                            <th>Sales Velocity (Slope)</th>
                            <th>Actual Sales (Last 30d)</th>
                            <th>Projected Demand (Next 30d)</th>
                            <th>Recommended Purchase Qty</th>
                            <th>Action Urgency</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($predictions as $p): ?>
                        <tr>
                            <td style="font-weight: 600; color:var(--white)"><?= h($p['product_name']) ?></td>
                            <td>
                                <?php if ($p['trend'] === 'up'): ?>
                                <span class="badge badge-success"><i class="fas fa-caret-up"></i> Upward Trend</span>
                                <?php elseif ($p['trend'] === 'down'): ?>
                                <span class="badge badge-danger"><i class="fas fa-caret-down"></i> Declining</span>
                                <?php else: ?>
                                <span class="badge badge-gold"><i class="fas fa-minus"></i> Stable</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-family: monospace; font-size:12px">
                                <?= ($p['slope'] >= 0 ? '+' : '') . number_format($p['slope'], 3) ?> units/wk
                            </td>
                            <td style="font-weight: 600; text-align: center"><?= number_format($p['recent_30_qty'], 1) ?></td>
                            <td style="font-weight: 700; color:var(--gold); text-align: center">
                                <i class="fas fa-bolt" style="font-size:10px; margin-right:2px"></i> 
                                <?= number_format($p['projected_30_qty'], 1) ?>
                            </td>
                            <td style="font-weight: 700; text-align: center"><?= $p['suggested_restock'] ?></td>
                            <td>
                                <?php if ($p['urgency'] === 'High'): ?>
                                <span class="badge badge-danger" style="animation: pulseBorder 2s infinite">High Alert</span>
                                <?php elseif ($p['urgency'] === 'Medium'): ?>
                                <span class="badge badge-info">Medium</span>
                                <?php else: ?>
                                <span class="badge badge-gold" style="opacity: 0.7">Low</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- TAB 2: CLOUD GEMINI AI MODEL -->
<div id="tab_cloud" class="prediction-content">
    <div class="ai-summary-box fade-in">
        <h2 style="color:var(--gold);font-family:var(--font-display);font-size:22px;margin-bottom:8px">Google Gemini Strategy Consultant</h2>
        <p style="color:var(--gray-3);line-height:1.5;font-size:13.5px;max-width:800px">
            By analyzing product velocities, seasonal transaction clusters, and discount impact over the last 90 days, Gemini generates structured recommendations for pricing optimizations, purchasing strategies, and inventory restock requirements.
        </p>
        
        <div style="display:flex;gap:14px;margin-top:20px;align-items:center;flex-wrap:wrap">
            <button class="btn btn-gold" id="btnRunCloudAi" onclick="fetchCloudInsights(false)">
                <i class="fas fa-magic"></i> Run AI Strategy Audit
            </button>
            <?php if (empty($apiKey)): ?>
            <button class="btn btn-outline" id="btnRunDemoCloud" onclick="fetchCloudInsights(true)">
                <i class="fas fa-vial"></i> Test in Demo Mode
            </button>
            <?php endif; ?>
            <div style="display:flex;align-items:center;gap:6px">
                <span class="form-label" style="margin:0">API Link:</span>
                <a href="modules/settings.php" style="color:var(--gold);font-size:11px;text-decoration:underline"><i class="fas fa-cog"></i> Configure API Key</a>
            </div>
        </div>
    </div>

    <!-- Insights Display Area -->
    <div id="insightsContainer" style="display:none">
        <div class="ai-insight-grid">
            <!-- Left: Executive Summary & Predictions -->
            <div>
                <div class="card" style="margin-bottom: 20px">
                    <div class="card-header">
                        <span class="card-title"><i class="fas fa-quote-left"></i> Executive Strategy Summary</span>
                    </div>
                    <div class="card-body">
                        <p id="aiSummaryText" style="line-height:1.6;font-size:14px;color:var(--white)"></p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-title"><i class="fas fa-lightbulb"></i> Predictive Stocking & Promotion Strategies</span>
                    </div>
                    <div class="card-body" id="aiStrategiesContainer">
                        <!-- Dynamic strategy items load here -->
                    </div>
                </div>
            </div>

            <!-- Right: Stock Alerts & Cross-Selling -->
            <div>
                <div class="card" style="margin-bottom: 20px">
                    <div class="card-header">
                        <span class="card-title"><i class="fas fa-exclamation-circle"></i> Projected Restock Requirements</span>
                    </div>
                    <div class="card-body" id="aiRestocksContainer">
                        <!-- Dynamic restock alerts load here -->
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-title"><i class="fas fa-tags"></i> Data-Driven Cross-Selling Combos</span>
                    </div>
                    <div class="card-body" id="aiCrossSellingContainer">
                        <!-- Dynamic cross-selling bundles load here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Shimmer Loading State -->
    <div id="shimmerLoader" style="display:none">
        <div class="ai-insight-grid">
            <div>
                <div class="shimmer-placeholder" style="height: 120px"></div>
                <div class="shimmer-placeholder" style="height: 250px"></div>
            </div>
            <div>
                <div class="shimmer-placeholder" style="height: 180px"></div>
                <div class="shimmer-placeholder" style="height: 180px"></div>
            </div>
        </div>
    </div>

    <!-- Call to action if database is empty -->
    <div id="emptyApiDisclaimer" class="api-instructions-card fade-in" style="display: <?= empty($apiKey) ? 'block' : 'none' ?>">
        <i class="fas fa-info-circle" style="color:var(--gold);font-size:24px;margin-bottom:10px"></i>
        <h3 style="color:var(--white);font-size:16px;margin-bottom:6px">Gemini API Key is not configured</h3>
        <p style="color:var(--gray-3);font-size:12.5px;max-width:600px;margin:0 auto 14px">
            Configure your free Gemini API Key in the system settings to enable cloud-powered strategic forecasting that models local trends, buying cycles, and promotion analytics.
        </p>
        <a href="modules/settings.php" class="btn btn-gold btn-sm"><i class="fas fa-key"></i> Set Up API Key</a>
    </div>
</div>

<!-- CHARTS DATA INJECTION -->
<script>
// Tab Switching
function switchTab(tab) {
    document.querySelectorAll('.prediction-tab').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.prediction-content').forEach(c => c.classList.remove('active'));
    
    if (tab === 'local') {
        event.currentTarget.classList.add('active');
        document.getElementById('tab_local').classList.add('active');
    } else {
        event.currentTarget.classList.add('active');
        document.getElementById('tab_cloud').classList.add('active');
    }
}

// Generate Mock Data AJAX call
async function triggerMockDataGeneration() {
    showConfirm('Populate Sales Data', 
        'This will truncate all existing billing records and replace them with a simulated 6-month history for predictions testing. Proceed?', 
        async () => {
            showLoading('Simulating Transactions...');
            const csrf = document.querySelector('meta[name=csrf]').content;
            
            try {
                const res = await fetch('ajax/generate_mock_data.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `csrf=${csrf}`
                });
                const data = await res.json();
                hideLoading();
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast(data.error || 'Failed to populate data.', 'error');
                }
            } catch(e) {
                hideLoading();
                showToast('Network error generating mock history.', 'error');
            }
        }
    );
}

// Fetch Cloud Insights from Gemini AJAX call
async function fetchCloudInsights(forceDemo = false) {
    const btnReal = document.getElementById('btnRunCloudAi');
    const btnDemo = document.getElementById('btnRunDemoCloud');
    const container = document.getElementById('insightsContainer');
    const loader = document.getElementById('shimmerLoader');
    const disclaimer = document.getElementById('emptyApiDisclaimer');
    
    if (btnReal) btnReal.disabled = true;
    if (btnDemo) btnDemo.disabled = true;
    container.style.display = 'none';
    loader.style.display = 'block';
    if (disclaimer) disclaimer.style.display = 'none';
    
    const csrf = document.querySelector('meta[name=csrf]').content;
    const bodyStr = `csrf=${csrf}${forceDemo ? '&demo=1' : ''}`;
    
    try {
        const res = await fetch('ajax/fetch_gemini_insights.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: bodyStr
        });
        const data = await res.json();
        
        loader.style.display = 'none';
        if (btnReal) btnReal.disabled = false;
        if (btnDemo) btnDemo.disabled = false;
        
        if (data.success) {
            const insights = data.insights;
            
            // Populate Executive Summary
            document.getElementById('aiSummaryText').textContent = insights.summary;
            
            // Populate Restock Alerts
            const restocksWrap = document.getElementById('aiRestocksContainer');
            restocksWrap.innerHTML = '';
            if (insights.restock_alerts && insights.restock_alerts.length > 0) {
                insights.restock_alerts.forEach(item => {
                    const badgeType = item.urgency === 'High' ? 'badge-danger' : 'badge-gold';
                    restocksWrap.innerHTML += `
                        <div class="insight-card">
                            <div class="insight-title">
                                <span class="badge ${badgeType}">${item.urgency} Urgency</span>
                                <span>${item.product_name}</span>
                            </div>
                            <div class="insight-body">
                                <strong>Reorder qty:</strong> ${item.suggested_qty} units<br>
                                <p style="margin-top:4px;color:var(--gray-3)">${item.reason}</p>
                            </div>
                        </div>
                    `;
                });
            } else {
                restocksWrap.innerHTML = '<p class="insight-body">No stocking issues detected by AI.</p>';
            }
            
            // Populate Cross-Selling
            const comboWrap = document.getElementById('aiCrossSellingContainer');
            comboWrap.innerHTML = '';
            if (insights.cross_selling && insights.cross_selling.length > 0) {
                insights.cross_selling.forEach(item => {
                    comboWrap.innerHTML += `
                        <div class="insight-card">
                            <div class="insight-title"><i class="fas fa-gift"></i> ${item.combination}</div>
                            <div class="insight-body">${item.conversion_tip}</div>
                        </div>
                    `;
                });
            } else {
                comboWrap.innerHTML = '<p class="insight-body">No promotion pairs identified.</p>';
            }
            
            // Populate Pricing Strategies
            const pricingWrap = document.getElementById('aiStrategiesContainer');
            pricingWrap.innerHTML = '';
            if (insights.pricing_strategies && insights.pricing_strategies.length > 0) {
                insights.pricing_strategies.forEach(item => {
                    pricingWrap.innerHTML += `
                        <div class="insight-card">
                            <div class="insight-title" style="color:var(--success)"><i class="fas fa-coins"></i> ${item.product_name} - ${item.strategy}</div>
                            <div class="insight-body">${item.actionable_tip}</div>
                        </div>
                    `;
                });
            } else {
                pricingWrap.innerHTML = '<p class="insight-body">No strategy adjustments recommended.</p>';
            }
            
            container.style.display = 'block';
            showToast('AI analysis completed successfully!', 'success');
            
        } else {
            showToast(data.message || 'API calling failed.', 'error');
            if (disclaimer && data.error === 'API_KEY_MISSING') {
                disclaimer.style.display = 'block';
            }
        }
    } catch(e) {
        loader.style.display = 'none';
        if (btnReal) btnReal.disabled = false;
        if (btnDemo) btnDemo.disabled = false;
        showToast('Error sending requests to AI backend.', 'error');
    }
}

// Chart.js render scripts
document.addEventListener('DOMContentLoaded', () => {
    // 1. History Line Chart
    const histCtx = document.getElementById('historyChart')?.getContext('2d');
    
    // PHP variables mapped to JSON
    const productsData = <?= json_encode($predictions) ?>;
    
    if (histCtx && productsData.length > 0) {
        const labels = ['Wk 1 (Old)', 'Wk 2', 'Wk 3', 'Wk 4', 'Wk 5', 'Wk 6', 'Wk 7', 'Wk 8 (New)'];
        
        // Define clean contrasting colors for top products
        const colors = [
            { border: '#C9A84C', bg: 'rgba(201,168,76,0.1)' },
            { border: '#5B9BD5', bg: 'rgba(91,155,213,0.1)' },
            { border: '#4CAF82', bg: 'rgba(76,175,130,0.1)' },
            { border: '#E05260', bg: 'rgba(224,82,96,0.1)' },
            { border: '#9b59b6', bg: 'rgba(155,89,182,0.1)' }
        ];

        // Limit to top 4 products to avoid graph clutter
        const datasets = productsData.slice(0, 4).map((p, idx) => {
            const col = colors[idx % colors.length];
            return {
                label: p.product_name,
                data: p.history,
                borderColor: col.border,
                backgroundColor: col.bg,
                borderWidth: 2,
                pointBackgroundColor: col.border,
                pointRadius: 4,
                tension: 0.3,
                fill: false
            };
        });

        new Chart(histCtx, {
            type: 'line',
            data: { labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: '#ccc', boxWidth: 12, font: { size: 10 } }
                    }
                },
                scales: {
                    x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#777' } },
                    y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#777', stepSize: 1 } }
                }
            }
        });
    }

    // 2. Seasonality Bar Chart
    const seasCtx = document.getElementById('seasonalityChart')?.getContext('2d');
    const seasData = <?= json_encode($seasonalityIndex) ?>;
    
    if (seasCtx && seasData.length > 0) {
        new Chart(seasCtx, {
            type: 'bar',
            data: {
                labels: seasData.map(d => d.day),
                datasets: [{
                    label: 'Demand Seasonality Multiplier',
                    data: seasData.map(d => parseFloat(d.index)),
                    backgroundColor: seasData.map(d => {
                        // Highlight days with index > 1.2 in solid gold
                        return d.index > 1.2 ? '#C9A84C' : 'rgba(201,168,76,0.3)';
                    }),
                    borderColor: '#C9A84C',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#777' } },
                    y: { 
                        grid: { color: 'rgba(255,255,255,0.04)' }, 
                        ticks: { color: '#777' },
                        suggestedMax: 1.5
                    }
                }
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
