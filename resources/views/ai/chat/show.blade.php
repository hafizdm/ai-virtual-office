<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $conversation->title ?: 'AI Virtual Office' }} | RAPID</title>

    <style>

        :root {

            --navy: #111827;

            --navy-light: #1f2937;

            --orange: #f97316;

            --orange-hover: #ea580c;

            --page: #f4f6fa;

            --text: #1e293b;

            --muted: #64748b;

            --border: #e5e7eb;

        }

        * { box-sizing: border-box; }

        html, body {

            width: 100%;

            height: 100%;

            margin: 0;

        }

        body {

            font-family: Inter, -apple-system, BlinkMacSystemFont,

                "Segoe UI", Arial, sans-serif;

            background: var(--page);

            color: var(--text);

            font-size: 14px;

        }

        button, textarea { font: inherit; }

        .app {

            display: flex;

            width: 100%;

            height: 100vh;

            height: 100dvh;

            overflow: hidden;

        }

        .sidebar {

            display: flex;

            flex: 0 0 280px;

            flex-direction: column;

            min-width: 0;

            padding: 23px 16px 14px;

            background: var(--navy);

            color: white;

        }

        .brand {

            display: flex;

            align-items: center;

            gap: 12px;

            padding: 0 8px;

            margin-bottom: 28px;

        }

        .brand-logo {

            display: flex;

            align-items: center;

            justify-content: center;

            flex-shrink: 0;

            width: 43px;

            height: 43px;

            border-radius: 12px;

            background: var(--orange);

            color: white;

            font-size: 18px;

            font-weight: 800;

        }

        .brand-copy { min-width: 0; }

        .brand-name {

            font-size: 16px;

            font-weight: 750;

        }

        .brand-caption {

            margin-top: 4px;

            color: #94a3b8;

            font-size: 9px;

            letter-spacing: .8px;

            text-transform: uppercase;

        }

        .new-chat {

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 9px;

            width: 100%;

            min-height: 45px;

            padding: 10px;

            margin-bottom: 25px;

            border: 1px solid #fb923c;

            border-radius: 10px;

            background: var(--orange);

            color: white;

            cursor: pointer;

            font-weight: 650;

        }

        .new-chat:hover { background: var(--orange-hover); }

        .new-chat svg { width: 18px; height: 18px; }

        .sidebar-heading {

            margin: 0 9px 12px;

            color: #94a3b8;

            font-size: 10px;

            font-weight: 750;

            letter-spacing: 1.2px;

            text-transform: uppercase;

        }

        .conversation-list {

            flex: 1;

            min-height: 0;

            overflow-y: auto;

            overflow-x: hidden;

            scrollbar-width: thin;

            scrollbar-color: #374151 transparent;

        }

        .conversation-row {

            display: flex;

            align-items: center;

            gap: 3px;

            width: 100%;

            min-width: 0;

            margin-bottom: 5px;

            padding: 3px;

            border: 1px solid transparent;

            border-radius: 9px;

        }

        .conversation-row:hover,

        .conversation-row\.active {

            background: var(--navy-light);

        }

        .conversation-row\.active { border-color: #374151; }

        .conversation-item {

            display: block;

            flex: 1;

            min-width: 0;

            padding: 9px 7px;

            overflow-wrap: anywhere;

            color: #cbd5e1;

            text-decoration: none;

            font-size: 12px;

            line-height: 1.6;

        }

        .conversation-item:hover,

        .conversation-row\.active .conversation-item {

            color: white;

        }

        .delete-conversation-form {

            flex: 0 0 31px;

            width: 31px;

            margin: 0;

        }

        .delete-conversation-button {

            display: flex;

            align-items: center;

            justify-content: center;

            width: 30px;

            height: 32px;

            padding: 0;

            border: none;

            border-radius: 7px;

            background: transparent;

            color: #94a3b8;

            cursor: pointer;

            opacity: 0;

        }

        .delete-conversation-button svg {

            width: 16px;

            height: 16px;

        }

        .conversation-row:hover .delete-conversation-button,

        .conversation-row\.active .delete-conversation-button,

        .delete-conversation-button:focus-visible {

            opacity: 1;

        }

        .delete-conversation-button:hover {

            background: #374151;

            color: #fdba74;

        }

        .empty-conversations {

            padding: 12px 10px;

            color: #94a3b8;

            font-size: 12px;

            line-height: 1.8;

        }

        .sidebar-footer {

            flex-shrink: 0;

            padding-top: 14px;

            margin-top: 14px;

            border-top: 1px solid #293548;

        }

        .account {

            display: flex;

            align-items: center;

            gap: 10px;

            min-width: 0;

            padding: 9px 5px 12px;

        }

        .account-avatar {

            display: flex;

            align-items: center;

            justify-content: center;

            flex: 0 0 38px;

            width: 38px;

            height: 38px;

            border-radius: 50%;

            background: #374151;

            color: white;

            font-weight: 750;

        }

        .account-info { flex: 1; min-width: 0; }

        .account-name, .account-email {

            overflow: hidden;

            text-overflow: ellipsis;

            white-space: nowrap;

        }

        .account-name {

            color: #f8fafc;

            font-size: 12px;

            font-weight: 650;

        }

        .account-email {

            margin-top: 4px;

            color: #94a3b8;

            font-size: 10px;

        }

        .logout-button {

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            width: 100%;

            min-height: 38px;

            padding: 9px 12px;

            border: 1px solid #374151;

            border-radius: 8px;

            background: transparent;

            color: #cbd5e1;

            cursor: pointer;

            font-size: 12px;

        }

        .logout-button:hover {

            background: #293548;

            color: white;

        }

        .logout-button svg { width: 16px; height: 16px; }

        .main {

            display: flex;

            flex: 1;

            flex-direction: column;

            min-width: 0;

            height: 100%;

            overflow: hidden;

        }

        .topbar {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            flex-shrink: 0;

            min-height: 70px;

            padding: 12px 30px;

            border-bottom: 1px solid var(--border);

            background: white;

        }

        .topbar-info { min-width: 0; }

        .topbar-label {

            margin-bottom: 4px;

            color: var(--muted);

            font-size: 10px;

            font-weight: 750;

            letter-spacing: 1px;

            text-transform: uppercase;

        }

        .topbar-title {

            overflow: hidden;

            color: #0f172a;

            font-size: 15px;

            font-weight: 700;

            text-overflow: ellipsis;

            white-space: nowrap;

        }

        .status {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            flex-shrink: 0;

            padding: 7px 10px;

            border: 1px solid #d1fae5;

            border-radius: 20px;

            background: #ecfdf5;

            color: #047857;

            font-size: 10px;

            font-weight: 650;

        }

        .status-dot {

            width: 7px;

            height: 7px;

            border-radius: 50%;

            background: #10b981;

        }

        .messages {

            flex: 1;

            min-height: 0;

            overflow-y: auto;

            overflow-x: hidden;

            padding: 22px 20px;

            scrollbar-width: thin;

            scrollbar-color: #cbd5e1 transparent;

        }

        .messages-inner {

            width: 100%;

            max-width: 900px;

            min-width: 0;

            margin: 0 auto;

        }

        .ai-error, .success-message {

            margin-bottom: 22px;

            padding: 14px 16px;

            border-radius: 11px;

            font-size: 13px;

            line-height: 1.7;

            overflow-wrap: anywhere;

        }

        .ai-error {

            border: 1px solid #fed7aa;

            background: #fff7ed;

            color: #9a3412;

        }

        .success-message {

            border: 1px solid #a7f3d0;

            background: #ecfdf5;

            color: #047857;

        }

        .message {

            display: flex;

            width: 100%;

            min-width: 0;

            margin-bottom: 16px;

        }

        .message.user { justify-content: flex-end; }

        .message.assistant { justify-content: flex-start; }

        .message-content {

            width: fit-content;

            max-width: min(82%, 720px);

            min-width: 0;

            padding: 12px 15px;

            border: 1px solid transparent;

            border-radius: 13px;

            font-size: 13px;

            line-height: 1.7;

            /* Jangan pertahankan newline/indentasi HTML antar elemen header dan isi */

            white-space: normal;

            overflow-wrap: anywhere;

            word-break: break-word;

        }

        .message.user .message-content {

            border-color: #ea580c;

            border-bottom-right-radius: 4px;

            background: var(--orange);

            color: white;

        }

        .message.assistant .message-content {

            border-color: #e5eaf1;

            border-bottom-left-radius: 4px;

            background: white;

            color: #334155;

            box-shadow: 0 3px 12px rgb(15 23 42 / 3%);

        }

        .message-role {

            display: flex;

            align-items: center;

            gap: 7px;

            margin-bottom: 5px;

            font-size: 10px;

            font-weight: 800;

            letter-spacing: .5px;

            text-transform: uppercase;

            opacity: .8;

        }

        .message-role svg {

            width: 15px;

            height: 15px;

            flex-shrink: 0;

        }

        .message-body {

            min-width: 0;

            max-width: 100%;

            /* Tetap pertahankan baris baru pada isi pesan, bukan indentasi Blade */

            white-space: pre-wrap;

            overflow-wrap: anywhere;

            word-break: break-word;

        }

        .message-body a {

            color: #c2410c;

            text-decoration: underline;

            overflow-wrap: anywhere;

            word-break: break-all;

        }

        .message.user .message-body a { color: white; }

        .empty-chat {

            display: flex;

            align-items: center;

            justify-content: center;

            min-height: 100%;

            padding: 30px 10px;

            text-align: center;

            color: var(--muted);

        }

        .welcome-card {

            width: 100%;

            max-width: 510px;

            padding: 32px 24px;

            border: 1px solid #e5eaf1;

            border-radius: 20px;

            background: white;

            box-shadow: 0 12px 35px rgb(15 23 42 / 4%);

        }

        .welcome-icon {

            display: flex;

            align-items: center;

            justify-content: center;

            width: 58px;

            height: 58px;

            margin: 0 auto 20px;

            border-radius: 17px;

            background: #fff7ed;

            color: var(--orange);

        }

        .welcome-icon svg { width: 29px; height: 29px; }

        .welcome-card h2 {

            margin: 0 0 12px;

            color: #0f172a;

            font-size: 25px;

        }

        .welcome-card p {

            margin: 0;

            font-size: 13px;

            line-height: 1.8;

        }

        .welcome-caption {

            margin-top: 22px;

            color: #94a3b8;

            font-size: 11px;

        }

        .composer {

            flex-shrink: 0;

            padding: 16px 24px 12px;

            border-top: 1px solid var(--border);

            background: white;

        }

        .composer-inner {

            width: 100%;

            max-width: 900px;

            margin: 0 auto;

        }

        .composer-box {

            display: flex;

            align-items: flex-end;

            gap: 12px;

            width: 100%;

            min-width: 0;

            padding: 8px;

            border: 1px solid #dbe2ea;

            border-radius: 14px;

            background: #f8fafc;

        }

        .composer-box:focus-within {

            border-color: #fdba74;

            background: white;

            box-shadow: 0 0 0 3px rgb(249 115 22 / 9%);

        }

        .composer textarea {

            flex: 1;

            width: 100%;

            min-width: 0;

            min-height: 42px;

            max-height: 140px;

            padding: 12px 10px;

            resize: vertical;

            border: none;

            outline: none;

            background: transparent;

            color: var(--text);

            font-family: inherit;

            font-size: 13px;

            line-height: 1.6;

        }

        .send-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            flex-shrink: 0;

            min-height: 42px;

            padding: 0 17px;

            border: none;

            border-radius: 10px;

            background: var(--orange);

            color: white;

            cursor: pointer;

            font-size: 12px;

            font-weight: 700;

        }

        .send-button:hover { background: var(--orange-hover); }

        .send-button svg { width: 16px; height: 16px; }

        .composer-note {

            margin-top: 9px;

            color: #94a3b8;

            font-size: 10px;

            line-height: 1.6;

            text-align: center;

        }

        /* LOADING RAPID AI */

        .message-loading .message-content { min-width: 120px; }

        .typing-indicator {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            min-height: 18px;

            padding: 2px 0;

        }

        .typing-indicator span {

            width: 7px;

            height: 7px;

            border-radius: 50%;

            background: #94a3b8;

            animation: rapidTyping 1.2s infinite ease-in-out;

        }

        .typing-indicator span:nth-child(2) { animation-delay: .15s; }

        .typing-indicator span:nth-child(3) { animation-delay: .3s; }

        @keyframes rapidTyping {

            0%, 60%, 100% { transform: translateY(0); opacity: .45; }

            30% { transform: translateY(-4px); opacity: 1; }

        }

        .send-button:disabled { cursor: wait; opacity: .7; }

        /* MODAL KONFIRMASI HAPUS */

        .delete-modal-overlay {

            position: fixed;

            inset: 0;

            z-index: 9999;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 20px;

            background: rgb(15 23 42 / 58%);

            backdrop-filter: blur(5px);

            -webkit-backdrop-filter: blur(5px);

            opacity: 0;

            visibility: hidden;

            pointer-events: none;

            transition: opacity .22s, visibility .22s;

        }

        .delete-modal-overlay.is-visible {

            opacity: 1;

            visibility: visible;

            pointer-events: auto;

        }

        .delete-modal {

            width: 100%;

            max-width: 460px;

            padding: 25px;

            border: 1px solid var(--border);

            border-radius: 18px;

            background: white;

            box-shadow: 0 25px 80px rgb(15 23 42 / 25%);

            transform: translateY(12px) scale(.97);

            transition: transform .22s;

        }

        .delete-modal-overlay.is-visible .delete-modal {

            transform: translateY(0) scale(1);

        }

        .delete-modal-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 12px;

            margin-bottom: 18px;

        }

        .delete-modal-title {

            margin: 0;

            color: #111827;

            font-size: 21px;

            font-weight: 750;

        }

        .delete-modal-close {

            width: 34px;

            height: 34px;

            border: none;

            border-radius: 9px;

            background: transparent;

            color: #64748b;

            cursor: pointer;

            font-size: 23px;

        }

        .delete-modal-close:hover { background: #f1f5f9; }

        .delete-modal-description {

            margin: 0 0 15px;

            color: #64748b;

            font-size: 13px;

            line-height: 1.7;

        }

        .delete-modal-chat {

            display: flex;

            align-items: flex-start;

            gap: 12px;

            min-width: 0;

            padding: 14px;

            margin-bottom: 16px;

            border: 1px solid #fed7aa;

            border-radius: 11px;

            background: #fff7ed;

        }

        .delete-modal-chat-icon {

            display: flex;

            align-items: center;

            justify-content: center;

            flex-shrink: 0;

            width: 37px;

            height: 37px;

            border-radius: 10px;

            background: #ffedd5;

            color: var(--orange);

        }

        .delete-modal-chat-icon svg { width: 19px; height: 19px; }

        .delete-modal-chat-info { flex: 1; min-width: 0; }

        .delete-modal-chat-label {

            margin-bottom: 5px;

            color: #9a3412;

            font-size: 10px;

            font-weight: 750;

            letter-spacing: .7px;

            text-transform: uppercase;

        }

        .delete-modal-chat-title {

            color: #7c2d12;

            font-size: 13px;

            font-weight: 650;

            line-height: 1.6;

            overflow-wrap: anywhere;

            word-break: break-word;

        }

        .delete-modal-warning {

            margin: 0;

            color: #64748b;

            font-size: 12px;

            line-height: 1.8;

        }

        .delete-modal-actions {

            display: flex;

            justify-content: flex-end;

            flex-wrap: wrap;

            gap: 10px;

            margin-top: 24px;

            padding-top: 17px;

            border-top: 1px solid var(--border);

        }

        .delete-modal-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            min-height: 42px;

            padding: 0 17px;

            border-radius: 9px;

            cursor: pointer;

            font-size: 12px;

            font-weight: 700;

        }

        .delete-modal-button:disabled {

            cursor: wait;

            opacity: .7;

        }

        .delete-modal-cancel {

            border: 1px solid #d1d5db;

            background: white;

            color: #374151;

        }

        .delete-modal-cancel:hover { background: #f3f4f6; }

        .delete-modal-confirm {

            border: 1px solid var(--orange);

            background: var(--orange);

            color: white;

        }

        .delete-modal-confirm:hover {

            border-color: var(--orange-hover);

            background: var(--orange-hover);

        }

        .delete-modal-confirm svg { width: 16px; height: 16px; }

        body.modal-open { overflow: hidden; }

        @media (hover: none) {

            .delete-conversation-button { opacity: 1; }

        }

        @media (max-width: 900px) {

            .sidebar {

                flex-basis: 245px;

                padding-right: 12px;

                padding-left: 12px;

            }

            .topbar { padding-right: 20px; padding-left: 20px; }

            .messages { padding: 25px 18px; }

            .composer { padding: 16px 18px 12px; }

            .message-content { max-width: 90%; }

        }

        @media (max-width: 600px) {

            .sidebar {

                flex-basis: 190px;

                padding: 16px 9px 10px;

            }

            .brand { gap: 8px; padding: 0 4px; margin-bottom: 20px; }

            .brand-logo { width: 35px; height: 35px; font-size: 15px; }

            .brand-name { font-size: 12px; }

            .brand-caption { font-size: 7px; letter-spacing: .3px; }

            .new-chat { gap: 5px; padding: 8px 5px; font-size: 11px; }

            .conversation-item { padding-right: 3px; padding-left: 5px; font-size: 10px; }

            .delete-conversation-form { flex-basis: 27px; width: 27px; }

            .delete-conversation-button { width: 27px; }

            .topbar { min-height: 62px; gap: 8px; padding: 10px 12px; }

            .topbar-title { font-size: 12px; }

            .topbar-label { font-size: 9px; }

            .status { gap: 5px; padding: 6px; font-size: 8px; }

            .messages { padding: 18px 10px; }

            .message { margin-bottom: 17px; }

            .message-content { max-width: 96%; padding: 11px 12px; font-size: 12px; }

            .composer { padding: 10px; }

            .composer-box { gap: 5px; padding: 5px; }

            .composer textarea { padding: 10px 6px; font-size: 12px; }

            .send-button { min-height: 39px; padding: 0 10px; font-size: 11px; }

            .composer-note { font-size: 9px; }

            .account { gap: 6px; }

            .account-avatar { flex-basis: 31px; width: 31px; height: 31px; font-size: 11px; }

            .account-name { font-size: 10px; }

            .account-email { font-size: 8px; }

            .logout-button { font-size: 10px; }

            .delete-modal { padding: 20px; }

            .delete-modal-title { font-size: 19px; }

            .delete-modal-actions { flex-direction: column-reverse; }

            .delete-modal-button { width: 100%; }

        }

        @media (prefers-reduced-motion: reduce) {

            *, *::before, *::after { transition: none !important; }

        }

    
        /* ChatGPT-inspired plus menu for CV template upload */
        .composer-inner {
            position: relative;
        }

        .cv-upload-form[hidden] {
            display: none !important;
        }

        .cv-upload-form:not([hidden]) {
            position: absolute;
            z-index: 30;
            left: 0;
            bottom: calc(100% + 12px);
            width: min(420px, 100%);
            padding: 16px;
            margin: 0;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 16px 45px rgb(15 23 42 / 14%);
            animation: cvMenuIn .16s ease-out;
        }

        @keyframes cvMenuIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .cv-upload-panel-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }

        .cv-upload-panel-heading > div {
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 0;
        }

        .cv-upload-panel-heading strong {
            color: #0f172a;
            font-size: 13px;
            font-weight: 700;
        }

        .cv-upload-panel-heading span {
            color: #64748b;
            font-size: 11px;
            line-height: 1.5;
        }

        .cv-upload-close {
            flex: 0 0 28px;
            width: 28px;
            height: 28px;
            padding: 0;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: #64748b;
            font-size: 23px;
            line-height: 1;
            cursor: pointer;
        }

        .cv-upload-close:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .cv-upload-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            min-width: 0;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
        }

        .cv-upload-info {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .cv-attachment-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            color: #2563eb;
            background: #eff6ff;
        }

        .cv-attachment-icon svg {
            width: 20px;
            height: 20px;
        }

        .cv-upload-copy {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }

        .cv-upload-copy strong {
            overflow: hidden;
            color: #334155;
            font-size: 11px;
            font-weight: 650;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .cv-upload-copy span {
            color: #94a3b8;
            font-size: 10px;
        }

        .cv-choose-button {
            flex-shrink: 0;
            min-height: 32px;
            padding: 0 10px;
            border: 1px solid #dbe2ea;
            border-radius: 9px;
            background: #ffffff;
            color: #334155;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
        }

        .cv-choose-button:hover {
            border-color: #fdba74;
            color: #c2410c;
        }

        .cv-upload-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 38px;
            margin-top: 12px;
            padding: 0 14px;
            border: 1px solid #f97316;
            border-radius: 10px;
            background: #f97316;
            color: #ffffff;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
        }

        .cv-upload-button:hover:not(:disabled) {
            background: #ea580c;
        }

        .cv-upload-button:disabled {
            border-color: #fed7aa;
            background: #fed7aa;
            cursor: not-allowed;
        }

        .composer-attach-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 38px;
            width: 38px;
            height: 38px;
            margin: 2px 0 2px 2px;
            padding: 0;
            border: 0;
            border-radius: 50%;
            background: transparent;
            color: #64748b;
            cursor: pointer;
            transition: background .18s ease, color .18s ease, transform .18s ease;
        }

        .composer-attach-button svg {
            width: 22px;
            height: 22px;
        }

        .composer-attach-button:hover,
        .composer-attach-button[aria-expanded="true"] {
            background: #fff7ed;
            color: #ea580c;
        }

        .composer-attach-button[aria-expanded="true"] svg {
            transform: rotate(45deg);
        }

        .composer-attach-button svg {
            transition: transform .18s ease;
        }

        @media (max-width: 600px) {
            .cv-upload-form:not([hidden]) {
                width: 100%;
                padding: 12px;
            }

            .composer-attach-button {
                flex-basis: 32px;
                width: 32px;
                height: 36px;
            }

            .composer-attach-button svg {
                width: 20px;
                height: 20px;
            }
        }


        /* CV Tender Automation batch panel */
        .cv-batch-panel { margin: 0 0 22px; padding: 18px; border: 1px solid #fed7aa; border-radius: 14px; background: #fff; box-shadow: 0 3px 12px rgb(15 23 42 / 3%); }
        .cv-batch-panel-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
        .cv-batch-panel h3 { margin: 0 0 5px; color: #0f172a; font-size: 15px; }
        .cv-batch-panel p { margin: 0 0 12px; color: #64748b; font-size: 12px; line-height: 1.6; }
        .cv-batch-status { padding: 5px 8px; border-radius: 7px; background: #fff7ed; color: #9a3412; font-size: 10px; font-weight: 750; white-space: nowrap; }
        .cv-batch-folder-form label { display: block; margin-bottom: 7px; color: #334155; font-size: 12px; font-weight: 650; }
        .cv-batch-folder-row { display: flex; gap: 8px; }
        .cv-batch-folder-row input { flex: 1; min-width: 0; padding: 10px 11px; border: 1px solid #cbd5e1; border-radius: 9px; background: #fff; }
        .cv-batch-folder-row button, .cv-batch-confirm-button { padding: 10px 14px; border: 1px solid #f97316; border-radius: 9px; background: #f97316; color: #fff; cursor: pointer; font-size: 12px; font-weight: 700; }
        .cv-batch-folder-row button:hover, .cv-batch-confirm-button:hover { background: #ea580c; }
        .cv-batch-error { margin-top: 7px; color: #b91c1c; font-size: 12px; }
        .cv-batch-folder-label { margin: 12px 0; color: #64748b; font-size: 12px; }
        .cv-batch-file-list { display: grid; gap: 8px; margin: 12px 0; }
        .cv-batch-file { display: flex; align-items: center; gap: 10px; min-width: 0; padding: 10px; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc; }
        .cv-batch-file-icon { flex: 0 0 auto; padding: 6px; border-radius: 6px; background: #dbeafe; color: #1d4ed8; font-size: 9px; font-weight: 800; }
        .cv-batch-file-copy { display: flex; flex: 1; flex-direction: column; gap: 4px; min-width: 0; }
        .cv-batch-file-copy strong { overflow-wrap: anywhere; color: #334155; font-size: 12px; }
        .cv-batch-file-copy small { color: #64748b; font-size: 10px; }
        .cv-batch-file a { flex: 0 0 auto; color: #c2410c; font-size: 11px; }
        .cv-batch-progress { padding: 12px; margin: 12px 0; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc; }
        .cv-batch-progress-summary { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; color: #475569; font-size: 11px; }
        .cv-batch-progress-track { height: 7px; overflow: hidden; margin-top: 10px; border-radius: 99px; background: #e2e8f0; }
        .cv-batch-progress-track span { display: block; height: 100%; border-radius: inherit; background: #f97316; transition: width .25s ease; }
        .cv-batch-notice { margin: 10px 0 0 !important; color: #475569 !important; }
        .cv-batch-notice-success { color: #047857 !important; }
        .cv-batch-notice-error, .cv-batch-item-error { color: #b91c1c !important; overflow-wrap: anywhere; }
        .cv-batch-download { display: flex; justify-content: flex-end; margin-top: 14px; }
        .cv-batch-download-button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 40px; padding: 10px 14px; border: 1px solid #f97316; border-radius: 9px; background: #f97316; color: #fff !important; text-decoration: none; font-size: 12px; font-weight: 750; transition: background .2s ease, border-color .2s ease; }
        .cv-batch-download-button:hover { border-color: #ea580c; background: #ea580c; }
        .cv-batch-download-button svg { width: 17px; height: 17px; flex: 0 0 auto; }

        @media (max-width: 600px) { .cv-batch-panel { padding: 12px; } .cv-batch-folder-row { flex-direction: column; } .cv-batch-folder-row button { width: 100%; } .cv-batch-file { align-items: flex-start; flex-wrap: wrap; } .cv-batch-file a { margin-left: 42px; } }

    </style>

</head>

<body>

<div class="app">

    <aside class="sidebar">

        <div class="brand">

            <div class="brand-logo">R</div>

            <div class="brand-copy">

                <div class="brand-name">RAPID AI Office</div>

                <div class="brand-caption">Intelligent Workplace</div>

            </div>

        </div>

        <form method="POST" action="{{ route('ai.chat.store') }}">

            @csrf

            <button type="submit" class="new-chat">

                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"

                    stroke-width="2" stroke-linecap="round" aria-hidden="true">

                    <path d="M12 5v14M5 12h14"/>

                </svg>

                New Conversation

            </button>

        </form>

        <div class="sidebar-heading">Recent Chats</div>

        <div class="conversation-list">

            @forelse ($conversations as $item)

                <div class="conversation-row {{ $item->id === $conversation->id ? 'active' : '' }}">

                    <a href="{{ route('ai.chat.show', $item) }}"

                       class="conversation-item"

                       title="{{ $item->title ?: 'Untitled Conversation' }}">

                        {{ $item->title ?: 'Untitled Conversation' }}

                    </a>

                    <form method="POST"

                          action="{{ route('ai.chat.destroy', $item) }}"

                          class="delete-conversation-form"

                          data-chat-title="{{ $item->title ?: 'Untitled Conversation' }}">

                        @csrf

                        @method('DELETE')

                        <button type="submit"

                                class="delete-conversation-button"

                                title="Hapus percakapan"

                                aria-label="Hapus percakapan">

                            <svg viewBox="0 0 24 24" fill="none"

                                stroke="currentColor" stroke-width="1.8"

                                stroke-linecap="round" stroke-linejoin="round"

                                aria-hidden="true">

                                <path d="M3 6h18"/>

                                <path d="M8 6V4h8v2"/>

                                <path d="m19 6-1 14H6L5 6"/>

                                <path d="M10 11v5M14 11v5"/>

                            </svg>

                        </button>

                    </form>

                </div>

            @empty

                <div class="empty-conversations">

                    Belum ada percakapan.<br><br>

                    Klik <strong>New Conversation</strong> untuk memulai.

                </div>

            @endforelse

        </div>

        <div class="sidebar-footer">

            @auth

                <div class="account">

                    <div class="account-avatar">

                        {{ mb_strtoupper(mb_substr(auth()->user()->name ?? 'U', 0, 1)) }}

                    </div>

                    <div class="account-info">

                        <div class="account-name">{{ auth()->user()->name ?? 'User' }}</div>

                        <div class="account-email">{{ auth()->user()->email ?? '' }}</div>

                    </div>

                </div>

                <form method="POST" action="{{ route('logout') }}">

                    @csrf

                    <button type="submit" class="logout-button">

                        <svg viewBox="0 0 24 24" fill="none"

                            stroke="currentColor" stroke-width="1.8"

                            stroke-linecap="round" stroke-linejoin="round"

                            aria-hidden="true">

                            <path d="M10 17l5-5-5-5"/>

                            <path d="M15 12H3"/>

                            <path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/>

                        </svg>

                        Logout

                    </button>

                </form>

            @endauth

        </div>

    </aside>

    <main class="main">

        <header class="topbar">

            <div class="topbar-info">

                <div class="topbar-label">AI Workspace</div>

                <div class="topbar-title">

                    {{ $conversation->title ?: 'New Conversation' }}

                </div>

            </div>

            <div class="status"><span class="status-dot"></span> AI Assistant</div>

        </header>

        <section class="messages" id="messages">

            <div class="messages-inner">

                @if (session('ai_error'))

                    <div class="ai-error" role="alert">

                        <strong>AI sedang mengalami gangguan.</strong><br>

                        {{ session('ai_error') }}

                    </div>

                @endif

                @if (session('success'))

                    <div class="success-message" role="status">

                        {{ session('success') }}

                    </div>

                @endif

                
                @if (isset($cvBatch) && $cvBatch)
                    @php
                        $cvBatchItems = $cvBatch->items;
                        $cvTotal = $cvBatchItems->count();
                        $cvSuccess = $cvBatchItems->where('status', 'success')->count();
                        $cvFailed = $cvBatchItems->where('status', 'failed')->count();
                        $cvProcessing = $cvBatchItems->where('status', 'processing')->count();
                        $cvQueued = $cvBatchItems->where('status', 'queued')->count();
                        $cvFinished = $cvSuccess + $cvFailed;
                    @endphp

                    <section class="cv-batch-panel" aria-labelledby="cv-batch-title">
                        <div class="cv-batch-panel-header">
                            <div>
                                <h3 id="cv-batch-title">CV Tender Automation</h3>
                                <p>Template: {{ $cvBatch->template_original_name ?: 'Belum ditentukan' }}</p>
                            </div>
                            <span class="cv-batch-status">{{ str_replace('_', ' ', strtoupper($cvBatch->status)) }}</span>
                        </div>

                        @if ($cvBatch->status === 'draft')
                            <p>Pilih folder SharePoint yang berisi CV sumber. Untuk pengujian pertama, gunakan <strong>Format CV Rapid</strong>.</p>
                            <form method="POST" action="{{ route('ai.chat.cv-batches.folder', [$conversation, $cvBatch]) }}" class="cv-batch-folder-form">
                                @csrf
                                <label for="source_folder">Folder SharePoint (di dalam Documents)</label>
                                <div class="cv-batch-folder-row">
                                    <input id="source_folder" name="source_folder" type="text" value="{{ old('source_folder', 'Format CV Rapid') }}" required maxlength="500" placeholder="Contoh: Format CV Rapid">
                                    <button type="submit">Tampilkan CV</button>
                                </div>
                                @error('source_folder')
                                    <div class="cv-batch-error">{{ $message }}</div>
                                @enderror
                            </form>
                        @endif

                        @if ($cvBatchItems->isNotEmpty())
                            <div class="cv-batch-folder-label">
                                Folder sumber: <strong>{{ $cvBatch->source_folder }}</strong>
                            </div>

                            @if (in_array($cvBatch->status, ['confirmed', 'queued', 'processing', 'completed', 'completed_with_errors', 'failed'], true))
                                <div class="cv-batch-progress" role="status" aria-live="polite">
                                    <div class="cv-batch-progress-summary">
                                        <span><strong>{{ $cvFinished }}</strong> / {{ $cvTotal }} CV selesai</span>
                                        <span>{{ $cvSuccess }} berhasil · {{ $cvFailed }} gagal</span>
                                    </div>
                                    <div class="cv-batch-progress-track" aria-label="Kemajuan batch">
                                        <span style="width: {{ $cvTotal > 0 ? min(100, round(($cvFinished / $cvTotal) * 100)) : 0 }}%"></span>
                                    </div>

                                    @if (in_array($cvBatch->status, ['confirmed', 'queued'], true))
                                        <p class="cv-batch-notice">Batch sudah dikonfirmasi dan menunggu worker antrean untuk memproses dokumen.</p>
                                    @elseif ($cvBatch->status === 'processing')
                                        <p class="cv-batch-notice">Pemrosesan sedang berjalan. Anda dapat membuka kembali percakapan ini untuk melihat status terakhir.</p>
                                    @elseif ($cvBatch->status === 'completed')
                                        <p class="cv-batch-notice cv-batch-notice-success">Semua CV selesai diproses.</p>
                                    @elseif ($cvBatch->status === 'completed_with_errors')
                                        <p class="cv-batch-notice">Batch selesai, tetapi ada CV yang gagal. Periksa status setiap file di bawah.</p>
                                    @elseif ($cvBatch->status === 'failed')
                                        <p class="cv-batch-notice cv-batch-notice-error">Batch gagal diproses. Periksa status dan pesan kesalahan pada item yang gagal.</p>
                                    @endif
                                </div>
                            @endif

                            <div class="cv-batch-file-list">
                                @foreach ($cvBatchItems as $cvItem)
                                    <div class="cv-batch-file">
                                        <span class="cv-batch-file-icon">DOCX</span>
                                        <div class="cv-batch-file-copy">
                                            <strong>{{ $cvItem->source_filename }}</strong>
                                            <small>Status: {{ strtoupper(str_replace('_', ' ', $cvItem->status)) }}</small>
                                            @if ($cvItem->error_message)
                                                <small class="cv-batch-item-error">{{ $cvItem->error_message }}</small>
                                            @endif
                                            @if ($cvItem->output_filename)
                                                <small>Hasil: {{ $cvItem->output_filename }}</small>
                                            @endif
                                        </div>
                                        @if ($cvItem->source_web_url)
                                            <a href="{{ $cvItem->source_web_url }}" target="_blank" rel="noopener noreferrer">Buka sumber</a>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            @if ($cvBatch->status === 'awaiting_confirmation')
                                <p>Periksa daftar CV di atas. Dokumen belum diproses sampai Anda mengonfirmasi daftar ini.</p>
                                <form method="POST" action="{{ route('ai.chat.cv-batches.confirm', [$conversation, $cvBatch]) }}">
                                    @csrf
                                    <button type="submit" class="cv-batch-confirm-button">Konfirmasi &amp; Mulai Proses</button>
                                </form>
                            @elseif (in_array($cvBatch->status, ['confirmed', 'queued', 'processing'], true))
                                <p class="cv-batch-notice">Batch telah dikirim ke alur pemrosesan. Jangan mengirim konfirmasi berulang kali.</p>
                            @endif

                            @if (
                                in_array($cvBatch->status, ['completed', 'completed_with_errors'], true)
                                && $cvSuccess > 0
                            )
                                <div class="cv-batch-download">
                                    <a
                                        href="{{ route('ai.chat.cv-batches.download', [$conversation, $cvBatch]) }}"
                                        class="cv-batch-download-button"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none"
                                             stroke="currentColor" stroke-width="1.8"
                                             stroke-linecap="round" stroke-linejoin="round"
                                             aria-hidden="true">
                                            <path d="M12 3v12"/>
                                            <path d="m7 10 5 5 5-5"/>
                                            <path d="M5 20h14"/>
                                        </svg>
                                        Unduh ZIP Hasil CV ({{ $cvSuccess }} DOCX)
                                    </a>
                                </div>
                            @endif
                        @endif
                    </section>
                @endif

@forelse ($conversation->messages as $message)

                    <div class="message {{ $message->role === 'user' ? 'user' : 'assistant' }}">

                        <div class="message-content">

                            <div class="message-role">

                                @if ($message->role === 'user')

                                    <svg viewBox="0 0 24 24" fill="none"

                                        stroke="currentColor" stroke-width="1.8"

                                        stroke-linecap="round" stroke-linejoin="round"

                                        aria-hidden="true">

                                        <circle cx="12" cy="8" r="4"/>

                                        <path d="M5 21v-2a7 7 0 0 1 14 0v2"/>

                                    </svg>

                                    You

                                @else

                                    <svg viewBox="0 0 24 24" fill="none"

                                        stroke="currentColor" stroke-width="1.8"

                                        stroke-linecap="round" stroke-linejoin="round"

                                        aria-hidden="true">

                                        <rect x="4" y="7" width="16" height="13" rx="3"/>

                                        <path d="M9 7V4h6v3M9 12h.01M15 12h.01M9 16h6"/>

                                    </svg>

                                    RAPID AI

                                @endif

                            </div>

                            <div class="message-body">{{ $message->content }}</div>

                        </div>

                    </div>

                @empty

                    <div class="empty-chat">

                        <div class="welcome-card">

                            <div class="welcome-icon">

                                <svg viewBox="0 0 24 24" fill="none"

                                    stroke="currentColor" stroke-width="1.7"

                                    stroke-linecap="round" stroke-linejoin="round"

                                    aria-hidden="true">

                                    <rect x="4" y="7" width="16" height="13" rx="3"/>

                                    <path d="M9 7V4h6v3M9 12h.01M15 12h.01M9 16h6"/>

                                </svg>

                            </div>

                            <h2>Welcome to RAPID AI Office</h2>

                            <p>Asisten kerja digital Anda untuk membantu mencari dan membaca dokumen SharePoint yang tersedia. Mulai percakapan dengan mengetik pesan di bawah.</p>

                            <div class="welcome-caption">PT Rapid Infrastruktur Indonesia</div>

                        </div>

                    </div>

                @endforelse

            </div>

        </section>

        <footer class="composer">

            <div class="composer-inner">

                    @if ($errors->has('template'))
                        <div class="ai-error" role="alert">
                            {{ $errors->first('template') }}
                        </div>
                    @endif

                    <div
                        id="cv-template-form"
                        class="cv-upload-form"
                        aria-label="Upload Template CV Tender"
                        hidden
                    >
                        <div class="cv-upload-panel-heading">
                            <div>
                                <strong>Upload Template CV Tender</strong>
                                <span>Pilih format Word untuk konversi CV</span>
                            </div>
                            <button type="button" id="cv-upload-close" class="cv-upload-close" aria-label="Tutup menu upload">&times;</button>
                        </div>

                        <div class="cv-upload-row">
                            <div class="cv-upload-info">
                                <div class="cv-attachment-icon">
                                    <svg viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="1.8"
                                         stroke-linecap="round" stroke-linejoin="round"
                                         aria-hidden="true">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <path d="M14 2v6h6"/>
                                        <path d="M8 13h8M8 17h8"/>
                                    </svg>
                                </div>

                                <div class="cv-upload-copy">
                                    <strong id="cv-template-name">Belum ada file dipilih</strong>
                                    <span id="cv-template-status">DOCX · Maksimal 10 MB</span>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="cv-choose-button"
                                id="cv-choose-button"
                            >
                                Pilih file
                            </button>
                        </div>

                        <div class="cv-upload-panel-note">
                            File akan dikirim bersama instruksi saat Anda menekan Send.
                        </div>
                    </div>

             <form method="POST"

                      action="{{ route('ai.chat.send', $conversation) }}"

                      class="composer-box"
                       enctype="multipart/form-data">

                    @csrf

                    <button
                            type="button"
                            class="composer-attach-button"
                            id="cv-menu-toggle"
                            aria-label="Buka menu lampiran"
                            aria-expanded="false"
                            aria-controls="cv-template-form"
                            title="Lampiran dan fitur"
                        >
                            <svg viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" aria-hidden="true">
                                <path d="M12 5v14M5 12h14"/>
                            </svg>
                        </button>

                        <input
                            type="file"
                            name="template"
                            id="cv-template"
                            accept=".docx"
                            hidden
                        />

                        <textarea name="content"

                              id="chat-input"

                              placeholder="Tanyakan sesuatu kepada RAPID AI..."

                              aria-label="Pesan untuk RAPID AI"

                              rows="1"

                              maxlength="10000"

                              required>{{ old('content') }}</textarea>

                    <button type="submit" class="send-button">

                        Send

                        <svg viewBox="0 0 24 24" fill="none"

                            stroke="currentColor" stroke-width="2"

                            stroke-linecap="round" stroke-linejoin="round"

                            aria-hidden="true">

                            <path d="m22 2-7 20-4-9-9-4Z"/>

                            <path d="M22 2 11 13"/>

                        </svg>

                    </button>

                </form>

                <div class="composer-note">

                    RAPID AI dapat membuat kesalahan. Periksa kembali informasi penting sebelum digunakan.

                </div>

            </div>

        </footer>

    </main>

</div>

<!-- Modal hapus -->

<div class="delete-modal-overlay" id="delete-modal-overlay"

     aria-hidden="true">

    <section class="delete-modal" role="dialog" aria-modal="true"

             aria-labelledby="delete-modal-title"

             aria-describedby="delete-modal-description">

        <div class="delete-modal-header">

            <h2 class="delete-modal-title" id="delete-modal-title">

                Hapus percakapan?

            </h2>

            <button type="button" class="delete-modal-close"

                    id="delete-modal-close" aria-label="Tutup popup">&times;</button>

        </div>

        <p class="delete-modal-description" id="delete-modal-description">

            Anda akan menghapus percakapan berikut:

        </p>

        <div class="delete-modal-chat">

            <div class="delete-modal-chat-icon">

                <svg viewBox="0 0 24 24" fill="none"

                    stroke="currentColor" stroke-width="1.8"

                    stroke-linecap="round" stroke-linejoin="round"

                    aria-hidden="true">

                    <path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8

                    8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7

                    a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6

                    8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5Z"/>

                </svg>

            </div>

            <div class="delete-modal-chat-info">

                <div class="delete-modal-chat-label">Judul percakapan</div>

                <div class="delete-modal-chat-title" id="delete-modal-chat-title"></div>

            </div>

        </div>

        <p class="delete-modal-warning">

            Seluruh pesan dalam percakapan ini akan dihapus secara permanen.

            Tindakan ini tidak dapat dibatalkan.

        </p>

        <div class="delete-modal-actions">

            <button type="button"

                    class="delete-modal-button delete-modal-cancel"

                    id="delete-modal-cancel">Batal</button>

            <button type="button"

                    class="delete-modal-button delete-modal-confirm"

                    id="delete-modal-confirm">

                <svg viewBox="0 0 24 24" fill="none"

                    stroke="currentColor" stroke-width="1.8"

                    stroke-linecap="round" stroke-linejoin="round"

                    aria-hidden="true">

                    <path d="M3 6h18"/>

                    <path d="M8 6V4h8v2"/>

                    <path d="m19 6-1 14H6L5 6"/>

                    <path d="M10 11v5M14 11v5"/>

                </svg>

                Hapus percakapan

            </button>

        </div>

    </section>

</div>

<script>

(() => {

    const overlay = document.getElementById('delete-modal-overlay');

    const titleElement = document.getElementById('delete-modal-chat-title');

    const closeButton = document.getElementById('delete-modal-close');

    const cancelButton = document.getElementById('delete-modal-cancel');

    const confirmButton = document.getElementById('delete-modal-confirm');

    if (!overlay || !titleElement || !closeButton ||

        !cancelButton || !confirmButton) return;

    let selectedForm = null;

    let previousFocus = null;

    let closeTimer = null;

    function openModal(form) {

        if (closeTimer) clearTimeout(closeTimer);

        selectedForm = form;

        previousFocus = document.activeElement;

        titleElement.textContent =

            form.dataset.chatTitle || 'Untitled Conversation';

        overlay.classList.add('is-visible');

        overlay.setAttribute('aria-hidden', 'false');

        document.body.classList.add('modal-open');

        closeButton.focus();

    }

    function closeModal() {

        overlay.classList.remove('is-visible');

        overlay.setAttribute('aria-hidden', 'true');

        document.body.classList.remove('modal-open');

        selectedForm = null;

        if (closeTimer) clearTimeout(closeTimer);

        closeTimer = setTimeout(() => {

            if (previousFocus && previousFocus.isConnected) {

                previousFocus.focus();

            }

            previousFocus = null;

            closeTimer = null;

        }, 230);

    }

    document.querySelectorAll('.delete-conversation-form')

        .forEach(form => {

            form.addEventListener('submit', event => {

                event.preventDefault();

                openModal(form);

            });

        });

    closeButton.addEventListener('click', closeModal);

    cancelButton.addEventListener('click', closeModal);

    overlay.addEventListener('click', event => {

        if (event.target === overlay) closeModal();

    });

    document.addEventListener('keydown', event => {

        if (!overlay.classList.contains('is-visible')) return;

        if (event.key === 'Escape') closeModal();

        if (event.key === 'Tab') {

            const focusable = [closeButton, cancelButton, confirmButton];

            if (event.shiftKey && document.activeElement === focusable[0]) {

                event.preventDefault();

                focusable[2].focus();

            } else if (!event.shiftKey &&

                       document.activeElement === focusable[2]) {

                event.preventDefault();

                focusable[0].focus();

            }

        }

    });

    confirmButton.addEventListener('click', () => {

        if (!selectedForm) return;

        const form = selectedForm;

        confirmButton.disabled = true;

        confirmButton.textContent = 'Menghapus...';

        form.submit();

    });

})();

const messagesContainer = document.getElementById('messages');

if (messagesContainer) {

    messagesContainer.scrollTop = messagesContainer.scrollHeight;

}

const chatInput = document.getElementById('chat-input');

if (chatInput) {

    const resizeInput = () => {

        chatInput.style.height = 'auto';

        chatInput.style.height =

            Math.min(chatInput.scrollHeight, 140) + 'px';

    };

    chatInput.addEventListener('input', resizeInput);

    resizeInput();

}

// Optimistic UI: tampilkan pesan pengguna dan loading sebelum respons Laravel kembali.

const chatForm = document.querySelector('.composer-box');

const sendButton = chatForm ? chatForm.querySelector('.send-button') : null;

if (chatForm && chatInput && sendButton && messagesContainer) {

    let chatSubmitting = false;

    chatForm.addEventListener('submit', function (event) {

        if (chatSubmitting) {

            event.preventDefault();

            return;

        }

        const messageText = chatInput.value.trim();

        if (!messageText) {

            event.preventDefault();

            chatInput.focus();

            return;

        }

        // Salin isi pesan ke hidden input supaya teks tetap terkirim saat textarea dikosongkan.

        const hiddenContent = document.createElement('input');

        hiddenContent.type = 'hidden';

        hiddenContent.name = 'content';

        hiddenContent.value = messageText;

        chatForm.appendChild(hiddenContent);

        const inner = messagesContainer.querySelector('.messages-inner');

        if (!inner) return;

        const emptyChat = inner.querySelector('.empty-chat');

        if (emptyChat) emptyChat.remove();

        // Bubble pengguna.

        const userMessage = document.createElement('div');

        userMessage.className = 'message user';

        const userContent = document.createElement('div');

        userContent.className = 'message-content';

        const userRole = document.createElement('div');

        userRole.className = 'message-role';

        userRole.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/></svg> You';

        const userBody = document.createElement('div');

        userBody.className = 'message-body';

        userBody.textContent = messageText;

        userContent.append(userRole, userBody);

        userMessage.appendChild(userContent);

        inner.appendChild(userMessage);

        // Bubble RAPID AI dengan tiga titik bergerak.

        const loadingMessage = document.createElement('div');

        loadingMessage.className = 'message assistant message-loading';

        loadingMessage.id = 'rapid-ai-loading';

        const loadingContent = document.createElement('div');

        loadingContent.className = 'message-content';

        const loadingRole = document.createElement('div');

        loadingRole.className = 'message-role';

        loadingRole.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="7" width="16" height="13" rx="3"/><path d="M9 7V4h6v3M9 12h.01M15 12h.01M9 16h6"/></svg> RAPID AI';

        const typing = document.createElement('div');

        typing.className = 'typing-indicator';

        typing.setAttribute('role', 'status');

        typing.setAttribute('aria-label', 'RAPID AI sedang memproses');

        typing.innerHTML = '<span></span><span></span><span></span>';

        loadingContent.append(loadingRole, typing);

        loadingMessage.appendChild(loadingContent);

        inner.appendChild(loadingMessage);

        // Kosongkan kolom, cegah klik ganda, lalu biarkan form POST normal ke Laravel.

        chatInput.value = '';

        chatInput.disabled = true;

        sendButton.disabled = true;

        sendButton.textContent = 'Memproses...';

        chatSubmitting = true;

        messagesContainer.scrollTop = messagesContainer.scrollHeight;

    });

}


// CV template picker: isolated from the existing chat and delete-modal scripts.
(() => {
    const fileInput = document.getElementById('cv-template');
    const chooseButton = document.getElementById('cv-choose-button');
    const uploadButton = document.getElementById('cv-upload-button');
    const fileName = document.getElementById('cv-template-name');
    const fileStatus = document.getElementById('cv-template-status');
    const templateForm = document.getElementById('cv-template-form');

    if (!fileInput || !chooseButton || !uploadButton ||
        !fileName || !fileStatus || !templateForm) return;

    chooseButton.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', () => {
        const file = fileInput.files && fileInput.files[0];

        if (!file) {
            fileName.textContent = 'Lampirkan template CV tender';
            fileStatus.textContent = 'Pilih file Word (.docx), maksimal 10 MB';
            uploadButton.disabled = true;
            return;
        }

        fileName.textContent = file.name;
        const sizeInMb = file.size / (1024 * 1024);
        fileStatus.textContent = `File dipilih · ${sizeInMb.toFixed(2)} MB`;
        uploadButton.disabled = !file.name.toLowerCase().endsWith('.docx')
            || file.size > 10 * 1024 * 1024;
    });

    templateForm.addEventListener('submit', (event) => {
        const file = fileInput.files && fileInput.files[0];

        if (!file || !file.name.toLowerCase().endsWith('.docx')
            || file.size > 10 * 1024 * 1024) {
            event.preventDefault();
            fileStatus.textContent = 'Pilih file .docx yang berukuran maksimal 10 MB.';
            uploadButton.disabled = true;
            return;
        }

        uploadButton.disabled = true;
        uploadButton.textContent = 'Mengunggah...';
    });
})();


// CV template attachment: file is sent with the existing chat form.
(() => {
    const menu = document.getElementById('cv-template-form');
    const toggle = document.getElementById('cv-menu-toggle');
    const closeButton = document.getElementById('cv-upload-close');
    const fileInput = document.getElementById('cv-template');
    const chooseButton = document.getElementById('cv-choose-button');
    const fileName = document.getElementById('cv-template-name');
    const fileStatus = document.getElementById('cv-template-status');
    const chatForm = document.querySelector('.composer-box');

    if (!menu || !toggle || !closeButton || !fileInput ||
        !chooseButton || !fileName || !fileStatus || !chatForm) return;

    const openMenu = () => {
        menu.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
    };

    const closeMenu = () => {
        menu.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', () => {
        if (menu.hidden) openMenu();
        else closeMenu();
    });

    closeButton.addEventListener('click', closeMenu);
    chooseButton.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', () => {
        const file = fileInput.files && fileInput.files[0];

        if (!file) {
            fileName.textContent = 'Belum ada file dipilih';
            fileStatus.textContent = 'DOCX · Maksimal 10 MB';
            return;
        }

        fileName.textContent = file.name;
        const sizeInMb = file.size / (1024 * 1024);
        const valid = file.name.toLowerCase().endsWith('.docx') &&
            file.size <= 10 * 1024 * 1024;

        fileStatus.textContent = valid
            ? `Lampiran siap dikirim · ${sizeInMb.toFixed(2)} MB`
            : 'Pilih file .docx maksimal 10 MB.';
        if (valid) closeMenu();
    });

    chatForm.addEventListener('submit', (event) => {
        const file = fileInput.files && fileInput.files[0];
        if (file && (!file.name.toLowerCase().endsWith('.docx') ||
            file.size > 10 * 1024 * 1024)) {
            event.preventDefault();
            openMenu();
            fileStatus.textContent = 'Pilih file .docx maksimal 10 MB.';
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !menu.hidden) closeMenu();
    });

    document.addEventListener('click', (event) => {
        if (!menu.hidden && !menu.contains(event.target) &&
            !toggle.contains(event.target)) {
            closeMenu();
        }
    });
})();

</script>

</body>

</html>
