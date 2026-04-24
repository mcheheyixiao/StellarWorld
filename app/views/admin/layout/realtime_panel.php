<div id="tab-realtime" class="ta-tab-content tab-hidden">
    <div class="ta-card ta-realtime-panel">
        <div class="ta-realtime-head">
            <div>
                <h1>实时服务器监控</h1>
                <p>通过 Node WebSocket 服务持续接收服务器状态、玩家与聊天数据。</p>
            </div>
            <div id="realtime-connection-banner" class="ta-realtime-connection ta-realtime-connection-pending">
                <span id="realtime-connection-dot" class="ta-realtime-connection-dot"></span>
                <span id="realtime-connection-text">连接中</span>
            </div>
        </div>

        <div class="ta-realtime-metrics">
            <div class="ta-card ta-realtime-metric">
                <h3>在线人数</h3>
                <p id="realtime-online-count">--</p>
            </div>
            <div class="ta-card ta-realtime-metric">
                <h3>最大人数</h3>
                <p id="realtime-max-count">--</p>
            </div>
            <div class="ta-card ta-realtime-metric">
                <h3>TPS</h3>
                <p id="realtime-tps">--</p>
            </div>
            <div class="ta-card ta-realtime-metric">
                <h3>MSPT</h3>
                <p id="realtime-mspt">--</p>
            </div>
            <div class="ta-card ta-realtime-metric">
                <h3>CPU</h3>
                <p id="realtime-cpu">--</p>
            </div>
            <div class="ta-card ta-realtime-metric">
                <h3>内存</h3>
                <p id="realtime-memory">--</p>
            </div>
            <div class="ta-card ta-realtime-metric">
                <h3>服务端状态</h3>
                <p id="realtime-server-status">未知</p>
            </div>
            <div class="ta-card ta-realtime-metric">
                <h3>最后更新时间</h3>
                <p id="realtime-last-updated">--</p>
            </div>
        </div>

        <section class="ta-card ta-realtime-section ta-realtime-health-card">
            <div class="ta-realtime-section-head">
                <h2>Realtime Link Health</h2>
                <span class="ta-realtime-pill">1s cache</span>
            </div>
            <div class="ta-realtime-health-lines">
                <div class="ta-realtime-health-line">
                    <span>Realtime API:</span>
                    <strong id="realtime-health-api">--</strong>
                </div>
                <div class="ta-realtime-health-line">
                    <span>Plugin:</span>
                    <strong id="realtime-health-plugin">--</strong>
                </div>
                <div class="ta-realtime-health-line">
                    <span>Last update:</span>
                    <strong id="realtime-health-last-update">--</strong>
                </div>
                <div class="ta-realtime-health-line">
                    <span>Players source:</span>
                    <strong id="realtime-health-players-source">--</strong>
                </div>
                <div class="ta-realtime-health-line">
                    <span>Fallback:</span>
                    <strong id="realtime-health-fallback">--</strong>
                </div>
            </div>
        </section>

        <div class="ta-realtime-content-grid">
            <section class="ta-card ta-realtime-section">
                <div class="ta-realtime-section-head">
                    <h2>在线玩家</h2>
                    <span id="realtime-player-total" class="ta-realtime-pill">0 人</span>
                </div>
                <ul id="realtime-player-list" class="ta-realtime-player-list">
                    <li class="ta-realtime-empty">暂无在线玩家</li>
                </ul>
            </section>

            <section class="ta-card ta-realtime-section">
                <div class="ta-realtime-section-head">
                    <h2>插件状态</h2>
                    <span class="ta-realtime-pill">实时</span>
                </div>
                <div class="ta-table-wrap">
                    <table class="ta-table">
                        <thead>
                        <tr>
                            <th>插件名称</th>
                            <th>状态</th>
                        </tr>
                        </thead>
                        <tbody id="realtime-plugin-list">
                        <tr>
                            <td colspan="2" class="ta-realtime-empty">暂无插件状态数据</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="ta-card ta-realtime-section">
            <div class="ta-realtime-section-head">
                <h2>实时聊天流</h2>
                <span class="ta-realtime-pill">直播</span>
            </div>
            <div id="realtime-chat-stream" class="ta-realtime-chat-stream">
                <div class="ta-realtime-empty">暂无聊天消息</div>
            </div>
        </section>
    </div>
</div>
