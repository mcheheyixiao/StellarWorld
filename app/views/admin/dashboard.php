<?php
$csrfToken = htmlspecialchars(
    $_SESSION['csrf_token'] ?? '',
    ENT_QUOTES,
    'UTF-8'
);
$adminRealtimePanelScriptUrl = '/scripts/admin-realtime-panel.js';
$adminRedeemScriptUrl = '/scripts/admin-redeem.js';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Lexend:wght@500;600;700&family=Noto+Sans+SC:wght@400;500;600;700&display=swap');
:root {
    --ta-surface-shell: rgba(2, 6, 23, 0.82);
    --ta-surface-frame: rgba(15, 23, 42, 0.76);
    --ta-surface-sidebar: rgba(2, 6, 23, 0.84);
    --ta-surface-header: rgba(15, 23, 42, 0.86);
    --ta-surface-card: rgba(15, 23, 42, 0.84);
    --ta-surface-input: rgba(15, 23, 42, 0.9);
    --ta-border: rgba(148, 163, 184, 0.26);
    --ta-border-strong: rgba(148, 163, 184, 0.42);
    --ta-shadow: 0 24px 56px -30px rgba(2, 6, 23, 0.72);
    --ta-text-strong: #f8fafc;
    --ta-text-body: #e2e8f0;
    --ta-text-muted: #cbd5e1;
    --ta-header-title: #e2e8f0;
    --ta-user-name: #e2e8f0;
    --ta-toggle-bg: rgba(30, 41, 59, 0.82);
    --ta-toggle-border: rgba(148, 163, 184, 0.42);
    --ta-toggle-icon: #67e8f9;
    --ta-accent: #67e8f9;
    --ta-accent-bg: rgba(14, 165, 233, 0.22);
    --ta-accent-border: rgba(34, 211, 238, 0.48);
    --ta-accent-soft-bg: rgba(14, 165, 233, 0.14);
    --ta-btn-primary-bg: rgba(14, 165, 233, 0.28);
    --ta-btn-primary-border: rgba(34, 211, 238, 0.42);
    --ta-btn-primary-text: #e0f2fe;
    --ta-danger-border: rgba(248, 113, 113, 0.45);
    --ta-danger-bg: rgba(248, 113, 113, 0.12);
    --ta-danger-text: #fecaca;
}
[data-theme="light"] {
    --ta-surface-shell: rgba(241, 245, 249, 0.94);
    --ta-surface-frame: rgba(241, 245, 249, 0.92);
    --ta-surface-sidebar: rgba(248, 250, 252, 0.96);
    --ta-surface-header: rgba(255, 255, 255, 0.97);
    --ta-surface-card: rgba(255, 255, 255, 0.96);
    --ta-surface-input: rgba(255, 255, 255, 0.99);
    --ta-border: rgba(148, 163, 184, 0.35);
    --ta-border-strong: rgba(148, 163, 184, 0.48);
    --ta-shadow: 0 16px 36px -24px rgba(15, 23, 42, 0.2);
    --ta-text-strong: #0f172a;
    --ta-text-body: #1e293b;
    --ta-text-muted: #475569;
    --ta-header-title: #0f172a;
    --ta-user-name: #1e293b;
    --ta-toggle-bg: rgba(241, 245, 249, 0.95);
    --ta-toggle-border: rgba(148, 163, 184, 0.44);
    --ta-toggle-icon: #0e7490;
    --ta-accent: #0e7490;
    --ta-accent-bg: rgba(14, 165, 233, 0.16);
    --ta-accent-border: rgba(14, 116, 144, 0.48);
    --ta-accent-soft-bg: rgba(14, 165, 233, 0.1);
    --ta-btn-primary-bg: rgba(14, 165, 233, 0.16);
    --ta-btn-primary-border: rgba(14, 116, 144, 0.45);
    --ta-btn-primary-text: #0c4a6e;
    --ta-danger-border: rgba(220, 38, 38, 0.34);
    --ta-danger-bg: rgba(254, 226, 226, 0.9);
    --ta-danger-text: #991b1b;
}
.ta-admin-layout {
    display: flex;
    height: calc(100vh - 1.5rem);
    border-radius: 10px;
    overflow: hidden;
}
.ta-admin-frame {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
    background: var(--ta-surface-frame);
    transition: background-color 0.22s ease, color 0.22s ease;
}
.ta-admin-main {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
    color: var(--ta-text-body);
}
@media (min-width: 768px) {
    .ta-admin-main {
        padding: 1.5rem;
    }
}
.ta-sidebar-modern {
    width: 18rem;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    padding: 1.5rem 1rem;
    color: #fff;
    background: linear-gradient(to bottom, #4f46e5, #3b82f6);
}
.ta-sidebar-menu {
    flex: 1;
    overflow-y: auto;
}
.ta-sidebar-item {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border: 0;
    border-radius: 0.6rem;
    background: transparent;
    color: rgba(255, 255, 255, 0.92);
    font-size: 0.9rem;
    font-weight: 500;
    text-align: left;
    padding: 0.72rem 0.88rem;
    cursor: pointer;
    transition: background-color 0.18s ease;
}
.ta-sidebar-item:hover {
    background: rgba(255, 255, 255, 0.15);
}
.ta-sidebar-item.active {
    background: rgba(255, 255, 255, 0.2);
}
/* unused (no markup or JS references as of 2026-04-24):
.ta-sidebar-group {
    margin: 0.35rem 0 0.7rem;
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 0.65rem;
    padding: 0.45rem 0.45rem 0.35rem;
    background: rgba(2, 6, 23, 0.14);
}
.ta-sidebar-group-label {
    margin: 0 0 0.35rem;
    padding: 0 0.35rem;
    font-size: 0.74rem;
    letter-spacing: 0.04em;
    color: rgba(255, 255, 255, 0.72);
}
*/
.ta-sidebar-subitem {
    font-size: 0.83rem;
    padding-left: 0.6rem;
}
.ta-admin-header-modern {
    position: sticky;
    top: 0;
    z-index: 30;
    background: var(--ta-surface-header);
    border-bottom: 1px solid var(--ta-border);
    box-shadow: 0 8px 22px -18px rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(8px);
}
.ta-admin-header-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.8rem 1rem;
}
@media (min-width: 768px) {
    .ta-admin-header-inner {
        padding: 0.85rem 1.5rem;
    }
}
.ta-admin-header-spacer {
    flex: 1;
}
.ta-admin-header-title {
    color: var(--ta-header-title);
}
.ta-admin-header-actions {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}
.ta-admin-theme-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border: 1px solid var(--ta-toggle-border);
    border-radius: 0.62rem;
    background: var(--ta-toggle-bg);
    color: var(--ta-toggle-icon);
    padding: 0.45rem 0.62rem;
    transition: border-color 0.18s ease, background-color 0.18s ease, color 0.18s ease;
}
.ta-admin-theme-toggle:hover {
    border-color: var(--ta-accent-border);
    color: var(--ta-accent);
}
.ta-admin-theme-toggle .ta-theme-icon {
    width: 1rem;
    height: 1rem;
}
.ta-admin-theme-toggle .ta-theme-icon-sun {
    display: inline-block;
}
.ta-admin-theme-toggle .ta-theme-icon-moon {
    display: none;
}
[data-theme="light"] .ta-admin-theme-toggle .ta-theme-icon-sun {
    display: none;
}
[data-theme="light"] .ta-admin-theme-toggle .ta-theme-icon-moon {
    display: inline-block;
}
.ta-admin-theme-toggle-label {
    font-size: 0.78rem;
    font-weight: 600;
    line-height: 1;
}
.ta-admin-user-trigger {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border: 1px solid var(--ta-border);
    border-radius: 0.6rem;
    background: var(--ta-surface-card);
    color: var(--ta-user-name);
    padding: 0.45rem 0.7rem;
    transition: border-color 0.18s ease, background-color 0.18s ease;
}
.ta-admin-user-trigger:hover {
    border-color: var(--ta-accent-border);
}
.ta-admin-user-avatar {
    width: 2rem;
    height: 2rem;
    border-radius: 999px;
    border: 1px solid var(--ta-border-strong);
    object-fit: cover;
}
.ta-admin-user-name {
    max-width: 8rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--ta-user-name);
}
.ta-admin-user-icon {
    width: 1rem;
    height: 1rem;
    color: var(--ta-text-muted);
}
.ta-admin-user-dropdown {
    border: 1px solid var(--ta-border);
    border-radius: 0.78rem;
    background: var(--ta-surface-card);
    box-shadow: var(--ta-shadow);
    padding: 0.4rem;
}
.ta-admin-user-link {
    display: block;
    border-radius: 0.55rem;
    padding: 0.45rem 0.65rem;
    color: var(--ta-text-body);
    font-size: 0.86rem;
    text-decoration: none;
}
.ta-admin-user-link:hover {
    background: var(--ta-accent-soft-bg);
    color: var(--ta-accent);
}
/* unused (no markup or JS references as of 2026-04-24):
.ta-admin-user-link-btn {
    width: 100%;
    border: 0;
    background: transparent;
    text-align: left;
    cursor: pointer;
}
*/
@media (max-width: 1023px) {
    .ta-sidebar-modern {
        display: none;
    }
}
.ta-card {
    border: 1px solid var(--ta-border);
    border-radius: 0.9rem;
    background: var(--ta-surface-card);
    box-shadow: var(--ta-shadow);
    padding: 1rem;
    color: var(--ta-text-body);
}
.ta-card h1,
.ta-card h2,
.ta-card h3 {
    color: var(--ta-text-strong);
}
.ta-card p,
.ta-card label,
.ta-card span,
.ta-card div,
.ta-table td,
.ta-table th {
    color: inherit;
}
.ta-card p {
    color: var(--ta-text-body);
}
.ta-help-text {
    color: var(--ta-text-muted);
}
.ta-table-wrap {
    overflow-x: auto;
    width: 100%;
}
.ta-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
    margin-bottom: 1rem;
}
.ta-table-wide {
    min-width: 900px;
}
.ta-table-team {
    min-width: 520px;
}
.ta-table th,
.ta-table td {
    border-bottom: 1px solid var(--ta-border);
    padding: 0.55rem 0.5rem;
    text-align: left;
    vertical-align: top;
}
.ta-table th {
    color: var(--ta-text-muted);
    font-weight: 600;
}
.ta-table td {
    color: var(--ta-text-body);
}
.ta-table th:last-child,
.ta-table td:last-child {
    text-align: right;
}
.ta-feedback-card {
    display: grid;
    gap: 1rem;
}
.ta-feedback-alert {
    border-radius: 0.7rem;
    border: 1px solid var(--ta-border-strong);
    padding: 0.65rem 0.8rem;
    font-size: 0.86rem;
}
.ta-feedback-alert--success {
    border-color: rgba(34, 197, 94, 0.45);
    background: rgba(34, 197, 94, 0.14);
    color: #86efac;
}
.ta-feedback-alert--error {
    border-color: rgba(248, 113, 113, 0.45);
    background: rgba(248, 113, 113, 0.16);
    color: #fecaca;
}
[data-theme="light"] .ta-feedback-alert--success {
    color: #166534;
    background: rgba(187, 247, 208, 0.7);
}
[data-theme="light"] .ta-feedback-alert--error {
    color: #991b1b;
    background: rgba(254, 226, 226, 0.8);
}
.ta-feedback-filters {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(1, minmax(0, 1fr));
}
@media (min-width: 768px) {
    .ta-feedback-filters {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (min-width: 1280px) {
    .ta-feedback-filters {
        grid-template-columns: minmax(0, 1.5fr) repeat(2, minmax(0, 1fr)) auto;
        align-items: end;
    }
}
.ta-feedback-filter-actions {
    display: flex;
    gap: 0.6rem;
    flex-wrap: wrap;
}
.ta-feedback-table th:last-child,
.ta-feedback-table td:last-child {
    text-align: left;
}
.ta-feedback-status {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    border: 1px solid var(--ta-border-strong);
    padding: 0.2rem 0.6rem;
    font-size: 0.76rem;
    line-height: 1;
    white-space: nowrap;
}
.ta-feedback-status--pending {
    color: #fbbf24;
    border-color: rgba(251, 191, 36, 0.45);
    background: rgba(251, 191, 36, 0.12);
}
.ta-feedback-status--reviewing {
    color: #67e8f9;
    border-color: rgba(103, 232, 249, 0.45);
    background: rgba(103, 232, 249, 0.12);
}
.ta-feedback-status--need-more-info {
    color: #fb7185;
    border-color: rgba(251, 113, 133, 0.45);
    background: rgba(251, 113, 133, 0.12);
}
.ta-feedback-status--resolved {
    color: #4ade80;
    border-color: rgba(74, 222, 128, 0.45);
    background: rgba(74, 222, 128, 0.12);
}
.ta-feedback-status--rejected {
    color: #f87171;
    border-color: rgba(248, 113, 113, 0.45);
    background: rgba(248, 113, 113, 0.12);
}
[data-theme="light"] .ta-feedback-status--pending {
    color: #92400e;
}
[data-theme="light"] .ta-feedback-status--reviewing {
    color: #0f766e;
}
[data-theme="light"] .ta-feedback-status--need-more-info {
    color: #9f1239;
}
[data-theme="light"] .ta-feedback-status--resolved {
    color: #166534;
}
[data-theme="light"] .ta-feedback-status--rejected {
    color: #b91c1c;
}
.ta-feedback-cell-actions {
    min-width: auto;
    width: 1%;
    white-space: nowrap;
}
.ta-feedback-toggle-btn {
    white-space: nowrap;
}
.ta-feedback-detail-row td {
    padding: 0;
    background: transparent !important;
}
.ta-feedback-detail-panel {
    margin: 0.75rem 0 1rem;
    border: 1px solid var(--ta-border);
    border-radius: 0.9rem;
    background: var(--ta-surface-card);
    box-shadow: 0 16px 36px -28px rgba(15, 23, 42, 0.32);
    padding: 1rem;
}
.ta-feedback-detail-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(280px, 360px);
    gap: 1rem;
}
.ta-feedback-detail-main {
    min-width: 0;
    display: grid;
    gap: 0.85rem;
}
.ta-feedback-detail-action {
    min-width: 0;
}
.ta-feedback-action-card {
    border: 1px solid var(--ta-border);
    border-radius: 0.75rem;
    background: rgba(148, 163, 184, 0.08);
    padding: 0.8rem;
}
[data-theme="light"] .ta-feedback-action-card {
    background: rgba(248, 250, 252, 0.88);
}
.ta-feedback-meta-grid {
    display: grid;
    gap: 0.6rem 0.75rem;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}
.ta-feedback-meta-item {
    display: grid;
    gap: 0.22rem;
}
.ta-feedback-meta-item strong {
    color: var(--ta-text-strong);
    font-size: 0.9rem;
    line-height: 1.4;
}
[data-theme="light"] .ta-feedback-meta-item strong {
    color: #0f172a;
}
.ta-feedback-content {
    border-radius: 0.55rem;
    border: 1px solid var(--ta-border);
    background: var(--ta-surface-input);
    padding: 0.55rem 0.6rem;
    white-space: pre-wrap;
    line-height: 1.45;
}
.ta-feedback-attachments {
    margin-top: 0.5rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem;
}
.ta-feedback-attachments a {
    display: inline-flex;
    border: 1px solid var(--ta-border);
    border-radius: 0.55rem;
    overflow: hidden;
    width: 84px;
    height: 84px;
}
.ta-feedback-attachments img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.ta-feedback-update-form {
    margin-top: 0.75rem;
    display: grid;
    gap: 0.45rem;
}
.ta-feedback-update-form label {
    font-size: 0.8rem;
    color: var(--ta-text-muted);
}
.ta-feedback-update-form select,
.ta-feedback-update-form textarea {
    width: 100%;
}
.ta-feedback-update-form textarea {
    min-height: 88px !important;
}
@media (max-width: 1023px) {
    .ta-feedback-detail-grid {
        grid-template-columns: 1fr;
    }
}
.ta-gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1rem;
}
.ta-gallery-item {
    padding: 0.9rem;
}
.ta-gallery-image-wrap {
    border-radius: 0.65rem;
    overflow: hidden;
    margin-bottom: 0.75rem;
}
.ta-file-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: 0.5rem;
}
.ta-stack {
    display: flex;
    flex-direction: column;
}
.ta-stack-sm {
    gap: 0.3rem;
}
.ta-stack-limit {
    max-width: 180px;
}
.ta-action-stack {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    align-items: flex-end;
}
.ta-action-stack form {
    width: 100%;
}
.ta-inline-options {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    flex-wrap: wrap;
}
.ta-help-text {
    font-size: 0.88rem;
    color: var(--ta-text-muted);
}
.ta-hidden-input {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    opacity: 0;
    pointer-events: none;
}
.ta-datetime-input {
    display: none;
}
.ta-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.6rem;
    border: 1px solid var(--ta-border-strong);
    padding: 0.38rem 0.72rem;
    font-size: 0.84rem;
    background: var(--ta-surface-input);
    color: var(--ta-text-strong);
    cursor: pointer;
    text-decoration: none;
    transition: border-color 0.18s ease, background-color 0.18s ease, color 0.18s ease;
}
.ta-btn:hover {
    border-color: var(--ta-accent-border);
    background: var(--ta-accent-soft-bg);
    color: var(--ta-accent);
}
.ta-btn-secondary {
    background: var(--ta-surface-input);
    color: var(--ta-text-strong);
}
.ta-btn-primary {
    background: var(--ta-btn-primary-bg);
    border-color: var(--ta-btn-primary-border);
    color: var(--ta-btn-primary-text);
}
.ta-btn-primary:hover {
    background: var(--ta-accent-bg);
    color: var(--ta-accent);
}
.ta-kpi-shell,
.ta-kpi-card {
    border: 1px solid var(--ta-border);
    background: var(--ta-surface-card) !important;
    box-shadow: var(--ta-shadow);
}
.ta-kpi-card {
    border-radius: 0.8rem;
}
.ta-kpi-shell h1 {
    color: var(--ta-text-strong);
}
.ta-kpi-shell > p {
    color: var(--ta-text-body);
}
.ta-kpi-label {
    color: var(--ta-text-muted) !important;
}
.ta-kpi-value {
    color: var(--ta-text-strong) !important;
}
.ta-kpi-icon {
    background: var(--ta-accent-soft-bg) !important;
    color: var(--ta-accent) !important;
}
@media (max-width: 640px) {
    .ta-admin-theme-toggle-label {
        display: none;
    }
}
.ta-tab-content.tab-hidden {
    display: none !important;
}
.ta-checkin-page .ta-card {
    border-radius: 0.95rem;
}
.ta-checkin-weekdays {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.45rem;
    margin-bottom: 0.55rem;
}
.ta-checkin-weekdays span {
    text-align: center;
    font-size: 0.78rem;
    color: var(--ta-text-muted);
}
.ta-checkin-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.55rem;
}
.ta-checkin-day {
    min-height: 92px;
    border: 1px solid rgba(148, 163, 184, 0.34);
    border-radius: 0.72rem;
    padding: 0.6rem 0.52rem;
    background: rgba(148, 163, 184, 0.08);
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    text-align: left;
    transition: all 0.2s ease;
}
.ta-checkin-day:hover {
    transform: scale(1.02);
}
.ta-checkin-day--placeholder {
    pointer-events: none;
    min-height: 92px;
    border-style: dashed;
    opacity: 0.45;
}
.ta-checkin-day--modified {
    border-color: rgba(59, 130, 246, 0.78);
    box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.25);
}
.ta-checkin-day--today {
    border-color: rgba(56, 189, 248, 0.95);
    box-shadow: 0 0 0 1px rgba(56, 189, 248, 0.35), 0 0 24px -12px rgba(56, 189, 248, 0.92);
}
.ta-checkin-day--selected {
    background: var(--ta-accent-soft-bg);
}
.ta-checkin-day-number {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--ta-text-strong);
}
.ta-checkin-day-line {
    font-size: 0.78rem;
    color: var(--ta-text-body);
}
.ta-checkin-preview-card {
    height: fit-content;
    position: sticky;
    top: 1rem;
}
.ta-checkin-preview-line {
    font-size: 0.85rem;
    color: var(--ta-text-body);
}
.ta-checkin-json-preview {
    background: var(--ta-surface-input);
    border: 1px solid var(--ta-border-strong);
    border-radius: 0.72rem;
    padding: 0.7rem;
    max-height: 310px;
    overflow: auto;
    font-size: 0.78rem;
    line-height: 1.45;
}
.ta-checkin-modal {
    position: fixed;
    inset: 0;
    z-index: 60;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.ta-checkin-modal.hidden {
    display: none !important;
}
.ta-checkin-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(2, 6, 23, 0.62);
    backdrop-filter: blur(3px);
}
.ta-checkin-modal-panel {
    position: relative;
    width: min(760px, 100%);
    max-height: calc(100vh - 2rem);
    overflow-y: auto;
    z-index: 1;
}
.ta-checkin-input-card {
    border: 1px solid var(--ta-border);
    border-radius: 0.7rem;
    background: rgba(148, 163, 184, 0.08);
    padding: 0.55rem;
    display: grid;
    gap: 0.45rem;
}
.ta-checkin-scope-option {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    border: 1px solid var(--ta-border);
    border-radius: 0.6rem;
    padding: 0.55rem 0.65rem;
    background: rgba(148, 163, 184, 0.08);
}
.ta-checkin-status-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.2rem 0.6rem;
    font-size: 0.76rem;
    border: 1px solid var(--ta-border-strong);
}
.ta-checkin-status-pill--done {
    color: var(--ta-state-success-text);
    border-color: var(--ta-state-success-border);
    background: var(--ta-state-success-bg);
}
.ta-checkin-status-pill--pending {
    color: var(--ta-state-warning-text);
    border-color: var(--ta-state-warning-border);
    background: var(--ta-state-warning-bg);
}
.ta-checkin-stat-card {
    min-height: 115px;
}
.ta-checkin-stat-value {
    margin-top: 0.45rem;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--ta-text-strong);
}
.ta-checkin-chart-surface {
    min-height: 220px;
    border: 1px dashed var(--ta-border-strong);
    border-radius: 0.8rem;
    padding: 0.85rem;
    background: linear-gradient(180deg, rgba(14, 165, 233, 0.1), rgba(148, 163, 184, 0.04));
}
.ta-checkin-line-chart {
    position: relative;
    height: 180px;
}
.ta-checkin-line-chart::before {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    top: 0;
    bottom: 0;
    border-bottom: 2px solid rgba(56, 189, 248, 0.4);
}
.ta-checkin-line-dot {
    position: absolute;
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: rgba(56, 189, 248, 0.95);
    box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2);
}
.ta-checkin-line-dot:nth-child(1) { left: 5%; }
.ta-checkin-line-dot:nth-child(2) { left: 20%; }
.ta-checkin-line-dot:nth-child(3) { left: 35%; }
.ta-checkin-line-dot:nth-child(4) { left: 50%; }
.ta-checkin-line-dot:nth-child(5) { left: 65%; }
.ta-checkin-line-dot:nth-child(6) { left: 80%; }
.ta-checkin-line-dot:nth-child(7) { left: 95%; }
.ta-checkin-bar-chart {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 0.5rem;
}
.ta-checkin-bar-col {
    flex: 1;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
}
.ta-checkin-bar {
    display: block;
    width: 100%;
    border-radius: 0.5rem 0.5rem 0.35rem 0.35rem;
    background: linear-gradient(180deg, rgba(59, 130, 246, 0.95), rgba(14, 165, 233, 0.7));
}
.ta-checkin-bar-label {
    font-size: 0.72rem;
    color: var(--ta-text-muted);
}
.ta-checkin-player-card {
    border: 1px solid var(--ta-border);
    border-radius: 0.72rem;
    background: rgba(148, 163, 184, 0.08);
    padding: 0.8rem;
}
.ta-checkin-player-value {
    margin-top: 0.35rem;
    color: var(--ta-text-strong);
    font-size: 1.2rem;
    font-weight: 700;
}
.ta-checkin-player-value--time {
    font-size: 0.95rem;
}
@media (max-width: 1023px) {
    .ta-checkin-preview-card {
        position: static;
    }
}
@media (min-width: 768px) {
    .ta-checkin-input-card {
        grid-template-columns: 1fr 140px auto;
        align-items: center;
    }
    .ta-checkin-input-card input[data-checkin-command-text] {
        grid-column: 1 / span 2;
    }
}
.ta-realtime-panel {
    display: grid;
    gap: 1rem;
}
.ta-realtime-head {
    display: flex;
    justify-content: space-between;
    gap: 0.85rem;
    align-items: center;
    flex-wrap: wrap;
}
.ta-realtime-connection {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border: 1px solid var(--ta-border-strong);
    border-radius: 999px;
    padding: 0.35rem 0.75rem;
    font-size: 0.85rem;
    background: rgba(148, 163, 184, 0.1);
}
.ta-realtime-connection-dot {
    width: 0.6rem;
    height: 0.6rem;
    border-radius: 999px;
    background: currentColor;
    box-shadow: 0 0 0 4px rgba(148, 163, 184, 0.16);
}
.ta-realtime-connection-pending {
    color: var(--ta-state-warning-text) !important;
}
.ta-realtime-connection-connected {
    color: var(--ta-state-success-text) !important;
}
.ta-realtime-connection-reconnecting {
    color: var(--ta-state-warning-text) !important;
}
.ta-realtime-connection-disconnected {
    color: var(--ta-state-danger-text) !important;
}
.ta-realtime-connection-error {
    color: var(--ta-state-danger-text) !important;
}
.ta-realtime-connection-connected .ta-realtime-connection-dot {
    background: var(--ta-state-success-text);
    box-shadow: 0 0 0 4px var(--ta-state-success-ring);
}
.ta-realtime-connection-pending .ta-realtime-connection-dot,
.ta-realtime-connection-reconnecting .ta-realtime-connection-dot {
    background: var(--ta-state-warning-text);
    box-shadow: 0 0 0 4px var(--ta-state-warning-ring);
}
.ta-realtime-connection-disconnected .ta-realtime-connection-dot,
.ta-realtime-connection-error .ta-realtime-connection-dot {
    background: var(--ta-state-danger-text);
    box-shadow: 0 0 0 4px var(--ta-state-danger-ring);
}
.ta-realtime-metrics {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(auto-fit, minmax(165px, 1fr));
}
.ta-realtime-metric {
    padding: 0.85rem;
}
.ta-realtime-metric h3 {
    margin: 0 0 0.4rem;
    font-size: 0.8rem;
    color: var(--ta-text-muted);
}
.ta-realtime-metric p {
    margin: 0;
    font-size: 1.08rem;
    font-weight: 700;
    color: var(--ta-text-strong);
}
.ta-realtime-metric p.ta-metric-good {
    color: var(--ta-state-success-text) !important;
}
.ta-realtime-metric p.ta-metric-warn {
    color: var(--ta-state-warning-text) !important;
}
.ta-realtime-metric p.ta-metric-bad {
    color: var(--ta-state-danger-text) !important;
}
.ta-realtime-content-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
}
.ta-realtime-section {
    padding: 0.9rem;
}
.ta-realtime-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    margin-bottom: 0.75rem;
}
.ta-realtime-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.15rem 0.58rem;
    font-size: 0.76rem;
    border: 1px solid var(--ta-accent-border);
    background: var(--ta-accent-soft-bg);
    color: var(--ta-accent);
}
.ta-realtime-player-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 0.5rem;
}
.ta-realtime-player-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    border: 1px solid var(--ta-border);
    border-radius: 0.65rem;
    padding: 0.5rem 0.7rem;
    background: rgba(148, 163, 184, 0.08);
}
.ta-realtime-plugin-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 4.5rem;
    border-radius: 999px;
    padding: 0.2rem 0.62rem;
    font-size: 0.76rem;
    border: 1px solid var(--ta-border-strong);
}
.ta-realtime-plugin-enabled {
    color: var(--ta-state-success-text);
    border-color: var(--ta-state-success-border);
    background: var(--ta-state-success-bg);
}
.ta-realtime-plugin-disabled {
    color: var(--ta-state-neutral-text);
    border-color: var(--ta-state-neutral-border);
    background: var(--ta-state-neutral-bg);
}
.ta-realtime-chat-stream {
    display: grid;
    gap: 0.5rem;
    max-height: 22rem;
    overflow-y: auto;
}
.ta-realtime-chat-item {
    border: 1px solid var(--ta-border);
    border-radius: 0.65rem;
    padding: 0.55rem 0.7rem;
    background: rgba(148, 163, 184, 0.08);
}
.ta-realtime-chat-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    margin-bottom: 0.28rem;
    font-size: 0.78rem;
}
.ta-realtime-chat-player {
    font-weight: 600;
    color: var(--ta-text-strong);
}
.ta-realtime-chat-time {
    color: var(--ta-text-muted);
}
.ta-realtime-chat-message {
    margin: 0;
    font-size: 0.9rem;
    color: var(--ta-text-body);
    white-space: pre-wrap;
    word-break: break-word;
}
.ta-realtime-empty {
    color: var(--ta-text-muted);
    font-size: 0.9rem;
    text-align: center;
    padding: 0.9rem 0.65rem;
}
.ta-realtime-health-card {
    padding: 1rem;
}
.ta-realtime-health-lines {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 0.65rem 1rem;
}
.ta-realtime-health-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    border: 1px solid var(--ta-border);
    border-radius: 0.65rem;
    padding: 0.5rem 0.65rem;
    background: rgba(148, 163, 184, 0.08);
}
.ta-realtime-health-line span {
    color: var(--ta-text-muted);
    font-size: 0.82rem;
}
.ta-realtime-health-line strong {
    color: var(--ta-text-strong);
    font-size: 0.88rem;
}
.ta-health-good {
    color: var(--ta-state-success-text) !important;
}
.ta-health-warn {
    color: var(--ta-state-warning-text) !important;
}
.ta-health-bad {
    color: var(--ta-state-danger-text) !important;
}
#admin-main-content input[type="text"],
#admin-main-content input[type="number"],
#admin-main-content input[type="datetime-local"],
#admin-main-content input[type="file"],
#admin-main-content input[type="search"],
#admin-main-content input[type="email"],
#admin-main-content input[type="password"],
#admin-main-content textarea,
#admin-main-content select {
    width: 100%;
    margin-top: 0.3rem;
    padding: 0.5rem 0.65rem;
    background: var(--ta-surface-input);
    border: 1px solid var(--ta-border-strong);
    border-radius: 0.55rem;
    color: var(--ta-text-body);
}
#admin-main-content input::placeholder,
#admin-main-content textarea::placeholder {
    color: var(--ta-text-muted);
}
[data-theme="light"] #admin-main-content input:focus,
[data-theme="light"] #admin-main-content textarea:focus,
[data-theme="light"] #admin-main-content select:focus {
    border-color: rgba(14, 116, 144, 0.5);
    box-shadow: 0 0 0 1px rgba(14, 116, 144, 0.18), 0 0 18px -8px rgba(14, 116, 144, 0.28);
}
[data-theme="light"] .ta-card code {
    background: rgba(226, 232, 240, 0.85);
    color: #0f172a;
    border-radius: 0.35rem;
    padding: 0.08rem 0.3rem;
}
#admin-main-content textarea {
    resize: vertical;
}
#admin-main-content td > form {
    display: inline-block;
    margin-right: 0.35rem;
}
#admin-main-content td > form:last-child {
    margin-right: 0;
}
@media (max-width: 1024px) {
    .ta-realtime-head {
        align-items: flex-start;
    }
}

/* SaaS layout refresh */
:root {
    --ta-font-heading: 'Lexend', 'Noto Sans SC', 'Microsoft YaHei', sans-serif;
    --ta-font-body: 'Noto Sans SC', 'Microsoft YaHei', 'Segoe UI', sans-serif;
    --ta-surface-shell: rgba(8, 17, 34, 0.92);
    --ta-surface-frame: rgba(12, 24, 45, 0.88);
    --ta-surface-sidebar: rgba(9, 24, 45, 0.96);
    --ta-surface-header: rgba(7, 18, 36, 0.78);
    --ta-surface-card: rgba(14, 27, 52, 0.86);
    --ta-surface-input: rgba(10, 23, 46, 0.95);
    --ta-border: rgba(148, 163, 184, 0.23);
    --ta-border-strong: rgba(148, 163, 184, 0.4);
    --ta-shadow: 0 34px 68px -44px rgba(2, 6, 23, 0.92);
    --ta-text-strong: #f8fafc;
    --ta-text-body: #dbe7f8;
    --ta-text-muted: #a9bdd8;
    --ta-header-title: #f8fafc;
    --ta-user-name: #e8efff;
    --ta-toggle-bg: rgba(15, 30, 56, 0.8);
    --ta-toggle-border: rgba(148, 163, 184, 0.36);
    --ta-toggle-icon: #93c5fd;
    --ta-accent: #60a5fa;
    --ta-accent-bg: rgba(37, 99, 235, 0.22);
    --ta-accent-border: rgba(96, 165, 250, 0.48);
    --ta-accent-soft-bg: rgba(59, 130, 246, 0.14);
    --ta-btn-primary-bg: linear-gradient(135deg, rgba(37, 99, 235, 0.92), rgba(14, 165, 233, 0.9));
    --ta-btn-primary-border: rgba(125, 211, 252, 0.52);
    --ta-btn-primary-text: #eff6ff;
    --ta-state-success-text: #22c55e;
    --ta-state-success-border: rgba(34, 197, 94, 0.5);
    --ta-state-success-bg: rgba(34, 197, 94, 0.12);
    --ta-state-success-ring: rgba(34, 197, 94, 0.22);
    --ta-state-warning-text: #f59e0b;
    --ta-state-warning-border: rgba(245, 158, 11, 0.45);
    --ta-state-warning-bg: rgba(245, 158, 11, 0.12);
    --ta-state-warning-ring: rgba(245, 158, 11, 0.22);
    --ta-state-danger-text: #ef4444;
    --ta-state-danger-border: rgba(239, 68, 68, 0.48);
    --ta-state-danger-bg: rgba(239, 68, 68, 0.12);
    --ta-state-danger-ring: rgba(239, 68, 68, 0.22);
    --ta-state-neutral-text: #cbd5e1;
    --ta-state-neutral-border: rgba(148, 163, 184, 0.46);
    --ta-state-neutral-bg: rgba(148, 163, 184, 0.14);
}
[data-theme="light"] {
    --ta-surface-shell: rgba(244, 248, 255, 0.98);
    --ta-surface-frame: rgba(247, 250, 255, 0.96);
    --ta-surface-sidebar: rgba(12, 37, 73, 0.97);
    --ta-surface-header: rgba(255, 255, 255, 0.86);
    --ta-surface-card: rgba(255, 255, 255, 0.97);
    --ta-surface-input: rgba(255, 255, 255, 0.99);
    --ta-border: rgba(148, 163, 184, 0.34);
    --ta-border-strong: rgba(100, 116, 139, 0.42);
    --ta-shadow: 0 24px 48px -30px rgba(15, 23, 42, 0.24);
    --ta-text-strong: #0f172a;
    --ta-text-body: #1e293b;
    --ta-text-muted: #475569;
    --ta-header-title: #0f172a;
    --ta-user-name: #0f172a;
    --ta-toggle-bg: rgba(248, 251, 255, 0.98);
    --ta-toggle-border: rgba(100, 116, 139, 0.3);
    --ta-toggle-icon: #1d4ed8;
    --ta-accent: #1d4ed8;
    --ta-accent-bg: rgba(59, 130, 246, 0.16);
    --ta-accent-border: rgba(37, 99, 235, 0.42);
    --ta-accent-soft-bg: rgba(59, 130, 246, 0.1);
    --ta-btn-primary-bg: linear-gradient(135deg, #2563eb, #0284c7);
    --ta-btn-primary-border: rgba(37, 99, 235, 0.5);
    --ta-btn-primary-text: #ffffff;
    --ta-state-success-text: #22c55e;
    --ta-state-success-border: rgba(34, 197, 94, 0.48);
    --ta-state-success-bg: rgba(34, 197, 94, 0.14);
    --ta-state-success-ring: rgba(34, 197, 94, 0.24);
    --ta-state-warning-text: #92400e;
    --ta-state-warning-border: rgba(146, 64, 14, 0.34);
    --ta-state-warning-bg: rgba(255, 237, 213, 0.94);
    --ta-state-warning-ring: rgba(146, 64, 14, 0.18);
    --ta-state-danger-text: #b91c1c;
    --ta-state-danger-border: rgba(185, 28, 28, 0.35);
    --ta-state-danger-bg: rgba(254, 226, 226, 0.95);
    --ta-state-danger-ring: rgba(185, 28, 28, 0.18);
    --ta-state-neutral-text: #334155;
    --ta-state-neutral-border: rgba(71, 85, 105, 0.35);
    --ta-state-neutral-bg: rgba(241, 245, 249, 0.92);
}
[data-theme="light"] .ta-admin-frame {
    background: linear-gradient(180deg, rgba(250, 252, 255, 0.92), rgba(241, 245, 255, 0.86));
}
[data-theme="light"] .ta-realtime-connection {
    background: rgba(241, 245, 249, 0.92);
    border-color: rgba(71, 85, 105, 0.24);
}
[data-theme="light"] .ta-checkin-input-card,
[data-theme="light"] .ta-checkin-scope-option,
[data-theme="light"] .ta-checkin-player-card,
[data-theme="light"] .ta-realtime-player-item,
[data-theme="light"] .ta-realtime-chat-item,
[data-theme="light"] .ta-realtime-health-line {
    background: rgba(248, 250, 252, 0.94);
}
.ta-admin-layout {
    min-height: calc(100vh - 1.5rem);
    height: auto;
    border: 1px solid var(--ta-border);
    border-radius: 1.1rem;
    position: relative;
    isolation: isolate;
    background: linear-gradient(180deg, var(--ta-surface-shell), var(--ta-surface-frame));
}
.ta-admin-layout::before,
.ta-admin-layout::after {
    content: '';
    position: absolute;
    pointer-events: none;
    z-index: 0;
}
.ta-admin-layout::before {
    width: 26rem;
    height: 26rem;
    right: -8rem;
    top: -11rem;
    border-radius: 999px;
    background: radial-gradient(circle at center, rgba(56, 189, 248, 0.24), rgba(56, 189, 248, 0));
}
.ta-admin-layout::after {
    width: 22rem;
    height: 22rem;
    left: -9rem;
    bottom: -10rem;
    border-radius: 999px;
    background: radial-gradient(circle at center, rgba(249, 115, 22, 0.14), rgba(249, 115, 22, 0));
}
.ta-admin-layout > * {
    position: relative;
    z-index: 1;
}
.ta-admin-frame {
    background: linear-gradient(180deg, rgba(15, 28, 51, 0.62), rgba(10, 21, 42, 0.7));
    backdrop-filter: blur(12px);
}
.ta-admin-main {
    padding: 1.1rem;
    color: var(--ta-text-body);
    font-family: var(--ta-font-body);
}
@media (min-width: 768px) {
    .ta-admin-main {
        padding: 1.35rem;
    }
}
@media (min-width: 1280px) {
    .ta-admin-main {
        padding: 1.6rem 1.75rem;
    }
}
.ta-sidebar-modern {
    width: 17.2rem;
    padding: 1rem 0.85rem;
    border-right: 1px solid rgba(148, 163, 184, 0.22);
    box-shadow: inset -1px 0 0 rgba(148, 163, 184, 0.12);
    background:
        linear-gradient(180deg, rgba(13, 33, 64, 0.98) 0%, rgba(14, 44, 86, 0.98) 100%);
}
.ta-sidebar-modern .mb-6 h2 {
    color: #f8fafc !important;
    text-shadow: 0 1px 2px rgba(2, 6, 23, 0.45);
}
.ta-sidebar-modern .mb-6 p {
    color: rgba(226, 232, 240, 0.82) !important;
}
.ta-sidebar-menu {
    padding-right: 0.2rem;
}
.ta-sidebar-item {
    border: 1px solid transparent;
    border-radius: 0.82rem;
    font-size: 0.89rem;
    font-weight: 580;
    letter-spacing: 0.01em;
    transition: border-color 0.2s ease, background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
}
.ta-sidebar-item:hover {
    border-color: rgba(148, 163, 184, 0.36);
    background: rgba(148, 163, 184, 0.14);
    transform: translateY(-1px);
}
.ta-sidebar-item.active {
    border-color: rgba(125, 211, 252, 0.45);
    background: linear-gradient(90deg, rgba(37, 99, 235, 0.42), rgba(14, 165, 233, 0.22));
    box-shadow: 0 10px 20px -16px rgba(56, 189, 248, 0.7);
}
.ta-sidebar-subitem {
    font-size: 0.82rem;
    padding-left: 0.9rem;
}
.ta-admin-header-modern {
    top: 0;
    z-index: 50;
    border-bottom: 1px solid var(--ta-border);
    background: var(--ta-surface-header);
    backdrop-filter: blur(14px);
    box-shadow: 0 16px 34px -30px rgba(2, 6, 23, 0.84);
}
.ta-admin-header-inner {
    padding: 0.82rem 1rem;
}
@media (min-width: 768px) {
    .ta-admin-header-inner {
        padding: 0.82rem 1.35rem;
    }
}
.ta-admin-header-title {
    font-family: var(--ta-font-heading);
    font-size: 1.02rem;
    letter-spacing: 0.02em;
    font-weight: 640;
}
.ta-admin-header-subtitle {
    margin-top: 0.16rem;
    font-size: 0.78rem;
    line-height: 1.2;
    color: var(--ta-text-muted);
}
.ta-admin-theme-toggle,
.ta-admin-user-trigger {
    min-height: 2.5rem;
    border-radius: 0.82rem;
}
.ta-admin-theme-toggle {
    font-family: var(--ta-font-body);
    font-weight: 600;
}
.ta-admin-theme-toggle:focus-visible,
.ta-admin-user-trigger:focus-visible,
.ta-btn:focus-visible,
#admin-main-content input:focus-visible,
#admin-main-content textarea:focus-visible,
#admin-main-content select:focus-visible {
    outline: 0;
    border-color: var(--ta-accent-border);
    box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.2);
}
.ta-admin-user-trigger {
    background: var(--ta-surface-input);
    border-color: var(--ta-border-strong);
}
.ta-card {
    border-radius: 1rem;
    padding: 1.05rem;
    background: var(--ta-surface-card);
    border: 1px solid var(--ta-border);
    box-shadow: var(--ta-shadow);
}
@media (min-width: 768px) {
    .ta-card {
        padding: 1.2rem;
    }
}
.ta-card h1,
.ta-card h2,
.ta-card h3 {
    font-family: var(--ta-font-heading);
    letter-spacing: 0.01em;
}
.ta-card h1 {
    margin-bottom: 0.42rem;
    font-size: 1.15rem;
}
.ta-card h2 {
    margin-top: 0.85rem;
    margin-bottom: 0.38rem;
    font-size: 0.96rem;
}
.ta-kpi-shell {
    padding: 1.3rem !important;
}
.ta-kpi-card {
    border-radius: 0.96rem;
}
.ta-kpi-value {
    font-family: var(--ta-font-heading);
}
.ta-table-wrap {
    border-radius: 0.85rem;
}
.ta-table {
    border-collapse: separate;
    border-spacing: 0;
    margin-top: 0.75rem;
}
.ta-table th,
.ta-table td {
    padding: 0.7rem 0.6rem;
}
.ta-table th {
    font-size: 0.75rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--ta-text-muted);
    background: rgba(148, 163, 184, 0.08);
}
.ta-table tbody tr:hover td {
    background: rgba(59, 130, 246, 0.08);
}
.ta-btn {
    min-height: 2.3rem;
    padding: 0.4rem 0.86rem;
    border-radius: 0.72rem;
    font-family: var(--ta-font-body);
    font-weight: 600;
    line-height: 1;
}
.ta-btn-primary {
    background: var(--ta-btn-primary-bg);
    border-color: var(--ta-btn-primary-border);
    color: var(--ta-btn-primary-text);
}
.ta-btn-primary:hover {
    filter: brightness(1.05);
    color: #ffffff;
}
.ta-btn-secondary:hover {
    color: var(--ta-text-strong);
}
#admin-main-content input[type="text"],
#admin-main-content input[type="number"],
#admin-main-content input[type="datetime-local"],
#admin-main-content input[type="file"],
#admin-main-content input[type="search"],
#admin-main-content input[type="email"],
#admin-main-content input[type="password"],
#admin-main-content textarea,
#admin-main-content select {
    min-height: 2.5rem;
    border-radius: 0.72rem;
    border: 1px solid var(--ta-border-strong);
    background: var(--ta-surface-input);
    color: var(--ta-text-body);
    font-family: var(--ta-font-body);
    transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
}
#admin-main-content textarea {
    min-height: 7.25rem;
}
.ta-realtime-connection,
.ta-realtime-plugin-badge,
.ta-realtime-pill {
    font-weight: 600;
}
.ta-checkin-day:hover {
    transform: translateY(-1px);
}
#admin-mobile-tabs {
    display: none;
    gap: 0.5rem;
    margin: 0 0 0.95rem;
    padding: 0 0.05rem 0.3rem;
    overflow-x: auto;
    scrollbar-width: thin;
}
.ta-mobile-tab-btn {
    white-space: nowrap;
    border: 1px solid var(--ta-border-strong);
    border-radius: 999px;
    background: var(--ta-surface-input);
    color: var(--ta-text-body);
    font-family: var(--ta-font-body);
    font-size: 0.8rem;
    font-weight: 600;
    line-height: 1;
    padding: 0.58rem 0.82rem;
    transition: border-color 0.18s ease, background-color 0.18s ease, color 0.18s ease;
}
.ta-mobile-tab-btn.active {
    color: #ffffff;
    border-color: rgba(125, 211, 252, 0.52);
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.9), rgba(14, 165, 233, 0.86));
}
@media (max-width: 1023px) {
    #admin-mobile-tabs {
        display: flex;
    }
    .ta-admin-header-subtitle {
        display: none;
    }
}
@media (prefers-reduced-motion: reduce) {
    .ta-sidebar-item,
    .ta-btn,
    .ta-mobile-tab-btn,
    #admin-main-content input,
    #admin-main-content textarea,
    #admin-main-content select {
        transition: none !important;
    }
}
</style>

<!-- MOD: TailAdmin Layout Start -->
<div class="ta-admin-layout">
    <?php include BASE_PATH . '/app/views/admin/layout/sidebar.php'; ?>

    <div class="ta-admin-frame">
        <?php include BASE_PATH . '/app/views/admin/layout/header.php'; ?>

        <main class="ta-admin-main">
            <div id="admin-mobile-tabs" aria-label="Admin Tab Navigation"></div>
            <?php include BASE_PATH . '/app/views/admin/layout/layout.php'; ?>
        </main>
    </div>
</div>
<!-- MOD: TailAdmin Layout End -->

<script>
window.adminRealtimePanelConfig = <?= json_encode(
    $realtimeWsConfig ?? [
        'enable_realtime_panel' => false,
        'ws_url' => '',
        'ws_auth_token' => '',
        'ws_ticket_endpoint' => '/admin/realtime-ticket',
        'ws_ticket_query_param' => 'token',
        'ws_ticket_ttl_seconds' => 120,
        'reconnect_interval_ms' => 3000,
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) ?>;
</script>
<!--suppress HtmlUnknownTarget -->
<script src="<?= htmlspecialchars($adminRealtimePanelScriptUrl, ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars($adminRedeemScriptUrl, ENT_QUOTES, 'UTF-8') ?>"></script>

<script>
function toDatetimeLocalValue(mysqlDatetime) {
    if (!mysqlDatetime) return '';
    var s = String(mysqlDatetime).trim().replace(' ', 'T');
    if (s.length >= 16) return s.slice(0, 16);
    return s;
}

function updatePublishUi(form) {
    if (!form) return;
    var isPublishedEl = form.querySelector('input[name="is_published"]');
    var publishModeEls = form.querySelectorAll('input[name="publish_mode"]');
    var publishTimeEl = form.querySelector('input[name="publish_time"]');
    if (!isPublishedEl || !publishTimeEl || !publishModeEls || publishModeEls.length === 0) return;

    var isPublished = !!isPublishedEl.checked;
    var mode = 'immediate';
    publishModeEls.forEach(function (el) { if (el.checked) mode = el.value; });

    publishModeEls.forEach(function (el) { el.disabled = !isPublished; });

    var shouldShowTime = isPublished && mode === 'scheduled';
    publishTimeEl.style.display = shouldShowTime ? 'inline-block' : 'none';
    publishTimeEl.required = shouldShowTime;
    if (!shouldShowTime) publishTimeEl.value = '';
}

function editAnnouncement(id, title, content, isPublished, createdAt) {
    var form = document.querySelector('form[action="/admin/announcements/save"]');
    if (!form) return;

    var isPublishedEl = form.querySelector('input[name="is_published"]');
    var publishTimeEl = form.querySelector('input[name="publish_time"]');
    var modeImmediateEl = form.querySelector('input[name="publish_mode"][value="immediate"]');
    var modeScheduledEl = form.querySelector('input[name="publish_mode"][value="scheduled"]');

    form.querySelector('input[name="id"]').value = id;
    form.querySelector('input[name="title"]').value = title;
    form.querySelector('textarea[name="content"]').value = content;
    if (isPublishedEl) isPublishedEl.checked = (isPublished === 1);

    var createdMs = createdAt ? Date.parse(String(createdAt).replace(' ', 'T')) : NaN;
    var nowMs = Date.now();
    var isFuture = !isNaN(createdMs) && createdMs > nowMs;

    if (modeImmediateEl && modeScheduledEl) {
        if ((isPublished === 1) && isFuture) {
            modeScheduledEl.checked = true;
            if (publishTimeEl) publishTimeEl.value = toDatetimeLocalValue(createdAt);
        } else {
            modeImmediateEl.checked = true;
            if (publishTimeEl) publishTimeEl.value = '';
        }
    }

    updatePublishUi(form);

    var btn = document.getElementById('announcement-submit-btn');
    if (btn) btn.textContent = '保存修改';
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function editMilestone(id, milestoneDate, description) {
    var form = document.querySelector('form[action="/admin/milestones/save"]');
    if (!form) return;
    form.querySelector('input[name="id"]').value = id;
    form.querySelector('input[name="milestone_date"]').value = milestoneDate || '';
    form.querySelector('textarea[name="description"]').value = description || '';
    var btn = document.getElementById('milestone-submit-btn');
    if (btn) btn.textContent = '保存修改';
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function editTeamMember(id, username, role) {
    var form = document.querySelector('form[action="/admin/team-members/save"]');
    if (!form) return;
    form.querySelector('input[name="id"]').value = id;
    form.querySelector('input[name="username"]').value = username || '';
    form.querySelector('input[name="role"]').value = role || '';
    var btn = document.getElementById('team-member-submit-btn');
    if (btn) btn.textContent = '保存修改';
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

(function () {
    // 闁稿浚鍓欓幉锛勬偘閵娿儱绀嬪ù婧垮€撶花?
    var form = document.querySelector('form[action="/admin/announcements/save"]');
    if (form) {
        form.addEventListener('change', function (e) {
            if (!e || !e.target) return;
            var name = e.target.name;
            if (name === 'publish_mode' || name === 'is_published') {
                updatePublishUi(form);
            }
        });
        updatePublishUi(form);
    }

    // Tabs 闁告帒娲﹀畷鏌ユ焻閺勫繒甯?
    var tabButtons = document.querySelectorAll('.admin-tab-btn');
    var tabContents = document.querySelectorAll('.ta-tab-content');
    var headerTitle = document.querySelector('.ta-admin-header-title');
    var headerSubtitle = document.querySelector('.ta-admin-header-subtitle');
    var mobileTabsRoot = document.getElementById('admin-mobile-tabs');

    function resolveTabLabel(targetId) {
        var matchedBtn = document.querySelector('#admin-sidebar .admin-tab-btn[data-tab-target="' + targetId + '"]');
        if (!matchedBtn) return '';
        var matchedLabel = matchedBtn.querySelector('span');
        return matchedLabel ? matchedLabel.textContent.trim() : '';
    }

    function updateHeaderTabTitle(targetId) {
        if (!headerTitle) return;
        var tabLabel = resolveTabLabel(targetId);
        if (tabLabel !== '') {
            headerTitle.textContent = tabLabel;
        }
        if (headerSubtitle) {
            headerSubtitle.textContent = 'SaaS Operations Console';
        }
    }

    function syncMobileTabState(targetId) {
        if (!mobileTabsRoot) return;
        mobileTabsRoot.querySelectorAll('.ta-mobile-tab-btn').forEach(function (btn) {
            if (btn.getAttribute('data-mobile-tab-target') === targetId) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }

    function activateTab(targetId) {
        tabContents.forEach(function (content) {
            if (content.id === targetId) {
                content.classList.remove('tab-hidden');
            } else {
                content.classList.add('tab-hidden');
            }
        });

        tabButtons.forEach(function (btn) {
            var btnTarget = btn.getAttribute('data-tab-target');
            if (btnTarget === targetId) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        updateHeaderTabTitle(targetId);
        syncMobileTabState(targetId);
    }


    // 闁哄秷顫夊畵?URL ?tab=xxx 闂侇偄顦扮€氥劑宕氬┑鍡╂綏 Tab
    var params = new URLSearchParams(window.location.search);
    var initial = params.get('tab');
    var map = {
        dashboard: 'tab-dashboard',
        realtime: 'tab-realtime',
        'checkin-rewards': 'tab-checkin-rewards',
        'checkin-logs': 'tab-checkin-logs',
        'checkin-stats': 'tab-checkin-stats',
        redeem: 'tab-redeem',
        players: 'tab-players',
        users: 'tab-players',
        feedback: 'tab-feedback',
        announcements: 'tab-announcements',
        milestones: 'tab-milestones',
        gallery: 'tab-gallery',
        'site-settings': 'tab-site-settings',
        team: 'tab-team',
        'ip-whitelist': 'tab-ip-whitelist',
        'ip-blacklist': 'tab-ip-blacklist'
    };
    var initialId = map[initial] || 'tab-dashboard';
    if (!document.getElementById(initialId)) {
        initialId = 'tab-dashboard';
    }

    var reverseMap = {};
    Object.keys(map).forEach(function (key) {
        var id = map[key];
        if (!reverseMap[id]) {
            reverseMap[id] = key;
        }
    });

    function buildMobileTabNav(activeTargetId) {
        if (!mobileTabsRoot) return;
        var sourceButtons = document.querySelectorAll('#admin-sidebar .admin-tab-btn[data-tab-target]');
        if (!sourceButtons.length) return;

        var seenTargets = {};
        mobileTabsRoot.innerHTML = '';

        sourceButtons.forEach(function (btn) {
            var target = btn.getAttribute('data-tab-target');
            if (!target || seenTargets[target]) return;
            seenTargets[target] = true;

            var tabKey = reverseMap[target] || target.replace(/^tab-/, '');
            var tabLabel = resolveTabLabel(target);
            if (tabLabel === '') {
                tabLabel = tabKey;
            }

            var mobileBtn = document.createElement('button');
            mobileBtn.type = 'button';
            mobileBtn.className = 'ta-mobile-tab-btn';
            mobileBtn.setAttribute('data-mobile-tab-target', target);
            mobileBtn.textContent = tabLabel;
            if (target === activeTargetId) {
                mobileBtn.classList.add('active');
            }

            mobileBtn.addEventListener('click', function () {
                var nextParams = new URLSearchParams(window.location.search);
                nextParams.set('tab', tabKey);
                window.location.search = nextParams.toString();
            });

            mobileTabsRoot.appendChild(mobileBtn);
        });
    }

    buildMobileTabNav(initialId);

    tabButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = btn.getAttribute('data-tab-target');
            if (!target) return;

            var currentTabKey = reverseMap[initialId] || 'dashboard';
            var targetTabKey = reverseMap[target] || 'dashboard';

            if (targetTabKey !== currentTabKey) {
                var nextParams = new URLSearchParams(window.location.search);
                nextParams.set('tab', targetTabKey);
                window.location.search = nextParams.toString();
                return;
            }

            activateTab(target);
        });
    });

    activateTab(initialId);
})();

(function () {
    var feedbackForms = document.querySelectorAll('[data-feedback-admin-form]');
    if (!feedbackForms.length) return;

    var feedbackOpenLabel = '\u67e5\u770b\u8be6\u60c5';
    var feedbackCloseLabel = '\u6536\u8d77\u8be6\u60c5';
    var feedbackRequiredPlaceholder = '\u5f53\u72b6\u6001\u4e3a\u201c\u9700\u8981\u8865\u5145\u201d\u65f6\uff0c\u6b64\u9879\u5fc5\u586b\uff0c\u8bf7\u5199\u660e\u9700\u8981\u73a9\u5bb6\u8865\u5145\u54ea\u4e9b\u6750\u6599\u3002';
    var feedbackRequiredAlert = '\u5f53\u72b6\u6001\u4e3a\u201c\u9700\u8981\u8865\u5145\u201d\u65f6\uff0c\u8bf7\u586b\u5199\u7ba1\u7406\u5458\u56de\u590d\uff0c\u8bf4\u660e\u9700\u8981\u73a9\u5bb6\u8865\u5145\u7684\u5185\u5bb9\u3002';

    document.addEventListener('click', function (event) {
        var target = event.target;
        var toggleBtn = target && target.closest ? target.closest('[data-feedback-toggle]') : null;
        if (!toggleBtn) return;

        var feedbackId = String(toggleBtn.getAttribute('data-feedback-toggle') || '').trim();
        if (feedbackId === '') return;

        var detailRows = document.querySelectorAll('[data-feedback-detail]');
        var detailRow = null;
        for (var i = 0; i < detailRows.length; i++) {
            var itemId = String(detailRows[i].getAttribute('data-feedback-detail') || '');
            if (itemId === feedbackId) {
                detailRow = detailRows[i];
                break;
            }
        }
        if (!detailRow) return;

        var willOpen = detailRow.hasAttribute('hidden');
        if (willOpen) {
            detailRow.removeAttribute('hidden');
        } else {
            detailRow.setAttribute('hidden', 'hidden');
        }
        toggleBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        toggleBtn.textContent = willOpen ? feedbackCloseLabel : feedbackOpenLabel;
    });

    feedbackForms.forEach(function (form) {
        var statusSelect = form.querySelector('[data-feedback-admin-status]');
        var replyInput = form.querySelector('[data-feedback-admin-reply]');
        var hintNode = form.querySelector('[data-feedback-admin-hint]');
        if (!statusSelect || !replyInput) return;

        var defaultPlaceholder = replyInput.getAttribute('placeholder') || '';

        var syncState = function () {
            var needReply = String(statusSelect.value || '') === 'need_more_info';
            replyInput.placeholder = needReply ? feedbackRequiredPlaceholder : defaultPlaceholder;
            if (hintNode) {
                hintNode.style.color = needReply ? '#f97316' : '';
            }
        };

        statusSelect.addEventListener('change', syncState);
        form.addEventListener('submit', function (event) {
            if (String(statusSelect.value || '') === 'need_more_info' && String(replyInput.value || '').trim() === '') {
                event.preventDefault();
                alert(feedbackRequiredAlert);
                replyInput.focus();
            }
        });

        syncState();
    });
})();

(function () {
    var userMenuTrigger = document.getElementById('admin-user-menu-trigger');
    var userMenuDropdown = document.getElementById('admin-user-menu-dropdown');
    if (!userMenuTrigger || !userMenuDropdown) return;

    function closeUserMenu() {
        userMenuDropdown.classList.add('hidden');
        userMenuTrigger.setAttribute('aria-expanded', 'false');
    }

    function openUserMenu() {
        userMenuDropdown.classList.remove('hidden');
        userMenuTrigger.setAttribute('aria-expanded', 'true');
    }

    userMenuTrigger.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (userMenuDropdown.classList.contains('hidden')) {
            openUserMenu();
        } else {
            closeUserMenu();
        }
    });

    document.addEventListener('click', function (event) {
        if (userMenuDropdown.contains(event.target) || userMenuTrigger.contains(event.target)) {
            return;
        }
        closeUserMenu();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeUserMenu();
        }
    });
})();

(function () {
    var fileInput = document.getElementById('gallery-upload-input');
    var fileNameSpan = document.getElementById('gallery-file-name');
    if (!fileInput || !fileNameSpan) return;

    fileInput.addEventListener('change', function () {
        if (fileInput.files && fileInput.files.length > 0) {
            fileNameSpan.textContent = fileInput.files[0].name;
        } else {
            fileNameSpan.textContent = '未选择任何文件';
        }
    });
})();
</script>
