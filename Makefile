.PHONY: release changelog test analyse format qa

# Release a new version (auto-detected from commits, or specify: make release v=2.0.0)
release:
	./scripts/release.sh $(v)

# Preview unreleased changes (does not write to CHANGELOG.md)
changelog:
	@git-cliff --unreleased

test:
	composer test

analyse:
	composer analyse

format:
	composer format

qa:
	composer qa
