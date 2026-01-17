# Guesthouse Marusho Reservation System

ゲストハウスマル正（Guesthouse Marusho）向けのWeb予約システムです。
電話予約中心の運用から、Web予約への移行をスムーズに行うために設計されています。
**現地現金払い（Cash Only）**を前提としており、オンライン決済機能は意図的に除外されています。

## システム概要

- **運用形態**: 現地現金決済専用（クレジットカード決済非対応）
- **ターゲット**: 国内外の旅行者（日・英 多言語対応）
- **主な目的**:
    - 電話対応コストの削減
    - 予約台帳のデジタル化と一元管理
    - 顧客利便性の向上（24時間予約可能化）

## 機能一覧 (Features)

### ユーザー向け機能 (Public)
- **空室検索・予約**:
    - 日付、人数、部屋タイプを指定したリアルタイム検索。
    - **レスポンシブUI**: スマートフォン（Tailwind CSS）に最適化された操作画面。
- **予約カレンダー**:
    - デスクトップでは月表示、モバイルではリスト表示に自動切り替え（FullCalendar採用）。
- **予約管理**:
    - **ゲスト予約**: 会員登録なしで予約可能（メールによる本人確認リンク方式）。
    - **会員機能**: アカウント作成により、マイページでの予約履歴確認・キャンセルが可能。
    - **キャンセルポリシー**: 到着直前までWebからのキャンセルが可能（設定により変更可）。
- **多言語対応 (i18n)**: 日本語と英語の切り替えに対応。

### 管理者向け機能 (Admin)
- **ダッシュボード**:
    - 本日のチェックイン/チェックアウト、未払い予約、売上推移チャートなどのKPI表示。
    - 新規予約のリアルタイム通知（ポーリング）。
- **予約台帳管理**:
    - **ステータス管理**: 予約確定/キャンセル、支払済/未払、チェックイン済/待ち。
    - **検索・フィルタ**: 日付範囲、キーワードによる絞り込み。
    - **電話予約登録**: 管理者が手動で予約を作成する機能。
- **部屋・在庫管理**:
    - 部屋ごとの料金設定（大人/子供）。
    - 部屋タイプごとの画像管理、公開/非公開設定。
- **権限管理 (RBAC)**:
    - **Manager (管理者)**: 全機能へのアクセス。
    - **Staff (スタッフ)**: 予約管理・顧客対応のみ。
    - **Cleaner (清掃)**: 予約状況の閲覧のみ（編集不可）。
- **システム監査**:
    - `admin_logs`: 管理者の操作ログ（ログイン、予約変更など）を記録。
    - `email_logs`: 送信されたメールの履歴確認。

### バックグラウンド処理
- **メール自動配信**:
    - リマインダーメール（チェックイン3日前）。
    - サンキューメール（チェックアウト翌日）。
    - 定期実行スクリプト (`cron_mail.php`) による自動化。

## ディレクトリ構成

```
/
├── admin/              # 管理画面 (要ログイン)
│   ├── api/            # 管理画面用 内部API (予約確認、在庫チェックなど)
│   └── ...             # 各種管理ページ (bookings.php, rooms.php 等)
├── api/                # 公開API (空室状況取得など)
├── assets/             # 静的リソース
│   ├── css/            # スタイル (Tailwind CSS, style.css)
│   ├── images/         # 部屋・サイト画像
│   └── js/             # フロントエンドJS
├── db_sql/             # データベース定義
│   ├── database.sql    # 完全なスキーマ定義 (新規インストール用)
│   └── migration_*.sql # 開発用差分SQL (参照用)
├── includes/           # 共通ライブラリ
│   ├── phpmailer/      # PHPMailer ライブラリ
│   ├── config.php      # 定数・設定
│   ├── db_connect.php  # DB接続設定
│   └── functions.php   # 共通関数 (メール送信, ログ記録など)
├── lang/               # 言語定義ファイル (JSON)
└── [root files]        # 公開ページ (index.php, book.php, register.php 等)
```

## インストール・運用手順

### 1. データベースのセットアップ
MySQLまたはMariaDBデータベースを作成し、初期スキーマをインポートします。
新規インストールの場合は `db_sql/database.sql` のみを使用してください。

```bash
mysql -u [username] -p [database_name] < db_sql/database.sql
```

> **Note**: `migration_*.sql` ファイルは開発過程の差分記録であり、`database.sql` にすべて統合されています。

### 2. 設定ファイルの編集
以下のファイルを環境に合わせて編集してください。

- **DB接続**: `includes/db_connect.php`
  ```php
  $dsn = 'mysql:host=localhost;dbname=YOUR_DB_NAME;charset=utf8mb4';
  $user = 'YOUR_DB_USER';
  $password = 'YOUR_DB_PASSWORD';
  ```

- **SMTP設定**: `includes/functions.php` 内の `send_email_smtp` 関数
  - 送信元アドレス、SMTPホスト、認証情報を設定してください。

### 3. 初期管理者アカウントの作成
`database.sql` に含まれる `admin` ユーザーのパスワードはダミーのため使用できません。以下の手順で管理者を作成してください。

1. Webブラウザで `/register.php` にアクセスし、新しいアカウントを作成します。
2. データベースの `users` テーブルを直接操作し、作成したユーザーの権限 (`role`) を **1 (管理者)** に変更します。

```sql
UPDATE users SET role = 1 WHERE email = 'your_registered_email@example.com';
```

3. `/admin/` にアクセスし、作成したアカウントでログインしてください。

### 4. 定期実行タスク (Cron) の設定
自動メール配信（リマインダー・サンキューメール）を有効にするため、Cronジョブを設定します。
（例: 毎日午前10時に実行）

```cron
0 10 * * * /usr/bin/php /path/to/your/project/cron_mail.php >> /path/to/cron.log 2>&1
```

※ Web経由で実行させる場合は `cron_mail.php?secret_key=YOUR_SECRET_KEY` のようにパラメータ認証を設定してください（コード内の修正が必要です）。

## 技術スタック

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+ / MariaDB 10.x
- **Frontend**: HTML5, JavaScript, Tailwind CSS (CDN)
- **Libraries**:
  - [FullCalendar](https://fullcalendar.io/) (予約カレンダー表示)
  - [PHPMailer](https://github.com/PHPMailer/PHPMailer) (メール送信)
  - [Chart.js](https://www.chartjs.org/) (売上グラフ)
