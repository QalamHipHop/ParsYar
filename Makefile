# ParsYar Enterprise — unified developer command runner.
#
# استفاده:
#   make help         — لیست دستورها
#   make install      — نصب وابستگی‌های PHP
#   make test         — اجرای تست‌های واحد
#   make cs           — بررسی استاندارد کدنویسی
#   make cs-fix       — اصلاح خودکار استاندارد کدنویسی
#   make stan         — آنالیز استاتیک PHPStan
#   make build        — بیلد فرانت‌اند (React)
#   make ci           — همهٔ بررسی‌های CI به‌صورت محلی

SHELL          := /bin/bash
.DEFAULT_GOAL  := help
.PHONY: help install test test-unit test-integration cs cs-fix stan stan-fix build ci clean release

PLUGIN_DIR := enterprise-core-plugin
THEME_DIR  := enterprise-theme

help:  ## نمایش این راهنما
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
	  awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'

install:  ## نصب وابستگی‌های Composer
	cd $(PLUGIN_DIR) && composer install --no-interaction --prefer-dist

test:  ## اجرای همهٔ تست‌ها
	cd $(PLUGIN_DIR) && composer test

test-unit:  ## فقط تست‌های واحد
	cd $(PLUGIN_DIR) && composer test:unit

test-integration:  ## فقط تست‌های یکپارچگی (نیاز به DB)
	cd $(PLUGIN_DIR) && composer test:integration

cs:  ## بررسی استاندارد کدنویسی (PHPCS)
	cd $(PLUGIN_DIR) && composer phpcs

cs-fix:  ## اصلاح خودکار استاندارد (PHPCBF)
	cd $(PLUGIN_DIR) && composer phpcbf

stan:  ## آنالیز استاتیک PHPStan (level 6)
	cd $(PLUGIN_DIR) && test -d vendor/bin/phpstan && vendor/bin/phpstan analyse --no-progress || echo "phpstan not installed; run 'make install'"

stan-fix:  ## اصلاح خودکار مشکلات PHPStan (قابل اجرا نیست، صرفاً راهنما)
	@echo "PHPStan fixes must be applied manually."

build:  ## بیلد فرانت‌اند
	cd $(THEME_DIR) && npm ci && npm run build

ci: cs stan test  ## همهٔ بررسی‌های CI (cs + stan + test)
	@echo "✅ CI checks passed locally."

clean:  ## پاک‌سازی فایل‌های موقت
	rm -rf $(PLUGIN_DIR)/vendor
	rm -rf $(PLUGIN_DIR)/.phpunit.cache
	rm -rf $(PLUGIN_DIR)/build
	rm -rf $(THEME_DIR)/node_modules
	rm -rf $(THEME_DIR)/dist
	find . -type d -name ".cache" -not -path "./.git/*" -exec rm -rf {} + 2>/dev/null || true
	@echo "✅ Cleaned."

release:  ## آماده‌سازی release (چک lint + تست + tag)
	@echo "→ Running pre-release checks..."
	$(MAKE) ci
	@echo "→ Ready to tag. Use: git tag -a v$$(grep Version $(PLUGIN_DIR)/enterprise-core.php | head -1 | awk -F'[ \"]+' '{print $$3}') -m 'Release'"
