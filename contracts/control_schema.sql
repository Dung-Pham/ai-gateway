-- HỢP ĐỒNG DỮ LIỆU giữa giao diện điều khiển (Laravel, sở hữu migration) và gateway (Python).
-- Sinh bằng scripts/dump_contract.py từ CSDL đã migrate của control/. KHÔNG sửa tay: sửa migration rồi sinh lại.
-- Gateway chỉ ĐỌC các bảng chính sách; chỉ GHI usage_events (INSERT) và app_keys.last_used_at.

CREATE TABLE "providers" ("id" integer primary key autoincrement not null, "code" varchar not null, "name" varchar not null, "color" varchar not null, "created_at" datetime, "updated_at" datetime);
CREATE UNIQUE INDEX "providers_code_unique" on "providers" ("code");

CREATE TABLE "accounts" ("id" integer primary key autoincrement not null, "provider_id" integer not null, "label" varchar not null, "plan" varchar, "status" varchar not null default 'active', "cooldown_until" datetime, "max_concurrency" integer not null default '2', "current_concurrency" integer not null default '0', "runner_url" varchar, "notes" text, "pct_5h" numeric, "pct_week" numeric, "quota_source" varchar, "quota_updated_at" datetime, "last_auth_check_at" datetime, "created_at" datetime, "updated_at" datetime, foreign key("provider_id") references "providers"("id") on delete cascade);
CREATE UNIQUE INDEX "accounts_label_unique" on "accounts" ("label");
CREATE INDEX "accounts_status_index" on "accounts" ("status");

CREATE TABLE "pools" ("id" integer primary key autoincrement not null, "name" varchar not null, "strategy" varchar not null default 'least_used', "description" text, "created_at" datetime, "updated_at" datetime);
CREATE UNIQUE INDEX "pools_name_unique" on "pools" ("name");

CREATE TABLE "pool_members" ("id" integer primary key autoincrement not null, "pool_id" integer not null, "account_id" integer not null, "priority" integer not null default '1', "weight" integer not null default '1', foreign key("pool_id") references "pools"("id") on delete cascade, foreign key("account_id") references "accounts"("id") on delete cascade);
CREATE UNIQUE INDEX "pool_members_pool_id_account_id_unique" on "pool_members" ("pool_id", "account_id");

CREATE TABLE "capabilities" ("id" integer primary key autoincrement not null, "code" varchar not null, "name" varchar not null, "description" text, "risk_level" varchar not null default 'low', "handler" varchar, "enabled" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime);
CREATE UNIQUE INDEX "capabilities_code_unique" on "capabilities" ("code");

CREATE TABLE "apps" ("id" integer primary key autoincrement not null, "name" varchar not null, "slug" varchar not null, "owner" varchar, "status" varchar not null default 'active', "description" text, "created_at" datetime, "updated_at" datetime);
CREATE UNIQUE INDEX "apps_slug_unique" on "apps" ("slug");
CREATE INDEX "apps_status_index" on "apps" ("status");

CREATE TABLE "app_keys" ("id" integer primary key autoincrement not null, "app_id" integer not null, "name" varchar not null, "key_prefix" varchar not null, "key_hash" varchar not null, "last_used_at" datetime, "expires_at" datetime, "revoked_at" datetime, "created_at" datetime, "updated_at" datetime, foreign key("app_id") references "apps"("id") on delete cascade);

CREATE TABLE "app_policies" ("id" integer primary key autoincrement not null, "app_id" integer not null, "capability_id" integer not null, "allowed_models" text, "pool_id" integer, "account_id" integer, "fallback_pool_id" integer, "enabled" tinyint(1) not null default '1', foreign key("app_id") references "apps"("id") on delete cascade, foreign key("capability_id") references "capabilities"("id") on delete cascade, foreign key("pool_id") references "pools"("id") on delete set null, foreign key("account_id") references "accounts"("id") on delete set null, foreign key("fallback_pool_id") references "pools"("id") on delete set null);
CREATE UNIQUE INDEX "app_policies_app_id_capability_id_unique" on "app_policies" ("app_id", "capability_id");

CREATE TABLE "app_limits" ("id" integer primary key autoincrement not null, "app_id" integer not null, "capability_code" varchar, "rpm" integer, "max_concurrency" integer, "units_per_day" integer, "units_per_month" integer, "mode" varchar not null default 'hard', foreign key("app_id") references "apps"("id") on delete cascade);

CREATE TABLE "usage_events" ("id" integer primary key autoincrement not null, "ts" datetime not null, "app_id" integer not null, "account_id" integer not null, "capability" varchar not null, "model" varchar not null, "input_units" integer not null default '0', "output_units" integer not null default '0', "weighted_units" integer not null default '0', "latency_ms" integer not null default '0', "status" varchar not null, "error_class" varchar, "request_id" varchar not null, "estimated" tinyint(1) not null default '0', foreign key("app_id") references "apps"("id") on delete cascade, foreign key("account_id") references "accounts"("id") on delete cascade);
CREATE INDEX "usage_events_account_id_ts_index" on "usage_events" ("account_id", "ts");
CREATE INDEX "usage_events_app_id_ts_index" on "usage_events" ("app_id", "ts");
CREATE INDEX "usage_events_request_id_index" on "usage_events" ("request_id");
CREATE INDEX "usage_events_status_index" on "usage_events" ("status");
CREATE INDEX "usage_events_ts_index" on "usage_events" ("ts");

CREATE TABLE "model_weights" ("id" integer primary key autoincrement not null, "model" varchar not null, "weight" numeric not null, "note" varchar, "created_at" datetime, "updated_at" datetime);
CREATE UNIQUE INDEX "model_weights_model_unique" on "model_weights" ("model");

CREATE TABLE "settings" ("key" varchar not null, "value" text, "encrypted" tinyint(1) not null default '0', "created_at" datetime, "updated_at" datetime, primary key ("key"));
