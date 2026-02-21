<?php

/**
 * 統計儀表板頁面
 * 
 * 顯示 API 使用量、對話次數、熱門話題等統計資料
 * 
 * @package MP_Ukagaka
 * @subpackage Admin
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
    exit();
}

// 獲取儀表板資料
$days = isset($_GET['stats_days']) ? intval($_GET['stats_days']) : 7;
$days = in_array($days, [7, 30]) ? $days : 7;

$dashboard_data = mpu_get_dashboard_data($days);
$summary = $dashboard_data['summary'];
$trend = $dashboard_data['trend'];
$top_topics = $dashboard_data['top_topics'];
$provider_distribution = $dashboard_data['provider_distribution'];
$conversation_distribution = $dashboard_data['conversation_distribution'];

// 準備圖表資料（使用 wp_json_encode 以確保 XSS 安全）
$chart_data = [
    'trend' => [
        'labels' => array_column($trend, 'date_display'),
        'api_calls' => array_column($trend, 'api_calls'),
        'cached' => array_column($trend, 'cached'),
        'conversations' => array_column($trend, 'total_conversations')
    ],
    'provider' => [
        'labels' => array_keys($provider_distribution),
        'values' => array_values($provider_distribution)
    ],
    'conversation' => [
        'labels' => [
            __('頁面感知', 'mp-ukagaka'),
            __('互動對話', 'mp-ukagaka'),
            __('首次問候', 'mp-ukagaka'),
            __('觸摸反應', 'mp-ukagaka'),
            __('自言自語', 'mp-ukagaka'),
            __('裝飾品反應', 'mp-ukagaka')
        ],
        'values' => array_values($conversation_distribution)
    ]
];
?>

<div class="mpu-stats-dashboard">
    <div class="mpu-stats-header">
        <h3><?php _e('📊 統計儀表板', 'mp-ukagaka'); ?></h3>
        <div class="mpu-time-filter">
            <a href="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=8&stats_days=7'); ?>" 
               class="<?php echo $days === 7 ? 'active' : ''; ?>">
                <?php _e('7 天', 'mp-ukagaka'); ?>
            </a>
            <a href="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=8&stats_days=30'); ?>" 
               class="<?php echo $days === 30 ? 'active' : ''; ?>">
                <?php _e('30 天', 'mp-ukagaka'); ?>
            </a>
        </div>
    </div>

    <!-- 摘要卡片 -->
    <div class="mpu-stats-summary">
        <div class="mpu-stat-card">
            <div class="stat-icon">🚀</div>
            <div class="stat-value"><?php echo number_format($summary['today_api_calls']); ?></div>
            <div class="stat-label"><?php _e('今日 API 調用', 'mp-ukagaka'); ?></div>
            <div class="stat-sub"><?php printf(__('錯誤: %d', 'mp-ukagaka'), $summary['today_errors']); ?></div>
        </div>
        <div class="mpu-stat-card">
            <div class="stat-icon">💬</div>
            <div class="stat-value"><?php echo number_format($summary['week_conversations']); ?></div>
            <div class="stat-label"><?php printf(__('本週對話 (%d天)', 'mp-ukagaka'), $days); ?></div>
            <div class="stat-sub"><?php printf(__('今日: %d', 'mp-ukagaka'), $summary['today_conversations']); ?></div>
        </div>
        <div class="mpu-stat-card">
            <div class="stat-icon">⚡</div>
            <div class="stat-value"><?php echo $summary['cache_hit_rate']; ?>%</div>
            <div class="stat-label"><?php _e('快取命中率', 'mp-ukagaka'); ?></div>
            <div class="stat-sub"><?php printf(__('命中: %d', 'mp-ukagaka'), $summary['today_cached']); ?></div>
        </div>
        <div class="mpu-stat-card">
            <div class="stat-icon">⏱️</div>
            <div class="stat-value"><?php echo number_format($summary['avg_response_time']); ?></div>
            <div class="stat-label"><?php _e('平均回應時間 (ms)', 'mp-ukagaka'); ?></div>
            <div class="stat-sub"><?php _e('今日平均', 'mp-ukagaka'); ?></div>
        </div>
    </div>

    <!-- 圖表區塊：API 調用趨勢（獨立一行） -->
    <div class="mpu-charts-row mpu-charts-row-full">
        <div class="mpu-chart-card">
            <h4>📈 <?php printf(__('API 調用趨勢 (%d天)', 'mp-ukagaka'), $days); ?></h4>
            <div class="mpu-chart-container">
                <canvas id="mpuTrendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- 圖表區塊：對話類型 + 供應商 + 熱門話題（三欄） -->
    <div class="mpu-charts-row mpu-charts-row-three">
        <div class="mpu-chart-card">
            <h4>🥧 <?php _e('對話類型分佈', 'mp-ukagaka'); ?></h4>
            <div class="mpu-chart-container">
                <canvas id="mpuConversationChart"></canvas>
            </div>
        </div>
        <div class="mpu-chart-card">
            <h4>📊 <?php _e('供應商使用分佈', 'mp-ukagaka'); ?></h4>
            <div class="mpu-chart-container">
                <canvas id="mpuProviderChart"></canvas>
            </div>
        </div>
        
        <!-- 熱門話題 -->
        <div class="mpu-topics-card">
            <h4>🔥 <?php printf(__('熱門話題 (Top 10 / %d天)', 'mp-ukagaka'), $days); ?></h4>
            <?php if (!empty($top_topics)): ?>
                <ul class="mpu-topics-list">
                    <?php foreach ($top_topics as $index => $topic): ?>
                        <li>
                            <span class="mpu-topic-rank <?php echo $index < 3 ? 'top-3' : ''; ?>"><?php echo $index + 1; ?></span>
                            <span class="mpu-topic-name" title="<?php echo esc_attr($topic['topic']); ?>"><?php echo esc_html($topic['topic']); ?></span>
                            <span class="mpu-topic-count"><?php echo number_format($topic['count']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="mpu-no-data"><?php _e('尚無話題資料', 'mp-ukagaka'); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 控制區 -->
    <div class="mpu-stats-controls">
        <h4>⚙️ <?php _e('統計設定', 'mp-ukagaka'); ?></h4>
        <p><?php _e('統計資料會自動保留 30 天，超過的資料會自動清除。', 'mp-ukagaka'); ?></p>
        <form method="post" action="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=8'); ?>" style="display: inline;">
            <?php wp_nonce_field('mpu_clear_stats', 'mpu_clear_stats_nonce'); ?>
            <input type="hidden" name="mpu_action" value="clear_stats">
            <button type="submit" class="button button-danger" onclick="return confirm('<?php esc_attr_e('確定要清除所有統計資料嗎？此操作無法復原。', 'mp-ukagaka'); ?>');">
                <?php _e('🗑️ 清除所有統計資料', 'mp-ukagaka'); ?>
            </button>
        </form>
        <p style="margin-top: 12px; font-size: 12px; color: #8A7FA0;">
            <?php printf(__('資料更新時間: %s', 'mp-ukagaka'), $dashboard_data['generated_at']); ?>
        </p>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 圖表資料（由 PHP wp_json_encode 輸出，確保 XSS 安全）
    const chartData = <?php echo wp_json_encode($chart_data); ?>;
    
    // 顏色主題（Frieren 紫色系）
    const colors = {
        primary: 'rgba(123, 104, 174, 1)',
        primaryLight: 'rgba(123, 104, 174, 0.2)',
        secondary: 'rgba(106, 159, 91, 1)',
        secondaryLight: 'rgba(106, 159, 91, 0.2)',
        accent: 'rgba(196, 182, 222, 1)',
        danger: 'rgba(201, 79, 79, 1)',
        chart: [
            'rgba(123, 104, 174, 0.8)',
            'rgba(106, 159, 91, 0.8)',
            'rgba(196, 182, 222, 0.8)',
            'rgba(201, 79, 79, 0.8)',
            'rgba(255, 183, 77, 0.8)',
            'rgba(149, 117, 205, 0.8)'
        ]
    };

    // 趨勢圖
    const trendCtx = document.getElementById('mpuTrendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: chartData.trend.labels,
                datasets: [
                    {
                        label: '<?php esc_attr_e('API 調用', 'mp-ukagaka'); ?>',
                        data: chartData.trend.api_calls,
                        borderColor: colors.primary,
                        backgroundColor: colors.primaryLight,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: '<?php esc_attr_e('快取命中', 'mp-ukagaka'); ?>',
                        data: chartData.trend.cached,
                        borderColor: colors.secondary,
                        backgroundColor: colors.secondaryLight,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: '<?php esc_attr_e('對話次數', 'mp-ukagaka'); ?>',
                        data: chartData.trend.conversations,
                        borderColor: colors.accent,
                        backgroundColor: 'rgba(168, 216, 234, 0.3)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }

    // 對話類型圓餅圖
    const conversationCtx = document.getElementById('mpuConversationChart');
    if (conversationCtx) {
        const conversationData = chartData.conversation.values;
        const hasData = conversationData.some(v => v > 0);
        
        // 6 種對話類型的專屬顏色（Frieren 紫色系）
        const conversationColors = [
            'rgba(123, 104, 174, 0.85)',  // 頁面感知 - 長袍紫
            'rgba(106, 159, 91, 0.85)',   // 互動對話 - 森林綠
            'rgba(255, 183, 77, 0.85)',   // 首次問候 - 橙黃
            'rgba(201, 79, 79, 0.85)',    // 觸摸反應 - 柔紅
            'rgba(196, 182, 222, 0.85)',  // 自言自語 - 柔薰衣草
            'rgba(94, 77, 139, 0.85)'     // 裝飾品反應 - 深紫
        ];
        
        new Chart(conversationCtx, {
            type: 'doughnut',
            data: {
                labels: chartData.conversation.labels,
                datasets: [{
                    data: hasData ? conversationData : [1],
                    backgroundColor: hasData ? conversationColors : ['rgba(200, 200, 200, 0.3)'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 10
                        }
                    }
                }
            }
        });
    }

    // 供應商分佈圓餅圖
    const providerCtx = document.getElementById('mpuProviderChart');
    if (providerCtx) {
        const providerLabels = chartData.provider.labels;
        const providerData = chartData.provider.values;
        const hasProviderData = providerData.some(v => v > 0);
        
        // 供應商專屬顏色
        const providerColors = [
            'rgba(66, 133, 244, 0.85)',   // Gemini - Google 藍
            'rgba(16, 163, 127, 0.85)',   // OpenAI - 綠色
            'rgba(204, 119, 68, 0.85)',   // Claude - 橘色
            'rgba(108, 117, 125, 0.85)'   // Ollama - 灰色
        ];
        
        new Chart(providerCtx, {
            type: 'doughnut',
            data: {
                labels: hasProviderData ? providerLabels : ['<?php esc_attr_e('無資料', 'mp-ukagaka'); ?>'],
                datasets: [{
                    data: hasProviderData ? providerData : [1],
                    backgroundColor: hasProviderData ? providerColors : ['rgba(200, 200, 200, 0.3)'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 10
                        }
                    }
                }
            }
        });
    }
});
</script>
