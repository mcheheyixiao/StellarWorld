# StellarVan Realtime Status Platform

## 1. 系统架构

本系统采用“网站消费层 + WebSocket 状态中心 + Minecraft 插件采集层”的三层架构。

- 网站（PHP MVC）: 对外提供页面与统一 REST API。
- WS 状态中心（Node/WebSocket Service）: 聚合服务器实时状态、玩家、聊天流。
- 插件采集层（Minecraft Plugin Agent）: 从游戏服采集运行指标与事件并推送到状态中心。
- MySQL: 作为历史快照与审计数据存储。

数据流路径:

1. 插件采集层 -> WS 状态中心
2. 网站 API -> WS 状态中心
3. 网站 API 在 WS 不可达时 -> MySQL 历史快照 -> 本地 snapshot

---

## 2. 数据来源说明

### 实时来源

网站实时读路径全部指向 WS 状态中心:

- GET /api/status -> {WS_STATUS_API_BASE}/api/status
- GET /api/players -> {WS_STATUS_API_BASE}/api/players
- GET /api/chat -> {WS_STATUS_API_BASE}/api/chat

### 历史来源

- MySQL server_status_history 保存状态历史快照。
- 本地 storage/cache/*.json 保存短周期缓存与降级 snapshot。

说明:

- 实时读优先级始终为 WS。
- DB 用于历史与故障兜底。

---

## 3. API 说明

### GET /api/status

上游调用:

- {WS_STATUS_API_BASE}/api/status

返回结构（保持兼容）:

`json
{
  "online": true,
  "players": {
    "online": 12,
    "max": 100,
    "list": [
      { "name": "Alice", "name_clean": "Alice" }
    ]
  },
  "motd": {
    "clean": "Welcome"
  },
  "version": {
    "name_clean": "1.20.1"
  }
}
`

### GET /api/players

上游调用:

- {WS_STATUS_API_BASE}/api/players

返回结构（保持兼容）:

`json
{
  "online": 12,
  "max": 100,
  "list": [
    { "name": "Alice", "name_clean": "Alice" }
  ]
}
`

### GET /api/chat

上游调用:

- {WS_STATUS_API_BASE}/api/chat

返回结构:

`json
[
  {
    "player": "Alice",
    "message": "hello",
    "time": 1710000000
  }
]
`

---

## 4. WebSocket 使用方式

前端通过 PUBLIC_STATUS_WS_URL 连接状态中心，实时接收 snapshot / players_update / server_status / chat_message 等事件。

示例:

`js
const ws = new WebSocket(window.PUBLIC_STATUS_WS_URL);

ws.onmessage = (event) => {
  const msg = JSON.parse(event.data);
  const type = msg.type || msg.event;

  if (type === "snapshot") {
    // 全量状态更新：在线状态、玩家列表、版本、MOTD
  }

  if (type === "players_update") {
    // 增量玩家列表更新
  }

  if (type === "chat_message") {
    // 实时聊天流
  }
};
`

说明:

- 首页保留轮询兜底。
- WS 可用时页面会优先采用实时事件。

---

## 5. 降级策略

当 WS 状态中心不可用时，网站 API 采用多级兜底:

1. WS API 实时请求
2. MySQL server_status_history 最新记录
3. 本地 snapshot 缓存
4. 离线默认响应

对应接口:

- /api/status: WS -> DB -> snapshot -> offline payload
- /api/players: WS -> status/DB -> snapshot -> offline players
- /api/chat: WS -> DB(status.chat) -> snapshot -> []

---

## 6. 配置说明

配置项位于 pp/config/config.php，支持环境变量覆盖。

| Key | 默认值 | 说明 |
|---|---|---|
| WS_STATUS_API_BASE | http://localhost:3001 | WS 状态中心 HTTP 基址 |
| WS_STATUS_API_TIMEOUT_MS | 2500 | WS API 请求超时 |
| PUBLIC_STATUS_WS_URL | "" | 前端实时 WS 地址 |

建议:

- 生产环境通过部署平台注入环境变量。
- PUBLIC_STATUS_WS_URL 建议使用 wss://。

---

## 7. 性能策略

### API 缓存

- 状态接口: 2s 短缓存（目标区间 1-3s）
- 玩家接口: 2s 短缓存
- 聊天接口: 1s 极短缓存

### Snapshot 策略

- 最近可用快照落盘到 storage/cache。
- 快照用于故障窗口内持续服务。

### 数据层角色

- WS: 实时状态中心
- DB: 历史数据与故障兜底

---

## 8. 运维检查清单

- 检查 WS 状态中心健康: {WS_STATUS_API_BASE}/api/status
- 检查网站 API 健康: /api/status
- 检查 WebSocket 连接: PUBLIC_STATUS_WS_URL
- 检查降级链: 停止 WS 服务后验证 /api/status 与 /api/players 仍有可读结果
