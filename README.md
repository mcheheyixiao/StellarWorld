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

StellarRealtime 默认链路（建议）：

- Realtime HTTP health：`http://host:3001/health`
- Realtime API：`http://host:3001/api/status`
- WebSocket 后台：`ws://host:3001/ws/admin`
- 插件上报：`ws://host:3001/ws/plugin`

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
  "plugin_online": true,
  "last_update_seconds": 3,
  "players_source": "WS",
  "fallback_enabled": false,
  "fallback_active": false,
  "fallback_available": true,
  "fallback": "Disabled",
  "checked_at": 1710000000
}
```

失败时（示例）：

```json
{
  "realtime_api": "ERROR",
  "plugin": "Offline",
  "plugin_online": false,
  "players_source": "Fallback",
  "fallback_enabled": true,
  "fallback_active": true,
  "fallback_available": true,
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

Token 兼容与安全约束：

- `WS_STATUS_API_TOKEN` 仅用于网站后端请求 Realtime `/api/*`。
- `/health` 默认无需 token，网站侧不会强制附带 token。
- 不在浏览器端暴露 `WS_STATUS_API_TOKEN`。

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

Plugin 显示 `Offline` 的常见原因：

1. `StellarStatsSync` 配置 `websocket.enabled=false`
2. 插件 `auth_token` 与 Realtime `PLUGIN_TOKENS` 不一致
3. 插件连接路径不是 `/ws/plugin`
4. Realtime 服务未启动或端口不通
5. 网站 `WS_STATUS_API_BASE` 指向错误端口

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

- `WS_STATUS_API_BASE`：WS 状态中心 HTTP 基址，默认 `http://127.0.0.1:3001`
- `WS_STATUS_API_TIMEOUT_MS`：状态中心请求超时（毫秒）
- `WS_STATUS_API_TOKEN`：状态中心 API token（可留空；为空时仅依赖无需鉴权的接口如 `/health`）
- `PUBLIC_STATUS_WS_URL`：前台 WebSocket 地址

## Realtime Ticket Verify Env

```dotenv
REALTIME_TICKET_VERIFY_TOKEN=replace-with-internal-service-token
REALTIME_TICKET_VERIFY_ALLOW_EMPTY_TOKEN=0
```

## Redeem V2 + V3 (Operations + Rule Restrictions on top of V1)

### Manual SQL Setup

This project does not use a dedicated migration framework.
Run the base SQL file manually:

```bash
mysql -u <user> -p <database> < database.sql
```

### Admin Entry

- `/admin?tab=redeem`
- `/admin/redeem` (redirects to the tab above)

### Admin APIs (V1 + V2 + V3)

- `GET /api/admin/redeem/categories`
- `POST /api/admin/redeem/categories`
- `PATCH /api/admin/redeem/categories/{id}`
- `DELETE /api/admin/redeem/categories/{id}`

- `GET /api/admin/redeem/keys`
  - V3 filter params: `bound_player_uuid`, `allowed_server_id`, `require_bound_account`, `require_email_verified`, `require_account_active`
- `GET /api/admin/redeem/keys/export` (V2: export current filters to CSV payload)
- `POST /api/admin/redeem/keys/batch`
- `PATCH /api/admin/redeem/keys/{id}/revoke`
- `POST /api/admin/redeem/keys/revoke-batch`
- `POST /api/admin/redeem/keys/delete-batch`
  - `delete-batch` is a soft delete only (`redeem_keys.status` -> `deleted`), not a physical row delete.

- `GET /api/admin/redeem/batches` (V2)
- `GET /api/admin/redeem/batches/{id}` (V2)
- `GET /api/admin/redeem/batches/{id}/stats` (V2)

- `GET /api/admin/redeem/logs`
  - V3 filter params: `rule_result`, `rule_reason`, `website_user_id`
- `PATCH /api/admin/redeem/logs/{id}/admin-status` (V2: `pending|handled|ignored`)
- `GET /api/admin/redeem/admin-logs` (V2)
- `GET /api/admin/redeem/stats/publish`

### New Minecraft Plugin APIs

- `POST /api/minecraft/redeem/claim`
- `POST /api/minecraft/redeem/{redeemId}/complete`
- `POST /api/minecraft/redeem/{redeemId}/fail`
- `POST /api/minecraft/redeem/heartbeat`

### Required Environment Variables

- `REDEEM_CODE_PEPPER` (required)
- `REDEEM_CODE_CASE_INSENSITIVE` (default: `true`)
- `REDEEM_PLUGIN_SERVER_ID` (required for plugin API)
- `REDEEM_PLUGIN_SERVER_SECRET` (required for plugin API)
- `REDEEM_PLUGIN_TIME_WINDOW_SECONDS` (default: `300`)

Realtime internal forwarding env (optional but recommended):

```dotenv
REALTIME_INTERNAL_EVENT_URL=http://127.0.0.1:3001/internal/events
REALTIME_INTERNAL_SECRET=replace-with-same-secret-as-StellarRealtime
REALTIME_INTERNAL_TIMEOUT_MS=800
```

### Website Sign-in Gateway (LiteSignIn Bridge)

Required env for website sign-in gateway:

```dotenv
REALTIME_INTERNAL_URL=http://127.0.0.1:3001
REALTIME_INTERNAL_SECRET=replace-with-long-random-secret
SIGNIN_SERVER_ID=survival-1
SIGNIN_REQUIRE_PLAYER_ONLINE=true
SIGNIN_REQUEST_TIMEOUT_MS=5000
WS_STATUS_API_BASE=http://127.0.0.1:3001
WS_STATUS_API_TOKEN=replace-with-api-token
```

Operational notes:

- Website only sends `signin.request` to Realtime `/internal/plugin-command`.
- Website does not write LiteSignIn database tables directly.
- Website does not deliver in-game sign-in rewards.
- `stellar_signin_daily_cache` is display cache only.
- LiteSignIn plugin remains the source of truth for check-in results.

### Plugin Auth Contract (Redeem API)

Headers:

- `X-Stellar-Server-Id`
- `X-Stellar-Timestamp`
- `X-Stellar-Signature`

Signature:

```text
hmac_sha256(timestamp + "." + raw_body, REDEEM_PLUGIN_SERVER_SECRET)
```

Server checks:

- server id must equal `REDEEM_PLUGIN_SERVER_ID`
- timestamp window validation
- constant-time signature comparison

### Realtime Event Hook (Lightweight, best-effort)

Redeem V2/V3 emits lightweight event calls through `Core\RealtimeNotifier::emit(...)`:

- `redeem.key.generated`
- `redeem.key.revoked`
- `redeem.claim.success`
- `redeem.claim.failed`
- `redeem.stats.updated`
- `redeem.plugin.heartbeat` (from `POST /api/minecraft/redeem/heartbeat`)
- `redeem.batch.generated` (V2)
- `redeem.key.exported` (V2)
- `redeem.log.admin_status_updated` (V2)
- `redeem.admin_log.created` (V2)
- `redeem.claim.rejected` (V3)
- `redeem.rule.matched` (V3)
- `redeem.rule.denied` (V3)
- `redeem.rule.stats.updated` (V3)

By default this is a no-op unless you provide a bridge function like `stellar_realtime_emit`
or configure `REALTIME_INTERNAL_EVENT_URL` + `REALTIME_INTERNAL_SECRET`.

Realtime forwarding is best-effort and never blocks the main redeem flow.
V2/V3 event payloads avoid secret/pepper/plain-code leakage and do not include CSV file contents.

### V2/V3 Added Features

- batch management (`redeem_batches`)
- channel/source field on keys (`redeem_keys.channel`)
- admin operation logs (`redeem_admin_logs`)
- failed redeem manual handling (`redeem_logs.admin_status/admin_note/handled_by/handled_at`)
- key CSV export for current filters
- enhanced filters for keys/logs
- batch statistics
- server restriction rules (`redeem_keys.allowed_server_ids`)
- player restriction rules (`redeem_keys.bound_player_uuid`, optional `bound_player_name`)
- bound-account requirements (`require_bound_account`, `require_email_verified`, `require_account_active`)
- per-player and per-account limits (`per_player_limit`, `per_account_limit`)
- rule result logging (`redeem_logs.rule_result/rule_reason/rule_snapshot_json/website_user_id`)
- V3 admin actions: `v3_rule_batch_generate`, `v3_rule_export`

### V3 Rule Result Semantics

- `rule_result = passed`: rule check enabled and passed, then usage is incremented and log enters `executing`.
- `rule_result = rejected`: rule denied claim, usage is not incremented and commands are not executed.
- `rule_result = skipped`: key has no V3 restriction enabled.

Per-limit counting policy:

- `per_player_limit` / `per_account_limit` count `redeem_logs.status IN ('executing','success')`.
- `failed` is not counted automatically; failed command execution still needs manual admin handling in V2 flow.

### V3 Still Not Included

- no offline reward queue
- no automatic command rollback on plugin fail callback
- no complete reward center
- no independent per-server inventory matrix
- no PlaceholderAPI expansion service on website side
- no QQ-binding strong verification enforcement (reserved for future extension)
- failed records do not automatically roll back `used_count`

### Rollback

1. remove new routes in `app/config/routes.php`
2. remove redeem tab/button include from admin views
3. remove redeem-related controllers/services/models/scripts
4. drop tables if needed:
   - `DROP TABLE redeem_logs;`
   - `DROP TABLE redeem_keys;`
   - `DROP TABLE redeem_categories;`
   - `DROP TABLE redeem_batches;`
   - `DROP TABLE redeem_admin_logs;`
5. remove redeem env vars from deployment config
