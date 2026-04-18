<?php
/** @var string $username */
/** @var string|int $playerId */
/** @var int $coins */

$username = (string)($username ?? '');
$playerId = (string)($playerId ?? '—');
$coins = (int)($coins ?? 0);

$fallbackName = $username !== '' ? $username : 'Steve';
$skinTextureUrl = (string)($skinTextureUrl ?? ('https://minotar.net/skin/' . rawurlencode($fallbackName)));

// If the controller provided a Crafatar skin URL (commonly used as a fallback when MUA isn't bound),
// switch to Minotar raw skin so skinview3d can reliably render it.
if (is_string($skinTextureUrl) && strpos($skinTextureUrl, 'https://crafatar.com/skins/') === 0) {
    $skinTextureUrl = 'https://minotar.net/skin/' . rawurlencode($fallbackName);
}
?>

<style>
.player-profile-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1rem 0.75rem 2.5rem;
}
.player-profile-dashboard {
    display: grid;
    grid-template-columns: minmax(0, 320px) minmax(0, 1fr);
    gap: 1rem;
}
.player-profile-sidebar {
    position: sticky;
    top: 6.5rem;
    border: 1px solid rgba(148, 163, 184, 0.22);
    border-radius: 1.5rem;
    background: rgba(2, 6, 23, 0.72);
    backdrop-filter: blur(20px);
    box-shadow: 0 24px 64px -32px rgba(0, 0, 0, 0.75);
    padding: 1rem;
}
.player-profile-avatar-wrap {
    border-radius: 1rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
    background: rgba(15, 23, 42, 0.72);
    min-height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
#skin_viewer_container {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}
#mua_skin_viewer_3d,
#mua_skin_viewer_2d {
    width: 100%;
    max-width: 220px;
    height: auto;
    display: block;
}
#mua_skin_viewer_3d {
    display: none;
}
.player-skin-controls {
    margin-top: 0.85rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: center;
}
.player-skin-controls .btn {
    border-radius: 0.65rem;
    border: 1px solid rgba(148, 163, 184, 0.28);
    background: rgba(15, 23, 42, 0.72);
    color: #e2e8f0;
    padding: 0.35rem 0.7rem;
    font-size: 0.78rem;
}
.player-skin-controls .btn:hover {
    border-color: rgba(34, 211, 238, 0.45);
    background: rgba(14, 165, 233, 0.2);
}
.player-profile-username {
    margin: 0.85rem 0 0;
    font-size: 1.35rem;
    font-weight: 800;
    color: #f8fafc;
}
.player-profile-meta {
    margin-top: 0.95rem;
    border-top: 1px dashed rgba(148, 163, 184, 0.3);
    padding-top: 0.85rem;
    font-size: 0.85rem;
    color: #cbd5e1;
    line-height: 1.8;
}
.player-profile-meta strong { color: #f8fafc; }
.player-profile-content-stack {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.player-card {
    border: 1px solid rgba(148, 163, 184, 0.22);
    border-radius: 1.5rem;
    background: rgba(2, 6, 23, 0.72);
    backdrop-filter: blur(20px);
    box-shadow: 0 22px 60px -34px rgba(0, 0, 0, 0.8);
    padding: 1.15rem;
}
.player-card h2 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.05rem;
    color: #f8fafc;
}
.player-card h2 i { color: #67e8f9; }
.player-card p {
    margin-top: 0.45rem;
    color: #94a3b8;
    font-size: 0.9rem;
}
#player-stats-bar,
#player-radar {
    margin-top: 0.8rem;
    width: 100%;
    height: 320px;
    border-radius: 0.85rem;
    border: 1px solid rgba(148, 163, 184, 0.15);
    background: rgba(15, 23, 42, 0.5);
}
.player-timeline {
    margin-top: 0.9rem;
    border-left: 2px solid rgba(148, 163, 184, 0.25);
    padding-left: 0.75rem;
}
.player-timeline-item {
    position: relative;
    margin-bottom: 0.85rem;
    border-radius: 0.75rem;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(15, 23, 42, 0.58);
    padding: 0.75rem;
}
.player-timeline-dot {
    position: absolute;
    left: -1rem;
    top: 1rem;
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 9999px;
    background: #22d3ee;
    box-shadow: 0 0 0 4px rgba(34, 211, 238, 0.15);
}
.player-timeline-title { color: #e2e8f0; font-weight: 700; font-size: 0.92rem; }
.player-timeline-time { margin-top: 0.2rem; color: #94a3b8; font-size: 0.82rem; }
.player-timeline-sub { margin-top: 0.3rem; color: #cbd5e1; font-size: 0.85rem; }
@media (max-width: 900px) {
    .player-profile-dashboard { grid-template-columns: 1fr; }
    .player-profile-sidebar { position: static; top: auto; }
}
</style>

<div class="player-profile-page">
    <div class="player-profile-dashboard">
        <aside class="player-profile-sidebar">
            <div class="player-profile-avatar-wrap">
                <div id="skin_viewer_container">
                    <canvas id="mua_skin_viewer_3d" width="280" height="400" aria-label="<?= htmlspecialchars('3D 皮肤预览：' . $username, ENT_QUOTES, 'UTF-8') ?>"></canvas>
                    <img
                        id="mua_skin_viewer_2d"
                        src="<?= htmlspecialchars('https://minotar.net/armor/body/' . rawurlencode($fallbackName) . '/300.png', ENT_QUOTES, 'UTF-8') ?>"
                        alt="<?= htmlspecialchars($username . ' 的 2D 皮肤预览', ENT_QUOTES, 'UTF-8') ?>"
                        width="220"
                        height="293"
                        loading="eager"
                        decoding="async"
                    >
                </div>
            </div>
            <div class="player-skin-controls">
                <button type="button" class="btn btn-secondary" id="skin-anim-walk">行走动画</button>
                <button type="button" class="btn btn-secondary" id="skin-anim-run">跑步动画</button>
            </div>

            <h1 class="player-profile-username">
                <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>
            </h1>

            <div class="player-profile-meta">
                玩家 ID：<strong><?= htmlspecialchars($playerId, ENT_QUOTES, 'UTF-8') ?></strong>
                <br>
                网页硬币：<strong><?= htmlspecialchars((string)$coins, ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
        </aside>

        <div class="player-profile-content-stack">
            <section class="player-card">
                <h2>
                    <i class="mdi mdi-chart-bar"></i>
                    <span>综合数据统计（占位）</span>
                </h2>
                <p>
                    这里预留用于展示玩家综合数据汇总与可视化统计图表。
                </p>
                <div id="player-stats-bar"></div>
            </section>

            <section class="player-card">
                <h2>
                    <i class="mdi mdi-history"></i>
                    <span>近期活跃记录（占位）</span>
                </h2>
                <p>
                    预留：近期游戏活跃、网页签到、关键行为记录等。
                </p>
                <div class="player-timeline">
                    <?php
                    $timelineEvents = $timelineEvents ?? [];
                    ?>
                    <?php if (empty($timelineEvents)): ?>
                        <p style="color:#94a3b8;margin:0.75rem 0 0 0;font-size:0.9rem;opacity:0.85;">
                            暂无近期行为记录
                        </p>
                    <?php else: ?>
                        <?php foreach ($timelineEvents as $ev): ?>
                            <?php
                            $time = (string)($ev['time'] ?? '');
                            $title = (string)($ev['title'] ?? '行为记录');
                            $sub = $ev['sub'] ?? null;
                            ?>
                            <div class="player-timeline-item">
                                <div class="player-timeline-dot"></div>
                                <div class="player-timeline-content">
                                    <div class="player-timeline-title">
                                        <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <?php if ($time !== ''): ?>
                                        <div class="player-timeline-time">
                                            <?= htmlspecialchars($time, ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (is_string($sub) && $sub !== ''): ?>
                                        <div class="player-timeline-sub">
                                            <?= htmlspecialchars($sub, ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="player-card">
                <h2>
                    <i class="mdi mdi-radar"></i>
                    <span>PVP / PVE 偏好雷达图（占位）</span>
                </h2>
                <p>
                    预留雷达图：PVP 与 PVE 行为偏好参数。
                </p>
                <div id="player-radar"></div>
            </section>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/skinview3d@3.0.1/bundles/skinview3d.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
<script>
    const statsData = JSON.parse('<?= htmlspecialchars(json_encode($statsData ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>');
    const radarData = JSON.parse('<?= htmlspecialchars(json_encode($radarData ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>');
    const skinTextureUrl = <?= json_encode($skinTextureUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;
    const playerUsername = <?= json_encode($fallbackName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;

    (function initSkinView3d() {
        var canvas = document.getElementById('mua_skin_viewer_3d');
        var imgFallback = document.getElementById('mua_skin_viewer_2d');
        var btnWalk = document.getElementById('skin-anim-walk');
        var btnRun = document.getElementById('skin-anim-run');
        var fallback2dUrl = 'https://minotar.net/armor/body/' + encodeURIComponent(playerUsername || 'Steve') + '/300.png';

        if (!canvas || !imgFallback) {
            return;
        }

        var show2D = function () {
            canvas.style.display = 'none';
            if (!imgFallback.src) {
                imgFallback.src = fallback2dUrl;
            }
            imgFallback.style.display = 'block';
        };

        var show3D = function () {
            imgFallback.style.display = 'none';
            canvas.style.display = 'block';
        };

        // Progressive enhancement: default to 2D so the page remains usable with JS disabled or 3D load failure.
        show2D();

        if (typeof skinview3d === 'undefined') {
            return;
        }

        try {
            var SV = skinview3d;
            var viewer = new SV.SkinViewer({
                canvas: canvas,
                width: 280,
                height: 400
            });
            if (viewer.controls) {
                viewer.controls.enableRotate = true;
            }

            viewer.loadSkin(skinTextureUrl).then(function () {
                show3D();
                if (viewer.animations) {
                    var walk = new SV.WalkingAnimation();
                    walk.speed = 0.82;
                    viewer.animations.add(walk);
                }
            }).catch(function (error) {
                console.warn('skinview3d loadSkin failed, fallback to 2D', error);
                show2D();
            });

            if (btnWalk && viewer.animations) {
                btnWalk.addEventListener('click', function () {
                    try {
                        viewer.animations.clear();
                        var w = new SV.WalkingAnimation();
                        w.speed = 0.82;
                        viewer.animations.add(w);
                    } catch (e) {}
                });
            }
            if (btnRun && viewer.animations) {
                btnRun.addEventListener('click', function () {
                    try {
                        viewer.animations.clear();
                        var r = new SV.RunningAnimation();
                        r.speed = 1.05;
                        viewer.animations.add(r);
                    } catch (e) {}
                });
            }
        } catch (e) {
            console.warn('skinview3d init failed', e);
            show2D();
        }
    })();

    (function () {
        const barEl = document.getElementById('player-stats-bar');
        const radarEl = document.getElementById('player-radar');
        if (!barEl || !radarEl || typeof echarts === 'undefined') {
            return;
        }

        // 读取 CSS 变量，确保 ECharts 渲染时拿到可用的实际颜色值
        const rootStyles = getComputedStyle(document.documentElement);
        const primaryColor = rootStyles.getPropertyValue('--color-primary').trim() || '#5a8cff';
        const colorTextLight = rootStyles.getPropertyValue('--color-text-light').trim() || '#cbd5f5';

        const barChart = echarts.init(barEl);
        const radarChart = echarts.init(radarEl);

        const barCategories = [
            '活跃(小时)',
            '挖掘(Blocks)',
            '放置(Blocks)',
            '休闲(鱼获)',
            '战斗(击杀)',
            '生存(死亡)'
        ];
        const barValues = [
            Number(statsData.playTimeHours ?? 0),
            Number(statsData.mined ?? 0),
            Number(statsData.placed ?? 0),
            Number(statsData.fishCaught ?? 0),
            Number(statsData.kills ?? 0),
            Number(statsData.deaths ?? 0),
        ];

        barChart.setOption({
            backgroundColor: 'transparent',
            tooltip: { trigger: 'axis' },
            grid: { left: '3%', right: '4%', bottom: '10%', containLabel: true },
            xAxis: {
                type: 'category',
                data: barCategories,
                axisLabel: { interval: 0, rotate: 10 }
            },
            yAxis: { type: 'value' },
            series: [
                {
                    name: '数据',
                    type: 'bar',
                    data: barValues,
                    barWidth: '50%',
                    itemStyle: {
                        color: primaryColor
                    },
                }
            ]
        });

        const radarDims = [
            { name: '战斗(kills)', key: 'kills' },
            { name: '生存(deaths)', key: 'deaths' },
            { name: '建造(placed)', key: 'placed' },
            { name: '开采(mined)', key: 'mined' },
            { name: '休闲(fish)', key: 'fishCaught' },
        ];

        const rawRadarValues = radarDims.map(d => Number(radarData[d.key] ?? 0));
        const normalized = rawRadarValues.map(v => Math.log(v + 1));
        const maxVal = Math.max(...normalized, 1);

        radarChart.setOption({
            backgroundColor: 'transparent',
            tooltip: { trigger: 'item' },
            radar: {
                indicator: radarDims.map(d => ({ name: d.name, max: maxVal })),
                shape: 'circle',
                name: { textStyle: { color: colorTextLight } },
                splitNumber: 5,
                splitLine: { lineStyle: { color: 'rgba(255,255,255,0.08)' } },
                axisLine: { lineStyle: { color: 'rgba(255,255,255,0.12)' } },
                splitArea: { areaStyle: { color: 'rgba(90,140,255,0.05)' } }
            },
            series: [
                {
                    type: 'radar',
                    data: [
                        {
                            value: normalized,
                            name: '偏好'
                        }
                    ],
                    symbol: 'circle',
                    symbolSize: 4,
                    itemStyle: { color: 'color-mix(in srgb, var(--color-primary) 70%, var(--glass-bg))' },
                    lineStyle: { width: 2 }
                }
            ]
        });

        const resize = function () {
            barChart.resize();
            radarChart.resize();
        };
        window.addEventListener('resize', resize);
    })();
</script>
