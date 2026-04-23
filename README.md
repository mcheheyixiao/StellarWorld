# Project Internal README

---

## 🧠 项目定位

本项目是一个面向 Minecraft 服务器的站点与管理面板一体化系统，当前形态为「官网展示 + 玩家中心 + 管理后台 + 外部实时数据接入」。

项目用途（按代码推断）：
- MC 服务器官网（主页、公告、相册、关于页、排行榜）
- 玩家账号系统（登录/注册/找回密码/MUA 绑定）
- 玩家中心（账号绑定、安全中心、角色信息、皮肤展示）
- 管理后台（玩家管理、公告、里程碑、图库、站点设置、IP 黑白名单）
- 数据同步层（HTTP 推送 + WebSocket 实时面板 + RCON 指令联动）

当前技术形态：
- 原生 PHP MVC 单体应用
- 服务端渲染（PHP View）+ 前端 `fetch` 增量交互
- MySQL（PDO）+ 可选 Redis 缓存
- 外部服务整合：Cloudflare Turnstile、MUA OAuth、MC 状态 API、WebSocket、RCON

---

## 🧱 当前架构

### 架构类型
原生 PHP MVC（自研轻量 Router + Controller + Model + View）。

### 请求流程
用户请求 → `public/index.php` → `Router` 路由匹配 → Controller 业务处理 → Model/DB 查询 → View 渲染（或 JSON 响应）→ 前端页面/脚本继续调用 API。

### 数据流
- 前端 → 后端 API：登录注册、资料修改、排行榜搜索、状态查询、头像/皮肤代理。
- 后端 → 外部 API：`mcstatus`、MUA 节点、Cloudflare Turnstile、音乐接口等。
- 插件/服务端 → 网站：`POST /api/server/status/update` 写入缓存数据。
- 管理端前端 → WebSocket：实时请求 `snapshot`，接收玩家/插件/聊天/状态更新事件。
- 网站 → MC 服务端：通过 RCON 发送皮肤同步指令（MUA 绑定后）。

---

## 📁 项目结构（核心）

```text
/
├─ app/
│  ├─ config/                  # 运行配置、路由定义、关于页配置
│  ├─ controllers/             # 业务控制器（Auth/API/Admin/Profile/Page/Home）
│  ├─ core/                    # 核心基类与基础设施（Router/Controller/View/DB/RCON/MUA等）
│  ├─ models/                  # 数据模型（User/Audit）
│  └─ views/                   # 页面模板与后台子视图
├─ public/
│  ├─ assets/                  # 业务资源（如 memberlist/whitelist.json）
│  ├─ fonts/                   # 自定义字体
│  ├─ images/                  # 图片素材与背景资源
│  ├─ scripts/                 # 前端脚本（主题、导航、加载、实时面板）
│  ├─ styles/                  # Tailwind 编译产物与全局样式
│  └─ index.php                # Web 入口
├─ deploy/                     # Nginx 缓存策略等部署辅助配置
├─ vendor/                     # Composer 依赖
├─ database.sql                # 核心数据库结构与升级脚本
├─ lighthouse-report.json      # 性能分析报告（本地采样）
└─ tailwind.config.js          # Tailwind 扫描配置
```

目录作用 + 当前完成度（按代码可运行程度推断）：
- `app/config`：配置覆盖完整，完成度 85%（存在敏感默认值硬编码问题）。
- `app/controllers`：功能最完整，完成度 88%。
- `app/core`：基础能力较齐全，完成度 85%。
- `app/models`：模型层较薄，完成度 70%（大量 SQL 仍在 Controller）。
- `app/views`：页面覆盖广，完成度 82%（样式与脚本分散）。
- `public/scripts`：基础交互 + 实时面板已落地，完成度 80%。
- `public/styles`：Tailwind + 自定义并存，完成度 78%（重复样式与冗余明显）。
- `deploy`：静态缓存规则存在，完成度 65%。
- `database.sql`：主业务表完整，完成度 86%。

---

## 🔧 核心模块拆解

### 1. 用户系统
功能：
- 登录、注册、邮箱验证、找回密码、重置密码、登出、Remember Me。
- MUA OAuth 登录与绑定。

实现方式：
- `AuthController` + `User` Model + `AuditModel`。
- CSRF 校验、Turnstile 校验、登录失败计数、IP 黑白名单联动、审计日志记录。
- 密码哈希兼容 AuthMe 格式；Remember Me 采用 selector/validator + hash 存储。

完成度：
- 90%。

### 2. 玩家仪表盘
当前 UI 结构：
- 用户资料页含游戏数据、账号绑定、安全中心、玩家功能区（含签到展示）。
- 玩家公开页含 3D/2D 皮肤展示、统计与时间线。

数据来源：
- `users`、`player_stats`、`user_checkins`、`audit_logs`。
- 皮肤数据由 MUA/Crafatar/Minotar + `skin-proxy` 组合获取。

问题点：
- 签到功能前端大量为静态演示逻辑，未形成完整后端写入链路。
- 页面内联样式与内联脚本体量大，维护成本偏高。

完成度：
- 76%。

### 3. 管理后台
功能模块：
- 仪表盘统计、玩家管理、公告管理、里程碑、图库、站点设置、团队成员、IP 黑白名单。
- 实时面板入口与配置注入。

权限逻辑：
- 统一使用 `requireAdmin()` 判断 `$_SESSION['role'] === 'admin'`。
- 各表单带 CSRF 校验。

完成度：
- 83%。

### 4. Minecraft 数据同步
WebSocket / API：
- HTTP 推送：`POST /api/server/status/update`（token 校验）。
- 缓存读取：`GET /api/status/cache`。
- 管理端实时：`public/scripts/admin-realtime-panel.js` 通过 WS 接收 `snapshot` / `stats_update` / `players_update` / `plugins_update` / `chat_message` / `server_status`。
- RCON 同步：MUA 皮肤绑定后可向服务器执行 `sr createcustom`、`sr set`。

插件通信方式：
- 仓库内未见插件实现源码，网站侧通过约定 JSON + Token 接收推送，并依赖外部 WS 服务与 MC 服务端协作。

完成度：
- 68%。

### 5. UI 系统
组件结构：
- 具备 PHP 层组件化（`layouts` + `partials` + admin 子布局）。
- 但页面级内联样式和内联 JS 仍占比较高。

样式体系：
- Tailwind（`tailwind-input.css` + `tailwind-dist.css`）与自定义 CSS 并行。
- 玻璃态、模糊、过渡动画使用频繁。

完成度：
- 74%。

---

## 🔌 API 设计（必须重点分析）

### 公共 API（`/api/*`）

#### `GET /api/status/cache`
用途：
- 读取服务器状态缓存；优先 Redis，其次本地缓存文件，必要时回源外部 `mcstatus`。

返回结构（推测）：
```json
{
  "online": true,
  "players": {
    "online": 12,
    "max": 100,
    "list": ["playerA", "playerB"]
  }
}
```

#### `POST /api/server/status/update`
用途：
- 接收外部服务/插件推送的状态 JSON，写入缓存（`mod_api_push.json`）。

请求结构（推测）：
```json
{
  "token": "SERVER_TOKEN",
  "server": {},
  "stats": {},
  "players": [],
  "plugins": [],
  "chat": []
}
```

返回结构：
```json
{
  "success": true,
  "message": "Accepted"
}
```

#### `GET /api/leaderboard/search?board=play_time&q=xxx`
用途：
- 排行榜模糊搜索与 TopN 返回。

返回结构（代码可确认）：
```json
{
  "success": true,
  "board": "play_time",
  "results": [
    {
      "mc_uuid": "xxxx",
      "username": "playerA",
      "value": 12.3,
      "unit": "h",
      "rank": 1
    }
  ]
}
```

#### `GET /api/avatar?username=xxx&size=32`
用途：
- 头像代理与缓存（Redis + DB `avatar_cache` + 文件缓存）。
- 返回 PNG（二进制），非 JSON。

#### `GET /api/skin-proxy?url=https://...`
用途：
- 图片跨域代理，供皮肤渲染使用。
- 返回 PNG（二进制），失败时返回透明占位图。

### 业务 AJAX 接口（非 `/api`）

#### 认证相关
- `POST /auth/login`
- `POST /auth/register`
- `POST /forgot-password`
- `POST /reset-password`

通用响应形态：
```json
{
  "success": true,
  "message": "..."
}
```

#### 个人中心
- `POST /profile/password/update`
- `POST /profile/password/quick-reset`
- `POST /profile/mc-character/update`

示例返回：
```json
{
  "success": true,
  "message": "游戏角色绑定更新成功",
  "mc_uuid": "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
}
```

### 管理端接口（表单提交 + 重定向为主）
- `POST /admin/players/update|delete|unbind`
- `POST /admin/announcements/save|delete`
- `POST /admin/milestones/save|delete`
- `POST /admin/gallery/upload|delete`
- `POST /admin/ip-blacklist/add|delete`
- `POST /admin/ip-whitelist/add|delete`
- `POST /admin/site-settings/save`
- `POST /admin/team-members/save|delete`

说明：
- 多数管理接口返回重定向（`Location: /admin?...`），非标准 JSON API。

### 数据结构（核心表）
- `users`：账号、角色、状态、MC 绑定、MUA 绑定、邮箱验证状态。
- `player_stats`：玩家统计（在线时长、方块挖掘/放置、击杀、死亡等）。
- `user_checkins`：签到记录。
- `audit_logs`：行为审计。
- `auth_tokens`：Remember Me 持久令牌。
- `site_settings`：站点配置中心。
- `ip_whitelist` / `ip_blacklist`：IP 策略控制。

---

## 📡 WebSocket 结构

连接地址：
- 来源于配置常量 `REALTIME_WS_URL`，默认值为 `wss://.../ws/admin?...`。
- 前端可附加 `token` 参数，且连接建立后会再发送一次 `auth` 消息。

触发逻辑：
- 仅在后台 `realtime` 标签激活时连接。
- 页面可见时维持连接，不可见时暂停/断开并重连。
- 定时发送 `snapshot` 请求（含最小间隔节流）。

前端发出消息（推测）：
```json
{ "type": "auth", "payload": { "token": "..." } }
```
```json
{ "type": "snapshot", "payload": {} }
```

接收事件类型（代码可确认）：
- `snapshot`
- `stats_update`
- `players_update`
- `plugins_update`
- `chat_message`
- `server_status`

数据用途：
- 实时刷新在线人数、TPS/CPU/内存等指标、玩家列表、插件状态、聊天流、服务器状态文案。

---

## 🎨 前端结构

页面划分：
- 公共页面：主页/相册/排行榜/公告/关于/玩家公开页。
- 认证页面：登录/注册/找回/重置/验证结果。
- 用户中心：资料页（多面板）。
- 管理后台：统一布局 + Tab 子页面（含 realtime/checkin 子块）。

UI 逻辑：
- 服务端渲染首屏。
- 前端 `fetch` 拉取 JSON 更新局部模块。
- 管理实时页通过 WebSocket 增量更新。

JS 交互方式：
- 原生 JS 为主，未见 axios 依赖。
- 皮肤展示依赖 `skinview3d` CDN。
- 音乐播放器与第三方接口直接交互。

是否组件化：
- PHP 模板层具备组件化（`partials`、`layouts`）。
- CSS/JS 组件化程度中等，页面内联逻辑较多。

样式体系：
- Tailwind + 自定义 CSS 并行。
- 频繁使用 `transition`、`backdrop-filter`、hover 动效。
- 存在重复样式片段与页面私有样式堆积。

响应式策略：
- 以 `@media` 与 Tailwind 断点混合实现。
- 导航、卡片、后台布局均具备移动端适配分支。

---

## 📊 当前开发进度（非常重要）

已完成：
- ✔ 登录系统（CSRF + Turnstile + 限流 + Remember Me）
- ✔ 注册/邮箱验证/找回密码闭环
- ✔ 管理后台主流程（玩家、公告、里程碑、图库、站点设置、IP 黑白名单）
- ✔ 排行榜与玩家页的数据展示链路
- ✔ 头像缓存代理与皮肤代理
- ✔ WebSocket 实时面板前端接入
- ✔ RCON 皮肤同步通道（MUA 绑定后）

未完成 / 待完善：
- ❌ 签到系统后端闭环（当前以静态演示为主）
- ❌ API 规范统一（JSON API 与表单重定向混用）
- ❌ 状态推送链路一致性（`status/update` 写入与 `status/cache` 回源策略存在分叉）
- ❌ 配置安全化（敏感默认值仍硬编码）
- ❌ 统一错误码与响应契约
- ❌ 前端样式与脚本分层标准化

整体完成度（工程视角）：
- 约 78%（可运行、可用，但仍有中后期治理任务）。

---

## ⚠️ 当前问题（仅分析）

结构问题：
- Controller 承担大量 SQL 与流程分支，Model 层偏薄。
- 管理端接口大多重定向返回，不利于 API 化与前后端职责清晰化。
- 实时链路依赖外部 WS 服务，仓库内缺少对应服务实现与契约文档。

性能隐患：
- `lighthouse-report.json`（2026-04-21）显示：Performance 60，FCP 6.0s，LCP 47.3s，TTI 47.4s，总传输约 9.4MiB。
- 字体资源总体偏重，存在重复请求。
- Lighthouse 指出未使用 CSS 体积较大（约 362KiB）与明显渲染阻塞时延（约 2520ms）。

代码耦合点：
- 认证、审计、IP 策略、MUA、RCON 在单控制器内交织。
- 多处业务逻辑直接依赖全局常量与 `$_SESSION`，缺少抽象边界。
- 配置项与业务逻辑强耦合，环境切换风险较高。

UI 问题：
- 页面内联样式/脚本多，复用与维护成本偏高。
- 管理端与前台视觉体系存在风格分裂。
- 部分模块（签到）UI 完整但数据闭环未落地，易造成“功能已完成”误判。

---

## 🧩 技术债记录（重要）

- 配置债务：敏感值在 `config.php` 以默认值形式存在，且部分 token 出现在 URL 级配置。
- 安全债务：多处外部请求关闭 SSL 校验；`skin-proxy` 为开放 URL 代理，存在 SSRF 攻击面。
- 会话债务：登录成功后未见显式 session ID 轮换流程（需关注会话固定风险）。
- API 债务：同一系统内混合 JSON 接口与表单跳转，契约统一性不足。
- 模块债务：签到模块前后端不对齐（前端演示先行，后端落地滞后）。
- 依赖债务：存在 `BaiduSEO` 引用但仓库内无对应核心类实现迹象（潜在不可达或遗留路径）。
- 性能债务：首屏资源重量高，前端资源分发策略与页面模块化颗粒度不均衡。

---

## 🧭 后续开发建议（仅方向）

架构层建议：
- 将认证/账号安全、玩家域、内容域、后台管理域进行边界化拆分。
- 建立统一 API 契约层（状态码、错误码、响应包装）并逐步替代重定向式后台写操作。
- 将配置中心与密钥管理标准化，降低运行环境耦合。
- 为外部 WS 服务与插件推送建立协议文档（消息 schema + 版本策略）。

模块拆分建议：
- 将签到系统独立为完整子模块（规则、发奖、日志、统计、审计）。
- 将“实时状态聚合”从页面逻辑中抽离为独立服务契约层。
- 将前端样式体系从“页面内联”迁移到“组件样式清单”治理模式。
- 将审计与风控（限流、IP 策略、验证码）整理为可复用中间层能力。

---

## 📝 总结

架构成熟度：
- 中等偏上。核心业务链路齐全，具备实际生产雏形。

可维护性：
- 中等。功能可用，但控制器臃肿、接口风格混杂、配置耦合降低了长期可维护性。

扩展性：
- 中等。已有 MVC 与缓存/实时基础，但需要在模块边界、协议契约与配置治理层面进一步工程化，才能支撑后续多人协作和持续演进。
```
