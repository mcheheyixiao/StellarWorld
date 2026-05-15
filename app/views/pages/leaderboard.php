<?php
/** @var array $leaderboards */
/** @var string|null $lastUpdate */
/** @var string|null $leaderboardError */

$leaderboards = is_array($leaderboards ?? null) ? $leaderboards : [];
$lastUpdate = $lastUpdate ?? null;
$leaderboardError = $leaderboardError ?? null;
$fallbackAvatar = '/images/owner_avatar.png';

$formatScore = static function (string $format, $value): string {
    if ($format === 'float1') {
        return number_format((float)$value, 1, '.', ',');
    }
    if ($format === 'float2') {
        return number_format((float)$value, 2, '.', ',');
    }

    return number_format((int)$value, 0, '.', ',');
};

$normalizedLeaderboards = [];
foreach ($leaderboards as $index => $board) {
    if (!is_array($board)) {
        continue;
    }

    $rawKey = trim((string)($board['key'] ?? ''));
    $rawTitle = trim((string)($board['title'] ?? ''));
    $boardKey = $rawKey !== '' ? $rawKey : ('board_' . $index);
    $boardTitle = $rawTitle !== '' ? $rawTitle : '未命名榜单';
    $entries = isset($board['entries']) && is_array($board['entries']) ? $board['entries'] : [];
    $normalizedEntries = [];

    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $normalizedEntries[] = [
            'username' => trim((string)($entry['username'] ?? '')),
            'value' => $entry['value'] ?? 0,
            'unit' => (string)($entry['unit'] ?? ''),
            'rank' => isset($entry['rank']) && is_numeric($entry['rank']) ? (int)$entry['rank'] : null,
        ];
    }

    $normalizedLeaderboards[] = [
        'key' => $boardKey,
        'title' => $boardTitle,
        'unit' => (string)($board['unit'] ?? ''),
        'format' => (string)($board['format'] ?? 'int'),
        'entries' => $normalizedEntries,
    ];
}

$leaderboardsJson = json_encode($normalizedLeaderboards, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($leaderboardsJson)) {
    $leaderboardsJson = '[]';
}

$firstBoardTitle = $normalizedLeaderboards[0]['title'] ?? '当前分类';
?>

<style>
.leaderboard-page .leaderboard-shell {
  position: relative;
  margin: 0 auto;
  max-width: 1260px;
  padding: clamp(1rem, 2vw, 1.75rem);
  overflow: hidden;
}
.leaderboard-page .leaderboard-shell::before,
.leaderboard-page .leaderboard-shell::after {
  content: "";
  position: absolute;
  pointer-events: none;
}
.leaderboard-page .leaderboard-shell::before {
  top: -140px;
  right: -120px;
  width: 340px;
  height: 340px;
  border-radius: 999px;
  background: radial-gradient(circle, rgba(56, 189, 248, 0.16) 0%, rgba(56, 189, 248, 0.02) 52%, transparent 72%);
}
.leaderboard-page .leaderboard-shell::after {
  bottom: -160px;
  left: -120px;
  width: 360px;
  height: 360px;
  border-radius: 999px;
  background: radial-gradient(circle, rgba(251, 191, 36, 0.14) 0%, rgba(251, 191, 36, 0.03) 50%, transparent 72%);
}
.leaderboard-page .leaderboard-header,
.leaderboard-page .leaderboard-layout,
.leaderboard-page .leaderboard-error-banner,
.leaderboard-page .leaderboard-empty-all {
  position: relative;
  z-index: 1;
}
.leaderboard-page .leaderboard-header {
  margin-bottom: 1rem;
}
.leaderboard-page .leaderboard-title {
  margin: 0 0 0.5rem;
  color: #f8fafc;
}
.leaderboard-page .leaderboard-subtitle {
  margin: 0;
  color: #a7c0e4;
  font-size: 0.93rem;
  line-height: 1.55;
}
.leaderboard-page .leaderboard-updated {
  margin: 0.6rem 0 0;
  color: #93add4;
  font-size: 0.85rem;
}
.leaderboard-page .leaderboard-error-banner {
  margin: 0 0 1rem;
  padding: 0.8rem 1rem;
  border: 1px solid rgba(248, 113, 113, 0.38);
  background: rgba(248, 113, 113, 0.12);
  border-radius: 0.9rem;
  color: #f8fafc;
}
.leaderboard-page .leaderboard-error-banner .error-title {
  margin: 0 0 0.25rem;
  font-size: 0.95rem;
  font-weight: 600;
}
.leaderboard-page .leaderboard-error-banner .error-text {
  margin: 0;
  color: #e2e8f0;
  font-size: 0.88rem;
}
.leaderboard-page .leaderboard-empty-all {
  margin: 0;
  padding: 2.4rem 1rem;
  border-radius: 1rem;
  border: 1px dashed rgba(148, 163, 184, 0.36);
  text-align: center;
  color: #94a3b8;
}
.leaderboard-page .leaderboard-layout {
  display: grid;
  gap: 1rem;
}
.leaderboard-page .leaderboard-main {
  min-width: 0;
}
.leaderboard-page .leaderboard-hall {
  min-width: 0;
  border: 1px solid rgba(148, 163, 184, 0.3);
  border-radius: 1rem;
  padding: 0.95rem;
  background:
    linear-gradient(170deg, rgba(15, 23, 42, 0.8) 0%, rgba(2, 6, 23, 0.78) 100%);
  box-shadow: 0 12px 34px -24px rgba(2, 6, 23, 0.92), inset 0 0 0 1px rgba(125, 211, 252, 0.08);
}
.leaderboard-page .hall-header {
  margin-bottom: 0.85rem;
}
.leaderboard-page .hall-title {
  margin: 0;
  color: #f8fafc;
  font-size: 1.15rem;
  font-weight: 600;
}
.leaderboard-page .hall-subtitle {
  margin: 0.2rem 0 0;
  color: #a7c0e4;
  font-size: 0.85rem;
}
.leaderboard-page .hall-board {
  margin-top: 0.55rem;
  display: inline-flex;
  align-items: center;
  gap: 0.38rem;
  border-radius: 999px;
  border: 1px solid rgba(125, 211, 252, 0.35);
  background: rgba(14, 165, 233, 0.14);
  color: #dff9ff;
  font-size: 0.8rem;
  padding: 0.27rem 0.65rem;
}
.leaderboard-page .hall-board strong {
  color: inherit;
  font-weight: 600;
}
.leaderboard-page .leaderboard-podium {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.7rem;
}
.leaderboard-page .hall-card {
  border-radius: 0.95rem;
  border: 1px solid rgba(148, 163, 184, 0.3);
  background: rgba(15, 23, 42, 0.56);
  padding: 0.72rem;
  min-height: 132px;
  display: flex;
  flex-direction: column;
  gap: 0.48rem;
}
.leaderboard-page .hall-card.hall-card-1 {
  grid-column: 1 / -1;
  min-height: 152px;
  padding: 0.95rem;
}
.leaderboard-page .hall-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.leaderboard-page .hall-rank {
  display: inline-flex;
  align-items: center;
  gap: 0.32rem;
  padding: 0.2rem 0.48rem;
  border-radius: 999px;
  font-size: 0.76rem;
  letter-spacing: 0.03em;
  font-weight: 700;
  text-transform: uppercase;
  text-shadow: 0 1px 0 rgba(2, 6, 23, 0.35);
}
.leaderboard-page .hall-rank em {
  font-style: normal;
  font-size: 0.72rem;
  opacity: 0.92;
}
.leaderboard-page .hall-badge {
  font-size: 0.88rem;
}
.leaderboard-page .hall-tone-gold .hall-rank {
  color: #fef3c7;
  border: 1px solid rgba(245, 158, 11, 0.46);
  background: rgba(245, 158, 11, 0.2);
}
.leaderboard-page .hall-tone-silver .hall-rank {
  color: #dbeafe;
  border: 1px solid rgba(148, 163, 184, 0.5);
  background: rgba(59, 130, 246, 0.15);
}
.leaderboard-page .hall-tone-bronze .hall-rank {
  color: #ffedd5;
  border: 1px solid rgba(251, 146, 60, 0.5);
  background: rgba(251, 146, 60, 0.18);
}
.leaderboard-page .hall-player {
  display: flex;
  align-items: center;
  gap: 0.68rem;
  min-width: 0;
}
.leaderboard-page .hall-avatar {
  width: 52px;
  height: 52px;
  border-radius: 0.7rem;
  object-fit: cover;
  image-rendering: pixelated;
  border: 1px solid rgba(148, 163, 184, 0.38);
  background: rgba(15, 23, 42, 0.72);
  box-shadow: 0 10px 24px -18px rgba(2, 6, 23, 0.92);
}
.leaderboard-page .hall-card.hall-card-1 .hall-avatar {
  width: 64px;
  height: 64px;
  border-color: rgba(245, 158, 11, 0.52);
  box-shadow: 0 14px 28px -18px rgba(234, 179, 8, 0.7);
}
.leaderboard-page .hall-player-text {
  min-width: 0;
}
.leaderboard-page .hall-player-name {
  margin: 0;
  color: #f8fafc;
  font-size: 1rem;
  line-height: 1.2;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.leaderboard-page .hall-card:not(.hall-card-1) .hall-player-name {
  font-size: 0.93rem;
}
.leaderboard-page .hall-player-note {
  margin: 0.18rem 0 0;
  color: #9fc0eb;
  font-size: 0.78rem;
}
.leaderboard-page .hall-score {
  margin-top: auto;
  color: #e2e8f0;
  font-size: 0.96rem;
  font-weight: 600;
}
.leaderboard-page .hall-empty .hall-score {
  color: #93add4;
}
.leaderboard-page .hall-empty .hall-player-name {
  color: #bfd2ec;
}
.leaderboard-page .hall-empty-note {
  margin-top: 0.8rem;
  color: #9fb8d8;
  font-size: 0.83rem;
}
.leaderboard-page .leaderboard-search-wrap {
  margin-bottom: 1rem;
}
.leaderboard-page .leaderboard-search-wrap label {
  display: block;
  margin: 0 0 0.36rem;
  color: #b5c9e5;
  font-size: 0.85rem;
}
.leaderboard-page .leaderboard-search-wrap input {
  width: min(520px, 100%);
  padding: 0.62rem 0.84rem;
  border-radius: 0.85rem;
  border: 1px solid rgba(148, 163, 184, 0.42);
  background: rgba(15, 23, 42, 0.72);
  color: #f8fafc;
}
.leaderboard-page .leaderboard-search-wrap input:focus {
  outline: none;
  border-color: rgba(56, 189, 248, 0.72);
  box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.18);
}
.leaderboard-page .leaderboard-search-wrap input::placeholder {
  color: #9ab3d4;
}
.leaderboard-page .leaderboard-search-hint {
  margin: 0.35rem 0 0;
  color: #9db6d6;
  font-size: 0.76rem;
}
.leaderboard-page .leaderboard-tabs .tabs-container {
  display: flex;
  gap: 0.52rem;
  overflow-x: auto;
  white-space: nowrap;
  -webkit-overflow-scrolling: touch;
  padding-bottom: 0.45rem;
  margin-bottom: 0.8rem;
  border-bottom: 1px solid rgba(148, 163, 184, 0.25);
}
.leaderboard-page .leaderboard-tabs .tab-btn {
  flex-shrink: 0;
  padding: 0.5rem 0.95rem;
  border: 1px solid rgba(148, 163, 184, 0.35);
  border-radius: 0.72rem;
  background: rgba(15, 23, 42, 0.7);
  color: #cfddf2;
  font-size: 0.88rem;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}
.leaderboard-page .leaderboard-tabs .tab-btn:hover {
  color: #e0f2fe;
  border-color: rgba(125, 211, 252, 0.62);
  background: rgba(14, 165, 233, 0.18);
}
.leaderboard-page .leaderboard-tabs .tab-btn.active {
  color: #f8fafc;
  border-color: rgba(14, 165, 233, 0.72);
  background:
    linear-gradient(140deg, rgba(14, 165, 233, 0.34), rgba(59, 130, 246, 0.2));
}
.leaderboard-page .leaderboard-tabs .tab-content {
  display: none;
}
.leaderboard-page .leaderboard-tabs .tab-content.active {
  display: block;
}
.leaderboard-page .table-responsive {
  width: 100%;
  overflow-x: auto;
}
.leaderboard-page .table {
  width: 100%;
  min-width: 0;
  table-layout: fixed;
  border-collapse: collapse;
}
.leaderboard-page .table th,
.leaderboard-page .table td {
  padding: 0.7rem 0.52rem;
}
.leaderboard-page .table th {
  text-align: left;
  color: #aac2e2;
  font-size: 0.82rem;
  letter-spacing: 0.03em;
  border-bottom: 1px solid rgba(148, 163, 184, 0.3);
}
.leaderboard-page .table td {
  color: #e6edf8;
  font-size: 0.9rem;
  border-bottom: 1px solid rgba(148, 163, 184, 0.17);
}
.leaderboard-page .lb-row {
  transition: background-color 0.2s ease;
}
.leaderboard-page .lb-row:hover {
  background: rgba(56, 189, 248, 0.08);
}
.leaderboard-page .lb-row-top-1 {
  background: linear-gradient(90deg, rgba(245, 158, 11, 0.2), rgba(245, 158, 11, 0.06));
}
.leaderboard-page .lb-row-top-2 {
  background: linear-gradient(90deg, rgba(148, 163, 184, 0.16), rgba(59, 130, 246, 0.04));
}
.leaderboard-page .lb-row-top-3 {
  background: linear-gradient(90deg, rgba(251, 146, 60, 0.16), rgba(251, 146, 60, 0.04));
}
.leaderboard-page .table th.lb-col-rank,
.leaderboard-page .table td.lb-col-rank {
  width: 72px;
  font-weight: 700;
}
.leaderboard-page .table .rank-1 {
  color: #fcd34d;
}
.leaderboard-page .table .rank-2 {
  color: #dbeafe;
}
.leaderboard-page .table .rank-3 {
  color: #fdba74;
}
.leaderboard-page .table .player-cell {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  min-width: 0;
}
.leaderboard-page .table .player-link {
  color: inherit;
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 0.55rem;
  min-width: 0;
}
.leaderboard-page .table .player-link:hover {
  color: #a5f3fc;
}
.leaderboard-page .lb-avatar {
  width: 34px;
  height: 34px;
  border-radius: 0.45rem;
  object-fit: cover;
  image-rendering: pixelated;
  background: rgba(15, 23, 42, 0.85);
  border: 1px solid rgba(148, 163, 184, 0.34);
}
.leaderboard-page .lb-player-name {
  max-width: 100%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.leaderboard-page .table th.lb-col-score,
.leaderboard-page .table td.lb-col-score {
  width: 96px;
  text-align: right;
  white-space: nowrap;
  color: #c9d9ee;
  font-variant-numeric: tabular-nums;
}
.leaderboard-page .lb-empty-msg {
  text-align: center;
  color: #9db6d6;
  padding: 1.25rem !important;
}

[data-theme="light"] .leaderboard-page .leaderboard-title {
  color: #0f172a;
}
[data-theme="light"] .leaderboard-page .leaderboard-subtitle,
[data-theme="light"] .leaderboard-page .leaderboard-updated {
  color: #475569;
}
[data-theme="light"] .leaderboard-page .leaderboard-error-banner {
  background: rgba(254, 226, 226, 0.9);
  border-color: rgba(220, 38, 38, 0.35);
  color: #7f1d1d;
}
[data-theme="light"] .leaderboard-page .leaderboard-error-banner .error-text {
  color: #991b1b;
}
[data-theme="light"] .leaderboard-page .leaderboard-empty-all {
  border-color: rgba(148, 163, 184, 0.42);
  color: #475569;
  background: rgba(248, 250, 252, 0.78);
}
[data-theme="light"] .leaderboard-page .leaderboard-hall {
  border-color: rgba(148, 163, 184, 0.38);
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.95), rgba(241, 245, 249, 0.9));
  box-shadow: 0 16px 36px -28px rgba(15, 23, 42, 0.4), inset 0 0 0 1px rgba(59, 130, 246, 0.08);
}
[data-theme="light"] .leaderboard-page .hall-title,
[data-theme="light"] .leaderboard-page .hall-player-name {
  color: #0f172a;
}
[data-theme="light"] .leaderboard-page .hall-subtitle,
[data-theme="light"] .leaderboard-page .hall-player-note,
[data-theme="light"] .leaderboard-page .hall-empty-note {
  color: #475569;
}
[data-theme="light"] .leaderboard-page .hall-board {
  color: #0c4a6e;
  border-color: rgba(14, 165, 233, 0.4);
  background: rgba(224, 242, 254, 0.92);
}
[data-theme="light"] .leaderboard-page .hall-card {
  border-color: rgba(148, 163, 184, 0.38);
  background: rgba(255, 255, 255, 0.87);
}
[data-theme="light"] .leaderboard-page .hall-avatar {
  background: #e2e8f0;
  border-color: rgba(148, 163, 184, 0.44);
}
[data-theme="light"] .leaderboard-page .hall-card.hall-card-1 .hall-avatar {
  border-color: rgba(245, 158, 11, 0.42);
}
[data-theme="light"] .leaderboard-page .hall-score {
  color: #1e293b;
}
[data-theme="light"] .leaderboard-page .hall-rank {
  text-shadow: none;
}
[data-theme="light"] .leaderboard-page .hall-tone-gold .hall-rank {
  color: #7c2d12;
  border-color: rgba(180, 83, 9, 0.44);
  background: rgba(251, 191, 36, 0.36);
}
[data-theme="light"] .leaderboard-page .hall-tone-silver .hall-rank {
  color: #1e3a8a;
  border-color: rgba(59, 130, 246, 0.42);
  background: rgba(191, 219, 254, 0.56);
}
[data-theme="light"] .leaderboard-page .hall-tone-bronze .hall-rank {
  color: #7c2d12;
  border-color: rgba(194, 65, 12, 0.4);
  background: rgba(253, 186, 116, 0.44);
}
[data-theme="light"] .leaderboard-page .leaderboard-search-wrap label,
[data-theme="light"] .leaderboard-page .leaderboard-search-hint {
  color: #475569;
}
[data-theme="light"] .leaderboard-page .leaderboard-search-wrap input {
  background: rgba(255, 255, 255, 0.92);
  color: #0f172a;
  border-color: rgba(148, 163, 184, 0.5);
}
[data-theme="light"] .leaderboard-page .leaderboard-search-wrap input::placeholder {
  color: #64748b;
}
[data-theme="light"] .leaderboard-page .leaderboard-tabs .tabs-container {
  border-bottom-color: rgba(148, 163, 184, 0.4);
}
[data-theme="light"] .leaderboard-page .leaderboard-tabs .tab-btn {
  background: rgba(255, 255, 255, 0.9);
  color: #334155;
  border-color: rgba(148, 163, 184, 0.5);
}
[data-theme="light"] .leaderboard-page .leaderboard-tabs .tab-btn:hover {
  color: #075985;
  border-color: rgba(14, 165, 233, 0.56);
  background: rgba(224, 242, 254, 0.92);
}
[data-theme="light"] .leaderboard-page .leaderboard-tabs .tab-btn.active {
  color: #0c4a6e;
  border-color: rgba(14, 165, 233, 0.62);
  background: linear-gradient(145deg, rgba(186, 230, 253, 0.92), rgba(219, 234, 254, 0.88));
}
[data-theme="light"] .leaderboard-page .table th {
  color: #475569;
  border-bottom-color: rgba(148, 163, 184, 0.3);
}
[data-theme="light"] .leaderboard-page .table td {
  color: #0f172a;
  border-bottom-color: rgba(148, 163, 184, 0.22);
}
[data-theme="light"] .leaderboard-page .lb-row:hover {
  background: rgba(14, 165, 233, 0.09);
}
[data-theme="light"] .leaderboard-page .lb-row-top-1 {
  background: linear-gradient(90deg, rgba(253, 230, 138, 0.42), rgba(254, 240, 138, 0.12));
}
[data-theme="light"] .leaderboard-page .lb-row-top-2 {
  background: linear-gradient(90deg, rgba(226, 232, 240, 0.76), rgba(219, 234, 254, 0.3));
}
[data-theme="light"] .leaderboard-page .lb-row-top-3 {
  background: linear-gradient(90deg, rgba(254, 215, 170, 0.62), rgba(255, 237, 213, 0.22));
}
[data-theme="light"] .leaderboard-page .table .player-link:hover {
  color: #0369a1;
}
[data-theme="light"] .leaderboard-page .lb-avatar {
  background: #e2e8f0;
  border-color: rgba(148, 163, 184, 0.44);
}
[data-theme="light"] .leaderboard-page .table .lb-col-score {
  color: #334155;
}
[data-theme="light"] .leaderboard-page .lb-empty-msg {
  color: #475569;
}

@media (min-width: 768px) and (max-width: 1199px) {
  .leaderboard-page .leaderboard-tabs .tabs-container {
    flex-wrap: wrap;
    overflow-x: visible;
    white-space: normal;
    row-gap: 0.45rem;
  }
}

@media (min-width: 1200px) {
  .leaderboard-page .leaderboard-layout {
    grid-template-columns: minmax(0, 1fr) clamp(320px, 28vw, 420px);
    align-items: start;
    column-gap: 1.15rem;
    row-gap: 1rem;
  }
  .leaderboard-page .leaderboard-main,
  .leaderboard-page .leaderboard-tabs {
    display: contents;
  }
  .leaderboard-page .leaderboard-search-wrap {
    grid-column: 1 / -1;
    margin-bottom: 0.6rem;
  }
  .leaderboard-page .leaderboard-tabs .tabs-container {
    grid-column: 1 / -1;
    flex-wrap: nowrap;
    overflow-x: visible;
    white-space: nowrap;
    gap: 0.38rem;
    row-gap: 0;
    margin-bottom: 0.2rem;
  }
  .leaderboard-page .leaderboard-tabs .tab-btn {
    flex: 1 1 0;
    min-width: 0;
    padding: 0.42rem 0.5rem;
    font-size: 0.8rem;
    text-align: center;
    white-space: nowrap;
  }
  .leaderboard-page .leaderboard-tabs .tab-content.active {
    grid-column: 1 / 2;
  }
  .leaderboard-page .leaderboard-hall {
    grid-column: 2 / 3;
    margin-top: 0;
    position: static;
    top: auto;
  }
}

@media (max-width: 639px) {
  .leaderboard-page .leaderboard-shell {
    padding: 0.95rem;
  }
  .leaderboard-page .leaderboard-tabs .tabs-container {
    overflow-x: auto;
    white-space: nowrap;
    flex-wrap: nowrap;
  }
  .leaderboard-page .leaderboard-tabs .tab-btn {
    padding: 0.46rem 0.74rem;
    font-size: 0.82rem;
  }
  .leaderboard-page .hall-card {
    min-height: 124px;
  }
  .leaderboard-page .hall-card.hall-card-1 {
    min-height: 146px;
  }
  .leaderboard-page .lb-player-name {
    max-width: 120px;
  }
}
</style>

<div class="page-container leaderboard-page">
    <div class="mc-glass-card leaderboard-shell">
        <div class="leaderboard-header">
            <h1 class="text-fusion-pixel text-2xl md:text-3xl leaderboard-title">王座排行榜</h1>
            <p class="leaderboard-subtitle"> · 见证繁星世界的冒险足迹</p>
            <?php if ($lastUpdate !== null && $lastUpdate !== ''): ?>
                <p class="leaderboard-updated">数据最后更新时间：<?php echo htmlspecialchars($lastUpdate, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>

        <?php if ($leaderboardError): ?>
            <div class="leaderboard-error-banner">
                <p class="error-title">排行榜暂不可用</p>
                <p class="error-text">
                    数据表可能尚未创建或未导入到当前数据库。错误信息：
                    <code style="word-break:break-all;"><?php echo htmlspecialchars((string)$leaderboardError, ENT_QUOTES, 'UTF-8'); ?></code>
                </p>
            </div>
        <?php endif; ?>

        <?php if ($normalizedLeaderboards === []): ?>
            <p class="leaderboard-empty-all">暂无排行榜数据，请稍后再来看。</p>
        <?php else: ?>
            <div class="leaderboard-layout">
                <section class="leaderboard-main">
                    <div class="leaderboard-search-wrap">
                        <label for="leaderboard-search-input">玩家名搜索</label>
                        <input type="search" id="leaderboard-search-input" placeholder="输入游戏名" autocomplete="off">
                    </div>

                    <div class="leaderboard-tabs">
                        <div class="tabs-container" role="tablist" aria-label="排行榜分类">
                            <?php foreach ($normalizedLeaderboards as $index => $board): ?>
                                <?php
                                $boardKey = (string)$board['key'];
                                $tabId = 'tab-' . $boardKey;
                                $active = $index === 0;
                                ?>
                                <button
                                    type="button"
                                    id="tab-btn-<?php echo htmlspecialchars($boardKey, ENT_QUOTES, 'UTF-8'); ?>"
                                    class="tab-btn <?php echo $active ? 'active' : ''; ?>"
                                    data-target="<?php echo htmlspecialchars($tabId, ENT_QUOTES, 'UTF-8'); ?>"
                                    role="tab"
                                    aria-controls="<?php echo htmlspecialchars($tabId, ENT_QUOTES, 'UTF-8'); ?>"
                                    aria-selected="<?php echo $active ? 'true' : 'false'; ?>"
                                >
                                    <?php echo htmlspecialchars((string)$board['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <?php foreach ($normalizedLeaderboards as $index => $board): ?>
                            <?php
                            $boardKey = (string)$board['key'];
                            $tabId = 'tab-' . $boardKey;
                            $entries = (array)($board['entries'] ?? []);
                            $unit = (string)($board['unit'] ?? '');
                            $format = (string)($board['format'] ?? 'int');
                            $active = $index === 0;
                            ?>
                            <div
                                id="<?php echo htmlspecialchars($tabId, ENT_QUOTES, 'UTF-8'); ?>"
                                class="tab-content <?php echo $active ? 'active' : ''; ?>"
                                data-board-key="<?php echo htmlspecialchars($boardKey, ENT_QUOTES, 'UTF-8'); ?>"
                                role="tabpanel"
                                aria-labelledby="tab-btn-<?php echo htmlspecialchars($boardKey, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th class="lb-col-rank">排名</th>
                                                <th>玩家</th>
                                                <th class="lb-col-score">分数</th>
                                            </tr>
                                        </thead>
                                        <tbody id="lb-tbody-<?php echo htmlspecialchars($boardKey, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php if ($entries === []): ?>
                                                <tr>
                                                    <td colspan="3" class="lb-empty-msg">暂无上榜玩家，等待第一位冒险者登上王座。</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php
                                                $rank = 1;
                                                foreach ($entries as $entry):
                                                    $username = trim((string)($entry['username'] ?? ''));
                                                    $displayName = $username !== '' ? $username : '未知玩家';
                                                    $avatarUrl = $username !== ''
                                                        ? ('https://minotar.net/helm/' . rawurlencode($username) . '/34.png')
                                                        : $fallbackAvatar;
                                                    $value = $entry['value'] ?? 0;
                                                    $rankClass = $rank <= 3 ? ('rank-' . $rank) : '';
                                                    $rowClass = 'lb-row' . ($rank <= 3 ? (' lb-row-top-' . $rank) : '');
                                                ?>
                                                    <tr class="<?php echo htmlspecialchars($rowClass, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <td class="lb-col-rank <?php echo htmlspecialchars($rankClass, ENT_QUOTES, 'UTF-8'); ?>">#<?php echo (int)$rank; ?></td>
                                                        <td>
                                                            <div class="player-cell">
                                                                <a href="/player?username=<?php echo htmlspecialchars(rawurlencode($displayName), ENT_QUOTES, 'UTF-8'); ?>" class="player-link">
                                                                    <img
                                                                        class="lb-avatar"
                                                                        src="<?php echo htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                                                        alt="<?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?> 的头像"
                                                                        width="34"
                                                                        height="34"
                                                                        onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($fallbackAvatar, ENT_QUOTES, 'UTF-8'); ?>';"
                                                                    >
                                                                    <span class="lb-player-name"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                                                                </a>
                                                            </div>
                                                        </td>
                                                        <td class="lb-col-score">
                                                            <?php echo htmlspecialchars($formatScore($format, $value), ENT_QUOTES, 'UTF-8'); ?><?php if ($unit !== ''): ?><?php echo htmlspecialchars($unit, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php
                                                    $rank++;
                                                endforeach;
                                                ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <aside class="leaderboard-hall" aria-live="polite">
                    <div class="hall-header">
                        <h2 class="hall-title">荣耀殿堂</h2>
                        <p class="hall-subtitle">当前分类前三名</p>
                        <p class="hall-board">当前榜单：<strong id="leaderboard-hall-board-title"><?php echo htmlspecialchars((string)$firstBoardTitle, ENT_QUOTES, 'UTF-8'); ?></strong></p>
                    </div>
                    <div id="leaderboard-podium" class="leaderboard-podium"></div>
                    <p id="leaderboard-hall-empty-note" class="hall-empty-note" style="display:none;">暂无上榜玩家，等待第一位冒险者登上王座。</p>
                </aside>
            </div>

            <script>
            (function() {
                var container = document.querySelector('.leaderboard-page .leaderboard-tabs');
                if (!container) {
                    return;
                }

                var fallbackAvatar = <?php echo json_encode($fallbackAvatar, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                var boards = <?php echo $leaderboardsJson; ?>;
                var buttons = container.querySelectorAll('.tab-btn');
                var contents = container.querySelectorAll('.tab-content');
                var hallPodium = document.getElementById('leaderboard-podium');
                var hallBoardTitle = document.getElementById('leaderboard-hall-board-title');
                var hallEmptyNote = document.getElementById('leaderboard-hall-empty-note');
                var searchInput = document.getElementById('leaderboard-search-input');
                var searchTimer = null;

                var boardsByKey = {};
                var boardsMeta = [];

                boards.forEach(function(board) {
                    if (!board || !board.key) {
                        return;
                    }
                    boardsByKey[board.key] = board;
                    boardsMeta.push({
                        key: board.key,
                        title: board.title || '',
                        unit: board.unit || '',
                        format: board.format || 'int'
                    });
                });

                function getActiveBoardKey() {
                    var active = container.querySelector('.tab-content.active');
                    return active ? (active.getAttribute('data-board-key') || '') : '';
                }

                function metaForKey(key) {
                    for (var i = 0; i < boardsMeta.length; i++) {
                        if (boardsMeta[i].key === key) {
                            return boardsMeta[i];
                        }
                    }
                    return { key: key, title: '', unit: '', format: 'int' };
                }

                function normalizeNumber(value) {
                    var n = Number(value);
                    if (!Number.isFinite(n)) {
                        return 0;
                    }
                    return n;
                }

                function formatValue(fmt, value, unit) {
                    var n = normalizeNumber(value);
                    var suffix = unit ? String(unit) : '';
                    if (fmt === 'float1') {
                        return n.toLocaleString('zh-CN', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + suffix;
                    }
                    if (fmt === 'float2') {
                        return n.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + suffix;
                    }
                    return Math.round(n).toLocaleString('zh-CN') + suffix;
                }

                function escHtml(value) {
                    return String(value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                }

                function buildAvatarUrl(username, size) {
                    if (!username) {
                        return fallbackAvatar;
                    }
                    return 'https://minotar.net/helm/' + encodeURIComponent(username) + '/' + size + '.png';
                }

                function slotNote(rank) {
                    if (rank === 1) {
                        return '王座保持者';
                    }
                    if (rank === 2) {
                        return '稳定追赶';
                    }
                    return '本榜新星';
                }

                function slotBadge(rank) {
                    if (rank === 1) {
                        return '&#9819;';
                    }
                    if (rank === 2) {
                        return '&#9733;';
                    }
                    return '&#10038;';
                }

                function slotRoman(rank) {
                    if (rank === 1) {
                        return 'I';
                    }
                    if (rank === 2) {
                        return 'II';
                    }
                    return 'III';
                }

                function slotTone(rank) {
                    if (rank === 1) {
                        return 'hall-tone-gold';
                    }
                    if (rank === 2) {
                        return 'hall-tone-silver';
                    }
                    return 'hall-tone-bronze';
                }

                function buildHallCard(entry, rank, boardMeta) {
                    var card = document.createElement('article');
                    var tone = slotTone(rank);
                    card.className = 'hall-card hall-card-' + rank + ' ' + tone;

                    if (!entry || typeof entry !== 'object') {
                        card.className += ' hall-empty';
                        card.innerHTML = ''
                            + '<div class="hall-card-top">'
                            + '  <span class="hall-rank"><span class="hall-badge">' + slotBadge(rank) + '</span>#' + rank + '<em>' + slotRoman(rank) + '</em></span>'
                            + '</div>'
                            + '<div class="hall-player">'
                            + '  <img class="hall-avatar" src="' + escHtml(fallbackAvatar) + '" alt="" width="64" height="64">'
                            + '  <div class="hall-player-text">'
                            + '    <p class="hall-player-name">席位待定</p>'
                            + '    <p class="hall-player-note">' + slotNote(rank) + '</p>'
                            + '  </div>'
                            + '</div>'
                            + '<p class="hall-score">暂无数据</p>';
                        return card;
                    }

                    var username = String(entry.username || '').trim();
                    if (!username) {
                        username = '未知玩家';
                    }
                    var avatarSize = rank === 1 ? 64 : 52;
                    var avatarUrl = buildAvatarUrl(username, avatarSize);
                    var valueText = formatValue(boardMeta.format, entry.value, boardMeta.unit || '');

                    card.innerHTML = ''
                        + '<div class="hall-card-top">'
                        + '  <span class="hall-rank"><span class="hall-badge">' + slotBadge(rank) + '</span>#' + rank + '<em>' + slotRoman(rank) + '</em></span>'
                        + '</div>'
                        + '<div class="hall-player">'
                        + '  <img class="hall-avatar" src="' + escHtml(avatarUrl) + '" alt="' + escHtml(username) + ' 的头像" width="' + avatarSize + '" height="' + avatarSize + '" onerror="this.onerror=null;this.src=\'' + escHtml(fallbackAvatar) + '\'">'
                        + '  <div class="hall-player-text">'
                        + '    <p class="hall-player-name">' + escHtml(username) + '</p>'
                        + '    <p class="hall-player-note">' + slotNote(rank) + '</p>'
                        + '  </div>'
                        + '</div>'
                        + '<p class="hall-score">' + escHtml(valueText) + '</p>';

                    return card;
                }

                function updateHall(boardKey) {
                    if (!hallPodium) {
                        return;
                    }

                    var board = boardsByKey[boardKey] || null;
                    var boardMeta = metaForKey(boardKey);
                    var entries = board && Array.isArray(board.entries) ? board.entries : [];

                    hallPodium.innerHTML = '';
                    for (var i = 0; i < 3; i++) {
                        hallPodium.appendChild(buildHallCard(entries[i] || null, i + 1, boardMeta));
                    }

                    if (hallBoardTitle) {
                        hallBoardTitle.textContent = boardMeta.title || '当前分类';
                    }
                    if (hallEmptyNote) {
                        hallEmptyNote.style.display = entries.length > 0 ? 'none' : 'block';
                    }
                }

                function rowToneClass(rank) {
                    if (rank <= 0) {
                        return 'lb-row';
                    }
                    if (rank <= 3) {
                        return 'lb-row lb-row-top-' + rank;
                    }
                    return 'lb-row';
                }

                function buildRow(entry, rank, boardKey) {
                    var boardMeta = metaForKey(boardKey);
                    var username = '';
                    if (entry && typeof entry.username === 'string') {
                        username = entry.username.trim();
                    }
                    if (!username) {
                        username = '未知玩家';
                    }

                    var displayRank = Number(entry && entry.rank);
                    if (!Number.isFinite(displayRank) || displayRank < 1) {
                        displayRank = rank;
                    }
                    displayRank = Math.round(displayRank);

                    var scoreText = formatValue(boardMeta.format, entry ? entry.value : 0, boardMeta.unit || '');
                    var avatarUrl = buildAvatarUrl(username, 34);
                    var rankClass = displayRank <= 3 ? ('rank-' + displayRank) : '';
                    var tr = document.createElement('tr');
                    tr.className = rowToneClass(displayRank);
                    tr.innerHTML = ''
                        + '<td class="lb-col-rank ' + escHtml(rankClass) + '">#' + displayRank + '</td>'
                        + '<td>'
                        + '  <div class="player-cell">'
                        + '    <a href="/player?username=' + encodeURIComponent(username) + '" class="player-link">'
                        + '      <img class="lb-avatar" src="' + escHtml(avatarUrl) + '" alt="' + escHtml(username) + ' 的头像" width="34" height="34" onerror="this.onerror=null;this.src=\'' + escHtml(fallbackAvatar) + '\'">'
                        + '      <span class="lb-player-name">' + escHtml(username) + '</span>'
                        + '    </a>'
                        + '  </div>'
                        + '</td>'
                        + '<td class="lb-col-score">' + escHtml(scoreText) + '</td>';

                    return tr;
                }

                function snapshotDefaultBodies() {
                    var map = {};
                    boardsMeta.forEach(function(board) {
                        var tbody = document.getElementById('lb-tbody-' + board.key);
                        if (tbody) {
                            map[board.key] = tbody.innerHTML;
                        }
                    });
                    return map;
                }

                var defaultHtml = snapshotDefaultBodies();

                function restoreDefaultRows(boardKey) {
                    if (!boardKey) {
                        return;
                    }
                    var tbody = document.getElementById('lb-tbody-' + boardKey);
                    if (!tbody) {
                        return;
                    }
                    if (Object.prototype.hasOwnProperty.call(defaultHtml, boardKey)) {
                        tbody.innerHTML = defaultHtml[boardKey];
                    }
                }

                function runSearch(query) {
                    var boardKey = getActiveBoardKey();
                    if (!boardKey) {
                        return;
                    }
                    var tbody = document.getElementById('lb-tbody-' + boardKey);
                    if (!tbody) {
                        return;
                    }

                    var text = query && query.trim ? query.trim() : '';
                    if (!text) {
                        restoreDefaultRows(boardKey);
                        return;
                    }

                    fetch('/api/leaderboard/search?board=' + encodeURIComponent(boardKey) + '&q=' + encodeURIComponent(text))
                        .then(function(resp) { return resp.json(); })
                        .then(function(data) {
                            if (!data || !data.success || !Array.isArray(data.results)) {
                                return;
                            }

                            tbody.innerHTML = '';
                            if (data.results.length === 0) {
                                var emptyRow = document.createElement('tr');
                                emptyRow.innerHTML = '<td colspan="3" class="lb-empty-msg">无匹配玩家</td>';
                                tbody.appendChild(emptyRow);
                                return;
                            }

                            data.results.forEach(function(entry, idx) {
                                tbody.appendChild(buildRow(entry, idx + 1, boardKey));
                            });
                        })
                        .catch(function() {});
                }

                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        clearTimeout(searchTimer);
                        var value = searchInput.value;
                        searchTimer = setTimeout(function() {
                            runSearch(value);
                        }, 280);
                    });
                }

                buttons.forEach(function(button) {
                    button.addEventListener('click', function() {
                        var targetId = button.getAttribute('data-target');
                        if (!targetId) {
                            return;
                        }

                        buttons.forEach(function(btn) {
                            btn.classList.remove('active');
                            btn.setAttribute('aria-selected', 'false');
                        });
                        contents.forEach(function(content) {
                            content.classList.remove('active');
                        });

                        button.classList.add('active');
                        button.setAttribute('aria-selected', 'true');

                        var target = document.getElementById(targetId);
                        if (target) {
                            target.classList.add('active');
                        }

                        var nextKey = target ? (target.getAttribute('data-board-key') || '') : '';
                        updateHall(nextKey);

                        if (searchInput && searchInput.value && searchInput.value.trim()) {
                            runSearch(searchInput.value);
                        } else {
                            restoreDefaultRows(nextKey);
                        }
                    });
                });

                updateHall(getActiveBoardKey());
            })();
            </script>
        <?php endif; ?>
    </div>
</div>
