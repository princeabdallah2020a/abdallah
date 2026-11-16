---
name: release-packaging
description: "Use when preparing a delivery: version bump, changelog, building installable plugin/theme zips, and writing the upload/deployment steps."
---

# Release packaging

1. **Version**: bump the header `Version:` and the matching PHP constant in each changed plugin/theme (semver: fix = patch, feature = minor, breaking = major).
2. **Changelog**: add an entry to `CHANGELOG.md` (date, version, Added/Changed/Fixed, migration notes).
3. **Quality gate**: `find . -name '*.php' -not -path './vendor/*' -exec php -l {} \; | grep -v 'No syntax errors'` must print nothing; QA report must be ✅ for the delivered scope.
4. **Build**: `tools/dev/build-release.sh dist` (syncs design system, installs composer deps without dev).
5. **Inspect zips**: `unzip -l dist/<file>.zip` — no `.git`, `node_modules`, tests, `.env`, keys, or `dist` inside; correct top folder name (= plugin slug).
6. **Deliver**: list each zip with size + install order (plugins before themes; central before dealer connector), settings to configure, cron/cache/backup steps from `docs/DEPLOYMENT.md`.
7. Never deploy to production without the owner's explicit OK; always back up DB + uploads first.
