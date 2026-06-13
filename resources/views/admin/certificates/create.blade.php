<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Certificate</title>
  @vite(['resources/js/app.js'])
  <style>
    :root {
      --bg-a: #eaf2ff;
      --bg-b: #f4fbf8;
      --card: #ffffff;
      --card-border: #d9e4f1;
      --text: #0f172a;
      --muted: #52637a;
      --label: #1e293b;
      --accent: #0d4f8c;
      --accent-2: #0f8b8d;
      --focus: rgba(13, 79, 140, 0.18);
      --danger-bg: #fff1f2;
      --danger-border: #fecdd3;
      --danger-text: #9f1239;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 24px;
      color: var(--text);
      font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
      background:
        radial-gradient(1000px 360px at 0% -10%, #d8e6ff 0%, transparent 58%),
        radial-gradient(900px 420px at 100% -20%, #d7f4ef 0%, transparent 60%),
        linear-gradient(145deg, var(--bg-a) 0%, var(--bg-b) 100%);
      min-height: 100vh;
    }

    .shell {
      width: 100%;
      max-width: 1280px; /* Widened to accommodate the side-by-side live preview */
      margin: 0 auto;
      border: 1px solid var(--card-border);
      border-radius: 22px;
      background: var(--card);
      box-shadow: 0 22px 60px rgba(15, 23, 42, 0.12);
      overflow: hidden;
    }

    .hero {
      padding: 24px 24px 18px;
      border-bottom: 1px solid #e4edf7;
      background: linear-gradient(130deg, #f7fbff 0%, #ecf6ff 52%, #eefcf8 100%);
      display: flex;
      justify-content: space-between;
      gap: 14px;
      align-items: flex-start;
    }

    .eyebrow {
      margin: 0;
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      border: 1px solid #b9d6ef;
      background: #e8f3ff;
      color: #0b4c8c;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.07em;
      text-transform: uppercase;
      padding: 5px 10px;
    }

    h1 {
      margin: 10px 0 0;
      font-size: clamp(28px, 3.1vw, 36px);
      font-weight: 900;
      letter-spacing: -0.03em;
      line-height: 1.05;
    }

    .subtitle {
      margin: 10px 0 0;
      max-width: 600px;
      color: var(--muted);
      font-size: 14px;
      line-height: 1.45;
    }

    .hero-actions {
      margin-top: 6px;
      display: flex;
      justify-content: flex-end;
      position: relative;
      z-index: 30;
    }

    .content {
      padding: 20px 24px 24px;
    }

    .err {
      border: 1px solid var(--danger-border);
      background: var(--danger-bg);
      color: var(--danger-text);
      border-radius: 14px;
      padding: 12px 14px;
      margin: 0 0 16px;
      font-size: 14px;
    }

    .err ul {
      margin: 8px 0 0 18px;
      padding: 0;
    }

    .main-layout {
      display: grid;
      grid-template-columns: 1.1fr 0.9fr; /* Form gets the wider column; preview stays inside the layout */
      gap: 32px;
      align-items: start;
    }

    .form-stack {
      display: grid;
      gap: 14px;
    }

    @media (max-width: 1024px) {
      .main-layout {
        grid-template-columns: 1fr;
      }
    }

    .panel {
      border: 1px solid #dde8f4;
      border-radius: 16px;
      background: #fbfdff;
      padding: 14px;
    }

    .panel-title {
      margin: 0 0 10px;
      font-size: 13px;
      font-weight: 900;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #0f3e63;
    }

    .row {
      margin-bottom: 12px;
    }

    .row:last-child {
      margin-bottom: 0;
    }

    label {
      display: block;
      color: var(--label);
      font-size: 12px;
      font-weight: 800;
      letter-spacing: 0.01em;
      margin-bottom: 6px;
    }

    input,
    select {
      width: 100%;
      border: 1px solid #bdd0e2;
      border-radius: 12px;
      background: #ffffff;
      color: #0f172a;
      font-size: 14px;
      font-weight: 600;
      padding: 11px 12px;
      outline: none;
      transition: border-color 140ms ease, box-shadow 140ms ease;
    }

    input:focus,
    select:focus {
      border-color: #67a2d1;
      box-shadow: 0 0 0 4px var(--focus);
    }

    .grid-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
    }

    .template-choice-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
      margin-bottom: 12px;
    }

    .template-choice {
      display: block;
      cursor: pointer;
    }

    .template-choice-input {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }

    .template-choice-card {
      position: relative;
      min-height: 118px;
      display: grid;
      gap: 6px;
      padding: 16px 18px 16px 50px;
      border: 1px solid #d7e4f1;
      border-radius: 14px;
      background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
      transition: border-color 140ms ease, box-shadow 140ms ease, transform 140ms ease, background 140ms ease;
    }

    .template-choice-card::before,
    .template-choice-card::after {
      content: "";
      position: absolute;
      border-radius: 999px;
      transition: transform 140ms ease, border-color 140ms ease, background 140ms ease;
    }

    .template-choice-card::before {
      top: 19px;
      left: 18px;
      width: 18px;
      height: 18px;
      border: 1.5px solid #9fb9d3;
      background: #ffffff;
      box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
    }

    .template-choice-card::after {
      top: 24px;
      left: 23px;
      width: 8px;
      height: 8px;
      background: #0d4f8c;
      transform: scale(0);
    }

    .template-choice-input:checked + .template-choice-card {
      border-color: #72a8d4;
      background: linear-gradient(180deg, #fafdff 0%, #edf6ff 100%);
      box-shadow: 0 0 0 4px rgba(13, 79, 140, 0.08), 0 10px 24px rgba(15, 23, 42, 0.08);
      transform: translateY(-1px);
    }

    .template-choice-input:checked + .template-choice-card::before {
      border-color: #0d4f8c;
      background: #e8f3ff;
    }

    .template-choice-input:checked + .template-choice-card::after {
      transform: scale(1);
    }

    .template-choice-input:focus-visible + .template-choice-card {
      box-shadow: 0 0 0 4px var(--focus);
    }

    .template-choice-kicker {
      color: #5f738a;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }

    .template-choice-title {
      color: #102b46;
      font-size: 16px;
      font-weight: 900;
      line-height: 1.15;
    }

    .template-choice-meta {
      color: #5d7188;
      font-size: 12.5px;
      line-height: 1.45;
      max-width: 30ch;
    }

    .upload-shell {
      display: grid;
      gap: 10px;
    }

    .upload-surface {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      padding: 14px 16px;
      border: 1px dashed #bdd0e2;
      border-radius: 14px;
      background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
      cursor: pointer;
      transition: border-color 140ms ease, box-shadow 140ms ease, transform 140ms ease, background 140ms ease;
    }

    .upload-surface:hover {
      border-color: #72a8d4;
      box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
      transform: translateY(-1px);
    }

    .upload-surface:focus-within {
      border-color: #67a2d1;
      box-shadow: 0 0 0 4px var(--focus);
    }

    .upload-surface-main {
      display: grid;
      gap: 4px;
      min-width: 0;
    }

    .upload-surface-title {
      color: #102b46;
      font-size: 14px;
      font-weight: 900;
      line-height: 1.2;
    }

    .upload-surface-hint {
      color: #61748b;
      font-size: 12.5px;
      line-height: 1.35;
    }

    .upload-surface-button {
      flex-shrink: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 122px;
      padding: 10px 14px;
      border-radius: 10px;
      background: linear-gradient(180deg, #185c9b 0%, #0d4f8c 100%);
      color: #ffffff;
      font-size: 11px;
      font-weight: 900;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      box-shadow: 0 10px 20px rgba(13, 79, 140, 0.18);
    }

    .upload-meta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
      color: #61748b;
      font-size: 12.5px;
      line-height: 1.4;
    }

    .upload-file-name {
      color: #16324d;
      font-weight: 800;
      max-width: 100%;
      word-break: break-word;
    }

    .file-input-hidden {
      position: absolute;
      width: 1px;
      height: 1px;
      padding: 0;
      margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      white-space: nowrap;
      border: 0;
    }

    .upload-guidance {
      margin-top: 10px;
      padding: 12px 14px;
      border: 1px solid #d7e5f2;
      border-radius: 12px;
      background: #f8fbff;
    }

    @media (max-width: 760px) {
      .template-choice-grid {
        grid-template-columns: 1fr;
      }

      .upload-surface {
        align-items: flex-start;
        flex-direction: column;
      }

      .upload-surface-button {
        min-width: 100%;
      }
    }

    .preview-container {
      display: flex;
      flex-direction: column;
      gap: 16px;
      position: sticky;
      top: 24px;
    }

    .preview-frame {
      position: relative;
      width: 100%;
      max-width: 100%;
      padding: 8px;
      border-radius: 18px;
      background:
        linear-gradient(135deg, #0d4f8c 0%, #0f8b8d 100%);
      box-shadow:
        0 24px 48px -28px rgba(13, 79, 140, 0.4),
        0 10px 20px -12px rgba(15, 139, 141, 0.2);
    }

    .preview-frame::before {
      content: "";
      position: absolute;
      inset: 1px;
      border-radius: 17px;
      background: linear-gradient(180deg, #ffffff 0%, #f6fbff 100%);
      pointer-events: none;
    }

    .preview-box {
      position: relative;
      width: 100%;
      max-width: 100%;
      aspect-ratio: 1.414 / 1; /* Landscape A4-like proportion */
      max-height: 520px; /* Cap so the preview never grows past the layout's visual rhythm */
      background:
        radial-gradient(120% 80% at 50% 0%, #ffffff 0%, #f3f8ff 70%, #e9f2fb 100%);
      border-radius: 12px;
      overflow: hidden;
      box-shadow:
        inset 0 0 0 1px rgba(15, 62, 99, 0.08),
        0 12px 30px -20px rgba(15, 23, 42, 0.3);
    }

    .preview-pdf-frame {
      display: none; /* hidden until first live render; prevents stray chrome PDF embed */
      width: 100%;
      height: 100%;
      border: none;
      position: absolute;
      top: 0;
      left: 0;
      z-index: 1;
      background: #ffffff;
    }

    .preview-pdf-frame.is-ready {
      display: block;
    }

    .preview-idle {
      position: absolute;
      inset: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      gap: 14px;
      padding: 32px;
      color: #2c4869;
      z-index: 1;
    }

    .preview-idle[hidden] {
      display: none;
    }

    .preview-idle-icon {
      width: 64px;
      height: 64px;
      border-radius: 18px;
      display: grid;
      place-items: center;
      background: linear-gradient(135deg, rgba(13, 79, 140, 0.12), rgba(15, 139, 141, 0.12));
      color: #0d4f8c;
      box-shadow: inset 0 0 0 1px rgba(13, 79, 140, 0.18);
    }

    .preview-idle-icon svg {
      width: 32px;
      height: 32px;
    }

    .preview-idle-title {
      margin: 0;
      font-size: 18px;
      font-weight: 800;
      letter-spacing: -0.01em;
      color: #0f3e63;
    }

    .preview-idle-text {
      margin: 0;
      max-width: 360px;
      font-size: 13.5px;
      line-height: 1.55;
      color: #4a607a;
    }

    .preview-status {
      border: 1px solid #d7e5f2;
      border-radius: 12px;
      background: linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
      color: #3f5268;
      padding: 10px 14px;
      font-size: 12.5px;
      line-height: 1.45;
      display: flex;
      align-items: flex-start;
      gap: 10px;
    }

    .preview-status::before {
      content: "";
      flex: 0 0 auto;
      width: 8px;
      height: 8px;
      margin-top: 6px;
      border-radius: 999px;
      background: #0f8b8d;
      box-shadow: 0 0 0 4px rgba(15, 139, 141, 0.18);
    }

    .preview-status.error {
      border-color: #fecdd3;
      background: linear-gradient(180deg, #fff1f2 0%, #ffe4e6 100%);
      color: #9f1239;
    }

    .preview-status.error::before {
      background: #e11d48;
      box-shadow: 0 0 0 4px rgba(225, 29, 72, 0.18);
    }

    .preview-status[hidden] {
      display: none;
    }

    /* ---- AI reminder note ---- */
    .ai-reminder {
      display: flex;
      gap: 12px;
      padding: 14px 16px;
      border-radius: 14px;
      border: 1px solid #cfe6e6;
      background: linear-gradient(135deg, #f3fbfa 0%, #eef6ff 100%);
    }

    .ai-reminder-spark {
      flex: none;
      display: inline-grid;
      place-items: center;
      width: 30px;
      height: 30px;
      border-radius: 9px;
      background: linear-gradient(135deg, #0d4f8c 0%, #16a89b 100%);
      box-shadow: 0 6px 14px rgba(15, 139, 141, 0.28);
      font-size: 15px;
    }

    .ai-reminder-body {
      min-width: 0;
    }

    .ai-reminder-title {
      margin: 2px 0 4px;
      font-size: 13px;
      font-weight: 800;
      color: #0f2f54;
    }

    .ai-reminder-text {
      margin: 4px 0;
      font-size: 12.5px;
      line-height: 1.5;
      color: #45607a;
    }

    .ai-reminder-text strong {
      color: #0d4f8c;
      font-weight: 800;
    }

    .ai-reminder-list {
      margin: 6px 0;
      padding: 0;
      list-style: none;
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }

    .ai-reminder-list li {
      font-size: 11.5px;
      font-weight: 700;
      color: #20617a;
      background: #ffffff;
      border: 1px solid #cfe2e6;
      border-radius: 999px;
      padding: 3px 10px;
    }

    .preview-loading {
      position: absolute;
      inset: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 12px;
      background: rgba(246, 251, 255, 0.88);
      color: #0f3e63;
      font-size: 13px;
      font-weight: 700;
      letter-spacing: 0.01em;
      z-index: 3;
      opacity: 0;
      pointer-events: none;
      transition: opacity 180ms ease;
      backdrop-filter: blur(6px);
    }

    .preview-loading.visible {
      opacity: 1;
    }

    .preview-spinner {
      width: 36px;
      height: 36px;
      border-radius: 999px;
      border: 3px solid rgba(13, 79, 140, 0.18);
      border-top-color: #0d4f8c;
      animation: preview-spin 0.8s linear infinite;
    }

    @keyframes preview-spin {
      to {
        transform: rotate(360deg);
      }
    }

    @media (max-width: 1024px) {
      .preview-box {
        max-height: 460px;
      }
    }

    @media (max-width: 640px) {
      .preview-box {
        max-height: 380px;
      }
    }

    .caption-editor {
      position: relative;
      border: 1px solid #cdddee;
      border-radius: 14px;
      background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85);
      /* overflow stays visible so the AI assistant popover is not clipped;
         the toolbar rounds its own top corners to keep the editor's frame. */
    }

    .caption-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 10px 12px;
      background: linear-gradient(180deg, #f9fcff 0%, #eef5fb 100%);
      border-bottom: 1px solid #d9e7f4;
      border-top-left-radius: 13px;
      border-top-right-radius: 13px;
    }

    .caption-toolbar-group {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-wrap: wrap;
    }

    .caption-toolbar-divider {
      width: 1px;
      height: 22px;
      background: #c9d8e7;
      margin: 0 2px;
    }

    .caption-toolbar-label {
      color: #52637a;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      white-space: nowrap;
    }

    .caption-btn {
      min-width: 34px;
      height: 34px;
      border: 1px solid #c7d7e7;
      background: #ffffff;
      border-radius: 9px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0 10px;
      font-size: 14px;
      font-weight: 700;
      color: #37506b;
      transition: transform 0.15s ease, border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease, color 0.15s ease;
      box-shadow: 0 1px 1px rgba(15, 23, 42, 0.04);
    }

    .caption-btn:hover {
      background: #f4f8fc;
      border-color: #94aecd;
      transform: translateY(-1px);
    }

    .caption-btn:focus-visible {
      outline: none;
      border-color: #67a2d1;
      box-shadow: 0 0 0 4px rgba(13, 79, 140, 0.12);
    }

    .caption-btn.active {
      background: linear-gradient(180deg, #185c9b 0%, #0d4f8c 100%);
      color: #ffffff;
      border-color: #0d4f8c;
      box-shadow: 0 8px 18px rgba(13, 79, 140, 0.18);
    }

    .caption-editor-surface {
      width: 100%;
      min-height: 148px;
      max-height: 260px;
      overflow-y: auto;
      padding: 14px 16px 12px;
      color: #1e293b;
      font-size: 15px;
      line-height: 1.55;
      font-weight: 500;
      outline: none;
      font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
      background: transparent;
      text-align: center;
      white-space: pre-wrap;
      word-break: break-word;
      caret-color: #0d4f8c;
    }

    .caption-editor-surface strong,
    .caption-editor-surface b {
      font-weight: 800;
    }

    .caption-editor-surface em,
    .caption-editor-surface i {
      font-style: italic;
    }

    .caption-editor-surface.is-empty::before {
      content: attr(data-placeholder);
      color: #7b8ca4;
      font-weight: 500;
      pointer-events: none;
    }

    .caption-source-input {
      display: none;
    }

    .caption-footnote {
      margin-top: 10px;
      color: #60748a;
      font-size: 12.5px;
      line-height: 1.45;
    }

    /* ---- AI Caption Assistant ---- */
    .caption-ai-cluster {
      position: relative;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .caption-ai-btn {
      position: relative;
      display: inline-flex;
      align-items: center;
      gap: 7px;
      height: 34px;
      padding: 0 14px;
      border: 0;
      border-radius: 999px;
      cursor: pointer;
      font-size: 12.5px;
      font-weight: 800;
      letter-spacing: 0.01em;
      color: #ffffff;
      background: linear-gradient(120deg, #0d4f8c 0%, #0f8b8d 55%, #16a89b 100%);
      background-size: 180% 180%;
      box-shadow: 0 8px 18px rgba(13, 79, 140, 0.28), inset 0 1px 0 rgba(255, 255, 255, 0.28);
      transition: transform 0.16s ease, box-shadow 0.16s ease, filter 0.16s ease;
      white-space: nowrap;
    }

    .caption-ai-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 12px 24px rgba(13, 79, 140, 0.34), inset 0 1px 0 rgba(255, 255, 255, 0.32);
      animation: captionAiSheen 2.4s ease infinite;
    }

    .caption-ai-btn:focus-visible {
      outline: none;
      box-shadow: 0 0 0 4px rgba(15, 139, 141, 0.28);
    }

    .caption-ai-btn:disabled {
      cursor: progress;
      filter: saturate(0.8) brightness(0.98);
    }

    .caption-ai-btn .caption-ai-spark {
      font-size: 14px;
      line-height: 1;
      filter: drop-shadow(0 0 4px rgba(255, 255, 255, 0.5));
    }

    .caption-ai-btn.is-loading .caption-ai-spark {
      display: none;
    }

    .caption-ai-spinner {
      display: none;
      width: 14px;
      height: 14px;
      border: 2px solid rgba(255, 255, 255, 0.45);
      border-top-color: #ffffff;
      border-radius: 50%;
      animation: captionAiSpin 0.65s linear infinite;
    }

    .caption-ai-btn.is-loading .caption-ai-spinner {
      display: inline-block;
    }

    @keyframes captionAiSpin {
      to { transform: rotate(360deg); }
    }

    @keyframes captionAiSheen {
      0% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }

    .caption-ai-panel {
      position: absolute;
      top: calc(100% + 10px);
      right: 0;
      z-index: 40;
      width: 320px;
      max-width: calc(100vw - 40px);
      padding: 16px;
      border-radius: 16px;
      background: linear-gradient(180deg, #ffffff 0%, #f6fbff 100%);
      border: 1px solid #d7e6f3;
      box-shadow: 0 24px 48px rgba(13, 47, 84, 0.22), 0 2px 8px rgba(13, 47, 84, 0.08);
      opacity: 0;
      transform: translateY(-6px) scale(0.98);
      transform-origin: top right;
      pointer-events: none;
      transition: opacity 0.16s ease, transform 0.16s ease;
    }

    .caption-ai-panel.open {
      opacity: 1;
      transform: translateY(0) scale(1);
      pointer-events: auto;
    }

    .caption-ai-panel::before {
      content: "";
      position: absolute;
      top: -7px;
      right: 26px;
      width: 13px;
      height: 13px;
      background: #ffffff;
      border-left: 1px solid #d7e6f3;
      border-top: 1px solid #d7e6f3;
      transform: rotate(45deg);
    }

    .caption-ai-panel-head {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      margin-bottom: 14px;
    }

    .caption-ai-orb {
      flex: none;
      width: 34px;
      height: 34px;
      border-radius: 11px;
      display: grid;
      place-items: center;
      font-size: 17px;
      color: #ffffff;
      background: linear-gradient(135deg, #0d4f8c 0%, #16a89b 100%);
      box-shadow: 0 6px 14px rgba(15, 139, 141, 0.32);
    }

    .caption-ai-panel-title {
      margin: 0;
      font-size: 14px;
      font-weight: 900;
      color: #0f2f54;
      letter-spacing: -0.01em;
    }

    .caption-ai-panel-sub {
      margin: 2px 0 0;
      font-size: 11.5px;
      line-height: 1.4;
      color: #61748a;
    }

    .caption-ai-field-label {
      display: block;
      margin: 0 0 8px;
      font-size: 10.5px;
      font-weight: 800;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: #6a7d93;
    }

    .caption-ai-context {
      width: 100%;
      box-sizing: border-box;
      resize: vertical;
      min-height: 62px;
      max-height: 140px;
      padding: 8px 10px;
      margin-bottom: 14px;
      border: 1px solid #cfdded;
      border-radius: 7px;
      background: #f7fafd;
      font-size: 12px;
      line-height: 1.5;
      color: #243040;
      font-family: inherit;
      transition: border-color 0.15s;
    }
    .caption-ai-context:focus {
      outline: none;
      border-color: #4d90d5;
      background: #ffffff;
    }
    .caption-ai-context::placeholder {
      color: #9aafbf;
    }

    .caption-ai-tones {
      display: flex;
      flex-wrap: wrap;
      gap: 7px;
      margin-bottom: 14px;
    }

    .caption-ai-tone {
      border: 1px solid #cfdded;
      background: #ffffff;
      color: #3c5670;
      border-radius: 999px;
      padding: 6px 12px;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
      transition: border-color 0.14s ease, background 0.14s ease, color 0.14s ease, transform 0.14s ease;
    }

    .caption-ai-tone:hover {
      border-color: #8fb2d4;
      transform: translateY(-1px);
    }

    .caption-ai-tone.active {
      background: linear-gradient(135deg, #0d4f8c 0%, #0f8b8d 100%);
      border-color: transparent;
      color: #ffffff;
      box-shadow: 0 6px 14px rgba(13, 79, 140, 0.22);
    }

    .caption-ai-generate {
      width: 100%;
      border: 0;
      border-radius: 12px;
      padding: 11px 14px;
      font-size: 13px;
      font-weight: 800;
      color: #ffffff;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      background: linear-gradient(120deg, #0d4f8c 0%, #0f8b8d 100%);
      box-shadow: 0 10px 20px rgba(13, 79, 140, 0.26);
      transition: transform 0.15s ease, filter 0.15s ease;
    }

    .caption-ai-generate:hover {
      transform: translateY(-1px);
      filter: brightness(1.04);
    }

    .caption-ai-generate:disabled {
      opacity: 0.7;
      cursor: progress;
      transform: none;
    }

    .caption-ai-generate .caption-ai-spinner {
      border-color: rgba(255, 255, 255, 0.45);
      border-top-color: #ffffff;
    }

    .caption-ai-generate.is-loading .caption-ai-spinner {
      display: inline-block;
    }

    .caption-ai-generate.is-loading .caption-ai-generate-label::after {
      content: "Writing…";
    }

    .caption-ai-generate:not(.is-loading) .caption-ai-generate-label::after {
      content: attr(data-idle);
    }

    .caption-ai-status {
      margin-top: 12px;
      font-size: 11.5px;
      line-height: 1.45;
      color: #4b6076;
      display: none;
      align-items: flex-start;
      gap: 7px;
    }

    .caption-ai-status.show {
      display: flex;
    }

    .caption-ai-status.error {
      color: #b3261e;
    }

    .caption-ai-status .dot {
      flex: none;
      margin-top: 3px;
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #16a34a;
      box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.16);
    }

    .caption-ai-status.error .dot {
      background: #dc2626;
      box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.16);
    }

    .caption-ai-status.fallback .dot {
      background: #d97706;
      box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.16);
    }

    .caption-editor-surface.is-generating {
      position: relative;
      color: transparent;
      caret-color: transparent;
      background-image: linear-gradient(100deg, #eef4fb 30%, #dbe9f7 50%, #eef4fb 70%);
      background-size: 200% 100%;
      animation: captionAiShimmer 1.1s linear infinite;
      border-radius: 0 0 12px 12px;
    }

    .caption-editor-surface.is-generating::after {
      content: "Drafting your caption…";
      position: absolute;
      top: 14px;
      left: 16px;
      right: 16px;
      text-align: center;
      color: #5b7790;
      font-weight: 700;
      font-size: 13px;
      letter-spacing: 0.01em;
    }

    @keyframes captionAiShimmer {
      to { background-position: -200% 0; }
    }

    @media (prefers-reduced-motion: reduce) {
      .caption-ai-btn:hover { animation: none; }
      .caption-editor-surface.is-generating { animation: none; }
      .caption-ai-spinner { animation-duration: 1.2s; }
    }

    .muted {
      margin-top: 8px;
      color: var(--muted);
      font-size: 12.5px;
      line-height: 1.45;
    }

    .muted b,
    .muted code {
      color: #0f3e63;
      font-weight: 800;
    }

    .muted code {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      font-size: 12px;
      background: #edf4fb;
      border: 1px solid #d7e5f2;
      border-radius: 6px;
      padding: 1px 5px;
      display: inline-block;
      margin: 2px 2px 0 0;
    }

    .actions {
      margin-top: 4px;
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      flex-wrap: wrap;
    }

    .btn {
      border: 0;
      border-radius: 12px;
      padding: 12px 16px;
      font-size: 14px;
      font-weight: 900;
      cursor: pointer;
      transition: transform 130ms ease, filter 130ms ease, box-shadow 130ms ease;
    }

    .btn:hover {
      transform: translateY(-1px);
      filter: brightness(1.02);
    }

    .btn-main {
      color: #ffffff;
      background: linear-gradient(130deg, var(--accent) 0%, var(--accent-2) 100%);
      box-shadow: 0 8px 18px rgba(13, 79, 140, 0.28);
    }

    .btn-ghost {
      color: #0f3e63;
      background: #ffffff;
      border: 1px solid #c3d7ea;
    }

    @media (max-width: 1024px) {
      .main-layout {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 720px) {
      body {
        padding: 12px;
      }

      .hero,
      .content {
        padding: 14px;
      }

      .hero {
        flex-direction: column;
      }

      .grid-2 {
        grid-template-columns: 1fr;
      }

      .hero-actions {
        margin-top: 0;
        width: 100%;
        justify-content: flex-start;
      }
    }
  </style>
</head>
<body>
  @php
    $canGenerateNow = (bool) ($isRegionalDirector ?? false);
    $submitAction = $canGenerateNow ? route('admin.certs.store') : route('admin.certs.endorse');
    $submitLabel = $canGenerateNow ? 'Create Certificates with QR Code' : 'Endorse to Regional Director';
  @endphp
  <div class="shell">
    <header class="hero">
      <div>
        <p class="eyebrow">Certificate Issuance</p>
        <h1>Create Certificate</h1>
        <p class="subtitle">
          {{ $canGenerateNow
              ? 'Regional Director can generate standardized, QR-enabled certificates directly.'
              : 'Prepare all training details and files, then endorse to the Regional Director for approval and QR generation.' }}
        </p>
      </div>
      <div class="hero-actions">
        @include('admin.partials.action-menu', [
          'menuId' => 'certificate-create-menu',
        ])
      </div>
    </header>

    <div class="content">
      @if (session('success'))
        <div class="err" style="border-color:#bbf7d0;background:#ecfdf5;color:#166534;margin-bottom:16px;">
          {{ session('success') }}
        </div>
      @endif

      @if ($errors->any())
        <div class="err">
          <b>Please fix the errors:</b>
          <ul>
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form id="certificateCreateForm" method="POST" action="{{ $submitAction }}" enctype="multipart/form-data" data-preview-url="{{ route('admin.certs.live-preview') }}">
        @csrf

        <div class="main-layout">
          <div class="form-stack">
            <section class="panel">
              <h2 class="panel-title">Document Details</h2>

              <div class="row">
                <label>Title</label>
                <input id="trainingTitleInput" name="training_title" value="{{ old('training_title') }}" required>
              </div>

            <div class="row">
              <label>Recipient Type</label>
              <select name="recipient_type" id="recipientTypeSelect" required>
                <option value="" disabled @selected(old('recipient_type', '') === '')>Select Recipient Type</option>
                @foreach ($recipientTypes as $recipientType)
                  <option value="{{ $recipientType }}" @selected(old('recipient_type') === $recipientType)>{{ $recipientType }}</option>
                @endforeach
              </select>
            </div>

            <div class="row" id="recipientTypeOtherRow" style="{{ old('recipient_type') === 'Others' ? '' : 'display:none;' }}">
              <label>If Others, please specify recipient type</label>
              <input
                type="text"
                id="recipientTypeOtherInput"
                name="recipient_type_other"
                value="{{ old('recipient_type_other') }}"
                maxlength="255"
                {{ old('recipient_type') === 'Others' ? 'required' : '' }}
              >
            </div>

            <div class="grid-2">
              <div class="row">
                <label>Activity Type</label>
                <select name="activity_type" id="activityTypeSelect" required>
                  <option value="" disabled @selected(old('activity_type', '') === '')>Select Activity Type</option>
                  @foreach ($activityTypes as $activityType)
                    <option value="{{ $activityType }}" @selected(old('activity_type') === $activityType)>{{ $activityType }}</option>
                  @endforeach
                </select>
              </div>

              <div class="row" id="activityTypeOtherRow" style="{{ old('activity_type') === 'Others' ? '' : 'display:none;' }}">
                <label>If Others, please specify activity type</label>
                <input
                  type="text"
                  id="activityTypeOtherInput"
                  name="activity_type_other"
                  value="{{ old('activity_type_other') }}"
                  maxlength="255"
                  {{ old('activity_type') === 'Others' ? 'required' : '' }}
                >
              </div>

              <div class="row">
                <label>Certificate Type</label>
                <select name="certificate_type" id="certificateTypeSelect" required>
                  <option value="" disabled @selected(old('certificate_type', '') === '')>Select Certificate Type</option>
                  @foreach ($certificateTypes as $certificateType)
                    <option value="{{ $certificateType }}" @selected(old('certificate_type') === $certificateType)>{{ $certificateType }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="row">
              <label>Venue</label>
              <input name="venue" value="{{ old('venue') }}" required>
            </div>

            <div class="row">
              <label>Topic</label>
              <select name="topic" required>
                <option value="" disabled @selected(old('topic', '') === '')>Select Topic</option>
                @foreach ($topics as $topic)
                  <option value="{{ $topic }}" @selected(old('topic') === $topic)>{{ $topic }}</option>
                @endforeach
              </select>
            </div>

            <div class="row" id="topicOtherRow" style="{{ old('topic') === 'Others' ? '' : 'display:none;' }}">
              <label>If Others, please specify topic</label>
              <input
                type="text"
                id="topicOtherInput"
                name="topic_other"
                value="{{ old('topic_other') }}"
                maxlength="255"
                {{ old('topic') === 'Others' ? 'required' : '' }}
              >
            </div>

            <div class="grid-2">
              <div class="row">
                <label>Date (From)</label>
                <input type="date" name="training_date_from" value="{{ old('training_date_from') }}" required>
              </div>

              <div class="row">
                <label>Date (To)</label>
                <input type="date" name="training_date_to" value="{{ old('training_date_to') }}" required>
              </div>
            </div>

            <div class="row">
              <label id="trainingHoursLabel">Number of Training Hours</label>
              <input type="number" min="1" name="number_of_training_hours" value="{{ old('number_of_training_hours') }}" required>
            </div>

            <div class="grid-2">
              <div class="row">
                <label>DOST Program</label>
                <select name="dost_program" required>
                  <option value="" disabled @selected(old('dost_program', '') === '')>Select DOST Program</option>
                  @foreach ($dostPrograms as $dostProgram)
                    <option value="{{ $dostProgram }}" @selected(old('dost_program') === $dostProgram)>{{ $dostProgram }}</option>
                  @endforeach
                </select>
              </div>

              <div class="row">
                <label id="dostProjectLabel">{{ old('dost_program') === ($setupProgramLabel ?? null) ? 'DOST Office/Province' : 'DOST Project' }}</label>
                <select name="dost_project" id="dostProjectSelect" required>
                  <option value="" disabled @selected(old('dost_project', '') === '')>Select DOST Project</option>
                  @foreach ($dostProjects as $project)
                    <option
                      value="{{ $project['name'] }}"
                      data-code="{{ $project['code'] }}"
                      data-program-prefix="{{ $project['program_prefix'] ?? '' }}"
                      @selected(old('dost_project') === $project['name'])
                    >
                      {{ $project['name'] }}
                    </option>
                  @endforeach
                  @if (old('dost_project') === 'Others')
                    <option
                      value="Others"
                      data-code=""
                      data-program-prefix="SSCP"
                      selected
                    >
                      {{ $customDostProjectOptionLabel ?? 'Others, please specify' }}
                    </option>
                  @endif
                </select>
              </div>
            </div>

            <div class="row" id="dostProjectOtherRow" style="{{ old('dost_program') === ($sscpProgramLabel ?? null) && old('dost_project') === 'Others' ? '' : 'display:none;' }}">
              <label>If Others, please specify DOST Project</label>
              <input
                type="text"
                id="dostProjectOtherInput"
                name="dost_project_other"
                value="{{ old('dost_project_other') }}"
                maxlength="255"
                {{ old('dost_program') === ($sscpProgramLabel ?? null) && old('dost_project') === 'Others' ? 'required' : '' }}
              >
            </div>

            <div class="row" id="dostProgramOtherRow" style="{{ old('dost_program') === 'Others' ? '' : 'display:none;' }}">
              <label>If Others, please specify DOST Program</label>
              <input
                type="text"
                id="dostProgramOtherInput"
                name="dost_program_other"
                value="{{ old('dost_program_other') }}"
                maxlength="255"
                {{ old('dost_program') === 'Others' ? 'required' : '' }}
              >
            </div>

            <div class="grid-2">
              <div class="row">
                <label>Pillar</label>
                <select name="pillar" required>
                  <option value="" disabled @selected(old('pillar', '') === '')>Select Pillar</option>
                  @foreach ($pillars as $pillar)
                    <option value="{{ $pillar }}" @selected(old('pillar') === $pillar)>{{ $pillar }}</option>
                  @endforeach
                </select>
              </div>

              <div class="row">
                <label>Source of Funds</label>
                <select name="source_of_funds" id="sourceOfFundsSelect" required>
                  <option value="" disabled @selected(old('source_of_funds', '') === '')>Select Source of Funds</option>
                  @foreach ($sourceOfFundsOptions as $sourceOfFunds)
                    <option value="{{ $sourceOfFunds }}" @selected(old('source_of_funds') === $sourceOfFunds)>{{ $sourceOfFunds }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="row" id="projectCodeRow" style="{{ old('dost_program') === ($setupProgramLabel ?? null) ? 'display:none;' : '' }}">
              <label>Project Code</label>
              <input
                id="projectCodeInput"
                name="project_code"
                value="{{ old('project_code') }}"
                readonly
                class="readonly-input"
              >
            </div>

            <div class="row">
              <label id="trainingBudgetLabel">Training Budget</label>
              <input
                type="number"
                name="training_budget"
                min="0"
                step="0.01"
                value="{{ old('training_budget') }}"
                placeholder="e.g. 50000.00"
              >
            </div>

            <div class="row">
              <label>Expected Number of Recipients</label>
              <input type="number" min="1" name="expected_number_of_participants" value="{{ old('expected_number_of_participants') }}">
            </div>

            <div class="row">
              <label>Issuing Office/Organizing Unit</label>
              <select name="issuing_office" required>
                @php
                  $officeValue = old('issuing_office', $defaults['issuing_office']);
                  $offices = [
                    'DOST Caraga - Fields Operation Division',
                    'DOST Caraga - Financial and Administrative Services',
                    'DOST Caraga - Office of the Regional Director',
                    'DOST Caraga - Technical Support Services',
                    'DOST Caraga - Innovation Unit',
                    'DOST Caraga - Management Information Systems - Information Communication Technology',
                    'DOST Caraga - Regional Standards and Testing Laboratory',
                    'DOST Caraga - Regional Metrology Laboratory',
                    'DOST Caraga - PSTO-Agusan Del Norte',
                    'DOST Caraga - PSTO-Agusan Del Sur',
                    'DOST Caraga - PSTO-Surigao Del Norte',
                    'DOST Caraga - PSTO-Surigao Del Sur',
                    'DOST Caraga - PSTO-Province of Dinagat Island',
                  ];
                @endphp
                @foreach ($offices as $office)
                  <option value="{{ $office }}" @selected($officeValue === $office)>{{ $office }}</option>
                @endforeach
              </select>
            </div>
          </section>

          <section class="panel">
            <h2 class="panel-title">Import Files</h2>

            <div class="row">
              <label>Certificate Template</label>
              <div class="template-choice-grid">
                <label class="template-choice">
                  <input class="template-choice-input" type="radio" name="template_source" value="default" checked id="templateDefaultRadio">
                  <span class="template-choice-card">
                    <span class="template-choice-kicker">Embedded</span>
                    <span class="template-choice-title">Use Default Template</span>
                    <span id="defaultTemplateName" class="template-choice-meta">Auto-selects based on the chosen certificate type.</span>
                  </span>
                </label>
                <label class="template-choice">
                  <input class="template-choice-input" type="radio" name="template_source" value="custom" id="templateCustomRadio">
                  <span class="template-choice-card">
                    <span class="template-choice-kicker">Custom</span>
                    <span class="template-choice-title">Upload Template PDF</span>
                    <span class="template-choice-meta">Use one shared PDF design for every imported recipient.</span>
                  </span>
                </label>
              </div>
              <div id="customTemplateUpload" class="upload-shell" style="display: none;">
                <label class="upload-surface" for="customTemplateFileInput">
                  <span class="upload-surface-main">
                    <span class="upload-surface-title">Template PDF</span>
                    <span class="upload-surface-hint">One document layout applied to every generated certificate in this batch.</span>
                  </span>
                  <span class="upload-surface-button">Choose PDF</span>
                </label>
                <input class="file-input-hidden" type="file" id="customTemplateFileInput" name="certificate_pdf_shared" accept="application/pdf">
                <div class="upload-meta">
                  <span id="customTemplateFileName" class="upload-file-name">No file selected</span>
                  <span>Maximum file size: 50 MB</span>
                </div>
              </div>
            </div>

            <div class="row">
              <label>Import Participants (CSV/XLSX)</label>
              <div class="upload-shell">
                <label class="upload-surface" for="participantsFile">
                  <span class="upload-surface-main">
                    <span class="upload-surface-title">Participant Intake File</span>
                    <span class="upload-surface-hint">Upload the exported participant list in `.csv` or `.xlsx` format.</span>
                  </span>
                  <span class="upload-surface-button">Choose File</span>
                </label>
                <input class="file-input-hidden" type="file" id="participantsFile" name="participants_file" accept=".csv,.xlsx" required>
                <div class="upload-meta">
                  <span id="participantsFileName" class="upload-file-name">No file selected</span>
                  <span>CSV and XLSX supported</span>
                </div>
              </div>
              <div class="upload-guidance muted">
                Please do Participant Intake first and export csv or xlsx file, then upload here.
                <br>
                Headers supported:
                <code>participant_name</code> <code>participant name</code> <code>name</code>
                or split name fields
                <code>first_name</code> <code>middle_initial</code> <code>last_name</code>,
                plus
                <code>email</code> <code>gender</code> <code>sex</code> <code>age</code> <code>age_range</code>
                <code>region</code> <code>province</code> <code>state</code> <code>province/state</code>
                <code>city</code> <code>municipality</code> <code>city_municipality</code>
                <code>barangay</code> <code>brgy</code>
                <code>block</code> <code>lot</code> <code>purok</code>
                <code>industry</code> <code>affiliation/sector</code>.
              </div>
            </div>
          </section>
          </div> <!-- End .form-stack -->

          <!-- Live Preview Panel -->
          <div class="preview-container">
            <h2 class="panel-title">Live Certificate Preview</h2>
            <div class="preview-frame">
              <div class="preview-box" id="certificatePreviewBox">
                <iframe class="preview-pdf-frame" id="previewTemplateFrame" title="Certificate Template Preview"></iframe>
                <div class="preview-idle" id="previewIdleState">
                  <div class="preview-idle-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" />
                      <path d="M14 3v5h5" />
                      <path d="M9 13h6" />
                      <path d="M9 17h4" />
                    </svg>
                  </div>
                  <h3 class="preview-idle-title">Live preview will appear here</h3>
                  <p class="preview-idle-text">Fill in the certificate details on the left and your live PDF preview will render in this frame using the same backend renderer used for the final certificate.</p>
                </div>
                <div id="livePreviewLoading" class="preview-loading" aria-live="polite" aria-hidden="true">
                  <div class="preview-spinner"></div>
                  <div id="livePreviewLoadingText">Refreshing preview...</div>
                </div>
              </div>
            </div>
            <div id="livePreviewStatus" class="preview-status" hidden></div>

            <div class="row">
              <label>Certificate Caption (appears below name)</label>
              <div class="caption-editor">
                <div class="caption-toolbar">
                  <div class="caption-toolbar-group">
                    <button type="button" class="caption-btn" data-cmd="bold" title="Bold" aria-label="Bold"><b>B</b></button>
                    <button type="button" class="caption-btn" data-cmd="italic" title="Italic" aria-label="Italic"><i>I</i></button>
                    <div class="caption-toolbar-divider" aria-hidden="true"></div>
                    <button type="button" class="caption-btn" data-cmd="justifyLeft" title="Align Left" aria-label="Align Left">←</button>
                    <button type="button" class="caption-btn active" data-cmd="justifyCenter" title="Align Center" aria-label="Align Center">↔</button>
                    <button type="button" class="caption-btn" data-cmd="justifyRight" title="Align Right" aria-label="Align Right">→</button>
                    <button type="button" class="caption-btn" data-cmd="justifyFull" title="Justify" aria-label="Justify">≡</button>
                  </div>
                  <div class="caption-ai-cluster">
                    <span class="caption-toolbar-label">Caption Editor</span>
                    <button type="button" id="captionAiBtn" class="caption-ai-btn" aria-haspopup="dialog" aria-expanded="false" title="Draft this caption with AI">
                      <span class="caption-ai-spark" aria-hidden="true">✨</span>
                      <span class="caption-ai-spinner" aria-hidden="true"></span>
                      <span>Write with AI</span>
                    </button>

                    <div id="captionAiPanel" class="caption-ai-panel" role="dialog" aria-label="AI Caption Assistant" hidden>
                      <div class="caption-ai-panel-head">
                        <div class="caption-ai-orb" aria-hidden="true">✦</div>
                        <div>
                          <p class="caption-ai-panel-title">AI Caption Assistant</p>
                          <p class="caption-ai-panel-sub">Drafts a formal, gender-neutral 2-sentence citation from the title, recipient role, and activity details you entered.</p>
                        </div>
                      </div>

                      <span class="caption-ai-field-label">Tone</span>
                      <div class="caption-ai-tones" id="captionAiTones" role="group" aria-label="Caption tone">
                        <button type="button" class="caption-ai-tone active" data-tone="warm and dignified">Warm</button>
                        <button type="button" class="caption-ai-tone" data-tone="formal and ceremonial">Formal</button>
                        <button type="button" class="caption-ai-tone" data-tone="inspirational and motivating">Inspirational</button>
                        <button type="button" class="caption-ai-tone" data-tone="concise and direct">Concise</button>
                      </div>

                      <label class="caption-ai-field-label" for="captionAiContext">Training context <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional — briefly describe the purpose or background of the training)</span></label>
                      <textarea
                        id="captionAiContext"
                        class="caption-ai-context"
                        rows="3"
                        maxlength="500"
                        placeholder="e.g. The training was conducted to provide basic knowledge on AI for business professionals."></textarea>

                      <button type="button" id="captionAiGenerate" class="caption-ai-generate">
                        <span class="caption-ai-spinner" aria-hidden="true"></span>
                        <span class="caption-ai-generate-label" data-idle="Generate caption"></span>
                      </button>

                      <div id="captionAiStatus" class="caption-ai-status" role="status" aria-live="polite">
                        <span class="dot" aria-hidden="true"></span>
                        <span id="captionAiStatusText"></span>
                      </div>
                    </div>
                  </div>
                </div>
                <div
                  id="captionEditorSurface"
                  class="caption-editor-surface"
                  contenteditable="true"
                  role="textbox"
                  aria-multiline="true"
                  data-placeholder="Write the certificate caption exactly as it should appear beneath the participant name."></div>
                <textarea id="captionTextInput" name="caption_text" rows="5" class="caption-source-input">{{ old('caption_text') }}</textarea>
              </div>
              <input type="hidden" id="captionAlignmentInput" name="caption_alignment" value="{{ old('caption_alignment', 'center') }}">
                <br>
            <div class="ai-reminder" role="note">
              <span class="ai-reminder-spark" aria-hidden="true">✨</span>
              <div class="ai-reminder-body">
                <p class="ai-reminder-title">Want to use “Write with AI”?</p>
                <p class="ai-reminder-text">Fill in these details first so the assistant has enough to craft the citation:</p>
                <ul class="ai-reminder-list">
                  <li>Title</li>
                  <li>Recipient Type</li>
                  <li>Activity Type</li>
                  <li>Certificate Type</li>
                  <li>Venue</li>
                  <li>Topic</li>
                  <li>Date (From &amp; To)</li>
                </ul>
                <p class="ai-reminder-text">Then click <strong>✨ Write with AI</strong> in the Caption Editor to generate it.</p>
              </div>
            </div>
          </div>
        </div> <!-- End .main-layout -->

        <div class="actions">
          <button class="btn btn-ghost" type="submit" formaction="{{ route('admin.certs.preview') }}" formtarget="_blank">
            Preview First Participant
          </button>
          <button class="btn btn-main" type="submit">{{ $submitLabel }}</button>
        </div>
      </form>
    </div>
  </div>
  <script>
    const activityTypeSelect = document.getElementById('activityTypeSelect');
    const activityTypeOtherRow = document.getElementById('activityTypeOtherRow');
    const activityTypeOtherInput = document.getElementById('activityTypeOtherInput');
    const topicSelect = document.querySelector('select[name="topic"]');
    const topicOtherRow = document.getElementById('topicOtherRow');
    const topicOtherInput = document.getElementById('topicOtherInput');
    const recipientTypeSelect = document.getElementById('recipientTypeSelect');
    const recipientTypeOtherRow = document.getElementById('recipientTypeOtherRow');
    const recipientTypeOtherInput = document.getElementById('recipientTypeOtherInput');
    const certificateTypeSelect = document.getElementById('certificateTypeSelect');
    const dostProgramSelect = document.querySelector('select[name="dost_program"]');
    const dostProgramOtherRow = document.getElementById('dostProgramOtherRow');
    const dostProgramOtherInput = document.getElementById('dostProgramOtherInput');
    const trainingHoursLabel = document.getElementById('trainingHoursLabel');
    const trainingBudgetLabel = document.getElementById('trainingBudgetLabel');
    const dostProjectLabel = document.getElementById('dostProjectLabel');
    const dostProjectSelect = document.getElementById('dostProjectSelect');
    const dostProjectOtherRow = document.getElementById('dostProjectOtherRow');
    const dostProjectOtherInput = document.getElementById('dostProjectOtherInput');
    const sourceOfFundsSelect = document.getElementById('sourceOfFundsSelect');
    const projectCodeRow = document.getElementById('projectCodeRow');
    const projectCodeInput = document.getElementById('projectCodeInput');
    const automaticCertificateTypeByRecipientType = @json($automaticCertificateTypeByRecipientType ?? []);
    const certificateTypeOptions = @json($certificateTypes ?? []);
    const certificateTemplateFiles = @json($certificateTemplateFiles ?? []);
    const automaticSourceOfFundsByProgram = @json($automaticSourceOfFundsByProgram ?? []);
    const nationalRegularProgramLabel = @json($nationalRegularProgramLabel ?? null);
    const dostProgramProjectPrefixes = @json($dostProgramProjectPrefixes ?? []);
    const setupProgramLabel = @json($setupProgramLabel ?? null);
    const setupOfficeProvinces = @json($setupOfficeProvinces ?? []);
    const sscpProgramLabel = @json($sscpProgramLabel ?? null);
    const sourceOfFundsOptions = @json($sourceOfFundsOptions ?? []);
    const customDostProjectOptionLabel = @json($customDostProjectOptionLabel ?? 'Others, please specify');
    const customDostProjectOptionValue = 'Others';
    let persistedDostProjectValue = @json(old('dost_project', ''));
    const notApplicableValue = 'Not Applicable';
    const allDostProjectOptions = dostProjectSelect
      ? Array.from(dostProjectSelect.options)
        .filter((option) => option.value !== '')
        .map((option) => ({
          value: option.value,
          label: option.textContent,
          code: option.dataset.code || '',
          programPrefix: option.dataset.programPrefix || '',
        }))
      : [];
    const notApplicableProjectOption = allDostProjectOptions.find((option) => option.value === notApplicableValue)
      || {
        value: notApplicableValue,
        label: notApplicableValue,
        code: notApplicableValue,
        programPrefix: '',
      };
    const customDostProjectOption = {
      value: customDostProjectOptionValue,
      label: customDostProjectOptionLabel,
      code: '',
      programPrefix: 'SSCP',
    };

    const toggleActivityTypeOther = () => {
      if (!activityTypeSelect || !activityTypeOtherRow || !activityTypeOtherInput) {
        return;
      }

      const isOthers = activityTypeSelect.value === 'Others';
      activityTypeOtherRow.style.display = isOthers ? '' : 'none';
      activityTypeOtherInput.required = isOthers;
    };

    if (activityTypeSelect) {
      activityTypeSelect.addEventListener('change', toggleActivityTypeOther);
      toggleActivityTypeOther();
    }

    const toggleTopicOther = () => {
      if (!topicSelect || !topicOtherRow || !topicOtherInput) {
        return;
      }

      const isOthers = topicSelect.value === 'Others';
      topicOtherRow.style.display = isOthers ? '' : 'none';
      topicOtherInput.required = isOthers;
    };

    if (topicSelect) {
      topicSelect.addEventListener('change', toggleTopicOther);
      toggleTopicOther();
    }

    const toggleRecipientTypeOther = () => {
      if (!recipientTypeSelect || !recipientTypeOtherRow || !recipientTypeOtherInput) {
        return;
      }

      const isOthers = recipientTypeSelect.value === 'Others';
      recipientTypeOtherRow.style.display = isOthers ? '' : 'none';
      recipientTypeOtherInput.required = isOthers;
    };

    const buildCertificateTypeOptions = (availableOptions, previousValue, lockSelection = false) => {
      if (!certificateTypeSelect) {
        return;
      }

      certificateTypeSelect.innerHTML = '';

      let placeholderOption = null;
      if (!lockSelection) {
        placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = 'Select Certificate Type';
        placeholderOption.disabled = true;
        placeholderOption.selected = true;
        certificateTypeSelect.appendChild(placeholderOption);
      }

      availableOptions.forEach((optionValue) => {
        const option = document.createElement('option');
        option.value = optionValue;
        option.textContent = optionValue;
        option.selected = lockSelection
          ? optionValue === availableOptions[0]
          : optionValue === previousValue;
        if (option.selected && placeholderOption) {
          placeholderOption.selected = false;
        }
        certificateTypeSelect.appendChild(option);
      });

      if (lockSelection && availableOptions[0]) {
        certificateTypeSelect.value = availableOptions[0];
        return;
      }

      if (!availableOptions.includes(previousValue)) {
        certificateTypeSelect.value = '';
      }
    };

    const syncCertificateTypeOptions = () => {
      if (!recipientTypeSelect || !certificateTypeSelect) {
        return;
      }

      const selectedRecipientType = recipientTypeSelect.value;
      const previousValue = certificateTypeSelect.value;
      const automaticValue = automaticCertificateTypeByRecipientType[selectedRecipientType] || '';

      if (automaticValue) {
        buildCertificateTypeOptions([automaticValue], previousValue, true);
        certificateTypeSelect.dispatchEvent(new Event('change', { bubbles: true }));
        return;
      }

      buildCertificateTypeOptions(certificateTypeOptions, previousValue);
      certificateTypeSelect.dispatchEvent(new Event('change', { bubbles: true }));
    };

    if (recipientTypeSelect) {
      recipientTypeSelect.addEventListener('change', toggleRecipientTypeOther);
      recipientTypeSelect.addEventListener('change', syncCertificateTypeOptions);
      toggleRecipientTypeOther();
      syncCertificateTypeOptions();
    }

    const toggleDostProgramOther = () => {
      if (!dostProgramSelect || !dostProgramOtherRow || !dostProgramOtherInput) {
        return;
      }

      const isOthers = dostProgramSelect.value === 'Others';
      dostProgramOtherRow.style.display = isOthers ? '' : 'none';
      dostProgramOtherInput.required = isOthers;
    };

    if (dostProgramSelect) {
      dostProgramSelect.addEventListener('change', toggleDostProgramOther);
      toggleDostProgramOther();
    }

    const toggleDostProjectOther = () => {
      if (!dostProjectSelect || !dostProjectOtherRow || !dostProjectOtherInput) {
        return;
      }

      const isCustomProject = dostProgramSelect
        && dostProgramSelect.value === sscpProgramLabel
        && dostProjectSelect.value === customDostProjectOptionValue;

      dostProjectOtherRow.style.display = isCustomProject ? '' : 'none';
      dostProjectOtherInput.required = isCustomProject;
    };

    const syncActivityLabels = () => {
      if (!trainingHoursLabel && !trainingBudgetLabel) {
        return;
      }

      const activityType = activityTypeSelect ? activityTypeSelect.value.trim() : '';
      if (trainingHoursLabel) {
        trainingHoursLabel.textContent = activityType
          ? `Number of ${activityType} Hours`
          : 'Number of Training Hours';
      }
      if (trainingBudgetLabel) {
        trainingBudgetLabel.textContent = activityType
          ? `${activityType} Budget`
          : 'Training Budget';
      }
    };

    if (activityTypeSelect) {
      activityTypeSelect.addEventListener('change', syncActivityLabels);
    }
    syncActivityLabels();

    const syncProjectCode = () => {
      if (!dostProjectSelect || !projectCodeInput) {
        return;
      }

      const selectedOption = dostProjectSelect.options[dostProjectSelect.selectedIndex];
      const code = selectedOption ? (selectedOption.dataset.code || '') : '';
      projectCodeInput.value = code;
      persistedDostProjectValue = dostProjectSelect.value || persistedDostProjectValue;
      toggleDostProjectOther();
    };

    const buildDostProjectOptions = (availableOptions, placeholderText, previousValue, lockSelection = false) => {
      if (!dostProjectSelect) {
        return;
      }

      dostProjectSelect.innerHTML = '';

      let placeholderOption = null;
      if (!lockSelection) {
        placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = placeholderText;
        placeholderOption.disabled = true;
        placeholderOption.selected = true;
        dostProjectSelect.appendChild(placeholderOption);
      }

      availableOptions.forEach((optionData) => {
        const option = document.createElement('option');
        option.value = optionData.value;
        option.textContent = optionData.label;
        option.dataset.code = optionData.code;
        option.dataset.programPrefix = optionData.programPrefix;
        option.selected = lockSelection
          ? optionData.value === availableOptions[0]?.value
          : optionData.value === previousValue;
        if (option.selected && placeholderOption) {
          placeholderOption.selected = false;
        }
        dostProjectSelect.appendChild(option);
      });

      if (lockSelection && availableOptions[0]) {
        dostProjectSelect.value = availableOptions[0].value;
        return;
      }

      if (!availableOptions.some((option) => option.value === previousValue)) {
        dostProjectSelect.value = '';
      }
    };

    const buildSourceOfFundsOptions = (availableOptions, previousValue, lockSelection = false) => {
      if (!sourceOfFundsSelect) {
        return;
      }

      sourceOfFundsSelect.innerHTML = '';

      let placeholderOption = null;
      if (!lockSelection) {
        placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = 'Select Source of Funds';
        placeholderOption.disabled = true;
        placeholderOption.selected = true;
        sourceOfFundsSelect.appendChild(placeholderOption);
      }

      availableOptions.forEach((optionValue) => {
        const option = document.createElement('option');
        option.value = optionValue;
        option.textContent = optionValue;
        option.selected = lockSelection
          ? optionValue === availableOptions[0]
          : optionValue === previousValue;
        if (option.selected && placeholderOption) {
          placeholderOption.selected = false;
        }
        sourceOfFundsSelect.appendChild(option);
      });

      if (lockSelection && availableOptions[0]) {
        sourceOfFundsSelect.value = availableOptions[0];
        return;
      }

      if (!availableOptions.includes(previousValue)) {
        sourceOfFundsSelect.value = '';
      }
    };

    const syncSourceOfFundsOptions = () => {
      if (!dostProgramSelect || !sourceOfFundsSelect) {
        return;
      }

      const selectedProgram = dostProgramSelect.value;
      const previousValue = sourceOfFundsSelect.value;
      const automaticValue = automaticSourceOfFundsByProgram[selectedProgram] || '';

      if (automaticValue) {
        buildSourceOfFundsOptions([automaticValue], previousValue, true);
        return;
      }

      buildSourceOfFundsOptions(sourceOfFundsOptions, previousValue);
    };

    const syncDostProjectOptions = () => {
      if (!dostProgramSelect || !dostProjectSelect) {
        return;
      }

      const selectedProgram = dostProgramSelect.value;
      const previousValue = dostProjectSelect.value || persistedDostProjectValue;
      const isSetup = selectedProgram === setupProgramLabel;
      const isNationalRegular = selectedProgram === nationalRegularProgramLabel;
      const isSscp = selectedProgram === sscpProgramLabel;

      if (dostProjectLabel) {
        dostProjectLabel.textContent = isSetup ? 'DOST Office/Province' : 'DOST Project';
      }

      if (projectCodeRow) {
        projectCodeRow.style.display = isSetup ? 'none' : '';
      }

      if (isSetup) {
        buildDostProjectOptions(
          setupOfficeProvinces.map((office) => ({
            value: office,
            label: office,
            code: '',
            programPrefix: '',
          })),
          'Select DOST Office/Province',
          previousValue
        );
        if (projectCodeInput) {
          projectCodeInput.value = '';
        }
        return;
      }

      if (isNationalRegular) {
        buildDostProjectOptions([notApplicableProjectOption], 'Select DOST Project', previousValue, true);
        syncProjectCode();
        return;
      }

      const requiredPrefix = dostProgramProjectPrefixes[selectedProgram] || '';
      const matchingOptions = requiredPrefix === ''
        ? []
        : allDostProjectOptions.filter((option) => option.programPrefix === requiredPrefix);
      const availableOptions = matchingOptions.length > 0
        ? matchingOptions
        : allDostProjectOptions;
      const availableProjectOptions = isSscp
        ? [...availableOptions, customDostProjectOption]
        : availableOptions;

      buildDostProjectOptions(availableProjectOptions, 'Select DOST Project', previousValue);

      syncProjectCode();
    };

    if (dostProjectSelect) {
      dostProjectSelect.addEventListener('change', syncProjectCode);
    }

    if (dostProgramSelect) {
      dostProgramSelect.addEventListener('change', syncDostProjectOptions);
      dostProgramSelect.addEventListener('change', syncSourceOfFundsOptions);
      syncDostProjectOptions();
      syncSourceOfFundsOptions();
    } else {
      syncProjectCode();
    }

    // --- Template Toggle Logic ---
    const templateDefaultRadio = document.getElementById('templateDefaultRadio');
    const templateCustomRadio = document.getElementById('templateCustomRadio');
    const customTemplateUpload = document.getElementById('customTemplateUpload');
    const customTemplateFileInput = document.getElementById('customTemplateFileInput');
    const customTemplateFileName = document.getElementById('customTemplateFileName');
    const participantsFileName = document.getElementById('participantsFileName');
    const defaultTemplateName = document.getElementById('defaultTemplateName');
    const previewTemplateFrame = document.getElementById('previewTemplateFrame');

    const updateTemplateVisibility = () => {
      if (templateDefaultRadio && templateCustomRadio && customTemplateUpload && customTemplateFileInput) {
        if (templateDefaultRadio.checked) {
          customTemplateUpload.style.display = 'none';
          customTemplateFileInput.removeAttribute('required');
        } else {
          customTemplateUpload.style.display = '';
          customTemplateFileInput.setAttribute('required', 'required');
        }
      }
    };

    const updateSelectedFileName = (input, target, emptyLabel) => {
      if (!target) {
        return;
      }

      target.textContent = input?.files && input.files.length > 0
        ? input.files[0].name
        : emptyLabel;
    };

    if (templateDefaultRadio) {
      templateDefaultRadio.addEventListener('change', () => {
        updateTemplateVisibility();
        updateDefaultTemplateName();
      });
    }
    if (templateCustomRadio) {
      templateCustomRadio.addEventListener('change', updateTemplateVisibility);
    }
    updateTemplateVisibility();

    const updateDefaultTemplateName = () => {
      if (!certificateTypeSelect || !defaultTemplateName) {
        return;
      }

      const selectedType = certificateTypeSelect.value;
      const templateFile = certificateTemplateFiles[selectedType] || 'Participation.pdf';
      defaultTemplateName.textContent = selectedType
        ? `${templateFile} selected automatically from the certificate type.`
        : 'Auto-selects based on the chosen certificate type.';

      // The preview iframe is intentionally hidden until the live preview renders,
      // so changing the default template just refreshes the label — no stray chrome
      // PDF embed is mounted into the DOM.
    };

    if (certificateTypeSelect) {
      certificateTypeSelect.addEventListener('change', updateDefaultTemplateName);
      updateDefaultTemplateName(); // Initial call
    }

    // --- Live Preview Logic ---
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('certificateCreateForm');
      const captionInput = document.getElementById('captionTextInput');
      const captionEditor = document.getElementById('captionEditorSurface');
      const alignInput = document.getElementById('captionAlignmentInput');
      const captionButtons = document.querySelectorAll('.caption-btn');
      const previewStatus = document.getElementById('livePreviewStatus');
      const previewLoading = document.getElementById('livePreviewLoading');
      const previewLoadingText = document.getElementById('livePreviewLoadingText');
      const previewUrl = form?.dataset.previewUrl || '';
      const participantsFileInput = document.getElementById('participantsFile');
      const templateFileInput = form?.querySelector('input[name="certificate_pdf_shared"]');

      let previewRefreshTimeout = null;
      let livePreviewController = null;
      let currentPreviewBlobUrl = null;
      let isRenderingLivePreview = false;

      const setPreviewStatus = (message, isError = false) => {
        if (!previewStatus) {
          return;
        }
        previewStatus.textContent = message || '';
        previewStatus.classList.toggle('error', isError);
        // Only surface the status row when there is something worth showing
        // (a render error). Routine success states stay quiet so the caption
        // mirror and AI reminder below the preview remain the focus.
        previewStatus.hidden = !message;
      };

      const setPreviewLoading = (visible, message = 'Refreshing preview...') => {
        if (!previewLoading) {
          return;
        }
        previewLoading.classList.toggle('visible', visible);
        previewLoading.setAttribute('aria-hidden', visible ? 'false' : 'true');
        if (previewLoadingText) {
          previewLoadingText.textContent = message;
        }
      };

      const revokeCurrentPreviewBlob = () => {
        if (!currentPreviewBlobUrl) {
          return;
        }
        URL.revokeObjectURL(currentPreviewBlobUrl);
        currentPreviewBlobUrl = null;
      };

      const setPreviewFrameSource = (src) => {
        if (!previewTemplateFrame) {
          return;
        }
        previewTemplateFrame.classList.add('is-ready');
        previewTemplateFrame.src = src;
      };

      const setPreviewIdleVisible = (visible) => {
        const idle = document.getElementById('previewIdleState');
        if (!idle) {
          return;
        }
        idle.hidden = visible ? false : true;
      };

      const hidePreviewFrame = () => {
        if (!previewTemplateFrame) {
          return;
        }
        previewTemplateFrame.classList.remove('is-ready');
        previewTemplateFrame.removeAttribute('src');
      };

      const isVisibleField = (field) => {
        if (!field) {
          return false;
        }
        if (field.type === 'hidden') {
          return false;
        }
        const hiddenContainer = field.closest('[style*="display:none"]');
        return !hiddenContainer;
      };

      const getDefaultTemplateUrl = () => {
        const selectedType = certificateTypeSelect?.value || '';
        const templateFile = certificateTemplateFiles[selectedType] || 'Participation.pdf';
        return `/templates/${encodeURIComponent(templateFile)}#toolbar=0&navpanes=0&v=${Date.now()}`;
      };

      const showTemplateFallback = (message, isError = false) => {
        revokeCurrentPreviewBlob();
        setPreviewLoading(false);
        if (templateDefaultRadio?.checked) {
          setPreviewFrameSource(getDefaultTemplateUrl());
          setPreviewIdleVisible(false);
        } else {
          hidePreviewFrame();
          setPreviewIdleVisible(true);
        }
        setPreviewStatus(message, isError);
      };

      const hasRequiredPreviewInputs = () => {
        if (!form) {
          return false;
        }

        if (templateCustomRadio?.checked && (!templateFileInput?.files || templateFileInput.files.length === 0)) {
          return false;
        }

        return true;
      };

      const refreshLivePreview = async () => {
        if (!form || !previewUrl) {
          return;
        }

        if (!hasRequiredPreviewInputs()) {
          showTemplateFallback('Fill in the fields you want to preview. The pane will use a sample participant until you upload the participants file.');
          return;
        }

        if (livePreviewController) {
          livePreviewController.abort();
        }

        livePreviewController = new AbortController();
        isRenderingLivePreview = true;
        setPreviewLoading(true, 'Rendering live PDF preview...');
        setPreviewStatus('Rendering PDF preview...');

        try {
          const response = await fetch(previewUrl, {
            method: 'POST',
            body: new FormData(form),
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/pdf, application/json',
            },
            signal: livePreviewController.signal,
            credentials: 'same-origin',
          });

          const contentType = response.headers.get('content-type') || '';
          if (!response.ok) {
            if (contentType.includes('application/json')) {
              const payload = await response.json();
              const message = payload.message || Object.values(payload.errors || {}).flat()[0] || 'Unable to render live preview.';
              showTemplateFallback(message, true);
              return;
            }

            showTemplateFallback('Unable to render live preview with the current form values.', true);
            return;
          }

          if (!contentType.includes('application/pdf')) {
            showTemplateFallback('Preview response was not a PDF.', true);
            return;
          }

          const blob = await response.blob();
          revokeCurrentPreviewBlob();
          currentPreviewBlobUrl = URL.createObjectURL(blob);
          setPreviewFrameSource(currentPreviewBlobUrl + '#toolbar=0&navpanes=0');
          setPreviewIdleVisible(false);
          setPreviewLoading(false);
          setPreviewStatus('');
        } catch (error) {
          if (error.name === 'AbortError') {
            return;
          }

          showTemplateFallback('Unable to refresh the live PDF preview right now.', true);
        } finally {
          if (!livePreviewController || !livePreviewController.signal.aborted) {
            setPreviewLoading(false);
          }
          isRenderingLivePreview = false;
        }
      };

      const scheduleLivePreview = () => {
        if (previewRefreshTimeout) {
          clearTimeout(previewRefreshTimeout);
        }
        previewRefreshTimeout = setTimeout(refreshLivePreview, 700);
      };

      if (form) {
        form.addEventListener('input', (event) => {
          if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement || event.target instanceof HTMLSelectElement) {
            scheduleLivePreview();
          }
        });

        form.addEventListener('change', (event) => {
          if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement || event.target instanceof HTMLSelectElement) {
            scheduleLivePreview();
          }
        });
      }

      if (templateDefaultRadio) {
        templateDefaultRadio.addEventListener('change', scheduleLivePreview);
      }
      if (templateCustomRadio) {
        templateCustomRadio.addEventListener('change', scheduleLivePreview);
      }
      if (customTemplateFileInput) {
        customTemplateFileInput.addEventListener('change', () => {
          updateSelectedFileName(customTemplateFileInput, customTemplateFileName, 'No file selected');
          scheduleLivePreview();
        });
      }
      if (participantsFileInput) {
        participantsFileInput.addEventListener('change', () => {
          updateSelectedFileName(participantsFileInput, participantsFileName, 'No file selected');
          scheduleLivePreview();
        });
      }
      updateSelectedFileName(customTemplateFileInput, customTemplateFileName, 'No file selected');
      updateSelectedFileName(participantsFileInput, participantsFileName, 'No file selected');

      const escapeCaptionHtml = (value) => value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

      // The hidden textarea should carry the actual characters (e.g. `&` rather
      // than `&amp;`) so the server's sanitizer can encode them exactly once.
      // Markup tags used for bold/italic/div are preserved as-is.
      const decodeHtmlEntities = (value) => {
        const decoder = document.createElement('textarea');
        decoder.innerHTML = value;
        return decoder.value;
      };

      const sanitizeCaptionEditorNode = (node) => {
        if (node.nodeType === Node.TEXT_NODE) {
          // Any literal newline a browser leaves in a text node (Firefox under
          // white-space: pre-wrap, pasted text, etc.) becomes an explicit <br>
          // so line breaks survive the round-trip exactly as the user sees them.
          return escapeCaptionHtml(node.textContent || '').replace(/\r\n?|\n/g, '<br>');
        }

        if (node.nodeType !== Node.ELEMENT_NODE) {
          return '';
        }

        const element = node;
        const tagName = element.tagName.toLowerCase();
        if (tagName === 'br') {
          return '<br>';
        }

        const content = Array.from(element.childNodes).map(sanitizeCaptionEditorNode).join('');
        if (tagName === 'div' || tagName === 'p') {
          return `<div>${content || '<br>'}</div>`;
        }

        const styleAttr = (element.getAttribute('style') || '').toLowerCase();
        const fontWeightMatch = styleAttr.match(/font-weight\s*:\s*([^;]+)/);
        const fontWeightValue = fontWeightMatch ? Number.parseInt(fontWeightMatch[1], 10) : Number.NaN;
        const isBold = ['strong', 'b'].includes(tagName)
          || styleAttr.includes('font-weight: bold')
          || (!Number.isNaN(fontWeightValue) && fontWeightValue >= 600);
        const isItalic = ['em', 'i'].includes(tagName) || styleAttr.includes('font-style: italic');

        let inlineContent = content;
        if (isBold) {
          inlineContent = `<strong>${inlineContent}</strong>`;
        }
        if (isItalic) {
          inlineContent = `<em>${inlineContent}</em>`;
        }

        return inlineContent;
      };

      const sanitizeCaptionEditorMarkup = (html) => {
        const template = document.createElement('template');
        template.innerHTML = html;
        return Array.from(template.content.childNodes).map(sanitizeCaptionEditorNode).join('');
      };

      const captionValueToEditorHtml = (value) => {
        const normalized = (value || '').replace(/\r\n?/g, '\n').trim();
        if (!normalized) {
          return '';
        }

        // Stored formatted captions are HTML and only need re-sanitizing.
        if (/<[^>]+>/.test(normalized)) {
          return sanitizeCaptionEditorMarkup(normalized);
        }

        // Plain captions are shown verbatim — every character (including * & "
        // and asterisks) is literal; only real line breaks become <br>. No
        // markdown is interpreted, so the editor matches the certificate exactly.
        return escapeCaptionHtml(normalized).replace(/\n/g, '<br>');
      };

      const getCaptionEditorText = () => (captionEditor?.textContent || '').replace(/​/g, '').trim();

      const updateCaptionPlaceholderState = () => {
        if (!captionEditor) {
          return;
        }

        captionEditor.classList.toggle('is-empty', getCaptionEditorText() === '');
      };

      const syncCaptionInputFromEditor = () => {
        if (!captionEditor || !captionInput) {
          return;
        }

        const sanitizedMarkup = sanitizeCaptionEditorMarkup(captionEditor.innerHTML).trim();
        const decodedMarkup = decodeHtmlEntities(sanitizedMarkup);
        captionInput.value = getCaptionEditorText() === '' ? '' : decodedMarkup;
        updateCaptionPlaceholderState();
      };

      const restoreCaptionEditorFromInput = () => {
        if (!captionEditor || !captionInput) {
          return;
        }

        captionEditor.innerHTML = captionValueToEditorHtml(captionInput.value);
        updateCaptionPlaceholderState();
      };

      const getCaptionEditorSelection = () => {
        if (!captionEditor) {
          return null;
        }

        const selection = window.getSelection();
        if (!selection || selection.rangeCount === 0) {
          return null;
        }

        const range = selection.getRangeAt(0);
        if (!captionEditor.contains(range.commonAncestorContainer)) {
          return null;
        }

        return { selection, range };
      };

      const insertPlainTextAtCursor = (value) => {
        if (!captionEditor) {
          return;
        }

        const selectionContext = getCaptionEditorSelection();
        if (!selectionContext) {
          captionEditor.focus();
          document.execCommand('insertText', false, value);
          return;
        }

        const { selection, range } = selectionContext;
        const normalized = value.replace(/\r\n?/g, '\n');
        const lines = normalized.split('\n');
        const fragment = document.createDocumentFragment();

        lines.forEach((line, index) => {
          fragment.appendChild(document.createTextNode(line));
          if (index < lines.length - 1) {
            fragment.appendChild(document.createElement('br'));
          }
        });

        range.deleteContents();
        range.insertNode(fragment);
        range.collapse(false);
        selection.removeAllRanges();
        selection.addRange(range);
      };

      const toggleCaptionInlineStyle = (command) => {
        if (!captionEditor) {
          return;
        }

        const selectionContext = getCaptionEditorSelection();
        if (!selectionContext || selectionContext.range.collapsed) {
          captionEditor.focus();
          syncCaptionToolbarState();
          return;
        }

        try {
          document.execCommand('styleWithCSS', false, false);
        } catch (error) {
          // Browser support is inconsistent; bold/italic still work without this hint.
        }

        document.execCommand(command, false, null);
        syncCaptionInputFromEditor();
        syncCaptionToolbarState();
      };

      const syncCaptionToolbarState = () => {
        const activeAlignment = alignInput?.value || 'center';
        const alignmentMap = {
          justifyLeft: 'left',
          justifyCenter: 'center',
          justifyRight: 'right',
          justifyFull: 'justify',
        };

        let isBold = false;
        let isItalic = false;
        const selectionContext = getCaptionEditorSelection();
        if (selectionContext) {
          try {
            isBold = document.queryCommandState('bold');
            isItalic = document.queryCommandState('italic');
          } catch (error) {
            isBold = false;
            isItalic = false;
          }
        }

        captionButtons.forEach((button) => {
          const cmd = button.dataset.cmd || '';
          if (cmd === 'bold') {
            button.classList.toggle('active', isBold);
            return;
          }

          if (cmd === 'italic') {
            button.classList.toggle('active', isItalic);
            return;
          }

          button.classList.toggle('active', alignmentMap[cmd] === activeAlignment);
        });
      };

      if (captionEditor && captionInput) {
        restoreCaptionEditorFromInput();
        const activeAlignment = alignInput?.value || 'center';
        captionEditor.style.textAlign = activeAlignment === 'justify' ? 'justify' : activeAlignment;

        // Enter inserts a single explicit line break instead of the browser's
        // default <div>/<p> block. This keeps the structure flat and makes the
        // mapping deterministic: one Enter = one <br>, two Enters = a blank line.
        captionEditor.addEventListener('keydown', (event) => {
          if (event.key !== 'Enter' || event.isComposing) {
            return;
          }
          event.preventDefault();
          document.execCommand('insertLineBreak');
          syncCaptionInputFromEditor();
          syncCaptionToolbarState();
          scheduleLivePreview();
        });

        captionEditor.addEventListener('input', () => {
          syncCaptionInputFromEditor();
          syncCaptionToolbarState();
          scheduleLivePreview();
        });

        captionEditor.addEventListener('paste', (event) => {
          event.preventDefault();
          insertPlainTextAtCursor(event.clipboardData?.getData('text/plain') || '');
          syncCaptionInputFromEditor();
          syncCaptionToolbarState();
          scheduleLivePreview();
        });

        ['keyup', 'mouseup', 'focus', 'blur'].forEach((eventName) => {
          captionEditor.addEventListener(eventName, syncCaptionToolbarState);
        });

        syncCaptionInputFromEditor();
      }

      if (captionButtons.length > 0) {
        captionButtons.forEach((btn) => {
          btn.addEventListener('mousedown', (event) => {
            event.preventDefault();
          });

          btn.addEventListener('click', (e) => {
            e.preventDefault();
            const cmd = btn.dataset.cmd;

            if (cmd === 'bold') {
              toggleCaptionInlineStyle('bold');
              scheduleLivePreview();
              return;
            }

            if (cmd === 'italic') {
              toggleCaptionInlineStyle('italic');
              scheduleLivePreview();
              return;
            }

            if (['justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull'].includes(cmd)) {
              const alignmentMap = {
                justifyLeft: 'left',
                justifyCenter: 'center',
                justifyRight: 'right',
                justifyFull: 'justify',
              };
              const nextAlignment = alignmentMap[cmd] || 'center';
              if (alignInput) {
                alignInput.value = nextAlignment;
              }
              if (captionEditor) {
                captionEditor.style.textAlign = nextAlignment === 'justify' ? 'justify' : nextAlignment;
                captionEditor.focus();
              }
              syncCaptionInputFromEditor();
              syncCaptionToolbarState();
            }

            scheduleLivePreview();
          });
        });
      }

      syncCaptionToolbarState();
      scheduleLivePreview();

      // ---- AI Caption Assistant ----
      (function initCaptionAi() {
        const aiBtn = document.getElementById('captionAiBtn');
        const aiPanel = document.getElementById('captionAiPanel');
        const aiGenerate = document.getElementById('captionAiGenerate');
        const aiTones = document.getElementById('captionAiTones');
        const aiStatus = document.getElementById('captionAiStatus');
        const aiStatusText = document.getElementById('captionAiStatusText');
        const suggestUrl = @json(route('admin.certs.caption-suggest'));
        const csrfToken = form?.querySelector('input[name="_token"]')?.value || '';

        if (!aiBtn || !aiPanel || !aiGenerate || !captionEditor || !captionInput) {
          return;
        }

        let selectedTone = 'warm and dignified';
        let isGenerating = false;

        const fieldValue = (name) => (form?.querySelector(`[name="${name}"]`)?.value || '').trim();

        // Resolve selects whose "Others" choice stores the real value in a sibling input.
        const resolveSelect = (name) => {
          const value = fieldValue(name);
          if (value === 'Others') {
            return fieldValue(`${name}_other`);
          }
          return value;
        };

        const gatherContext = () => ({
          training_title: fieldValue('training_title'),
          recipient_type: resolveSelect('recipient_type'),
          activity_type: resolveSelect('activity_type'),
          certificate_type: fieldValue('certificate_type'),
          topic: resolveSelect('topic'),
          venue: fieldValue('venue'),
          training_date_from: fieldValue('training_date_from'),
          training_date_to: fieldValue('training_date_to'),
          number_of_training_hours: fieldValue('number_of_training_hours'),
          tone: selectedTone,
          context: (document.getElementById('captionAiContext')?.value || '').trim(),
        });

        const setStatus = (text, variant) => {
          if (!aiStatus || !aiStatusText) {
            return;
          }
          aiStatus.classList.remove('error', 'fallback');
          if (variant === 'error') {
            aiStatus.classList.add('error');
          } else if (variant === 'fallback') {
            aiStatus.classList.add('fallback');
          }
          aiStatusText.textContent = text;
          aiStatus.classList.toggle('show', !!text);
        };

        const setLoading = (loading) => {
          isGenerating = loading;
          aiBtn.classList.toggle('is-loading', loading);
          aiBtn.disabled = loading;
          aiGenerate.classList.toggle('is-loading', loading);
          aiGenerate.disabled = loading;
          captionEditor.classList.toggle('is-generating', loading);
        };

        const openPanel = () => {
          aiPanel.hidden = false;
          // Allow the browser to register the un-hidden element before animating.
          requestAnimationFrame(() => aiPanel.classList.add('open'));
          aiBtn.setAttribute('aria-expanded', 'true');
        };

        const closePanel = () => {
          aiPanel.classList.remove('open');
          aiBtn.setAttribute('aria-expanded', 'false');
          setTimeout(() => { if (!aiPanel.classList.contains('open')) { aiPanel.hidden = true; } }, 180);
        };

        const togglePanel = () => {
          if (aiPanel.classList.contains('open')) {
            closePanel();
          } else {
            openPanel();
          }
        };

        const applyCaption = (caption) => {
          captionInput.value = caption;
          restoreCaptionEditorFromInput();
          syncCaptionInputFromEditor();
          syncCaptionToolbarState();
          scheduleLivePreview();
        };

        const generate = async () => {
          if (isGenerating) {
            return;
          }

          const context = gatherContext();
          if (!context.training_title && !context.recipient_type) {
            setStatus('Add a title or recipient type first so the caption has something to work with.', 'error');
            return;
          }

          setLoading(true);
          setStatus('Drafting your caption…', null);

          try {
            const response = await fetch(suggestUrl, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
              },
              credentials: 'same-origin',
              body: JSON.stringify(context),
            });

            if (!response.ok) {
              const payload = await response.json().catch(() => ({}));
              throw new Error(payload.message || 'The caption assistant is unavailable right now.');
            }

            const payload = await response.json();
            const caption = (payload.caption || '').trim();
            if (!caption) {
              throw new Error('The assistant returned an empty caption. Please try again.');
            }

            applyCaption(caption);

            if (payload.source === 'fallback') {
              setStatus('Drafted from a template (the AI model was unreachable). Feel free to edit it.', 'fallback');
            } else {
              setStatus('Drafted with AI. Review and tweak it to fit perfectly.', null);
            }
          } catch (error) {
            setStatus(error.message || 'Something went wrong while drafting the caption.', 'error');
          } finally {
            setLoading(false);
          }
        };

        aiBtn.addEventListener('click', (event) => {
          event.preventDefault();
          event.stopPropagation();
          togglePanel();
        });

        aiGenerate.addEventListener('click', (event) => {
          event.preventDefault();
          generate();
        });

        if (aiTones) {
          aiTones.addEventListener('click', (event) => {
            const chip = event.target.closest('.caption-ai-tone');
            if (!chip) {
              return;
            }
            event.preventDefault();
            aiTones.querySelectorAll('.caption-ai-tone').forEach((b) => b.classList.remove('active'));
            chip.classList.add('active');
            selectedTone = chip.dataset.tone || 'warm and dignified';
          });
        }

        // Close when clicking outside or pressing Escape.
        document.addEventListener('click', (event) => {
          if (!aiPanel.classList.contains('open')) {
            return;
          }
          if (!aiPanel.contains(event.target) && !aiBtn.contains(event.target)) {
            closePanel();
          }
        });

        document.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && aiPanel.classList.contains('open')) {
            closePanel();
            aiBtn.focus();
          }
        });
      })();

      window.addEventListener('beforeunload', revokeCurrentPreviewBlob);
    });
  </script>
</body>
</html>
