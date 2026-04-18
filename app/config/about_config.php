<?php
declare(strict_types=1);

/**
 * 关于页面静态文案配置。
 *
 * 说明：
 * - 本文件仅负责提供展示用文案与结构化配置，不涉及业务逻辑。
 * - 服主/管理员如需修改关于页内容，可直接编辑本数组内容保存后生效。
 */
return [
    'hero' => [
        'title' => '关于繁星World',
        'subtitle' => '一个偏爱「稳定、公平、公益」的 Minecraft 服务器',
        'description' => '这里是一个尽量不打扰你原本生活节奏，但又希望在你有空闲时能来放松一下的世界。我们更在意长期陪伴与良好氛围，而不是短期的「热度」。',
        'bullets' => [
            '长期维护，拒绝三分钟热度',
            '纯公益，无 P2W 机制',
            '适度RPG，兼顾生存与建筑体验',
        ],
    ],

    'info_sections' => [
        [
            'icon' => 'mdi-earth',
            'title' => '服务器定位',
            'description' => '以长期生存与轻度养老为主，不追求极限红石，也不过度追逐版本更新',
        ],
        [
            'icon' => 'mdi-shield-check',
            'title' => '安全与公平',
            'description' => '接入独立账号系统与多重风控校验，减少小号、外挂与恶意破坏。所有改动都会有记录，管理员同样受约束。',
        ],
        [
            'icon' => 'mdi-account-group-outline',
            'title' => '社区氛围',
            'description' => '欢迎主动打招呼，但不强迫社交。可以安静肝建筑，也可以和朋友组队开荒。我们鼓励尊重与包容，反对恶意引战。',
        ],
    ],

    'rules' => [
        'title' => '基本规则 & 约定',
        'description' => '以下规则并非要「约束一切」，而是希望为大多数玩家提供一个舒适的基础环境。如果你认同这些原则，我们会非常欢迎你长期留下来。',
        'items' => [
            [
                'icon' => 'mdi-emoticon-happy-outline',
                'title' => '尊重他人',
                'body' => '禁止人身攻击、恶意引战、辱骂或任何形式的骚扰行为。请像在现实生活中一样，对待每一位玩家。',
            ],
            [
                'icon' => 'mdi-cube-outline',
                'title' => '保护建筑与红石',
                'body' => '请勿未经允许拆改他人建筑及红石装置；如因误操作造成破坏，请第一时间主动联系管理员协调处理。',
            ],
            [
                'icon' => 'mdi-flash-outline',
                'title' => '理性使用资源',
                'body' => '鼓励建设公共设施与共享农场，但请避免过度刷怪/卡服机器。如果你有大型红石/生电计划，建议先与服主沟通。',
            ],
            [
                'icon' => 'mdi-alert-circle-outline',
                'title' => '零容忍作弊',
                'body' => '严禁外挂、恶意客户端、利用漏洞破坏服务器平衡。一旦确认存在恶意行为，将视情况封禁账号甚至列入黑名单。',
            ],
        ],
    ],

    'contacts' => [
        'title' => '联系服主 & 管理组',
        'description' => '如果你在游戏内遇到任何问题，或有合作、活动、技术交流的想法，都可以通过下列方式找到我们',
        'items' => [
            [
                'icon' => 'mdi-qqchat',
                'label' => '玩家交流群（QQ）',
                'hint' => '适合日常聊天、问题求助、活动发布',
                'url' => 'https://qun.qq.com/universal-share/share?ac=1&authKey=8UpEiMMKS7hiIpwIkQEaeUGAExHYfcrAy7%2B3Tby6s9kgaCxFeaAPeFx6OJcvtgcO&busi_data=eyJncm91cENvZGUiOiIxMDg3NzA2OTA5IiwidG9rZW4iOiJidVNIYUlHSlFTcktjZWdpQ3M5UUZhQVVER3NEUUZsRitMcVg5Z1FMZU5PdlRkdThRWHpYNkhyY0hEUkQ0Q1FOIiwidWluIjoiMjAyODIwNzM2OSJ9&data=gAUJbwpJpVWgPhS_YE7kB58ahTI8W7KOlk6_HZdN5aog3sEji3SMGYgrGCpxkkbwLPiFpkXtGLKR8zU8gKy9vg&svctype=4&tempid=h5_group_info',
            ],
            [
                'icon' => 'mdi-microphone',
                'label' => '社区语音频道',
                'hint' => '语音联机、开黑、闲聊都可以',
                'url' => 'https://kook.vip/TKynCD',
            ],
            [
                'icon' => 'mdi-email-outline',
                'label' => '邮件联系',
                'hint' => '反馈严重 Bug 或合作事宜建议使用邮件',
                'url' => 'mailto:2028207369@qq.com',
            ],
        ],
        'tip' => '欢迎您的到来！',
    ],

    'members' => [
        'title' => '管理团队',
        'description' => '繁星World管理团队信息展示区',
        'avatar' => [
            'enabled' => true,
            'type' => 'crafatar', // crafatar / none / custom
            'base_url' => 'https://crafatar.com/avatars/',
        ],
    ],
];

