# StellarWorld Realtime Observability

## 1. 系统架构

StellarWorld 采用三层实时观测架构：

- 网站层（PHP MVC）：提供后台页面与统一 API。
- 状态中心（WS + HTTP）：聚合游戏服实时指标、玩家、聊天与插件状态。
- 数据兜底层（MySQL + 本地缓存）：在状态中心不可用时提供可读结果。

链路如下：

1. Minecraft 插件 -> 状态中心（WebSocket/HTTP 推送）
2. 网站 API -> 状态中心（`{WS_STATUS_API_BASE}`）
3. 状态中心异常时 -> DB 历史快照 -> 本地 snapshot 缓存 -> 离线默认值

## 2. 数据来源（WS vs DB）

实时优先级：

- `/api/status` 优先读取 WS 状态中心。
- `/api/players` 优先读取 WS 玩家数据。
- `/api/chat` 优先读取 WS 聊天流。

兜底来源：

- MySQL 表 `server_status_history`：历史状态快照。
- `storage/cache/*.json`：短时缓存与 snapshot。

结论：

- WS 是实时主来源。
- DB 与 snapshot 仅用于故障窗口的连续服务。

## 3. API 说明

核心 API（保持现有接口不删除）：

- `GET /api/status`
- `GET /api/status/cache`
- `GET /api/status/health`
- `GET /api/plugins`
- `GET /api/players`
- `GET /api/chat`

### `GET /api/status`

上游：`{WS_STATUS_API_BASE}/api/status`

说明：

- 如果配置了 `WS_STATUS_API_TOKEN`，网站请求会上送：
  - `Authorization: Bearer {WS_STATUS_API_TOKEN}`
  - `x-api-token: {WS_STATUS_API_TOKEN}`

返回（示例）：

```json
{
  "online": true,
  "players": {
    "online": 12,
    "max": 100,
    "list": [{ "name": "Alice", "name_clean": "Alice" }]
  },
  "motd": { "clean": "Welcome" },
  "version": { "name_clean": "1.20.1" }
}
```

### `GET /api/status/health`

上游：`{WS_STATUS_API_BASE}/health`

返回（示例）：

```json
{
  "realtime_api": "OK",
  "plugin": "Online",
  "last_update_seconds": 3,
  "players_source": "WS",
  "fallback_enabled": false,
  "fallback": "Disabled",
  "checked_at": 1710000000
}
```

失败时（示例）：

```json
{
  "realtime_api": "ERROR",
  "plugin": "Offline",
  "players_source": "Fallback",
  "fallback_enabled": true,
  "fallback": "Enabled"
}
```

### `GET /api/players`

上游：`{WS_STATUS_API_BASE}/api/players`

### `GET /api/chat`

上游：`{WS_STATUS_API_BASE}/api/chat`

### `GET /api/plugins`

上游：`{WS_STATUS_API_BASE}/api/status`

返回（示例）：

```json
{
  "source": "WS",
  "plugins": [
    {
      "name": "LuckPerms",
      "version": "5.4.0",
      "enabled": true
    }
  ],
  "updated_at": 1710000000
}
```

兜底（示例）：

```json
{
  "source": "Fallback",
  "plugins": [],
  "updated_at": null,
  "error": "Realtime unavailable or unauthorized"
}
```

## 4. WebSocket 使用方式

前台页面通过 `PUBLIC_STATUS_WS_URL` 连接状态中心，实时接收：

- `snapshot`
- `players_update`
- `stats_update`
- `server_status`
- `chat_message`

示例：

```js
const ws = new WebSocket(window.PUBLIC_STATUS_WS_URL);

ws.onmessage = (event) => {
  const payload = JSON.parse(event.data);
  const type = payload.type || payload.event || payload.action;

  if (type === "snapshot") {
    // 全量刷新状态卡（在线状态、玩家、版本、TPS、MOTD）
  }

  if (type === "players_update") {
    // 增量刷新玩家数与玩家列表
  }
};
```

前台策略：

- WS 可用时优先使用实时事件。
- HTTP 轮询（`/api/status`）保留为兜底。

## 5. 健康检测

后台实时链路健康卡片会展示：

- `Realtime API: OK / ERROR`
- `Plugin: Online / Offline`
- `Last update: Ns ago`
- `Players source: WS / Fallback`
- `Fallback: Enabled / Disabled`

检测方式：

- 网站后端请求 `{WS_STATUS_API_BASE}/health`。
- 健康接口结果按 1s 短缓存提供给后台页面。
- 后台前端轮询 `/api/status/health` 进行可视化刷新。

## 6. 降级策略

当 WS 状态中心失败时，接口降级顺序：

1. 读取 WS 实时接口（主链路）
2. 读取 MySQL 最新状态快照
3. 读取本地 snapshot 缓存
4. 返回离线默认 payload

对应接口：

- `/api/status`: WS -> DB -> snapshot -> offline
- `/api/players`: WS -> status/DB -> snapshot -> offline players
- `/api/chat`: WS -> DB(status.chat) -> snapshot -> `[]`

## 7. 性能策略

缓存策略：

- `status API`: 1s 缓存
- `players`: 短缓存（2s）
- `chat`: 实时或极短缓存（1s）

性能目标：

- 在实时性与稳定性之间平衡请求压力。
- 状态中心短暂波动时，页面仍保持可读。
- 优先保证后台观测卡片与前台状态卡连续更新。

## 环境变量

- `WS_STATUS_API_BASE`：WS 状态中心 HTTP 基址，默认 `http://127.0.0.1:3002`
- `WS_STATUS_API_TIMEOUT_MS`：状态中心请求超时（毫秒）
- `WS_STATUS_API_TOKEN`：状态中心 API token（可留空；为空时仅依赖无需鉴权的接口如 `/health`）
- `PUBLIC_STATUS_WS_URL`：前台 WebSocket 地址
